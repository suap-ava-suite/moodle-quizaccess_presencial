# Plano de entregas da Liberação Presencial

## Objetivo

Entregar incrementalmente o plugin `quizaccess_presencial`, que impede o início de uma nova tentativa de Questionário até que um Professor ou Aplicador presente conceda uma autorização temporária. Cada marco abaixo produz um comportamento observável e testável, mesmo antes de o fluxo completo estar disponível.

## Limites da primeira versão

- Um único plugin do tipo `quizaccess` concentra regra de acesso, configuração, convites, delegações, painel e navegação. Seus módulos internos devem permitir extração futura se surgir uma limitação concreta.
- O Professor é reconhecido pela permissão contextual de administrar o Questionário, implementada inicialmente com `mod/quiz:manage`; o nome do papel Moodle é irrelevante.
- O Aplicador não precisa estar matriculado na disciplina nem receber papel Moodle.
- O Período de Autorização é finito e fica integralmente contido no período de disponibilidade do Questionário.
- A primeira versão não implementa restrição por endereço de rede nem interface própria de consulta à auditoria.
- A compatibilidade mínima é Moodle 4.5, com PHP 8.3 como referência e PostgreSQL e MariaDB como bancos suportados. A integração contínua também deve testar Moodle 5.0 e 5.1.

## Visão dos marcos

| Marco | Capacidade demonstrável | Depende de |
| --- | --- | --- |
| 1 | Professor habilita e configura a regra | — |
| 2 | Professor inclui Aplicadores e administra o link | 1 |
| 3 | Usuário aceita o convite aberto | 2 |
| 4 | Aplicador acessa o painel autônomo | 2 e 3 |
| 5 | Estudante solicita, Aplicador decide e a tentativa começa | 1 e 4 |
| 6 | Aplicador autoriza pela leitura do QR Code | 5 |
| 7 | Eventos, privacidade, expirações e compatibilidade são consolidados | 1–6 |

## Marco 1 — Professor habilita e configura a Liberação Presencial

### Problema resolvido

O Professor consegue declarar que um Questionário exige autorização presencial e definir quando autorizações podem ser concedidas.

### Escopo

- Acrescentar à configuração padrão do Questionário a ativação da Liberação Presencial e o início e fim do Período de Autorização.
- Na primeira ativação, copiar abertura e fechamento do Questionário. Se um desses limites não existir, exigir que o Professor informe o valor ausente.
- Exigir um período finito, com fim futuro no momento da ativação, integralmente contido na disponibilidade do Questionário.
- Rejeitar uma alteração da disponibilidade do Questionário que tornaria o Período de Autorização incompatível. O Professor deve ajustar primeiro a configuração do plugin.
- Permitir ativação quando a disponibilidade do Questionário já começou, desde que as demais invariantes sejam atendidas.
- Criar configurações administrativas para expiração da Solicitação, inicialmente 15 minutos; validade da Autorização, inicialmente 5 minutos; e obrigatoriedade da justificativa de rejeição, inicialmente desativada.

### Demonstração e aceite

- O Professor ativa a regra, vê as datas inicialmente copiadas, salva uma configuração válida e a encontra preservada ao reabrir o formulário.
- Datas ausentes, invertidas ou fora da disponibilidade produzem erro explicativo e não alteram a configuração anterior.
- Uma alteração incompatível da disponibilidade do Questionário falha sem truncar silenciosamente o Período de Autorização.
- Desabilitar a regra suspende Delegações ainda válidas e encerra definitivamente Solicitações pendentes e Autorizações não consumidas. Reabilitá-la no mesmo período recupera somente as Delegações.
- Tentativas que já existem continuam acessíveis sem nova autorização.

## Marco 2 — Professor inclui Aplicadores e administra o Convite por link

### Problema resolvido

O Professor consegue preparar a equipe de aplicação por seleção direta ou pela geração de um convite compartilhável.

### Escopo

- Oferecer uma página do plugin acessível pelo menu do Questionário para administrar Aplicadores e o Convite por link.
- Permitir Inclusão direta de vários usuários por meio do seletor padrão do Moodle, respeitando os campos de identidade e as políticas de visibilidade da instalação.
- Considerar elegível qualquer conta confirmada, autenticável, não excluída, não suspensa e diferente da conta de visitante, independentemente de papel, matrícula, curso ou categoria.
- Criar a Delegação imediatamente após a confirmação da seleção e registrar sua origem como direta.
- Tratar nova inclusão de um Aplicador ativo de forma idempotente. Se uma Delegação anterior foi revogada, criar uma nova em vez de reativar a antiga.
- Permitir revogação individual imediata, preservando os registros já produzidos.
- Manter um único Convite por link ativo por Questionário, válido por padrão até o fim do Período de Autorização.
- Armazenar somente uma derivação segura do token e mostrar o link completo apenas durante sua geração. Regenerar invalida o link anterior; desativar impede novos aceites sem revogar Delegações existentes.

### Demonstração e aceite

