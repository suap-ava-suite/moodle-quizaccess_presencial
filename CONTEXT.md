# Liberação Presencial

Este contexto reúne os conceitos usados para controlar presencialmente o início de tentativas em Questionários do Moodle e para delegar essa operação sem conceder acesso acadêmico adicional.

## Language

**Questionário**:
A atividade avaliativa do Moodle à qual a Liberação Presencial se aplica. Neste contexto, toda avaliação presencial é um Questionário.
_Avoid_: avaliação (quando for necessário identificar a atividade Moodle com precisão)

**Professor**:
Um usuário que possui a permissão contextual para administrar o Questionário, independentemente do nome de seu papel no Moodle.
_Avoid_: Gestor do Questionário, professor-editor

**Liberação Presencial**:
A capacidade que condiciona o início de uma tentativa de Questionário a uma decisão presencial válida e permite delegar essa operação a usuários autenticados.
_Avoid_: mecanismo de autorização presencial, autorização presencial (quando se referir à capacidade completa)

**Aplicador**:
Um usuário autenticado do Moodle que recebeu autorização temporária e restrita para operar a Liberação Presencial de um Questionário, sem precisar estar matriculado na disciplina.
_Avoid_: fiscal, coordenador, professor aplicador

**Conta elegível**:
Uma conta Moodle confirmada, não excluída, não suspensa, diferente da conta de visitante e capaz de autenticar-se. Papel, matrícula, curso e categoria não determinam elegibilidade.
_Avoid_: usuário da disciplina, servidor, docente

**Delegação de aplicação**:
A relação temporária que torna um usuário Aplicador de um Questionário durante um Período de Autorização, sem criar matrícula ou atribuição de papel no Moodle. A suspensão da conta interrompe temporariamente seu efeito; depois de expirar ou ser revogada, ela não é reativada, e uma nova concessão cria outra Delegação.
_Avoid_: permissão de curso, papel de aplicador, matrícula de aplicador

**Período de disponibilidade do Questionário**:
O intervalo já configurado no Questionário em que estudantes podem iniciar tentativas. A Liberação Presencial acrescenta uma condição de acesso, mas não cria nem substitui esse período.
_Avoid_: período da aplicação, Sessão de Aplicação

**Período de Autorização**:
O intervalo finito, contido no Período de disponibilidade do Questionário, em que professores autorizados e Aplicadores podem conceder Autorizações de tentativa.
_Avoid_: período da aplicação, período do Questionário

**Solicitação de liberação**:
O pedido temporário criado automaticamente quando um estudante presente no laboratório tenta iniciar sua próxima tentativa de um Questionário sujeito à Liberação Presencial. Ele expira após 15 minutos se não for decidido, e cliques repetidos retornam à mesma solicitação pendente sem renovar esse prazo; desabilitar a Liberação Presencial encerra-o definitivamente.
_Avoid_: solicitação de prova, pedido de acesso ao curso

**Autorização de tentativa**:
A decisão temporária e de uso único que permite ao estudante iniciar sua próxima tentativa, com validade administrativa padrão de 5 minutos. Ela é consumida quando o estudante confirma o início e a tentativa é criada; desabilitar a Liberação Presencial encerra autorizações não consumidas, mas retomar uma tentativa já criada não exige nova autorização.
_Avoid_: autorização do estudante, autorização permanente do Questionário

**Decisão vigente**:
A decisão mais recente sobre uma Solicitação de liberação ainda revisável. Ela substitui operacionalmente a decisão anterior, enquanto cada alteração permanece registrada como evento no log do Moodle.
_Avoid_: decisão definitiva, histórico de decisões do plugin

**Inclusão direta**:
A forma de conceder uma Delegação de aplicação pela seleção de um usuário Moodle pelo professor, sem utilizar um link.
_Avoid_: convite direto, convite sem link

**Convite por link**:
A forma aberta de oferecer uma Delegação de aplicação. Qualquer usuário autenticado com uma conta Moodle ativa pode acessar o único link vigente do Questionário e aceitar tornar-se Aplicador antes ou durante o Período de Autorização; o link completo é exibido somente quando gerado.
_Avoid_: convite individual, link público, Inclusão direta

**Painel do Aplicador**:
A página autônoma do Moodle em que um Aplicador encontra os Questionários com Delegação válida, inclusive os cujo Período de Autorização ainda não começou, e opera Solicitações de liberação sem precisar acessar a disciplina ou a página do Questionário. O item de navegação Minhas Aplicações fica oculto quando o usuário não possui Delegações atuais ou futuras.
_Avoid_: painel da disciplina, painel de coordenação

**Fila operacional**:
O conjunto paginado de Solicitações de liberação que ainda admitem ação do Aplicador. Em cada Questionário, solicitações pendentes aparecem da mais antiga para a mais recente, seguidas das decisões ainda revisáveis da mais recente para a mais antiga. Solicitações consumidas, expiradas ou definitivas permanecem somente nos registros de auditoria.
_Avoid_: histórico do Aplicador, lista completa de solicitações

**Identidade operacional do estudante**:
O conjunto mínimo de informações exibido ao Aplicador para distinguir o estudante: nome completo, foto de perfil e somente os campos de identidade disponibilizados pela configuração institucional. Não inclui acesso ao perfil nem outros dados pessoais ou acadêmicos.
_Avoid_: perfil do estudante, cadastro do estudante

**QR Code de autorização**:
A representação temporária de uma Solicitação de liberação exibida ao estudante e lida por um Professor ou Aplicador. Depois das validações de identidade, escopo e vigência, sua leitura autoriza imediatamente a próxima tentativa, sem uma confirmação adicional.
_Avoid_: QR Code de convite, comprovante de autorização
