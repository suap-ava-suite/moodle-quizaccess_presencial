# Plano e validação da correção da issue #40

## Objetivo e estado

Impedir que uma Inclusão direta conceda uma Delegação de aplicação depois que o Professor perdeu a autoridade durante a espera pelo bloqueio do Questionário, preservando as inclusões ainda autorizadas e a idempotência.

- Issue: [#40](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/issues/40).
- Pull request: [#43](https://github.com/suap-ava-suite/moodle-quizaccess_presencial/pull/43), branch `fixissue40`, destino `main`.
- Base original: `16d84e9027096348b24638e52a6ffd17b373c93a`.
- Implementação inicial: `c6c584c`; ajustes de padrões: `37daf91`.
- Revisão e tratamento dos cinco comentários do PR: 18/11/2026.

O [ADR 0006](adr/0006-reutilizar-a-capability-de-gestao-do-questionario.md) permanece válido: a autoridade depende de `mod/quiz:manage` no contexto do Questionário, independentemente do nome do papel.

## Diagnóstico e reprodução

Na base original, `delegation_manager::include_users` verificava a capability somente antes de adquirir o bloqueio. A reprodução controlada confirmou que uma atribuição de papel retirada e confirmada durante a espera não impedia a concessão.

A revisão do PR confirmou também dois limites da primeira implementação:

- Uma permissão temporária reconhecida pelo Moodle era recusada porque a consulta considerava apenas atribuições persistidas.
- A retirada do status de administrador durante a espera não era percebida porque `is_siteadmin` usava a configuração carregada no início da requisição.

Os novos testes reproduziram essas falhas antes dos ajustes. Foram acrescentados controles para evitar que a preservação de permissões temporárias reaceitasse uma atribuição persistida revogada em cache.

## Implementação entregue

[`delegation_manager::include_users`](../classes/local/delegation_manager.php) mantém a verificação inicial e revalida a autoridade imediatamente após adquirir o bloqueio, antes de sincronizar configurações ou conceder Delegações.

A revalidação:

1. Mantém as restrições de `has_capability` e verifica a existência atual de uma conta não excluída.
2. Consulta a lista vigente de administradores diretamente na configuração persistida, respeitando `siteadmins` quando forçado por `config.php` e a mesma comparação de IDs usada pelo Moodle.
3. Obtém as atribuições atuais e os papéis implícitos por `get_user_roles_with_special`.
4. Usa `get_roles_with_cap_in_context` para delegar ao Moodle a precedência de permissões do papel assumido, do papel padrão e das atribuições persistidas. Não carrega as definições de todas as capabilities do site nem mantém uma cópia desse algoritmo.
5. Quando a autoridade depende de acesso temporário, pede nova concessão aos plugins de matrícula ativos por `try_guestaccess`, usando uma cópia dos dados da sessão construída apenas com atribuições atuais. As configurações relevantes de matrícula são lidas novamente, e os objetos globais originais são restaurados em `finally`.
6. Retorna a exceção de permissão pela operação protegida e a lança depois de `quiz_lock::execute`, seguindo o padrão existente de erros esperados e preservando a transação externa.

### Contrato de permissões temporárias

A Inclusão direta preserva papéis temporários cuja concessão possa ser renovada por uma instância ativa de matrícula por meio de `try_guestaccess`. Papéis presentes apenas no cache da sessão, sem uma origem que reconceda o acesso, não são mantidos como prova de autoridade.

Essa distinção é necessária porque `load_temp_course_role` e o cache de atribuições persistidas usam a mesma estrutura, sem uma marca de origem. Preservar indiscriminadamente a diferença entre sessão e banco poderia reaceitar um papel revogado antes da chamada.

A revalidação respeita proibições das atribuições atuais e as regras contextuais do Moodle. Ela não cria matrículas nem atribuições persistidas para conceder acesso temporário.

## Testes e diagnóstico

A cobertura está nos limites públicos de [`tests/delegation_manager_test.php`](../tests/delegation_manager_test.php) e [`tests/delegation_concurrency_test.php`](../tests/delegation_concurrency_test.php).

| Cenário | Resultado esperado |
| --- | --- |
| Atribuição retirada durante a espera, para ator da sessão ou explícito | Recusa sem nova Delegação |
| Permissão mantida durante a espera | Inclusão válida e repetição idempotente |
| Atribuição revogada antes da chamada, mas ainda no cache | Recusa; não é tratada como concessão temporária |
| Administrador removido sem outro privilégio | Recusa |
| Administrador removido com papel de Professor ainda válido | Inclusão permitida pelo papel |
| Lista de administradores forçada em `config.php`, inclusive com espaços | Prevalência da configuração forçada |
| Concessão temporária renovada por plugin ativo | Inclusão permitida e idempotente |
| Instância de matrícula desabilitada ou permissão temporária retirada | Recusa |
| Proibição persistida concomitante à concessão temporária | Recusa |
| Conta excluída durante a espera | Recusa, mesmo com permissão no papel padrão |
| Combinações de permissões no curso, Questionário e papel padrão | Precedência aplicada pelo Moodle |

Os testes concorrentes usam processos e conexões independentes. `pg_blocking_pids` confirma a espera real pelo bloqueio antes da alteração de autoridade. A ausência ou existência de Delegações é observada pela operação pública `list_current`.

As tabelas modificadas pelo subprocesso são informadas ao rastreador público do PHPUnit no processo principal, garantindo a limpeza entre cenários. Em caso de falha antes dessa resposta, a limpeza considera todas as tabelas.

A inicialização dos subprocessos foi centralizada, preservando o fallback de autoload para Moodle 5.1 e `max_input_vars=5000`. As respostas são verificadas antes da decodificação, identificando se faltou a conexão ou o resultado da inclusão. A fixture captura somente `required_capability_exception` como recusa esperada; outras exceções permanecem visíveis no diagnóstico.

## Validação

Ambiente local: Moodle 4.5.12, PHP 8.3.36, PostgreSQL 15.19 e PHPUnit 9.6.34, com banco e diretórios exclusivos de teste.

A partir da raiz de um Moodle com PHPUnit preparado e o plugin instalado no caminho padrão:

```bash
vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/delegation_manager_test.php
vendor/bin/phpunit mod/quiz/accessrule/presencial/tests/delegation_concurrency_test.php
vendor/bin/phpunit --testsuite quizaccess_presencial_testsuite
```

O teste original também pode ser executado isoladamente:

```bash
vendor/bin/phpunit --filter test_inclusion_rechecks_authority_after_lock_wait mod/quiz/accessrule/presencial/tests/delegation_concurrency_test.php
```

Resultados finais: **256 testes e 1.628 verificações aprovados** na suíte completa; Moodle Code Checker 3.9.0 aprovado em todo o plugin; sintaxe PHP e `git diff --check` aprovados.

A revisão independente dos padrões e da conformidade com a issue foi repetida após os ajustes. Os resultados locais não substituem a matriz de versões e bancos do CI; a sincronização por monitoramento de bloqueios é específica de PostgreSQL e é explicitamente ignorada nos demais bancos. Behat não foi executado localmente nesta rodada.

## Escopo e aceite

A mudança fica restrita à Inclusão direta e aos testes associados. Não altera o esquema de dados, não cria capabilities nem modifica a interface. Revogação de Delegações e gestão de convites permanecem fora desta correção.

- [x] A retirada confirmada durante a espera é percebida antes da concessão.
- [x] Permissão válida e repetição idempotente são preservadas.
- [x] Administradores são avaliados pela configuração vigente, respeitando `config.php`.
- [x] Temporários são revalidados por uma origem ativa, sem confiar em atribuições revogadas em cache.
- [x] A precedência de permissões é calculada pelas APIs do Moodle.
- [x] O bootstrap e os diagnósticos dos subprocessos foram ajustados conforme a revisão.
- [x] Os limites de compatibilidade e de validação estão documentados.
