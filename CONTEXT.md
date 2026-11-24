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
A relação que torna um usuário Aplicador de um Questionário, sem criar matrícula ou atribuição de papel no Moodle. Ela não tem datas próprias: produz efeito sobre as Solicitações de liberação feitas dentro da Disponibilidade efetiva de cada estudante e acompanha as datas atuais do Questionário, inclusive quando ele é adiado, prorrogado ou reaberto. Pode ser concedida a qualquer momento, mesmo com o Questionário fechado ou a Liberação Presencial desabilitada. A suspensão da conta e a desabilitação da Liberação Presencial interrompem temporariamente seu efeito; somente a revogação a encerra definitivamente, e uma nova concessão cria outra Delegação.
_Avoid_: permissão de curso, papel de aplicador, matrícula de aplicador

**Período de disponibilidade do Questionário**:
O intervalo geral de abertura e fechamento configurado no Questionário, sem considerar exceções individuais ou de grupo. A Liberação Presencial não tem período próprio: este período determina a validade do Convite por link e a presença do Questionário no Painel do Aplicador. Qualquer um dos limites pode estar ausente; sem fechamento, a autoridade dura até a revogação da Delegação ou a desabilitação da Liberação Presencial.
_Avoid_: Período de Autorização, período da aplicação, Sessão de Aplicação

**Disponibilidade efetiva do estudante**:
O intervalo em que o Moodle permite que um estudante específico inicie uma tentativa, resultante do Período de disponibilidade do Questionário e das exceções individuais ou de grupo desse estudante. Uma Solicitação de liberação só pode ser decidida, por um Professor ou pela equipe atual de Aplicadores, enquanto o estudante que a criou estiver nessa disponibilidade, mesmo antes da abertura ou depois do fechamento gerais.
_Avoid_: período do estudante, período da exceção

**Solicitação de liberação**:
O pedido temporário criado automaticamente quando um estudante presente no laboratório tenta iniciar sua próxima tentativa de um Questionário sujeito à Liberação Presencial. Ele expira após 15 minutos se não for decidido, ou antes, no fim da Disponibilidade efetiva do estudante, e cliques repetidos retornam à mesma solicitação pendente sem renovar esse prazo; desabilitar a Liberação Presencial encerra-o definitivamente.
_Avoid_: solicitação de prova, pedido de acesso ao curso

**Autorização de tentativa**:
A decisão temporária e de uso único que permite ao estudante iniciar sua próxima tentativa, com validade administrativa padrão de 5 minutos. Uma autorização já reclamada para iniciar uma tentativa conclui essa criação antes de ser consumida; desabilitar a Liberação Presencial encerra apenas autorizações ainda não reclamadas, e retomar uma tentativa criada não exige nova autorização.
_Avoid_: autorização do estudante, autorização permanente do Questionário

**Decisão vigente**:
A decisão mais recente sobre uma Solicitação de liberação ainda revisável. Ela substitui operacionalmente a decisão anterior, enquanto cada alteração permanece registrada como evento no log do Moodle.
_Avoid_: decisão definitiva, histórico de decisões do plugin

**Inclusão direta**:
A forma de conceder uma Delegação de aplicação pela seleção de um usuário Moodle pelo professor, sem utilizar um link.
_Avoid_: convite direto, convite sem link

**Convite por link**:
A forma aberta de oferecer uma Delegação de aplicação. Qualquer usuário autenticado com uma conta Moodle ativa pode acessar o único link vigente do Questionário e aceitar tornar-se Aplicador antes ou durante o Período de disponibilidade do Questionário; o link completo é exibido somente quando gerado. Diferentemente da Delegação, o convite expira definitivamente no fechamento do Questionário e não volta a valer se ele for reaberto; sem fechamento, vale até ser desativado ou regenerado.
_Avoid_: convite individual, link público, Inclusão direta

**Painel do Aplicador**:
A página autônoma do Moodle em que um Aplicador encontra os Questionários com Delegação não revogada e opera Solicitações de liberação sem precisar acessar a disciplina ou a página do Questionário. Um Questionário aparece enquanto seu Período de disponibilidade for atual, futuro ou sem fechamento, ou enquanto sua Fila operacional tiver alguma Solicitação; fora disso, ele some e o acesso aos dados dos estudantes termina, mas a Delegação é preservada e o Questionário volta a aparecer se for reaberto. O item de navegação Minhas Aplicações fica oculto quando nenhum Questionário do usuário atende a essas condições.
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
