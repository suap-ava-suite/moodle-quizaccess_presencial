# Instruções do repositório

## Testes

- Teste o comportamento público exposto pelo Moodle, não métodos privados nem detalhes incidentais da estrutura SQL.
- Use `basic_testcase` quando o teste não precisar do estado do banco de dados do Moodle.
- Use `advanced_testcase` nos testes que exercitam a persistência do Moodle e chame `$this->resetAfterTest()` antes de alterar o estado do banco de dados.
- Acesse os geradores de dados do Moodle por meio de `self::getDataGenerator()`.
- Sobrescreva `setUp()` apenas para inicializações compartilhadas entre testes. Para isolamento, use `resetAfterTest()` em vez de tratar `parent::setUp()` como limpeza do banco de dados.
- Mantenha o PHPUnit concentrado nas regras de domínio e nos limites de integração. Use o Behat para jornadas representativas de navegador e navegação.

## Idioma

- Escreva o código em inglês, incluindo identificadores e comentários.
- Mantenha a conversa e os artefatos escritos em Português do Brasil. Identificadores técnicos, comandos, nomes de etiquetas e outros termos técnicos podem ser mantidos em inglês.

## Habilidades dos agentes

### Rastreamento de trabalho

Para consultar, criar ou atualizar issues, leia `docs/agents/issue-tracker.md`. O trabalho deste repositório é acompanhado no projeto nº 3 da organização `suap-ava-suite` no GitHub.

### Etiquetas de triagem

Para classificar issues, use o mapeamento de `docs/agents/triage-labels.md`.

### Documentação de domínio

Antes de explorar o código, siga `docs/agents/domain.md`. Este repositório usa um único contexto, com `CONTEXT.md` na raiz e decisões em `docs/adr/`.
