# Plano de correção da issue #40

## Objetivo e estado

Impedir que uma Inclusão direta conceda uma Delegação de aplicação quando o Professor perdeu `mod/quiz:manage` enquanto a operação aguardava o bloqueio do Questionário e essa retirada já foi confirmada.

- Issue: [#40 — Inclusão de Aplicador é concluída após o Professor perder a permissão](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/issues/40).
- Branch de trabalho: `fixissue40`, criada a partir da `main` local no commit `16d84e9027096348b24638e52a6ffd17b373c93a`.
- Planejamento realizado em 17/11/2026. A issue está aberta, marcada como `ready-for-agent` e não possui comentários na consulta desta data.
- A implementação foi iniciada nesta branch; os resultados das verificações devem ser registrados na entrega.

## Evidências e limites

A issue relata uma reprodução com duas conexões PostgreSQL independentes: a Inclusão direta passa pela verificação inicial, espera pelo bloqueio do Questionário e conclui a concessão depois da retirada confirmada da permissão do Professor. O relato inclui o teste e o processo auxiliar necessários para reproduzir a falha no Moodle 4.5.

A leitura de [`delegation_manager::include_users`](../classes/local/delegation_manager.php) confirma que `require_capability` é chamado antes de `quiz_lock::execute`; não existe nova validação de autoridade dentro da operação protegida. Isso é compatível com a reprodução relatada, mas não substitui executá-la.

[`quiz_lock::execute`](../classes/local/quiz_lock.php) mantém o bloqueio da linha do Questionário até o fim da transação externa. A correção deve validar a autoridade depois dessa espera e antes de sincronizar configurações, criar Delegações ou emitir eventos de concessão.

Há um precedente em [`release_request_service::can_manage_quiz`](../classes/local/release_request_service.php): o fluxo de decisões combina as restrições de `has_capability` com uma consulta de autoridade construída pela API Moodle `get_with_capability_join`, tratando administradores explicitamente. Esse precedente será avaliado para a Inclusão direta; ele depende do usuário da sessão, enquanto `include_users` recebe um `$actorid` explícito.

O [ADR 0006](adr/0006-reutilizar-a-capability-de-gestao-do-questionario.md) continua válido: a autoridade depende da capability contextual, independentemente do nome do papel. Não há mudança de domínio ou decisão arquitetural que exija novo ADR.

## Estratégia de trabalho

Aplicar o fluxo recomendado por `ask-matt` para bugs: primeiro obter uma reprodução automatizada que falhe pelo sintoma exato, depois corrigir em ciclos de teste e implementação e finalizar com revisão contra os padrões do repositório e os critérios da issue.

Como a solicitação atual é de planejamento, a reprodução, a investigação dinâmica e a implementação ficam para a execução deste plano. A issue já contém uma especificação pronta; não é necessário criar outra issue, decompor o trabalho em tickets ou publicar um novo plano no rastreador.

## Sequência de implementação

### 1. Fixar a reprodução no limite público

Adaptar a reprodução fornecida na issue para [`tests/delegation_concurrency_test.php`](../tests/delegation_concurrency_test.php), reutilizando o padrão de processos independentes existente. Avaliar uma extensão compatível de [`tests/fixtures/delegation_worker.php`](../tests/fixtures/delegation_worker.php); se o protocolo ficar confuso, usar uma fixture específica para a corrida de autoridade.

O teste deve:

1. Usar `advanced_testcase`, chamar `resetAfterTest()` antes das alterações e `preventResetByRollback()` para permitir dados confirmados visíveis pelas duas conexões.
2. Criar Questionário, Professor e Conta elegível pelos geradores/APIs Moodle, com Liberação Presencial habilitada, Período de Autorização vigente e um papel temporário que conceda `mod/quiz:manage` no contexto correto.
3. Manter o bloqueio do Questionário na conexão A e iniciar `delegation_manager::include_users` no processo B com a permissão inicialmente válida e os dados de acesso já carregados.
4. Confirmar a espera real de B pelo bloqueio de A antes de revogar a atribuição de papel em A e confirmar a transação. Usar a observação PostgreSQL de `pg_blocking_pids`, já empregada em outros testes do projeto; uma mensagem de início ou um atraso fixo não comprova a espera.
5. Receber de B uma exceção de permissão identificável, preferencialmente `required_capability_exception`/`nopermissions`, e verificar como administrador, por `delegation_manager::list_current`, que nenhuma Delegação foi concedida.
6. Encerrar processos, pipes e transações em `finally`, com prazos limitados e diagnóstico para falha de sincronização.

As consultas PostgreSQL servem apenas para coordenar as conexões. O resultado do produto deve ser verificado pelas operações públicas, sem chamar métodos privados nem afirmar detalhes da estrutura SQL do plugin. Restringir a instrumentação específica ao PostgreSQL e sinalizar o teste como não aplicável nos demais bancos, seguindo os precedentes da suíte.

**Conclusão desta etapa:** executar o teste na base e registrar uma falha causada pela concessão indevida após a retirada confirmada da permissão. Erros de ambiente, de processo ou de sincronização não satisfazem esse critério.

### 2. Validar a autoridade vigente sob o bloqueio

Alterar somente o caminho de Inclusão direta em [`classes/local/delegation_manager.php`](../classes/local/delegation_manager.php), mantendo a verificação inicial para recusar imediatamente um ator já sem permissão e acrescentando uma validação decisiva no início da operação protegida pelo bloqueio.

Essa validação deve:

- Considerar o `$actorid` informado, tanto quando é o usuário da sessão quanto quando difere dele.
- Consultar a autoridade confirmada depois da espera, sem depender apenas de repetir `require_capability` sobre dados de acesso carregados anteriormente.
- Preservar a semântica Moodle de contexto, herança de permissões, restrições da sessão e troca para papel menos privilegiado, além dos privilégios implícitos de administradores.
- Recusar a operação antes de qualquer concessão parcial, atualização de Delegação ou evento de concessão, usando o contrato de erro de permissão já existente.
- Preservar as transações aninhadas e a ordem atual dos bloqueios.

Preferir a abordagem já presente no fluxo de decisões, adaptada ao ator explícito e confinada à Inclusão direta. Validar as APIs no Moodle suportado antes de escolher o mecanismo definitivo. Uma alternativa de atualização de cache só é aceitável se demonstrar, pelo teste concorrente, a renovação dos dados do ator correto e a preservação das restrições da sessão. Evitar manipular diretamente as estruturas privadas de cache do Moodle ou reproduzir manualmente suas regras de permissão.

**Decisão durante a implementação:** a consulta `get_with_capability_join` verifica as atribuições vigentes para usuários sem papel assumido. Para um papel assumido na sessão, ela não representa a autorização do Moodle; nesse caso, a verificação usa `get_role_definitions_uncached` e reproduz a precedência de permissões dos papéis assumido e padrão do Moodle. O teste concorrente também cobre a retirada da capability do papel assumido. Uma tentativa de recarregar todos os caches de acesso provocou falha na suíte de integração de privacidade e foi descartada.

**Conclusão desta etapa:** o mesmo teste passa por recusa de permissão e ausência de Delegação, sem atualização artificial do cache no processo B pelo próprio teste.

### 3. Confirmar os comportamentos preservados

Ampliar a cobertura uma situação por vez, reutilizando [`tests/delegation_manager_test.php`](../tests/delegation_manager_test.php) e o teste concorrente:

| Situação | Resultado esperado |
| --- | --- |
| Permissão retirada e confirmada durante a espera | Recusa de permissão e nenhuma nova Delegação |
| Mesma espera, sem retirada de permissão | Inclusão concluída normalmente |
| Repetição da Inclusão direta com permissão válida | Mesma Delegação, sem duplicação |
| Ator já sem permissão ao iniciar | Recusa imediata pelo contrato existente |
| Ator explícito diferente do usuário da sessão | Decisão baseada na autoridade do ator informado |
| Professor com permissão herdada do curso | Inclusão permitida no contexto do Questionário |
| Administrador e usuário com papel temporariamente reduzido | Preservação da semântica Moodle de autoridade |

Manter os testes existentes de idempotência, elegibilidade e concessão concorrente por convite. Acrescentar casos somente onde a alteração introduzir risco ainda não coberto. Não usar administrador como Professor no teste de revogação, pois seus privilégios implícitos invalidariam esse cenário.

**Conclusão desta etapa:** a corrida de retirada e o controle positivo da espera passam, a idempotência permanece e as variantes relevantes do ator não contornam nem perdem a autorização correta.

### 4. Executar as verificações e revisar

Preparar um ambiente Moodle com PHPUnit inicializado, processos independentes e banco/diretório de dados exclusivos de teste. Começar por Moodle 4.5, PHP 8.3 e PostgreSQL 15, coerente com a evidência da issue. O ambiente Docker de uso manual descrito no README não inicializa PHPUnit/Behat; não presumir que ele está pronto para essa execução. Nesta sessão, `php` não foi encontrado no ambiente de execução do agente.

Comandos previstos, executados a partir da raiz do Moodle 4.5 e com o plugin instalado no caminho padrão:

```bash
# Nome proposto para o teste de regressão a criar.
vendor/bin/phpunit --filter test_capability_revoked_during_lock_wait mod/quiz/accessrule/presencial/tests/delegation_concurrency_test.php

vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/delegation_manager_test.php
vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/delegation_concurrency_test.php
vendor/bin/phpunit --testsuite quizaccess_presencial_testsuite
```

Executar lint, Moodle Code Checker e PHPDoc nos arquivos alterados, usando a configuração existente do projeto. Confirmar a cobertura funcional em MariaDB e as versões suportadas pela matriz de CI; a observação de espera específica de PostgreSQL permanece explicitamente limitada a esse banco. Não afirmar reprodução concorrente em MariaDB sem uma verificação equivalente.

Usar as jornadas Behat existentes de gestão/Delegações como verificação de integração quando o ambiente estiver disponível. Não criar um cenário de navegador para substituir a sincronização determinística entre conexões.

Revisar o diff contra os critérios da issue e as instruções do repositório, com atenção à autoridade do ator explícito, caches, ausência de efeitos em caso de recusa e comportamento transacional. Registrar na entrega quais verificações foram executadas e suas limitações.

## Limites do fix

- Sem alterações de banco, capabilities novas, mudanças de interface ou nova regra de domínio.
- Não estender automaticamente a correção a revogação, geração de convite ou outras operações. Padrões semelhantes são candidatos a investigação própria, não evidência de falha coberta pela #40.
- Não extrair um serviço geral de autorização nem refatorar o fluxo de decisões apenas para compartilhar código nesta correção.
- O compromisso é considerar a retirada já confirmada durante a espera do bloqueio; o plano não promete atomicidade global contra qualquer mudança de permissão posterior à validação.
- A consulta de atribuições persistidas pode recusar uma capability concedida exclusivamente por papel temporário mantido na sessão por um plugin de matrícula. A mudança concorrente do próprio status de administrador também depende da configuração carregada pelo Moodle; esses casos não foram cobertos pela reprodução da issue.
- Preservar as alterações locais preexistentes em `README.md`, `docs/guia-de-uso.md`, `docs/imagens/` e `docs/validacao-local-2026-11-13.md`.
- Commit, push, abertura de PR e atualizações no GitHub ficam fora desta etapa de planejamento.

## Aceite final da implementação

- [ ] A reprodução falha antes da correção pelo sintoma da #40 e passa depois dela.
- [ ] A autoridade é verificada após o bloqueio, com dados vigentes, antes de conceder Delegações.
- [ ] Retirada confirmada durante a espera produz recusa de permissão e nenhuma nova Delegação.
- [ ] Inclusão com permissão válida e repetição idempotente continuam funcionando.
- [ ] A cobertura usa conexões independentes e não depende de invalidar o cache pelo teste para passar.
- [ ] As verificações pertinentes e a revisão foram concluídas, com limitações de ambiente/banco documentadas.
