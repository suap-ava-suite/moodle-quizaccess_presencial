# quizaccess_presencial

O `quizaccess_presencial` é uma regra de acesso para Questionários do Moodle que permitirá condicionar o início de novas tentativas a uma Liberação Presencial. Nesta versão inicial, a regra permanece inativa e não altera Questionários que ainda não possuem configuração própria.

`quizaccess_presencial` is a Moodle quiz access rule that will make starting a new attempt conditional on an in-person release. In this initial version, the rule remains inactive and does not change quizzes that have no plugin configuration.

## Compatibilidade / Compatibility

- Moodle 4.5, 5.0 e 5.1 / Moodle 4.5, 5.0, and 5.1
- PHP 8.1 a 8.4, conforme a versão do Moodle / PHP 8.1 to 8.4, depending on the Moodle version
- PostgreSQL e MariaDB / PostgreSQL and MariaDB

## Instalação / Installation

Instale o plugin pelo instalador de plugins do Moodle ou coloque este código no diretório `mod/quiz/accessrule/presencial`. No Moodle 5.1, o diretório correspondente fica sob a nova raiz pública: `public/mod/quiz/accessrule/presencial`.

Install the plugin using Moodle's plugin installer or place this code in `mod/quiz/accessrule/presencial`. In Moodle 5.1, the corresponding directory is under the new public root: `public/mod/quiz/accessrule/presencial`.

Depois, execute a atualização administrativa do Moodle pela interface web ou pela CLI:

Then run the Moodle administrative upgrade from the web interface or CLI:

```bash
php admin/cli/upgrade.php --non-interactive
```

## Estado atual / Current state

Esta entrega fornece o componente instalável, o contrato da regra, internacionalização, declaração de privacidade e testes automatizados. Ela não adiciona configurações, tabelas ou restrições ao início de tentativas.

This delivery provides the installable component, rule contract, internationalisation, privacy declaration, and automated tests. It does not add settings, database tables, or restrictions on starting attempts.

## Testes / Tests

A integração contínua executa lint, verificações do `moodle-plugin-ci`, PHPUnit e o cenário de fumaça Behat em PostgreSQL e MariaDB. Em um ambiente preparado pelo `moodle-plugin-ci`, execute:

Continuous integration runs linting, `moodle-plugin-ci` checks, PHPUnit, and the Behat smoke scenario on PostgreSQL and MariaDB. In an environment prepared by `moodle-plugin-ci`, run:

```bash
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome --tags=@quizaccess_presencial
```

## Privacidade / Privacy

O plugin declara um `null_provider` porque esta versão não armazena nem transmite dados pessoais. Quando uma entrega futura introduzir persistência ou integração externa, o provider deverá ser atualizado para declarar e atender esses dados pela Privacy API do Moodle.

The plugin declares a `null_provider` because this version neither stores nor transmits personal data. When a future delivery introduces persistence or an external integration, the provider must be updated to declare and handle that data through Moodle's Privacy API.

## Licença / License

GNU GPL v3 ou posterior. Consulte `LICENSE`.

GNU GPL v3 or later. See `LICENSE`.
