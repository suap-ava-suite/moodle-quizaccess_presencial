# quizaccess_presencial

O `quizaccess_presencial` é uma regra de acesso para Questionários do Moodle que condiciona o início de novas tentativas a uma Solicitação de liberação presencial. A regra só afeta Questionários em que foi habilitada.

## Compatibilidade

- Moodle 4.5, 5.0 e 5.1
- PHP 8.1 a 8.4, conforme a versão do Moodle
- PostgreSQL e MariaDB

As referências das APIs do Moodle e suas indicações de uso estão na [documentação auxiliar para desenvolvimento](docs/desenvolvimento.md).

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

Após a decisão de autorização, o estudante confirma em **Iniciar tentativa** na página de espera. Essa confirmação cria a próxima tentativa e consome a Autorização de tentativa em uma única transação. Reenvios da mesma confirmação recuperam a tentativa original, inclusive após sua conclusão; a tentativa seguinte exige outra Solicitação de liberação e outra decisão. A autorização não consumida expira conforme a configuração global, inicialmente após 5 minutos. A expiração exige uma nova Solicitação, enquanto retomar uma tentativa existente dispensa nova autorização. As verificações nativas do Questionário, incluindo senha e disponibilidade, continuam aplicáveis.

Ao habilitar **Liberação Presencial** nas configurações de um Questionário, o plugin grava na tabela `quizaccess_presencial` a configuração daquele Questionário e o início e o fim do Período de Autorização. Há somente um registro por Questionário; desabilitar a opção suspende a regra e preserva o período para uma reabilitação posterior. As alterações também geram o evento de configuração correspondente no log do Moodle.

No menu **Mais** do Questionário, o Professor com `mod/quiz:manage` no contexto do Questionário abre **Gerenciar equipe de aplicação**, inclui Contas elegíveis pelo seletor padrão do Moodle e revoga Delegações individualmente. Essas operações não criam matrícula nem atribuem papéis no curso e exigem uma submissão POST com token de sessão válido. A inclusão repetida é idempotente; uma nova inclusão depois de revogação cria outro registro e preserva o anterior.

O item **Minhas Aplicações** aparece no menu do usuário somente para Contas elegíveis com Delegação atual ou futura. A página lista disciplina, Questionário, Período de Autorização, estado da Delegação, quantidade pendente e a ação **Abrir aplicação**, com paginação de 20 itens no servidor. O Aplicador pode abrir essa página e o **Painel do Aplicador** sem matrícula, mantendo bloqueado o acesso acadêmico à disciplina e ao Questionário. O Professor acessa o mesmo painel pelo menu do Questionário, sem precisar receber uma Delegação.

Desabilitar a regra suspende as Delegações ainda válidas e encerra definitivamente as Solicitações pendentes e as Autorizações não reclamadas. Reabilitar a mesma configuração recupera somente Delegações que não tenham expirado nem sido revogadas; o convite anterior permanece inválido. Reduzir o fim do Período de Autorização limita a vigência das Delegações existentes, sem interrompê-las antes desse novo fim. Uma ampliação posterior não estende essa vigência. Configurar um novo período com início futuro encerra a equipe, as Solicitações vigentes e o convite anteriores; o Professor precisa conceder novas Delegações e gerar outro convite. Correções ao início de um período já em andamento preservam a equipe e as Solicitações vigentes. A validação sob demanda e as tarefas agendadas registram cada expiração uma única vez no log do Moodle. Tentativas já criadas continuam retomáveis.

Cada requisição verifica novamente a conta, a configuração, o período e a autoridade no Questionário. Revogação, expiração, suspensão da conta ou desabilitação da regra interrompe o acesso. Remover a suspensão recupera a mesma Delegação somente enquanto ela e o período continuam válidos; não há histórico pessoal de aplicações. Aplicações futuras mostram apenas informações de preparação, sem ações operacionais antes do início do período.

