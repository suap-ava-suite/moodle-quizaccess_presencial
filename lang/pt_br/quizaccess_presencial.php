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

$string['authorisationvalidity'] = 'Validade da autorização de tentativa';
$string['authorisationvalidity_desc'] = 'Número de minutos inteiros durante os quais uma autorização de tentativa não consumida ' .
    'permanece válida.';
$string['authorizationperiodavailability'] = 'O Período de Autorização deve estar contido na disponibilidade do Questionário.';
$string['authorizationperiodend'] = 'Fim do Período de Autorização';
$string['authorizationperiodendrequired'] = 'Informe o fim do Período de Autorização.';
$string['authorizationperiodfuture'] = 'O Período de Autorização deve terminar no futuro.';
$string['authorizationperiodordered'] = 'O Período de Autorização deve terminar depois do início.';
$string['authorizationperiodstart'] = 'Início do Período de Autorização';
$string['authorizationperiodstartrequired'] = 'Informe o início do Período de Autorização.';
$string['enable'] = 'Habilitar Liberação Presencial';
$string['eventconfigurationupdated'] = 'Configuração da Liberação Presencial atualizada';
$string['eventdelegationupdated'] = 'Delegação de aplicação atualizada';
$string['eventdelegationcreated'] = 'Delegação de aplicação criada';
$string['eventdelegationidempotent'] = 'Delegação de aplicação incluída novamente';
$string['manageapplicators'] = 'Gerenciar equipe de aplicação';
$string['applicators'] = 'Contas elegíveis';
$string['addapplicators'] = 'Incluir contas selecionadas';
$string['currentapplicators'] = 'Equipe de aplicação atual';
$string['delegationorigin'] = 'Origem';
$string['revokeapplicator'] = 'Revogar';
$string['delegationperiodclosed'] = 'O Período de Autorização não está aberto para novas inclusões.';
$string['ineligibleuser'] = 'Uma ou mais contas selecionadas não podem receber uma Delegação de aplicação.';
$string['delegationincluded'] = 'Delegação de aplicação incluída.';
$string['delegationrevoked'] = 'Delegação de aplicação revogada.';
$string['delegationlocktimeout'] = 'Outra atualização da equipe de aplicação está em andamento. Tente novamente.';
$string['delegationnotfound'] = 'A Delegação de aplicação selecionada não pertence a este Questionário.';
$string['delegationorigin_direct'] = 'Inclusão direta';
$string['delegationorigin_invite'] = 'Convite';
$string['delegationorigin_unknown'] = 'Origem desconhecida';
$string['pluginname'] = 'Liberação Presencial';
$string['privacy:metadata'] = 'A regra de acesso Liberação Presencial armazena registros de Delegação de aplicação.';
$string['privacy:metadata:delegation'] = 'Registros de Delegação de aplicação de um Questionário.';
$string['privacy:metadata:delegation:quizid'] = 'O Questionário que recebeu a Delegação.';
$string['privacy:metadata:delegation:userid'] = 'A conta que recebeu a Delegação.';
$string['privacy:metadata:delegation:timeopen'] = 'O início do Período da Delegação.';
$string['privacy:metadata:delegation:timeclose'] = 'O fim do Período da Delegação.';
$string['privacy:metadata:delegation:origin'] = 'A origem que criou a Delegação.';
$string['privacy:metadata:delegation:timecreated'] = 'O instante em que a Delegação foi criada.';
$string['privacy:metadata:delegation:timemodified'] = 'O instante da última alteração da Delegação.';
$string['privacy:metadata:delegation:timerevoked'] = 'O instante em que a Delegação foi revogada.';
$string['privacy:metadata:delegation:revokedby'] = 'A conta que revogou a Delegação.';
$string['rejectionjustificationrequired'] = 'Exigir justificativa de rejeição';
$string['rejectionjustificationrequired_desc'] = 'Exige uma justificativa ao rejeitar uma solicitação de liberação.';
$string['requestvalidity'] = 'Validade da solicitação de liberação';
$string['requestvalidity_desc'] = 'Número de minutos inteiros durante os quais uma solicitação de liberação pendente ' .
    'permanece válida.';
