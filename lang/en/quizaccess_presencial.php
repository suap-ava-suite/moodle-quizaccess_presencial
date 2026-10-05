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
 * Language strings for the Presencial quiz access rule.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['authorisationvalidity'] = 'Attempt authorisation validity';
$string['authorisationvalidity_desc'] = 'Number of whole minutes an unused attempt authorisation remains valid.';
$string['authorizationperiodavailability'] = 'The authorization period must be contained within the quiz availability.';
$string['authorizationperiodend'] = 'Authorization period ends';
$string['authorizationperiodendrequired'] = 'Set when the authorization period ends.';
$string['authorizationperiodfuture'] = 'The authorization period must end in the future.';
$string['authorizationperiodordered'] = 'The authorization period must end after it starts.';
$string['authorizationperiodstart'] = 'Authorization period starts';
$string['authorizationperiodstartrequired'] = 'Set when the authorization period starts.';
$string['enable'] = 'Enable in-person release';
$string['eventconfigurationupdated'] = 'In-person release configuration updated';
$string['eventdelegationupdated'] = 'Application delegation updated';
$string['eventdelegationcreated'] = 'Application delegation created';
$string['eventdelegationidempotent'] = 'Application delegation included again';
$string['manageapplicators'] = 'Manage application team';
$string['applicators'] = 'Eligible accounts';
$string['addapplicators'] = 'Include selected accounts';
$string['currentapplicators'] = 'Current application team';
$string['delegationorigin'] = 'Origin';
$string['revokeapplicator'] = 'Revoke';
$string['delegationperiodclosed'] = 'The authorization period is not open for new applications.';
$string['ineligibleuser'] = 'One or more selected accounts cannot receive an application delegation.';
$string['delegationincluded'] = 'Application delegation included.';
$string['delegationrevoked'] = 'Application delegation revoked.';
$string['delegationlocktimeout'] = 'Another application team update is in progress. Please try again.';
$string['delegationorigin_direct'] = 'Direct inclusion';
$string['delegationorigin_invite'] = 'Invitation';
$string['delegationorigin_unknown'] = 'Unknown';
$string['pluginname'] = 'In-person release';
$string['privacy:metadata'] = 'The In-person release quiz access rule stores application delegation records.';
$string['privacy:metadata:delegation'] = 'Application delegation records for a quiz.';
$string['privacy:metadata:delegation:quizid'] = 'The quiz receiving the delegation.';
$string['privacy:metadata:delegation:userid'] = 'The account receiving the delegation.';
$string['privacy:metadata:delegation:timeopen'] = 'The start of the delegation period.';
$string['privacy:metadata:delegation:timeclose'] = 'The end of the delegation period.';
$string['privacy:metadata:delegation:origin'] = 'The source that created the delegation.';
$string['privacy:metadata:delegation:timecreated'] = 'The time the delegation was created.';
$string['privacy:metadata:delegation:timemodified'] = 'The time the delegation was last changed.';
$string['privacy:metadata:delegation:timerevoked'] = 'The time the delegation was revoked.';
$string['privacy:metadata:delegation:revokedby'] = 'The account that revoked the delegation.';
$string['rejectionjustificationrequired'] = 'Require a rejection justification';
$string['rejectionjustificationrequired_desc'] = 'Require a justification when a release request is rejected.';
$string['requestvalidity'] = 'Release request validity';
$string['requestvalidity_desc'] = 'Number of whole minutes a pending release request remains valid.';