Ao carregar ou atualizar **Minhas Aplicações**, a coluna de solicitações pendentes mostra a contagem real por Questionário. Somente solicitações ainda pendentes e dentro do prazo entram na soma, mesmo que a tarefa de expiração não tenha sido executada; zero significa que não há pendências válidas. O Painel do Aplicador mostra a Fila operacional daquele Questionário, com atualização manual e paginação de 20 solicitações no servidor. Pendências aparecem da mais antiga para a mais recente, seguidas das decisões ainda revisáveis da mais recente para a mais antiga. A identidade operacional contém nome completo, foto e somente os campos de identidade configurados e visíveis ao operador segundo as políticas do Moodle, sem link para o perfil.

O painel destaca o nome do Questionário e a disciplina, permite rolagem horizontal da tabela em telas estreitas e reúne **Atualizar** e **Minhas Aplicações** como botões de navegação.

A mesma página reúne a gestão do **Convite** por link. O Professor pode copiar novamente o mesmo link enquanto o convite estiver ativo. Convites criados antes desta atualização permanecem válidos e precisam ser regenerados para permitir nova cópia. Regenerar substitui imediatamente o segredo anterior; desativar impede seu uso. Desabilitar e reabilitar a regra também exige gerar outro convite.

O convite pode ser gerado e validado antes do início do Período de Autorização, mas expira no seu fim. Encurtar o período limita a validade definitivamente; ampliá-lo depois não estende nem reativa o convite existente. A validação aplica a expiração imediatamente, e uma tarefa agendada materializa as expirações pendentes sem repetir eventos. Transições de convite e tentativas de uso inválido são registradas pela Events API, sem o token.

O token usa 32 bytes de `random_bytes()`; sua derivação SHA-256 é usada na validação e o token é armazenado criptografado para recuperação pelo Professor. A chave de criptografia pertence ao Moodle; ao restaurar o banco de dados, preserve também a chave do servidor para permitir a nova cópia. As alterações usam bloqueio por Questionário, transação e uma geração numerada para recusar formulários antigos. A API pública de domínio `quizaccess_presencial\local\invitation::validate($cmid, $token)` retorna somente um booleano. Ao abrir o link, visitantes são encaminhados ao login e retornam ao convite; depois da autenticação, a página apresenta somente a disciplina, o Questionário, o Período de Autorização e o Professor que o gerou. Uma Conta elegível pode aceitar antes do início ou durante o período, criando a mesma Delegação de aplicação da Inclusão direta com origem `invite`. Um novo aceite apenas informa a Delegação vigente, sem alterá-la.

Para uma nova tentativa, o Moodle valida primeiro suas regras nativas. Depois, o plugin cria ou reutiliza uma Solicitação pendente sem criar `quiz_attempt`, e o estudante acompanha o estado numa página que consulta o servidor a cada cinco segundos. Se uma consulta falhar, a página avisa que não consegue atualizar a Solicitação e continuará tentando se reconectar. A próxima resposta bem-sucedida retira o aviso e apresenta a Decisão vigente. Solicitações pendentes expiram no instante absoluto persistido na criação; o prazo padrão é 15 minutos. Uma autorização transiciona a solicitação para `authorized` e o estudante pode então seguir pelo fluxo normal de início; uma autorização não utilizada também expira após o prazo global configurado (padrão: 5 minutos). Ao começar a criação normal da tentativa, a autorização passa por um lease curto de início e é consumida quando o Moodle persiste a tentativa. Se a regra for desabilitada durante esse lease, a criação já autorizada é concluída e consumida; solicitações pendentes e autorizações ainda não reclamadas são encerradas. Tentativas em andamento são retomadas sem Solicitação.

Professores com `mod/quiz:manage` no contexto do Questionário e Aplicadores com Delegação vigente podem **Autorizar** ou **Rejeitar** pela fila, somente durante o Período de Autorização. Cada submissão usa POST e token de sessão e revalida a conta, a autoridade, o período e o estado da solicitação depois de obter os bloqueios necessários no banco, incluindo o tempo decorrido durante uma espera concorrente. A operação AJAX Moodle `quizaccess_presencial_authorize_request` aplica as mesmas regras, inclusive para Aplicadores sem matrícula.

