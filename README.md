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

Esta entrega fornece o componente instalável, a configuração global, o contrato da regra, internacionalização, declaração de privacidade e testes automatizados. Ela ainda não adiciona tabelas ou restrições ao início de tentativas.

## Testes

A integração contínua executa lint, verificações do `moodle-plugin-ci`, PHPUnit e o cenário de fumaça Behat em PostgreSQL e MariaDB. Em um ambiente preparado pelo `moodle-plugin-ci`, execute:

```bash
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome --tags=@quizaccess_presencial
```

## Colaboração: criar um pull request

A skill [criar-pr](.agents/skills/criar-pr/SKILL.md) acompanha este repositório em `.agents/skills/criar-pr/`. Abra o projeto no Codex e acione-a com a issue e o escopo da mudança:

```text
$criar-pr Abra um PR para a issue #22 com as alterações da skill criar-pr.
```

Você pode indicar outra branch de destino no pedido. A skill verifica se pode reutilizar a branch atual, seleciona as alterações pertinentes, faz commit e push, abre ou atualiza o PR e o atribui ao autor. Ela registra a issue na descrição e usa fechamento automático quando aplicável.

É necessário acesso Git ao remoto para push e acesso autenticado ao GitHub pelo conector do ambiente ou pela CLI `gh`. Se a skill não aparecer na lista, peça explicitamente ao agente que leia `.agents/skills/criar-pr/SKILL.md` e execute suas instruções. Em caso de falha, ela informa o que concluiu e o que falta; peça para retomar o mesmo PR após resolver o impedimento.

## Privacidade

O plugin declara um `null_provider` porque esta versão não armazena nem transmite dados pessoais. Quando uma entrega futura introduzir persistência ou integração externa, o provider deverá ser atualizado para declarar e atender esses dados pela Privacy API do Moodle.

## Licença

GNU GPL v3 ou posterior. Consulte `LICENSE`.
