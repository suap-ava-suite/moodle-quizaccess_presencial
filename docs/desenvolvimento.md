# Documentação auxiliar para desenvolvimento

Referências oficiais do Moodle para desenvolver e testar o `quizaccess_presencial`. Os links versionados apontam para o Moodle 4.5, versão mínima suportada pelo plugin; ao trabalhar com outras versões, confira também os contratos e o código da versão em teste.

| Referência | Quando consultar |
| --- | --- |
| [Privacy API — Moodle 4.5](https://moodledev.io/docs/4.5/apis/subsystems/privacy) | Ao descrever dados pessoais armazenados, implementar exportação e apagamento por usuário ou contexto e testar o comportamento após exclusão. Consultar os contratos dos provedores de metadados e de solicitações. |
| [Events API](https://docs.moodle.org/dev/Events_API) | Ao definir ou revisar eventos de auditoria. Conferir contexto, usuário, objeto, tipo de operação e campos adicionais; manter tokens, URLs com segredos e outros dados de credenciais fora dos eventos. |
| [Access API — Moodle 4.5](https://moodledev.io/docs/4.5/apis/subsystems/access) | Ao verificar capabilities no contexto do Questionário e organizar a autorização compartilhada. Consultar `has_capability()` e `require_capability()`; para revalidação após espera por bloqueios, complementar a documentação com o código da versão Moodle em teste e os testes concorrentes do plugin. |
| [`core\encryption` — Moodle 4.5](https://phpdoc.moodledev.io/4.5/d4/d91/classcore_1_1encryption.html) | Ao armazenar ou recuperar segredos e testar chave ausente ou diferente. Consultar `key_exists()`, `create_key()`, `encrypt()` e `decrypt()`, considerando que `get_key()` pode criar automaticamente uma chave quando ela não existe. |

As convenções de teste e idioma estão em [AGENTS.md](../AGENTS.md). O vocabulário do plugin está em [CONTEXT.md](../CONTEXT.md), e as decisões do projeto ficam em [docs/adr/](adr/).
