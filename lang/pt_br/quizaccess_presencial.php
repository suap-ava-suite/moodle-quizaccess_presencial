<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings de idioma da regra de acesso Liberação Presencial.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addapplicators'] = 'Incluir contas selecionadas';
$string['applicators'] = 'Contas elegíveis';
$string['authorisationvalidity'] = 'Validade da autorização de tentativa';
$string['authorisationvalidity_desc'] = 'Número de minutos inteiros durante os quais uma autorização de tentativa não consumida ' .
    'permanece válida.';
$string['authorizationperiodavailability'] = 'O Período de Autorização deve estar contido na disponibilidade do Questionário.';
$string['authorizationperiodend'] = 'Fim do Período de Autorização';
$string['authorizationperiodendrequired'] = 'Informe o fim do Período de Autorização.';
$string['authorizationperiodfuture'] = 'O Período de Autorização deve terminar no futuro.';
$string['authorizationperiodinactive'] = 'O Período de Autorização não está ativo; esta solicitação não pode ser autorizada.';
$string['authorizationperiodordered'] = 'O Período de Autorização deve terminar depois do início.';
$string['authorizationperiodstart'] = 'Início do Período de Autorização';
$string['authorizationperiodstartrequired'] = 'Informe o início do Período de Autorização.';
$string['currentapplicators'] = 'Equipe de aplicação atual';
$string['delegationincluded'] = 'Delegação de aplicação incluída.';
$string['delegationlocktimeout'] = 'Outra atualização da equipe de aplicação está em andamento. Tente novamente.';
$string['delegationnotfound'] = 'A Delegação de aplicação selecionada não pertence a este Questionário.';
$string['delegationorigin'] = 'Origem';
$string['delegationorigin_direct'] = 'Inclusão direta';
$string['delegationorigin_invite'] = 'Convite';
$string['delegationorigin_unknown'] = 'Origem desconhecida';
$string['delegationperiodclosed'] = 'O Período de Autorização não está aberto para novas inclusões.';
$string['delegationrevoked'] = 'Delegação de aplicação revogada.';
$string['enable'] = 'Habilitar Liberação Presencial';
$string['eventconfigurationupdated'] = 'Configuração da Liberação Presencial atualizada';
$string['eventdelegationcreated'] = 'Delegação de aplicação criada';
$string['eventdelegationidempotent'] = 'Delegação de aplicação incluída novamente';
$string['eventdelegationupdated'] = 'Delegação de aplicação atualizada';
$string['eventinvitationinvalid'] = 'Uso inválido de convite';
$string['eventinvitationupdated'] = 'Convite atualizado';
$string['ineligibleuser'] = 'Uma ou mais contas selecionadas não podem receber uma Delegação de aplicação.';
$string['invitationcopied'] = 'Link do convite copiado.';
$string['invitationcopy'] = 'Copiar link do convite';
$string['invitationdisable'] = 'Desativar convite';
$string['invitationdisabled'] = 'Convite desativado.';
$string['invitationexpires'] = 'O convite expira em: {$a}';
$string['invitationgenerate'] = 'Gerar convite';
$string['invitationgenerated'] = 'Convite gerado';
$string['invitationlink'] = 'Link do convite';
$string['invitationlinkwarning'] = 'Copie este link agora. Ele é exibido apenas uma vez. Regenerar o convite invalida o link anterior.';
$string['invitationmanage'] = 'Convite';
$string['invitationregenerate'] = 'Regenerar convite';
$string['invitationreturn'] = 'Voltar à gestão do convite';
$string['invitationstale'] = 'Este convite foi alterado. Recarregue a página antes de tentar novamente.';
$string['invitationstate_active'] = 'O convite está ativo.';
$string['invitationstate_disabled'] = 'O convite está desativado.';
$string['invitationstate_expired'] = 'O convite expirou.';
$string['invitationstate_none'] = 'Nenhum convite foi gerado.';
$string['invitationunavailable'] = 'Habilite a Liberação Presencial e configure um período de autorização válido antes de gerar um convite.';
$string['manageapplicators'] = 'Gerenciar equipe de aplicação';
$string['pluginname'] = 'Liberação Presencial';
$string['privacy:metadata:delegation'] = 'Registros de Delegação de aplicação de um Questionário.';
$string['privacy:metadata:delegation:origin'] = 'A origem que criou a Delegação.';
$string['privacy:metadata:delegation:quizid'] = 'O Questionário que recebeu a Delegação.';
$string['privacy:metadata:delegation:revokedby'] = 'A conta que revogou a Delegação.';
$string['privacy:metadata:delegation:timeclose'] = 'O fim do Período da Delegação.';
$string['privacy:metadata:delegation:timecreated'] = 'O instante em que a Delegação foi criada.';
$string['privacy:metadata:delegation:timemodified'] = 'O instante da última alteração da Delegação.';
$string['privacy:metadata:delegation:timeopen'] = 'O início do Período da Delegação.';
$string['privacy:metadata:delegation:timerevoked'] = 'O instante em que a Delegação foi revogada.';
$string['privacy:metadata:delegation:userid'] = 'A conta que recebeu a Delegação.';
$string['privacy:metadata:invite'] = 'Informações sobre o convite vigente gerado por um Professor do Questionário.';
$string['privacy:metadata:invite:createdby'] = 'O identificador do usuário que gerou o convite.';
$string['privacy:metadata:invite:generation'] = 'A geração do convite.';
$string['privacy:metadata:invite:state'] = 'O estado operacional do convite.';
$string['privacy:metadata:invite:timecreated'] = 'Quando o convite foi gerado.';
$string['privacy:metadata:invite:timeexpires'] = 'O instante limite para utilização do convite.';
$string['privacy:metadata:invite:timemodified'] = 'Quando o convite foi alterado pela última vez.';
$string['privacy:metadata'] = 'A regra de acesso Liberação Presencial armazena Delegações de aplicação, dados do ciclo de vida dos Convites e solicitações de liberação de estudantes.';
$string['privacy:metadata:request'] = 'Uma solicitação associa estudante, Questionário e número de tentativa, com estado e datas.';
$string['privacy:metadata:request:attemptnumber'] = 'O número da próxima tentativa do Questionário coberta pela solicitação.';
$string['privacy:metadata:request:expiresat'] = 'O instante absoluto de expiração da solicitação pendente ou autorização não utilizada.';
$string['privacy:metadata:request:quizid'] = 'O Questionário associado à solicitação.';
$string['privacy:metadata:request:state'] = 'O estado atual da solicitação.';
$string['privacy:metadata:request:timecreated'] = 'O instante em que a solicitação foi criada.';
$string['privacy:metadata:request:timemodified'] = 'O instante da última mudança de estado da solicitação.';
$string['privacy:metadata:request:userid'] = 'O estudante que criou a solicitação.';
$string['privacy:exportpath:request'] = 'Solicitação de liberação';
$string['eventrequestcreated'] = 'Solicitação de liberação de tentativa criada';
$string['eventrequestcreateddesc'] = 'A solicitação de liberação {$a->requestid} foi criada para o estudante ' .
    '{$a->studentid}.';
