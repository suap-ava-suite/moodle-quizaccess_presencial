# Plano de resolução do feedback do PR #50

## Referência e estado

Análise de 25/11/2026 do [PR #50](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/50), referente à issue #48, no commit `b558e9b51868cf3a02db8d15125d96ed05f90044`. Foram consultados os oito comentários de revisão de `jalerson`, as revisões e os comentários gerais; não havia comentários gerais nem respostas aos oito apontamentos. As revisões estão como `COMMENTED`, sem aprovação ou pedido formal de mudanças. A prioridade indicada neste plano é nossa avaliação técnica, não um estado de bloqueio definido pelo revisor.

O objetivo desta etapa é analisar e planejar. As conclusões abaixo resultam da leitura do código, sem nova reprodução em testes. Nenhuma correção de produção foi aplicada, nenhuma resposta foi publicada e nenhuma conversa foi marcada como resolvida.

As referências oficiais das APIs envolvidas estão na [documentação auxiliar para desenvolvimento](desenvolvimento.md).

## Avaliação de cada comentário

| Referência | Avaliação | Tratamento proposto |
| --- | --- | --- |
| [Data após apagamento pela Privacy API](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/50#discussion_r4684517580) | Procedente e prioritário. `erase_user_data()` preserva o estado expirado e zera `timeexpires`; `manage.php` formata esse zero sem guarda. | Exibir a frase com data somente com prazo positivo. Sem prazo, usar `invitationstate_expired`, sem inventar uma data nem recuperar dados apagados. |
| [Motivo incorreto para cópia indisponível](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/50#discussion_r4684517810) | Procedente. Token ausente também pode significar configuração incompatível; a página deduz uma falha criptográfica sem conhecer a causa. | Fazer a operação de gestão informar o motivo; orientar o Professor conforme configuração, legado ou falha de recuperação. |
| [Prazo futuro em convite desativado](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/50#discussion_r4684518012) | Procedente e preexistente, de baixo custo na mesma apresentação já alterada. | Mostrar a frase de vencimento futuro apenas no estado ativo com prazo positivo. Preservar o prazo armazenado e as regras de encerramento. |
| [Testes acoplados ao armazenamento](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/50#discussion_r4684518247) | Concordância parcial. As mutações para simular falha/legado podem ser substituídas. Já a ausência de segredo em persistência é uma garantia de segurança, não apenas um detalhe SQL incidental. | Substituir a falha simulada por chave diferente via API pública do Moodle; tornar reproduzível o teste real de upgrade. Preservar verificações públicas de exportação e eventos sem segredo, reduzindo leituras da coluna. Exceções estritamente necessárias de integridade do armazenamento ficam concentradas e justificadas. |
| [Atalho público usado somente pelos testes](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/50#discussion_r4684518426) | Procedente: `get_current_token()` apenas delega e suas chamadas estão nos testes. | Remover o atalho introduzido neste PR e usar `get_management_details()` nos testes, como faz a página real. |
| [Autorização compartilhada em gerenciador de Delegações](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/50#discussion_r4684518661) | Procedente como melhoria de estrutura. Há dois consumidores reais e o PHPDoc continua restrito à inclusão. | Extrair a revalidação e seus auxiliares para `management_authority`, sem alterar o algoritmo. Testar pelo comportamento público de inclusão e gestão de convite. |
| [Auditoria de recuperação do segredo](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/50#discussion_r4684518957) | É uma lacuna de decisão, não violação comprovada do contrato atual: o README promete transições e uso inválido, não toda leitura. A exposição adicional justifica explicitar a política. | Recomenda-se auditar a recuperação bem-sucedida por evento Moodle sem credenciais. A proposta está detalhada abaixo e ainda não constitui decisão de domínio aceita. |
| [Resumo de estado duplicado](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/50#discussion_r4684519201) | Procedente como manutenção; os quatro campos do resumo são construídos duas vezes. | Extrair helper privado comum para o resumo e acrescentar os dados de recuperação somente na operação de gestão. Preservar leitura única sob bloqueio. |

## Integração com a base atual

O GitHub informou `mergeable = false` e `mergeable_state = dirty`. A referência remota `main` está em `93d27f040f740aaf948876e3379665cc08d0ce5a`, com os commits `5fddd10` (nova decisão de disponibilidade) e `93d27f0` (mensagem de perda de conexão), posteriores à base original `9be9d95`. A atualização da referência local foi somente uma consulta; a branch do PR não foi integrada nem reescrita.

Antes das correções, conciliar as mudanças em uma integração revisável:

- Preservar o vocabulário novo de `main`: Período de disponibilidade do Questionário e Disponibilidade efetiva do estudante. A decisão de `main` sobre ausência de período próprio deve permanecer; a resolução do feedback não implementará por conta própria toda essa mudança de modelo ainda não refletida no código.
- No conceito de Convite por link, combinar o ciclo de vida aceito em `main` com a possibilidade de copiar novamente o mesmo link ativo aceita na issue #48. Conservar os demais conceitos novos de `main`.
- Renumerar o ADR desta branch sobre cópia posterior para 0012, pois `main` já contém `0011-acompanhar-a-disponibilidade-do-questionario.md`. Corrigir os links no ADR 0005, no plano da issue e nas demais referências. Não reintroduzir os ADRs 0001/0002 removidos pela base.
- Preservar os passos e cenários de perda de conexão recebidos em `main`, junto aos passos de convite desta branch. Preservar a alteração de release para `0.6.1`; manter uma versão de plugin crescente que inclua o savepoint do campo criptografado.
- Manter a alteração local preexistente de `qr_landing.php` fora desta entrega. Isolá-la de forma reversível antes de qualquer operação que possa alcançá-la.

Reavaliar o cenário de configuração incompatível depois da integração. No código atual ele existe porque `can_issue()` ainda compara os dois períodos. Quando a nova decisão de disponibilidade for implementada, o teste deve acompanhar o contrato público vigente, sem manter artificialmente uma incompatibilidade que deixe de existir.

## Sequência proposta

### 1. Corrigir apresentação após privacidade e desativação

Adicionar uma jornada representativa: gerar convite, expirar pelo relógio/tarefa pública, apagar os dados pela Privacy API aprovada e abrir a gestão. Cobrir exclusão por usuário e por contexto no PHPUnit; a jornada Behat pode representar um desses caminhos.

Concluir quando um convite expirado com prazo positivo mostrar uma única mensagem com data/hora e, após o apagamento, mostrar somente o estado expirado. Nenhuma data Unix deve ser exibida. Convite desativado deve mostrar seu estado, sem prazo futuro nem campo/botão de cópia. Testar a renderização pelo navegador, não métodos privados ou o texto-fonte de `manage.php`.

### 2. Corrigir o motivo de indisponibilidade e unificar o resumo

Construir estado, geração, prazo e `canissue` por um helper privado comum a `get_status()` e `get_management_details()`. O helper recebe os registros já lidos/reconciliados; não adquire bloqueio, não consulta novamente o convite e não retorna segredo. Não chamar `get_status()` para compor a resposta de gestão, pois isso reintroduziria leituras separadas.

Na mesma leitura protegida, a recuperação informa um motivo explícito, por exemplo `copyreason`: `null` quando há token, `inactive`, `configuration`, `legacy` ou `recoveryfailed`. A configuração indisponível tem precedência sobre tentativa de descriptografia, evitando orientar regeneração ou recuperação de chave quando a configuração impede o uso. O nome final pode seguir as convenções existentes; o contrato relevante é distinguir as causas.

Falha criptográfica compreende chave ausente, falha de descriptografia e segredo recuperado inconsistente com sua derivação. A mensagem ao Professor deve orientar contato com o administrador do site, sem instruí-lo a manipular arquivos de chave. Legado mantém a orientação de regeneração explícita; configuração usa sua mensagem própria. Os testes devem confirmar o link ainda válido quando o problema é apenas a recuperação e que nenhuma chave é criada durante a leitura.

Concluir quando a página apenas apresenta o motivo fornecido pela operação, `get_status()` continua sem segredo e os testes concorrentes continuam comprovando a correspondência entre token, geração e prazo.

### 3. Reduzir a superfície pública e reorganizar a autorização

Trocar as chamadas de teste para `get_management_details()` e remover `get_current_token()`, sem criar outro atalho para testes.

Mover `can_manage_after_wait()` e os auxiliares `current_core_setting()` e `renew_temporary_roles()` para o módulo compartilhado `management_authority`, com uma operação pública de revalidação e auxiliares privados. Atualizar os dois consumidores e o PHPDoc. Preservar permissões temporárias, administradores, papéis assumidos, proibições, configuração forçada e restauração de globais em `finally`.

Concluir quando os testes públicos de Inclusão direta e de recuperação concorrente passarem nas duas condições: permissão mantida e permissão retirada durante a espera. Não acrescentar testes que chamem auxiliares privados.

### 4. Melhorar as preparações dos testes

Substituir o teste que escreve ciphertext inválido por troca temporária de `secretdataroot` e criação de outra chave por `core\encryption`, restaurando a configuração em `finally`. A chave ausente permanece como caso distinto. Evitar adicionar operações de produção apenas para preparar falhas em testes.

Converter a verificação manual de upgrade registrada no plano em procedimento versionado e repetível em instalação/banco descartáveis: instalar base anterior, gerar convite pela interface pública, atualizar e verificar validade do link e indisponibilidade de cópia, depois regenerar e confirmar substituição. Não depender de arquivos em `/tmp` de uma execução passada. O ciclo atual do CI que apenas reduz a versão instalada não substitui esse caso com dados anteriores reais.

Rever o teste que troca ciphertext entre registros: manter uma fixture de corrupção apenas se ela proteger uma garantia não alcançada pela chave errada, com um único helper documentando a exceção de armazenamento. Nas exportações, manter a lista pública exata dos campos permitidos e a ausência do token, dispensando buscar ciphertext apenas para comparar. A confidencialidade em persistência continua coberta no limite de integração já existente, sem acoplar toda a suíte ao formato do campo.

### 5. Explicitar a política de auditoria

Proposta recomendada: emitir `invitation_link_retrieved` somente quando `get_management_details()` devolver um token válido a um Professor autorizado, dentro da operação protegida. O nome descreve recuperação no servidor; não deve alegar que o navegador terminou de renderizar nem que houve cópia para a área de transferência.

O evento registra usuário/contexto Moodle, Questionário e geração; não contém token, URL, hash nem ciphertext. Consultar apenas `get_status()`, falhar na permissão ou não recuperar um segredo não emite esse evento. As transições de geração/regeneração já têm seu evento; não duplicá-las só para apresentar o link recém-gerado. Retornar à gestão e recuperar o convite é uma nova leitura auditável.

Essa proposta acrescenta comportamento observável e exige confirmar a política antes de implementá-la. Caso se opte por não auditar cada recuperação, registrar explicitamente o alcance da auditoria no ADR da cópia posterior e justificar a escolha. O ADR 0010 continua válido nas duas opções: os eventos usam o log do Moodle e não uma interface de auditoria do plugin.

## Validação e conclusão

Implementar um comportamento por vez com teste que falha antes da correção. Usar `advanced_testcase`, `resetAfterTest()` e `self::getDataGenerator()` para persistência; manter jornadas e apresentação em Behat. Não é necessária nova migração de dados para corrigir a apresentação, extrair módulos ou criar o evento proposto.

- PHPUnit afetado: convite, Privacy API, aceite, integração de Delegações, `delegation_manager_test.php` e `delegation_concurrency_test.php`; depois suíte completa uma vez após estabilizar as mudanças.
- Behat: ativo/cópia posterior, expirado com data, expirado após apagamento e desativado. Repetir os cenários representativos em inglês e Português do Brasil, conferindo a data e hora do usuário.
- Upgrade isolado com convite legado e preservação do token distribuído.
- Lint PHP, Moodle Code Checker, PHPDoc, validação do plugin e savepoints; lint Mustache com validador HTML local se o serviço remoto continuar indisponível. Executar a matriz do CI sobre o commit final da branch integrada.
- Revisão dos padrões e do atendimento aos oito comentários. Na futura etapa autorizada de publicação, associar cada resposta ao commit/teste correspondente e só resolver conversas depois de comprovar o resultado. Este planejamento não publica respostas nem resolve conversas.

O critério de conclusão é corrigir os três problemas de apresentação, preservar as garantias de autorização/concorrência/privacidade, reduzir a duplicação e o acoplamento dos testes e deixar a decisão de auditoria explícita. As prioridades são: integração da base, data após privacidade, causas de indisponibilidade, prazo em desativado, estrutura/testes e política de auditoria.
