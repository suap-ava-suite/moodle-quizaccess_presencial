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
$string['applicators'] = 'Eligible accounts';
$string['authorisationvalidity'] = 'Attempt authorisation validity';
$string['authorisationvalidity_desc'] = 'Number of whole minutes an unused attempt authorisation remains valid.';
$string['authorizationperiodavailability'] = 'The authorization period must be contained within the quiz availability.';
$string['authorizationperiodend'] = 'Authorization period ends';
$string['authorizationperiodendrequired'] = 'Set when the authorization period ends.';
$string['authorizationperiodfuture'] = 'The authorization period must end in the future.';
$string['authorizationperiodinactive'] = 'The authorization period is not active, so this request cannot be authorized.';
$string['authorizationperiodordered'] = 'The authorization period must end after it starts.';
$string['authorizationperiodstart'] = 'Authorization period starts';
$string['authorizationperiodstartrequired'] = 'Set when the authorization period starts.';
$string['backtoquiz'] = 'Return to the quiz';
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
$string['eventrequestauthorized'] = 'Attempt release request authorized';
$string['eventrequestauthorizeddesc'] = 'Release request {$a->requestid} for student {$a->studentid} was authorized ' .
    'by user {$a->actorid}.';
$string['eventrequestconsumed'] = 'Attempt release authorization consumed';
$string['eventrequestconsumeddesc'] = 'Release request {$a->requestid} for student {$a->studentid} was consumed ' .
    'by attempt {$a->attemptnumber}.';
$string['eventrequestcreated'] = 'Attempt release request created';
$string['eventrequestcreateddesc'] = 'Release request {$a->requestid} was created for student {$a->studentid}.';
$string['eventrequestexpired'] = 'Attempt release request or authorization expired';
$string['eventrequestexpireddesc'] = 'Release request {$a->requestid} or its unused authorization for student ' .
    '{$a->studentid} expired.';
$string['eventrequeststarting'] = 'Authorized attempt creation started';
$string['eventrequeststartingdesc'] = 'Release request {$a->requestid} for student {$a->studentid} began ' .
    'creating attempt {$a->attemptnumber}.';
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
$string['pluginname'] = 'In-person release';
$string['privacy:exportpath:request'] = 'Release request';
$string['privacy:metadata'] = 'The In-person release quiz access rule stores application delegations, invitation lifecycle data, and student release requests.';
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
$string['privacy:metadata:request'] = 'A release request links a student to a quiz and a numbered attempt, with its current state and timestamps.';
$string['privacy:metadata:request:attemptnumber'] = 'The next quiz attempt number covered by this request.';
$string['privacy:metadata:request:expiresat'] = 'The absolute expiry time for the pending request or unused authorization.';
$string['privacy:metadata:request:quizid'] = 'The quiz associated with this request.';
$string['privacy:metadata:request:state'] = 'The current state of this request.';
$string['privacy:metadata:request:timecreated'] = 'The time this request was created.';
$string['privacy:metadata:request:timemodified'] = 'The time this request last changed state.';
$string['privacy:metadata:request:userid'] = 'The student who created this request.';
$string['rejectionjustificationrequired'] = 'Require a rejection justification';
$string['rejectionjustificationrequired_desc'] = 'Require a justification when a release request is rejected.';
$string['requestattemptalreadyexists'] = 'An attempt has already been created for this request. ' .
    'Return to the quiz to resume it.';
$string['requestauthorized'] = 'Your request was authorized. Continue to start your attempt.';
$string['requestconsumed'] = 'This authorization has already been used. Return to the quiz to continue your attempt.';
$string['requestdescription'] = 'Starting a new attempt requires in-person release. Your request will appear on a ' .
    'waiting page and update automatically.';
$string['requestexpired'] = 'This request or its unused authorization has expired. Return to the quiz and start a ' .
    'new request.';
$string['requestnotfound'] = 'The release request was not found.';
$string['requestpending'] = 'Your release request is pending. Keep this page open while the status updates ' .
    'automatically.';
$string['requestpolling'] = 'This page checks for an update automatically every five seconds.';
$string['requeststartattempt'] = 'Start attempt';
$string['requestvalidity'] = 'Release request validity';
$string['requestvalidity_desc'] = 'Number of whole minutes a pending release request remains valid.';
$string['requestwaittitle'] = 'Waiting for in-person release';
$string['revokeapplicator'] = 'Revoke';
$string['ruleisdisabled'] = 'In-person release is not enabled for this quiz.';
$string['taskexpireinvitations'] = 'Expire invitations';
$string['taskexpirerequests'] = 'Expire due release requests and unused authorizations';
