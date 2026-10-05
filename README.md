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

Esta entrega fornece o componente instalável, a configuração global, o formulário de configuração por Questionário, o gerenciamento direto da equipe de aplicação, internacionalização, declaração de privacidade e testes automatizados.

Ao habilitar **Liberação Presencial** nas configurações de um Questionário, o plugin grava na tabela `quizaccess_presencial` a configuração daquele Questionário e o início e o fim do Período de Autorização. Há somente um registro por Questionário; desabilitar a opção suspende a regra e preserva o período para uma reabilitação posterior. As alterações também geram o evento de configuração correspondente no log do Moodle.

A partir das configurações do Questionário, o Professor pode abrir **Gerenciar equipe de aplicação**, incluir Contas elegíveis pelo seletor padrão do Moodle e revogar Delegações individualmente. Essas operações não criam matrícula nem atribuem papéis no curso. A inclusão repetida é idempotente; uma nova inclusão depois de revogação cria outro registro e preserva o anterior.

Nesta etapa, a regra ainda não impede nem autoriza o início de novas tentativas. Em particular, ela não cria Solicitações de liberação, não emite Autorizações de tentativa e não oferece o fluxo para Professor ou Aplicador decidir essas solicitações. Assim, mesmo quando habilitada e com o período salvo, a Liberação Presencial não altera o fluxo nativo de tentativas do Questionário.

## Testes

### Moodle local com Docker Compose

Com Docker e Docker Compose disponíveis, inicie o ambiente na raiz deste repositório:

```bash
docker compose up -d --build
docker compose logs -f moodle
```

A primeira inicialização baixa o Moodle 4.5.12 e instala automaticamente o site e o plugin,
usando PHP 8.3 e PostgreSQL 15. Quando a instalação terminar, acesse
<http://localhost:8085> com o usuário `admin` e a senha `PresencialLocal_2026!`.
O serviço fica disponível somente na máquina local e não envia e-mails.

O código deste repositório é montado diretamente no Moodle. Alterações no plugin ficam
disponíveis sem reconstruir a imagem; depois de uma mudança que exija atualização ou
limpeza de cache, execute:

```bash
docker compose restart moodle
```

Para usar outra porta ou senha administrativa na primeira instalação:

```bash
MOODLE_PORT=8086 MOODLE_ADMIN_PASSWORD='OutraSenha_2026!' docker compose up -d --build
```

Mantenha a mesma porta nos comandos seguintes. Alterar a variável de senha depois da
instalação não altera a senha da conta existente.

No navegador, é possível testar as configurações administrativas, a ativação por
Questionário, o preenchimento e a validação das datas, a preservação do período e os logs.
As configurações globais estão em
<http://localhost:8085/admin/settings.php?section=modsettingsquizcatpresencial>.
O início de tentativas continua seguindo o fluxo nativo do Moodle, mesmo com a opção
habilitada. A instalação usa Português do Brasil (`pt_br`) como idioma padrão e baixa
automaticamente o pacote de tradução do Moodle.

Para parar o ambiente preservando o banco de dados e os arquivos do site:

```bash
docker compose down
```

Os dados ficam nos volumes Docker `database` e `moodledata` deste projeto Compose.
Este ambiente serve para testes manuais; ele não configura PHPUnit ou Behat.

### Testes automatizados

A integração contínua executa lint, verificações do `moodle-plugin-ci`, PHPUnit e o cenário de fumaça Behat em PostgreSQL e MariaDB. Em um ambiente preparado pelo `moodle-plugin-ci`, execute:

```bash
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome --tags=@quizaccess_presencial
```

## Privacidade

O plugin armazena Delegações de aplicação com a conta, o Questionário, o Período de Autorização, a origem e os dados de auditoria de criação e revogação. Seu provider declara esses dados e atende à descoberta de contexto, exportação e exclusão pela Privacy API do Moodle.

## Licença

GNU GPL v3 ou posterior. Consulte `LICENSE`.
