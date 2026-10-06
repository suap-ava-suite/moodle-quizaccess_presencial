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

Esta entrega fornece o componente instalável, a configuração global, o formulário de configuração por Questionário, a gestão do Convite por link, internacionalização, Privacy API e testes automatizados.

Ao habilitar **Liberação Presencial** nas configurações de um Questionário, o plugin grava na tabela `quizaccess_presencial` a configuração daquele Questionário e o início e o fim do Período de Autorização. Há somente um registro por Questionário; desabilitar a opção suspende a regra e preserva o período para uma reabilitação posterior. As alterações também geram o evento de configuração correspondente no log do Moodle.

Professores com `mod/quiz:manage` no contexto do Questionário encontram **Convite** no menu **Mais**. O link completo aparece somente na resposta à geração, com um botão para copiá-lo. A página posterior mostra apenas o estado e a validade. Regenerar substitui imediatamente o segredo anterior; desativar impede seu uso. Desabilitar e reabilitar a regra também exige gerar outro convite.

O convite pode ser gerado e validado antes do início do Período de Autorização, mas expira no seu fim. Encurtar o período limita a validade definitivamente; ampliá-lo depois não estende nem reativa o convite existente. A validação aplica a expiração imediatamente, e uma tarefa agendada materializa as expirações pendentes sem repetir eventos. Transições de convite e tentativas de uso inválido são registradas pela Events API, sem o token.

O token usa 32 bytes de `random_bytes()`; somente sua derivação SHA-256 com identificação de finalidade é persistida. As alterações usam bloqueio por Questionário, transação e uma geração numerada para recusar formulários antigos. A API pública de domínio `quizaccess_presencial\local\invitation::validate($cmid, $token)` retorna somente um booleano. A rota de destino do link e a jornada de autenticação, apresentação, aceite e criação de Delegação serão entregues na Issue #8; esta versão ainda não atende esse destino.

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

A integração contínua executa lint, verificações do `moodle-plugin-ci`, PHPUnit e os cenários Behat em PostgreSQL e MariaDB. PHPUnit cobre geração, rejeição de tokens, estados, concorrência, integração com configuração, navegação, CSRF e privacidade; Behat cobre as jornadas de gestão e cópia do link. Em um ambiente preparado pelo `moodle-plugin-ci`, execute:

```bash
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome --tags=@quizaccess_presencial
```

## Privacidade

O registro do convite guarda a referência ao Professor que o gerou e dados mínimos do seu ciclo de vida. A Privacy API declara esses dados, localiza os usuários e contextos correspondentes, exporta o estado sem segredos e atende à exclusão individual, em lote e por contexto. A exclusão dos dados de um usuário anonimiza e desativa seu convite, preservando a geração para impedir o reaproveitamento de formulários antigos. A configuração do Questionário é preservada. Os eventos permanecem sob responsabilidade dos subsistemas de logs do Moodle.

## Licença

GNU GPL v3 ou posterior. Consulte `LICENSE`.
