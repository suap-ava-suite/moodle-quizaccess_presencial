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

## Configuração global

Em **Administração do site > Plugins > Módulos de atividade > Questionário > Liberação Presencial**, o administrador configura:

- a validade da Solicitação de liberação, em minutos inteiros positivos (padrão: 15);
- a validade da Autorização de tentativa não consumida, em minutos inteiros positivos (padrão: 5); e
- se a justificativa de rejeição é obrigatória (padrão: não).

Valores de prazo inválidos não são salvos.

## Estado atual

Esta entrega fornece o componente instalável, a configuração global, o formulário de configuração por Questionário, internacionalização, declaração de privacidade e testes automatizados.

Ao habilitar **Liberação Presencial** nas configurações de um Questionário, o plugin grava na tabela `quizaccess_presencial` a configuração daquele Questionário e o início e o fim do Período de Autorização. Há somente um registro por Questionário; desabilitar a opção suspende a regra e preserva o período para uma reabilitação posterior. As alterações também geram o evento de configuração correspondente no log do Moodle.

Nesta etapa, a regra ainda não impede nem autoriza o início de novas tentativas. Em particular, ela não cria Solicitações de liberação, não emite Autorizações de tentativa e não oferece o fluxo para Professor ou Aplicador decidir essas solicitações. Assim, mesmo quando habilitada e com o período salvo, a Liberação Presencial não altera o fluxo nativo de tentativas do Questionário.

## Testes

A integração contínua executa lint, verificações do `moodle-plugin-ci`, PHPUnit e o cenário de fumaça Behat em PostgreSQL e MariaDB. Em um ambiente preparado pelo `moodle-plugin-ci`, execute:

```bash
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome --tags=@quizaccess_presencial
```

## Privacidade

O plugin declara um `null_provider`: embora armazene a configuração e o período de cada Questionário, não armazena nem transmite dados pessoais. Quando uma entrega futura introduzir dados pessoais ou integração externa, o provider deverá ser atualizado para declarar e atender esses dados pela Privacy API do Moodle.

## Licença

GNU GPL v3 ou posterior. Consulte `LICENSE`.
