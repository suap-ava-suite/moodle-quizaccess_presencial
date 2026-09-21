# Instruções do repositório

## Testes

- Teste o comportamento público exposto pelo Moodle, não métodos privados nem detalhes incidentais da estrutura SQL.
- Use `basic_testcase` quando o teste não precisar do estado do banco de dados do Moodle.
- Use `advanced_testcase` nos testes que exercitam a persistência do Moodle e chame `$this->resetAfterTest()` antes de alterar o estado do banco de dados.
- Acesse os geradores de dados do Moodle por meio de `self::getDataGenerator()`.
- Sobrescreva `setUp()` apenas para inicializações compartilhadas entre testes. Para isolamento, use `resetAfterTest()` em vez de tratar `parent::setUp()` como limpeza do banco de dados.
- Mantenha o PHPUnit concentrado nas regras de domínio e nos limites de integração. Use o Behat para jornadas representativas de navegador e navegação.