$string['eventrequestauthorized'] = 'Solicitação de liberação de tentativa autorizada';
$string['eventrequestauthorizeddesc'] = 'A solicitação {$a->requestid} do estudante {$a->studentid} foi autorizada ' .
    'pelo usuário {$a->actorid}.';
$string['eventrequestconsumed'] = 'Autorização de tentativa consumida';
$string['eventrequestconsumeddesc'] = 'A solicitação {$a->requestid} do estudante {$a->studentid} foi consumida ' .
    'pela tentativa {$a->attemptnumber}.';
$string['eventrequeststarting'] = 'Início da criação de tentativa autorizada';
$string['eventrequeststartingdesc'] = 'A solicitação {$a->requestid} do estudante {$a->studentid} iniciou a ' .
    'criação da tentativa {$a->attemptnumber}.';
$string['eventrequestexpired'] = 'Solicitação de liberação de tentativa ou autorização expirada';
$string['eventrequestexpireddesc'] = 'A solicitação {$a->requestid} ou sua autorização não utilizada expirou para ' .
    'o estudante {$a->studentid}.';
$string['requestattemptalreadyexists'] = 'Uma tentativa já foi criada para esta solicitação. ' .
    'Volte ao Questionário para retomá-la.';
$string['requestnotfound'] = 'A solicitação de liberação não foi encontrada.';
$string['requestpending'] = 'Sua solicitação de liberação está pendente. Mantenha esta página aberta enquanto o ' .
    'estado é atualizado automaticamente.';
$string['requestdescription'] = 'Para iniciar uma nova tentativa, é necessária a liberação presencial. A solicitação ' .
    'aparecerá em uma página de espera com atualização automática.';
$string['requestexpired'] = 'Esta solicitação ou sua autorização não utilizada expirou. Volte ao Questionário e ' .
    'inicie uma nova solicitação.';
$string['requestauthorized'] = 'Sua solicitação foi autorizada. Continue para iniciar sua tentativa.';
$string['requestconsumed'] = 'Esta autorização já foi utilizada. Volte ao Questionário para continuar sua tentativa.';
$string['requeststartattempt'] = 'Iniciar tentativa';
$string['requestpolling'] = 'Esta página verifica automaticamente se houve atualização a cada cinco segundos.';
$string['backtoquiz'] = 'Voltar ao Questionário';
$string['requestwaittitle'] = 'Aguardando liberação presencial';
$string['ruleisdisabled'] = 'A Liberação Presencial não está habilitada neste Questionário.';
$string['taskexpirerequests'] = 'Expirar solicitações vencidas e autorizações não utilizadas';
$string['rejectionjustificationrequired'] = 'Exigir justificativa de rejeição';
$string['rejectionjustificationrequired_desc'] = 'Exige uma justificativa ao rejeitar uma solicitação de liberação.';
$string['requestvalidity'] = 'Validade da solicitação de liberação';
$string['requestvalidity_desc'] = 'Número de minutos inteiros durante os quais uma solicitação de liberação pendente ' .
    'permanece válida.';
$string['revokeapplicator'] = 'Revogar';
$string['taskexpireinvitations'] = 'Expirar convites';
