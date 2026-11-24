# Plano de implementação da issue #48

## Objetivo e estado

Permitir ao Professor copiar novamente o mesmo Convite por link ativo na gestão da equipe de aplicação e apresentar uma única mensagem de expiração: **“O convite expirou em {data_hora}”**.

- Fonte: [issue #48 — Melhorias no convite](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/issues/48), consultada com seus comentários em 24/11/2026; sem comentários naquela consulta.
- Branch selecionada: `codex/48-melhorias-no-convite`, criada a partir de `9be9d95`, mesmo ponto da branch anterior `48-melhorias-no-convite`.
- Estado: implementação concluída; validação local registrada abaixo.
- A alteração local preexistente em `qr_landing.php` foi preservada e está fora do escopo desta issue.

## Decisão confirmada

O Professor poderá recuperar o mesmo link em visitas futuras. Copiar não regenera o convite, não estende o prazo, não muda o criador e não altera Delegações de aplicação. Somente a regeneração explícita substitui o convite e invalida o link anterior.

O usuário confirmou armazenar o segredo de forma criptografada para permitir essa recuperação. Convites criados antes da atualização permanecem válidos; para habilitar nova cópia, o Professor precisa regenerá-los manualmente uma vez. A interface deve explicar essa condição e que regenerar invalida o link anterior, sem realizar substituição automática.

Essa decisão substitui a exibição única do [ADR 0005](adr/0005-exibir-o-link-de-convite-uma-unica-vez.md), conforme o [ADR 0011](adr/0011-permitir-nova-copia-do-convite-ativo.md).

## Situação anterior à implementação

- `classes/local/invitation.php`: `generate()` cria um token aleatório e persiste apenas sua derivação SHA-256. `get_status()` retorna estado, geração, prazo e possibilidade de emissão, sem segredo. Não existe forma de reconstruir os tokens antigos.
- `manage.php`: o link completo é montado somente na resposta à geração. Nas visitas seguintes, a seção apresenta estado, prazo e formulário de gestão.
- `templates/invitation.mustache`: o botão de cópia com `core/copy_to_clipboard` já existe, limitado à resposta de geração.
- A duplicidade do texto expirado ocorre porque a página sempre fornece a frase de estado e a frase de vencimento futuro, e o template imprime ambas.
- Há testes PHPUnit e Behat que exigem exibição única. Eles precisarão acompanhar a decisão revisada.

## Solução proposta

### Persistência e atualização

Adicionar um campo opcional para o token criptografado à tabela de convites, preservando `tokenhash` e o formato atual do token. Usar `core\encryption::encrypt()` e `decrypt()`, evitando um mecanismo próprio de criptografia ou um novo formato de link.

Atualizar `db/install.xml`, `db/upgrade.php` e `version.php` em conjunto. O upgrade adicionará o campo vazio aos registros existentes, sem tentar preencher segredos a partir do hash e sem mudar estado, geração ou prazo dos convites legados. A nova geração deve persistir hash e conteúdo criptografado na mesma transação; falhar na criptografia impede emitir um convite parcialmente salvo.

A API de criptografia foi localizada no Moodle 4.5 disponível no ambiente. Sua compatibilidade e execução devem ser verificadas na matriz suportada: Moodle 4.5, 5.0 e 5.1, com PostgreSQL e MariaDB. A chave fica sob gestão do Moodle, separada do registro do convite; a documentação operacional deve explicar que restaurar somente o banco não garante recuperar os links para cópia.

### Recuperação do convite ativo

Concentrar a recuperação em uma operação pública de `invitation`, restrita a `mod/quiz:manage` no contexto do Questionário. A operação deve verificar a autoridade e reconciliar configuração, estado e prazo junto à leitura protegida pelo bloqueio já existente. Recuperar o token somente se o convite ainda estiver ativo e a configuração permitir seu uso, inclusive antes do início do Período de Autorização.

Antes de devolver o segredo descriptografado, verificar seu formato e sua correspondência com a derivação persistida. Manter `get_status()` como resumo sem segredo e preservar o contrato booleano de `validate()`. Centralizar essas regras em `invitation`, sem duplicá-las na página ou expor um endpoint adicional apenas para copiar.

Convite legado sem conteúdo criptografado: não fornecer link para cópia e informar a necessidade de regeneração manual. Chave indisponível ou conteúdo inválido: não revelar dados, não substituir o convite e não impedir o restante da gestão; informar que a cópia está indisponível e orientar a recuperação da configuração ou a regeneração explícita. A validação dos links já distribuídos continua baseada no hash.

Expiração, desativação, encerramento por configuração e apagamento pela Privacy API devem eliminar também o segredo criptografado. Regeneração substitui o conteúdo anterior atomicamente. Tokens e conteúdo criptografado permanecem fora de eventos, exportações de dados pessoais e URLs da página de gestão.

### Interface e mensagens

Na seção de convite ativo, apresentar o link em campo somente leitura e reutilizar o botão **Copiar link do convite**, com o feedback Moodle já existente. O mesmo link deve continuar disponível ao retornar, recarregar ou entrar novamente na gestão com um Professor autorizado. Manter a página sem cache e a política de referência existente.

Separar a apresentação do campo e do botão da indicação de geração recente no template. Ajustar o aviso que hoje afirma que o link aparece uma única vez. Convites expirados, desativados ou inexistentes não oferecem cópia; o convite legado ativo apresenta a orientação de regeneração.

Para o estado expirado, renderizar apenas **“O convite expirou em {data_hora}”**, sem a primeira frase isolada e sem a frase “O convite expira em”. Usar o `timeexpires` efetivo do convite, formatado por `userdate()` no idioma e fuso do usuário. Atualizar as strings em `lang/pt_br/quizaccess_presencial.php` e `lang/en/quizaccess_presencial.php`, preservando mensagens adequadas aos demais estados.

## Sequência de implementação

Executar as etapas em incrementos com teste que falha antes da alteração correspondente, usando as interfaces públicas abaixo. Não escrever todos os testes antecipadamente.

1. **Mensagem de expiração:** acrescentar a jornada representativa e corrigir a seleção das mensagens na página/template. Resultado: uma única frase com data e hora, sem botão de cópia no estado expirado.
2. **Mesmo convite recuperável:** acrescentar teste público de geração e recuperação; implementar campo, upgrade e criptografia atômica, mais a operação restrita de recuperação. Resultado: recuperação repetida retorna o mesmo token válido, sem modificar geração ou prazo.
3. **Ciclo de vida e legado:** cobrir ausência de segredo recuperável, permissões, encerramento e regeneração. Implementar remoção do conteúdo, estados de indisponibilidade e preservação dos convites existentes.
4. **Cópia na gestão:** atualizar a jornada gerar → retornar/recarregar → copiar. Reutilizar `core/copy_to_clipboard`, atualizar o aviso e manter regeneração/desativação explícitas com POST e `sesskey`.
5. **Privacidade e documentação:** atualizar metadados e limpeza na Privacy API, garantir exportação sem credenciais e atualizar o README sobre nova cópia, convites antigos e dependência da chave Moodle.
6. **Validação e revisão:** executar os testes afetados, depois a suíte do plugin e as verificações exigidas pelo CI. Revisar padrões do repositório e atendimento à issue antes de preparar commit ou PR em uma etapa posterior.

## Cobertura proposta

As interfaces para os testes são as operações públicas de `invitation`, a Privacy API do Moodle e a jornada de gestão via Behat. Confirmar essa cobertura antes de iniciar a escrita dos testes; não testar métodos privados nem estrutura SQL incidental.

| Comportamento | Verificação |
| --- | --- |
| Gerar, recuperar repetidamente e aceitar o mesmo convite | PHPUnit pelas operações públicas de convite; geração, prazo e criador preservados |
| Copiar após retornar/recarregar a gestão | Behat com JavaScript, comparação do link e feedback de cópia |
| Professor sem autoridade no Questionário, estudante ou Aplicador sem permissão de gestão | Recusa de recuperação e ausência dos controles na gestão |
| Convite futuro, expirado, desativado ou regra desabilitada | Recuperação apenas quando ativo e permitido pela configuração; nenhum convite encerrado volta a ser copiável |
| Redução do prazo e ampliação posterior | Vencimento efetivo respeitado, sem extensão ou reativação pela cópia |
| Regeneração explícita e formulários antigos | Link anterior recusado, novo link recuperável e geração obsoleta rejeitada |
| Convite anterior à atualização | Link distribuído continua válido; cópia indisponível até regeneração manual |
| Falha de descriptografia | Gestão continua disponível; segredo não exposto; convite não é regenerado implicitamente |
| Privacidade e encerramento | Apagamento torna recuperação impossível; exportações e eventos não revelam token nem conteúdo criptografado |
| Convite expirado na interface | Uma única mensagem com data/hora efetiva e sem frase de vencimento futuro |
| Instalação e atualização do plugin | Campo disponível em instalação nova e upgrade, com dados legados preservados |

Reaproveitar `tests/invitation_test.php`, `tests/invitation_acceptance_test.php`, `tests/delegation_invitation_integration_test.php`, `tests/configuration_lifecycle_test.php`, `tests/privacy/provider_test.php`, `tests/privacy/metadata_test.php` e `tests/behat/invitation.feature`, conforme o comportamento exercitado. Atualizar testes existentes que exigem segredo irrecuperável; preservar as garantias de ausência de texto puro, escopo por Questionário e eventos sem segredo.

Usar `advanced_testcase` com `$this->resetAfterTest()` antes de persistir e `self::getDataGenerator()` para gerar dados. Reservar `basic_testcase` para testes sem estado do banco, como formulários isolados se forem afetados. Preparar expiração por relógio/tarefa pública, conforme os passos Behat existentes.

## Validação prevista

Na raiz de um Moodle com o plugin instalado e o PHPUnit preparado:

```bash
vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/invitation_test.php
vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/invitation_acceptance_test.php
vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/delegation_invitation_integration_test.php
vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/configuration_lifecycle_test.php
vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/privacy/provider_test.php
vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/privacy/metadata_test.php
vendor/bin/phpunit --testsuite quizaccess_presencial_testsuite
```

Executar Behat para a jornada de convite, incluindo os cenários JavaScript, e conferir a mensagem em Português do Brasil. Rodar lint PHP/Mustache, Moodle Code Checker, PHPDoc Checker, validação do plugin e savepoints conforme a configuração do CI. A matriz de versões e bancos também deve validar instalação/upgrade.

### Resultados locais

Em Moodle 4.5.12, PHP 8.3.24 e PostgreSQL 15.19, a suíte PHPUnit passou com 261 testes e 1.676 verificações. Após a suíte, os testes de convite foram repetidos com o caso adicional de chave ausente: 21 testes e 189 verificações aprovados. Os dois cenários adicionais de concorrência para perda ou manutenção de permissão também passaram, com 16 verificações. O Behat de convite passou no Chrome com 11 cenários e 274 passos. O Moodle Code Checker passou sem ocorrências.

Um teste de atualização em banco isolado instalou a versão anterior, gerou convite ativo e aplicou o upgrade. O link anterior permaneceu válido e não recuperável; após regeneração explícita, deixou de ser válido e o novo link tornou-se recuperável. Esses resultados não substituem a matriz de versões, bancos e demais verificações do CI.

O PHPDoc Checker, a validação do plugin e a verificação de savepoints também passaram. O lint Mustache não pôde concluir porque o validador HTML estava indisponível; a verificação em navegador passou. A matriz do CI e a conferência da mensagem no navegador em Português do Brasil permanecem pendentes.

## Critérios de conclusão e limites

- Professor autorizado copia o mesmo convite ativo após voltar à gestão, sem invalidar links distribuídos.
- Convite expirado apresenta uma única mensagem **“O convite expirou em {data_hora}”**.
- Convites legados exigem regeneração manual para habilitar cópia, preservando sua validade até essa ação.
- Não há recuperação de convite encerrado, exposição do segredo em texto puro na persistência ou vazamento por eventos/exportações.
- Atualização, testes afetados e verificações do CI aprovados, com documentação alinhada à regra entregue.

Fora do escopo: alterações no fluxo de QR Code, nas permissões acadêmicas, nas regras de Delegação ou no aceite do convite, exceto os testes que comprovam a preservação desses contratos.