- O Professor inclui usuários sem matrícula na disciplina, inclusive vários de uma vez, e vê suas Delegações e origens.
- Contas inelegíveis não podem ser incluídas e uma inclusão duplicada não cria outro registro.
- A revogação impede imediatamente novas ações do Aplicador.
- O Professor gera e copia o link uma única vez. Depois disso, a interface informa sua existência sem revelar o segredo.
- O link antigo falha depois de regeneração; a desativação não remove Aplicadores que já ingressaram.

## Marco 3 — Usuário aceita o Convite por link

### Problema resolvido

Um usuário com conta Moodle elegível consegue tornar-se Aplicador sem intervenção adicional do Professor.

### Escopo

- Exigir autenticação antes de revelar e aceitar o convite, preservando o destino durante o fluxo de login.
- Antes do aceite, mostrar somente disciplina, Questionário, Período de Autorização e nome do Professor que gerou o link.
- Permitir aceite antes do início e durante o Período de Autorização, nunca depois de seu fim.
- Criar uma Delegação equivalente à Inclusão direta e registrar sua origem no Convite por link.
- Tornar o aceite idempotente: se já existir Delegação válida, informar que o usuário já é Aplicador sem criar ou alterar registros.
- Recusar token expirado, desativado, substituído, associado a regra desabilitada ou acessado por conta inelegível.

### Demonstração e aceite

- Qualquer conta elegível com o link pode autenticar-se, conferir o escopo e aceitar o convite.
- O aceite não exige matrícula, papel específico nem aprovação posterior do Professor.
- Link inválido não revela questões, estudantes, notas ou outras informações restritas.
- Suspender a conta interrompe o exercício da Delegação; remover a suspensão o recupera somente enquanto a mesma Delegação e o Período de Autorização continuarem válidos.

## Marco 4 — Aplicador acessa e utiliza o Painel do Aplicador

### Problema resolvido

O Aplicador encontra suas aplicações e acessa a operação sem entrar na disciplina ou na página do Questionário.

### Escopo

- Acrescentar o item persistente **Minhas Aplicações** à navegação global ou ao menu do usuário somente quando existirem Delegações atuais ou futuras.
- Mostrar Questionários com Delegação válida, inclusive os cujo Período de Autorização ainda não começou, com disciplina, Questionário, período, estado, quantidade pendente e ação para abrir.
- Fornecer página autônoma protegida pela Delegação, sem conceder acesso acadêmico à disciplina.
- Mostrar na Fila operacional apenas solicitações pendentes e decisões ainda revisáveis.
- Identificar o estudante por nome completo, foto e somente campos institucionais permitidos; não oferecer link para seu perfil.
- Agrupar por Questionário, ordenar pendências da mais antiga para a mais recente e decisões revisáveis da mais recente para a mais antiga, com paginação no servidor.
- Exigir atualização manual da página pelo Aplicador.

### Demonstração e aceite

- Um Aplicador sem matrícula abre **Minhas Aplicações** e vê somente seus Questionários atuais e futuros.
- Um usuário sem Delegação não vê o item de navegação.
- Expiração, revogação ou suspensão da conta interrompe imediatamente o acesso aos Questionários e dados dos estudantes.
- O ex-Aplicador não possui histórico pessoal das aplicações passadas.
- O Professor pode operar o mesmo painel para Questionários que administra sem precisar de Delegação.

## Marco 5 — Estudante solicita e o Aplicador decide

### Problema resolvido

O fluxo presencial principal passa a controlar efetivamente a criação da próxima tentativa.

### Escopo

- Ao clicar em **Iniciar tentativa**, criar automaticamente uma Solicitação de liberação quando todas as demais regras do Questionário permitirem o início.
- Manter no máximo uma solicitação pendente por estudante, Questionário e próxima tentativa. Cliques repetidos retornam à solicitação existente sem alterar sua expiração.
- Expirar a solicitação pelo intervalo administrativo, inicialmente 15 minutos.
- Exibir uma espera automática que consulta o estado a cada 5 segundos, sem botão de verificação manual.
- Permitir que Professor ou Aplicador autorize ou rejeite somente dentro do Período de Autorização.
- Exigir ou não justificativa de rejeição conforme a configuração global; quando presente, mostrá-la integralmente ao estudante.
- Aplicar a última decisão confirmada enquanto a Solicitação ainda for revisável. Aprovação seguida de rejeição invalida a autorização não consumida; rejeição seguida de aprovação libera a tentativa.
- Depois de rejeição ou expiração, permitir que um novo clique crie imediatamente outra Solicitação.
- Ao autorizar, mostrar **Autorizado** e um novo botão **Iniciar tentativa**. Somente esse segundo clique cria a tentativa e consome a Autorização.
- Expirar a Autorização não consumida pelo intervalo administrativo, inicialmente 5 minutos.
- Direcionar o estado consumido para a tentativa existente e permitir retomá-la sem nova autorização.
- Resolver corrida entre rejeição e criação da tentativa pela primeira transação confirmada.

### Demonstração e aceite

