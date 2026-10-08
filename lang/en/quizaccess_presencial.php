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

$string['addapplicators'] = 'Include selected accounts';
$string['applicationaccessdenied'] = 'You do not currently have access to this application.';
$string['applicationnotstarted'] = 'The authorization period has not started. Student data and operational actions are unavailable.';
$string['applicationpanel'] = 'Application panel';
$string['applicationperiod'] = 'Authorization period: {$a->start} – {$a->end}';
$string['applicationstate'] = 'Delegation state';
$string['applicationstate_current'] = 'Current';
$string['applicationstate_future'] = 'Future';
$string['applicationstate_teacher'] = 'Direct quiz management';
$string['applicators'] = 'Eligible accounts';
$string['authorisationvalidity'] = 'Attempt authorisation validity';
$string['authorisationvalidity_desc'] = 'Number of whole minutes an unused attempt authorisation remains valid.';
$string['authorizationperiodavailability'] = 'The authorization period must be contained within the quiz availability.';
$string['authorizationperiodend'] = 'Authorization period ends';
$string['authorizationperiodendrequired'] = 'Set when the authorization period ends.';
$string['authorizationperiodfuture'] = 'The authorization period must end in the future.';
$string['authorizationperiodordered'] = 'The authorization period must end after it starts.';
$string['authorizationperiodstart'] = 'Authorization period starts';
$string['authorizationperiodstartrequired'] = 'Set when the authorization period starts.';
$string['currentapplicators'] = 'Current application team';
$string['delegationincluded'] = 'Application delegation included.';
$string['delegationlocktimeout'] = 'Another application team update is in progress. Please try again.';
$string['delegationnotfound'] = 'The selected application delegation does not belong to this quiz.';
$string['delegationorigin'] = 'Origin';
$string['delegationorigin_direct'] = 'Direct inclusion';
$string['delegationorigin_invite'] = 'Invitation';
$string['delegationorigin_unknown'] = 'Unknown';
$string['delegationperiodclosed'] = 'The authorization period is not open for new applications.';
$string['delegationrevoked'] = 'Application delegation revoked.';
$string['enable'] = 'Enable in-person release';
$string['eventconfigurationupdated'] = 'In-person release configuration updated';
$string['eventdelegationcreated'] = 'Application delegation created';
$string['eventdelegationidempotent'] = 'Application delegation included again';
$string['eventdelegationupdated'] = 'Application delegation updated';
$string['eventinvitationinvalid'] = 'Invalid invitation use';
$string['eventinvitationupdated'] = 'Invitation updated';
$string['ineligibleuser'] = 'One or more selected accounts cannot receive an application delegation.';
$string['invitationcopied'] = 'Invitation link copied.';
$string['invitationcopy'] = 'Copy invitation link';
$string['invitationdisable'] = 'Disable invitation';
$string['invitationdisabled'] = 'Invitation disabled.';
$string['invitationexpires'] = 'Invitation expires: {$a}';
$string['invitationgenerate'] = 'Generate invitation';
$string['invitationgenerated'] = 'Invitation generated';
$string['invitationlink'] = 'Invitation link';
$string['invitationlinkwarning'] = 'Copy this link now. It is shown only once. Regenerating the invitation invalidates the previous link.';
$string['invitationmanage'] = 'Invitation';
$string['invitationregenerate'] = 'Regenerate invitation';
$string['invitationreturn'] = 'Return to invitation management';
$string['invitationstale'] = 'This invitation has changed. Reload the page before trying again.';
$string['invitationstate_active'] = 'The invitation is active.';
$string['invitationstate_disabled'] = 'The invitation is disabled.';
$string['invitationstate_expired'] = 'The invitation has expired.';
$string['invitationstate_none'] = 'No invitation has been generated.';
$string['invitationunavailable'] = 'Enable in-person release and set a valid authorization period before generating an invitation.';
$string['manageapplicators'] = 'Manage application team';
$string['myapplications'] = 'My applications';
$string['noapplications'] = 'There are no current or future applications available to you.';
$string['nopendingrequests'] = 'No pending release requests.';
$string['openapplication'] = 'Open application';
$string['pendingrequests'] = 'Pending requests';
$string['pluginname'] = 'In-person release';
$string['privacy:metadata'] = 'The In-person release quiz access rule stores application delegation records.';
$string['privacy:metadata:delegation'] = 'Application delegation records for a quiz.';
$string['privacy:metadata:delegation:origin'] = 'The source that created the delegation.';
$string['privacy:metadata:delegation:quizid'] = 'The quiz receiving the delegation.';
$string['privacy:metadata:delegation:revokedby'] = 'The account that revoked the delegation.';
$string['privacy:metadata:delegation:timeclose'] = 'The end of the delegation period.';
$string['privacy:metadata:delegation:timecreated'] = 'The time the delegation was created.';
$string['privacy:metadata:delegation:timemodified'] = 'The time the delegation was last changed.';
$string['privacy:metadata:delegation:timeopen'] = 'The start of the delegation period.';
$string['privacy:metadata:delegation:timerevoked'] = 'The time the delegation was revoked.';
$string['privacy:metadata:delegation:userid'] = 'The account receiving the delegation.';
$string['privacy:metadata:invite'] = 'Information about the current invitation generated by a quiz manager.';
$string['privacy:metadata:invite:createdby'] = 'The ID of the user who generated the invitation.';
$string['privacy:metadata:invite:generation'] = 'The generation of the invitation.';
$string['privacy:metadata:invite:state'] = 'The operational state of the invitation.';
$string['privacy:metadata:invite:timecreated'] = 'When the invitation was generated.';
$string['privacy:metadata:invite:timeexpires'] = 'The latest time at which the invitation can be used.';
$string['privacy:metadata:invite:timemodified'] = 'When the invitation was last changed.';
$string['rejectionjustificationrequired'] = 'Require a rejection justification';
$string['rejectionjustificationrequired_desc'] = 'Require a justification when a release request is rejected.';
$string['requestvalidity'] = 'Release request validity';
$string['requestvalidity_desc'] = 'Number of whole minutes a pending release request remains valid.';
$string['revokeapplicator'] = 'Revoke';
$string['taskexpireinvitations'] = 'Expire invitations';