Na fila, **Autorizar** aplica a decisão diretamente. **Rejeitar** abre os campos de justificativa, sem alterar o estado da solicitação; a decisão é enviada pelo botão **Confirmar rejeição**. Se houver erro de validação, os campos permanecem abertos e preservam o texto informado. A rejeição exige justificativa somente quando a política global determinar. A explicação é preservada integralmente e apresentada ao estudante como texto. A rejeição não expira pelo prazo da pendência ou da autorização cancelada: ela continua visível e revisável enquanto a solicitação estiver vigente e o Período de Autorização permitir decisões. Uma nova Solicitação encerra definitivamente a revisão da anterior, e a desabilitação da regra encerra as solicitações ainda vigentes. A espera continua consultando o estado automaticamente após rejeição ou autorização; uma alteração na decisão ou na justificativa atualiza a tela. **Autorizado** apresenta outro botão **Iniciar tentativa**, sem iniciar automaticamente.

A última decisão substitui a anterior enquanto a solicitação ainda admitir revisão. Rejeitar invalida uma autorização não consumida; autorizar depois de rejeitar libera a mesma solicitação. Após rejeição ou expiração, o estudante pode voltar ao Questionário e clicar imediatamente em iniciar para criar outra Solicitação. A anterior deixa de admitir revisões. Solicitações cujo início já foi confirmado ou cuja autorização foi consumida também recusam decisões posteriores e saem da fila. Cada mudança de decisão emite um evento Moodle com o estado, a justificativa e o prazo confirmados, preservados mesmo após revisões posteriores; a tabela operacional guarda somente a decisão vigente e sua justificativa. Reenviar uma decisão idêntica não altera seu prazo nem emite outro evento. Decisões e a criação da tentativa são serializadas até a confirmação da transação: a primeira transação confirmada vence a corrida entre rejeição e nascimento da tentativa, e a operação perdedora não produz estado contraditório.

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

A imagem bloqueia o acesso HTTP a arquivos e diretórios ocultos, incluindo `.env`
e `.git`, pois a raiz do repositório é montada no diretório público do plugin.
O bloqueio também se aplica ao acesso por túneis e retorna **403 Forbidden**.
Ao atualizar um ambiente existente, execute `docker compose up -d --build moodle`
para incorporar essa proteção à imagem.

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

### QR Code pelo celular com encaminhamento de porta do VS Code

O fluxo de QR Code exige HTTPS no celular. A exceção HTTP para `localhost` não
abrange um endereço como `http://192.168.1.10:8085`. Para testar sem configurar
certificados na rede local, use o encaminhamento de porta do VS Code:

1. Com o Docker em execução, abra **Ports** no VS Code e encaminhe a porta **8085**
   (ou a porta definida em `MOODLE_PORT`). O serviço local usa **HTTP**; o endereço
   externo fornecido pelo túnel deve usar **HTTPS**.