- Sem Autorização válida, nenhuma nova tentativa é criada.
- A tela transita automaticamente entre Pendente, Autorizado, Rejeitado, Expirado e Consumido.
- Dois Aplicadores podem substituir decisões enquanto permitido sem produzir registros operacionais conflitantes.
- Depois que a tentativa nasce ou uma nova Solicitação substitui a anterior, a decisão antiga não pode mais ser alterada.
- A tabela operacional guarda somente a Decisão vigente; cada mudança gera evento Moodle.

## Marco 6 — Aplicador autoriza pela leitura do QR Code

### Problema resolvido

O Aplicador consegue liberar rapidamente o estudante presente usando outro dispositivo.

### Escopo

- Mostrar na espera do estudante um QR Code exclusivo da Solicitação e rotacioná-lo automaticamente a cada 60 segundos.
- Usar token próprio, de finalidade e validação distintas do token de Convite por link.
- Exigir que o leitor esteja autenticado como Professor autorizado ou Aplicador com Delegação válida.
- Autorizar imediatamente pela leitura válida, sem botão ou confirmação adicional.
- Executar autenticação, vigência, escopo, estado e autorização no servidor. A ausência de confirmação não autoriza uma alteração de estado por uma requisição desprotegida.
- Invalidar o código quando for substituído, expirar ou quando a Solicitação deixar de ser revisável.

### Demonstração e aceite

- A leitura por usuário autorizado muda a espera do estudante para **Autorizado** sem outra ação do Aplicador.
- Usuário não autenticado precisa entrar no Moodle; usuário sem permissão não autoriza.
- Código expirado, reutilizado ou pertencente a outra solicitação falha sem alterar estado.
- O QR Code não cria Delegação, e o link de convite não autoriza tentativa.

## Marco 7 — Auditoria, privacidade, expiração e endurecimento

### Problema resolvido

A solução fica administrável, rastreável e compatível com os mecanismos institucionais do Moodle sem manter históricos paralelos.

### Escopo

- Emitir eventos para habilitação, desabilitação e mudança do período; criação e revogação de Delegação; geração, desativação, regeneração e expiração de Convite; aceite; criação e expiração de Solicitação; cada mudança da Decisão vigente; consumo da Autorização; autorização por QR Code; e usos inválidos de convite ou QR Code.
- Incluir nos eventos contexto, resultado, responsáveis e identificadores necessários, sem duplicar dados de apresentação.
- Não criar interface própria de auditoria. Professores e auditores usam os mecanismos de log existentes, cuja retenção é configurada pela instituição.
- Implementar a Privacy API no contexto do módulo para declarar, localizar, exportar e eliminar dados pessoais. Deixar a retenção a cargo do Registro de Dados do Moodle e os eventos a cargo do subsistema de logs.
- Não copiar nome, foto, matrícula ou outros campos do perfil para as tabelas do plugin. Não armazenar IP, pois a restrição de rede está fora do escopo.
- Automatizar expirações e limpezas necessárias com tarefas agendadas idempotentes.
- Concluir testes de concorrência, revogação imediata, tokens, permissões, acessibilidade, internacionalização, bancos e versões suportadas.

### Demonstração e aceite

- Cada transição auditável produz um evento consultável pelos mecanismos padrão do Moodle.
- A remoção pelo Privacy API elimina ou anonimiza os dados abrangidos sem corromper outros fluxos.
- Expirações automáticas produzem o mesmo estado que a validação sob demanda e podem ser executadas novamente com segurança.
- A matriz de integração contínua passa em PostgreSQL e MariaDB, com Moodle 4.5, 5.0 e 5.1 nas combinações de PHP oficialmente compatíveis; PHP 8.3 permanece como ambiente principal.

## Definição de pronto comum

Cada marco só está concluído quando:

- seu fluxo pode ser demonstrado pela interface para o perfil interessado;
- regras de domínio e concorrência possuem testes PHPUnit;
- fluxos observáveis e controles de acesso relevantes possuem testes Behat;
- toda ação sensível revalida permissão, escopo, estado e vigência no servidor e usa as proteções Moodle contra requisições forjadas;
- eventos correspondentes ao comportamento introduzido já são emitidos;
- textos utilizam strings traduzíveis e a interface é navegável por teclado e compreensível por tecnologia assistiva;
- não são criadas dependências de categorias, campi, turmas, códigos institucionais ou nomes de papéis;
- documentação funcional e notas de atualização refletem o comportamento entregue.

## Fora do escopo atual

- Restrição de autorização pela rede ou pelo endereço IP.
- Interface própria para pesquisa ou visualização de eventos de auditoria.
- Matrícula automática ou criação de papel Moodle para Aplicadores.
- Dependência do Painel AVA ou de outro plugin da organização.
- Descoberta automática de cursos, campi, turmas, semestres ou equipes de aplicação.
- Entidade de ciclo ou sessão de aplicação distinta do Período de Autorização.
- Atualização automática da fila do Aplicador.
- Histórico pessoal de aplicações anteriores para o Aplicador.
- Separação antecipada da solução em mais de um plugin.
