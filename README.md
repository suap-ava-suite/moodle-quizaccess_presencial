# quizaccess_presencial

O `quizaccess_presencial` é uma regra de acesso para Questionários do Moodle que permitirá condicionar o início de novas tentativas a uma Liberação Presencial. Nesta versão inicial, a regra permanece inativa e não altera Questionários que ainda não possuem configuração própria.

## Compatibilidade

- Moodle 4.5, 5.0 e 5.1
- PHP 8.1 a 8.4, conforme a versão do Moodle
- PostgreSQL e MariaDB

## Instalação

Instale o plugin pelo instalador de plugins do Moodle ou coloque este código no diretório `mod/quiz/accessrule/presencial`. No Moodle 5.1, o diretório correspondente fica sob a nova raiz pública: `public/mod/quiz/accessrule/presencial`.

Depois, execute a atualização administrativa do Moodle pela interface web ou pela CLI:

```bash
php admin/cli/upgrade.php --non-interactive
```

## Estado atual

Esta entrega fornece o componente instalável, o contrato da regra, internacionalização, declaração de privacidade e testes automatizados. Ela não adiciona configurações, tabelas ou restrições ao início de tentativas.

## Testes

A integração contínua executa lint, verificações do `moodle-plugin-ci`, PHPUnit e o cenário de fumaça Behat em PostgreSQL e MariaDB. Em um ambiente preparado pelo `moodle-plugin-ci`, execute:

```bash
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome --tags=@quizaccess_presencial
```

## Privacidade

O plugin declara um `null_provider` porque esta versão não armazena nem transmite dados pessoais. Quando uma entrega futura introduzir persistência ou integração externa, o provider deverá ser atualizado para declarar e atender esses dados pela Privacy API do Moodle.

## Licença

GNU GPL v3 ou posterior. Consulte `LICENSE`.