2. Copie o endereço externo. A visibilidade **Private** exige autenticação no
   túnel com a mesma conta do VS Code, inclusive no celular. **Public** dispensa
   esse login adicional, mantendo o login do Moodle. Consulte a
   [documentação de encaminhamento de portas do VS Code](https://code.visualstudio.com/docs/debugtest/port-forwarding).
3. Na raiz deste repositório, crie ou edite `.env`, preservando outras variáveis
   existentes, e configure o endereço externo sem barra final:

   ```dotenv
   MOODLE_WWWROOT=https://ENDERECO-DO-TUNEL.devtunnels.ms
   MOODLE_SSLPROXY=1
   MOODLE_REVERSEPROXY=1
   ```

4. Aplique a configuração; os volumes e os dados existentes são preservados:

   ```bash
   docker compose up -d --build moodle
   ```

O Moodle passa a gerar links, imagens e QR Codes com esse endereço. A configuração
`MOODLE_SSLPROXY=1` informa que o túnel termina o HTTPS e encaminha HTTP ao
container. `MOODLE_REVERSEPROXY=1` permite que o VS Code encaminhe o pedido com o
nome interno do servidor, conforme a [configuração de proxy do Moodle](https://github.com/moodle/moodle/blob/v4.5.12/config-dist.php).
Enquanto estiver nesse modo, abra a mesma URL HTTPS no computador e no celular.
Se o endereço do túnel mudar, atualize `.env` e repita o comando.

No computador, entre como estudante e inicie um Questionário com Liberação
Presencial e Período de Autorização vigente. A página de espera exibirá o QR Code.
No celular, abra primeiro o endereço HTTPS no navegador, conclua o acesso ao túnel
se necessário e entre como Professor ou Aplicador daquele Questionário. Leia um
QR Code atual com a câmera e abra o endereço no mesmo navegador em que fez login.
A leitura autoriza a Solicitação; o estudante ainda precisa clicar em **Iniciar
tentativa** no computador. Se o leitor não enviar Fetch Metadata, use outro
navegador ou leitor compatível. O código muda a cada 60 segundos.

Para voltar ao acesso local, remova `MOODLE_WWWROOT`, `MOODLE_SSLPROXY` e
`MOODLE_REVERSEPROXY` de `.env`,
execute `docker compose up -d moodle` e encerre o encaminhamento no VS Code. O
arquivo `.env` é ignorado pelo Git.

### QR Code pelo celular com ngrok e autorização pelo Professor

Com o Moodle local ativo, instale e autentique o ngrok conforme o
[guia oficial](https://ngrok.com/docs/start). No macOS:

```bash
brew install --cask ngrok
ngrok config add-authtoken SEU_TOKEN
ngrok http http://localhost:8085 --inspect=false
```

Informe o token somente no seu terminal. Mantenha o ngrok em execução e copie a
URL HTTPS que ele apresentar. Na raiz do repositório, crie ou atualize `.env`,
preservando outras variáveis existentes:

```dotenv
MOODLE_WWWROOT=https://SEU-DOMINIO.ngrok-free.app
MOODLE_SSLPROXY=1
MOODLE_REVERSEPROXY=0
```

Use o domínio realmente fornecido pelo ngrok, sem barra final. Essa configuração
considera o encaminhamento padrão, que preserva o Host externo. Aplique com
`docker compose up -d --build moodle`. Se estiver migrando do túnel do VS Code,
substitua o endereço anterior e altere também `MOODLE_REVERSEPROXY` para `0`.

No celular, abra primeiro a URL HTTPS no navegador e avance em **Visit Site** se
o ngrok apresentar sua página de entrada. Entre com uma conta de Professor que
tenha `mod/quiz:manage` no contexto do Questionário, por exemplo, matriculada como
Professor editor na disciplina. Essa conta pode autorizar sem receber Delegação
de Aplicador. Consulte os detalhes da
[página de entrada do plano gratuito](https://ngrok.com/docs/pricing-limits/free-plan-limits).

No computador, use a mesma URL HTTPS, entre como estudante e inicie um
Questionário com Liberação Presencial e Período de Autorização vigente. Apresente
o QR Code da página de espera ao Professor. No celular, leia o código com a câmera
e abra o endereço no mesmo navegador em que entrou como Professor. A leitura
autoriza a Solicitação imediatamente; o estudante ainda confirma em **Iniciar
tentativa**. Use um código atual, pois ele muda a cada 60 segundos.

Ao encerrar o teste, pare o ngrok. Para recuperar o acesso em `localhost`, remova
`MOODLE_WWWROOT`, `MOODLE_SSLPROXY` e `MOODLE_REVERSEPROXY` de `.env` e execute
`docker compose up -d moodle`. Se o domínio mudar na próxima execução do ngrok,
atualize `.env` antes de tentar ler outro QR Code.

### Testes automatizados

A integração contínua executa lint, verificações do `moodle-plugin-ci`, PHPUnit e os cenários Behat em PostgreSQL e MariaDB.

PHPUnit cobre Delegações (inclusão, idempotência, revogação e elegibilidade), geração e rejeição de tokens, estados do convite, concorrência, integração com configuração, navegação, CSRF e privacidade. Também cobre autorização, paginação e visibilidade de **Minhas Aplicações**, fronteiras temporais, autoridade contextual do Professor e suspensão com recuperação da mesma Delegação. A confirmação de início também tem cobertura de rollback, falha na criação, confirmações concorrentes, uso único, prazo configurável, expiração idempotente com eventos e reenvio após a conclusão. As correções operacionais têm cobertura de reenvio idempotente, substituição concorrente da decisão e da justificativa, rejeição concorrente com a criação real da tentativa nas duas ordens de confirmação e auditoria persistida somente após commit. A sincronização dessas novas corridas usa conexões PostgreSQL independentes; os testes de revisão, idempotência e rollback também executam nos demais bancos suportados. Behat demonstra o segundo clique, a entrada e a retomada da tentativa, além da nova Solicitação após expirar uma autorização. Também cobre a perda de conexão somente no navegador do estudante, com decisão em outra sessão conectada, aviso acessível sem anúncios repetidos e recuperação sem criar outra Solicitação ou iniciar a tentativa automaticamente. Os testes da Fila operacional verificam ordenação, paginação, isolamento entre Questionários, identidade mínima, decisões por Aplicadores sem matrícula, revisão, justificativa, revogação, suspensão e início da tentativa. Behat cobre autorização pelo painel, abertura dos campos de rejeição, confirmação explícita, preservação dos campos visíveis após erro de justificativa e atualização automática da espera, inclusive uma revisão posterior à rejeição.

Behat cobre as jornadas de gestão da equipe de aplicação, geração, cópia, desativação e regeneração do link, além do retorno ao convite após login sem sessão ou como visitante, do aceite explícito e da confirmação. As jornadas de aceite usam Boost e uma conta sem matrícula, antes e durante o Período de Autorização, e verificam o HTML completo para impedir a exposição de dados e navegação acadêmicos.

As jornadas do painel cobrem navegação sem matrícula, recusa de acesso acadêmico, revogação e suspensão na sessão existente, usuário sem autoridade e Professor sem Delegação. Os cenários de teclado usam um navegador real e percorrem menu, abertura, atualização, retorno e paginação com Tab, setas e Enter, verificando o foco sem forçá-lo por JavaScript.

Em um ambiente preparado pelo `moodle-plugin-ci`, execute:

```bash
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome --tags=@quizaccess_presencial
```

Para execução local, inicialize PHPUnit e Behat com o mesmo código e versão do Moodle usados para executar os testes, em banco e diretórios de dados exclusivos para testes. Não reutilize um ambiente inicializado por outra versão do Moodle. A URL de Behat deve ser diferente da URL do site e acessível pelo navegador de testes. Após configurar os prefixos, diretórios e o perfil Chrome em `config.php`, execute a partir da raiz do Moodle:

```bash
php admin/tool/phpunit/cli/init.php
php admin/tool/behat/cli/init.php
vendor/bin/phpunit --testsuite quizaccess_presencial_testsuite
vendor/bin/behat --config /CAMINHO/BEHAT/behatrun/behat/behat.yml --profile chrome --tags=@quizaccess_presencial
```

No Moodle 5.1 ou posterior, os scripts administrativos ficam sob `public/admin/` na raiz do projeto.

## Privacidade

O plugin armazena Delegações de aplicação, dados mínimos do ciclo de vida do Convite e Solicitações associadas ao estudante, Questionário, tentativa, estado e justificativa integral da rejeição vigente. A Privacy API declara essas categorias, localiza usuários e contextos, exporta os dados sem segredos e atende à exclusão individual, em lote e por contexto. A exportação indica se cada Solicitação ainda está ativa. A exclusão é transacional por Questionário, preserva sua configuração e mantém os registros de auditoria sob responsabilidade dos subsistemas de logs do Moodle.

## Licença

GNU GPL v3 ou posterior. Consulte `LICENSE`.

### Autorização por QR Code

Na espera da Solicitação, o estudante apresenta o QR Code temporário ao Professor ou Aplicador. O código é substituído automaticamente a cada 60 segundos; cada código válido pode ser usado uma única vez. Após a leitura, o operador autenticado autoriza a Solicitação imediatamente, e o estudante ainda confirma o início da tentativa. A leitura usa JavaScript, armazenamento de sessão e os cabeçalhos Fetch Metadata do navegador. O site deve usar HTTPS, salvo em `localhost`; navegadores e aplicativos leitores que não enviem esses cabeçalhos não conseguem usar o fluxo.

Links para o QR Code abertos a partir de conteúdo do próprio Moodle são recusados. Essa restrição reduz o risco de uma autorização por link, mas não comprova que uma navegação direta tenha vindo da câmera.
