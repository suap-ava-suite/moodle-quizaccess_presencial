# quizaccess_presencial

O `quizaccess_presencial` é uma regra de acesso para Questionários do Moodle que condiciona o início de novas tentativas a uma Solicitação de liberação presencial. A regra só afeta Questionários em que foi habilitada.

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

Esta entrega fornece o componente instalável, a configuração global e por Questionário, o gerenciamento direto da equipe de aplicação, a gestão do Convite por link, solicitações de liberação com espera automática, internacionalização, Privacy API e testes automatizados.

Ao habilitar **Liberação Presencial** nas configurações de um Questionário, o plugin grava na tabela `quizaccess_presencial` a configuração daquele Questionário e o início e o fim do Período de Autorização. Há somente um registro por Questionário; desabilitar a opção suspende a regra e preserva o período para uma reabilitação posterior. As alterações também geram o evento de configuração correspondente no log do Moodle.

No menu **Mais** do Questionário, o Professor com `mod/quiz:manage` no contexto do Questionário abre **Gerenciar equipe de aplicação**, inclui Contas elegíveis pelo seletor padrão do Moodle e revoga Delegações individualmente. Essas operações não criam matrícula nem atribuem papéis no curso e exigem uma submissão POST com token de sessão válido. A inclusão repetida é idempotente; uma nova inclusão depois de revogação cria outro registro e preserva o anterior.

A mesma página reúne a gestão do **Convite** por link. O link completo aparece somente na resposta à geração, com um botão para copiá-lo. A página posterior mostra apenas o estado e a validade. Regenerar substitui imediatamente o segredo anterior; desativar impede seu uso. Desabilitar e reabilitar a regra também exige gerar outro convite.

O convite pode ser gerado e validado antes do início do Período de Autorização, mas expira no seu fim. Encurtar o período limita a validade definitivamente; ampliá-lo depois não estende nem reativa o convite existente. A validação aplica a expiração imediatamente, e uma tarefa agendada materializa as expirações pendentes sem repetir eventos. Transições de convite e tentativas de uso inválido são registradas pela Events API, sem o token.

O token usa 32 bytes de `random_bytes()`; somente sua derivação SHA-256 com identificação de finalidade é persistida. As alterações usam bloqueio por Questionário, transação e uma geração numerada para recusar formulários antigos. A API pública de domínio `quizaccess_presencial\local\invitation::validate($cmid, $token)` retorna somente um booleano. A rota de destino do link e a jornada de autenticação, apresentação, aceite e criação de Delegação serão entregues na Issue #8; esta versão ainda não atende esse destino.

Para uma nova tentativa, o Moodle valida primeiro suas regras nativas. Depois, o plugin cria ou reutiliza uma Solicitação pendente sem criar `quiz_attempt`, e o estudante acompanha o estado numa página que consulta o servidor a cada cinco segundos. Solicitações pendentes expiram no instante absoluto persistido na criação; o prazo padrão é 15 minutos. Uma autorização transiciona a solicitação para `authorized` e o estudante pode então seguir pelo fluxo normal de início; uma autorização não utilizada também expira após o prazo global configurado (padrão: 5 minutos). Ao começar a criação normal da tentativa, a autorização passa por um lease curto de início e é consumida quando o Moodle persiste a tentativa. Se a regra for desabilitada durante esse lease, a criação já autorizada é concluída e consumida; solicitações pendentes e autorizações ainda não reclamadas são encerradas. Tentativas em andamento são retomadas sem Solicitação.

A autorização pode ser concedida durante o Período de Autorização pela operação AJAX Moodle `quizaccess_presencial_authorize_request`, restrita a usuários autenticados com `mod/quiz:manage` no contexto do Questionário. A fila e a interface do Professor/Aplicador para operar essa ação permanecem fora desta entrega.

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
Questionário, o preenchimento e a validação das datas, a preservação do período, a equipe
de aplicação, o Convite e a jornada de Solicitação/espera.
As configurações globais estão em
<http://localhost:8085/admin/settings.php?section=modsettingsquizcatpresencial>.
Uma Solicitação pendente não cria tentativa; o início segue após autorização pelo fluxo
normal do Moodle. A instalação usa Português do Brasil (`pt_br`) como idioma padrão e baixa
automaticamente o pacote de tradução do Moodle.

Para parar o ambiente preservando o banco de dados e os arquivos do site:

```bash
docker compose down
```

Os dados ficam nos volumes Docker `database` e `moodledata` deste projeto Compose.
Este ambiente serve para testes manuais; ele não configura PHPUnit ou Behat.

### Testes automatizados

A integração contínua executa lint, verificações do `moodle-plugin-ci`, PHPUnit e os cenários Behat em PostgreSQL e MariaDB. PHPUnit cobre solicitações de liberação (unicidade, idempotência, relógio, expiração, autorização, retomada e privacidade), Delegações (inclusão, revogação e elegibilidade), convites (tokens, estados e concorrência), integração com configuração, navegação e CSRF. Behat cobre as jornadas de equipe de aplicação, Convite e Solicitação/espera. Em um ambiente preparado pelo `moodle-plugin-ci`, execute:

```bash
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome --tags=@quizaccess_presencial
```

## Privacidade

O plugin armazena Delegações de aplicação, dados mínimos do ciclo de vida do Convite e Solicitações associadas ao estudante, Questionário, tentativa e estado. A Privacy API declara essas categorias, localiza usuários e contextos, exporta os dados sem segredos e atende à exclusão individual, em lote e por contexto. A exclusão preserva a configuração do Questionário e mantém os registros de auditoria sob responsabilidade dos subsistemas de logs do Moodle.

## Licença

GNU GPL v3 ou posterior. Consulte `LICENSE`.
