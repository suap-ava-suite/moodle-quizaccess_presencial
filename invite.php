<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Accept an open application-team invitation.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use quizaccess_presencial\local\invitation;

require_once(__DIR__ . '/../../../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUMEXT);
$url = new moodle_url('/mod/quiz/accessrule/presencial/invite.php', ['cmid' => $cmid, 'token' => $token]);

$PAGE->set_url($url);
$PAGE->set_cacheable(false);
header('Referrer-Policy: no-referrer');
require_login(null, false);
if (isguestuser()) {
    // An existing guest session is allowed by require_login() when no course is supplied.
    $SESSION->wantsurl = $url->out(false);
    redirect(get_login_url());
}

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
// Associate the actual course and activity with the module, without requiring enrolment.
$invitationcourse = get_course($cm->course);
$invitationquiz = $DB->get_record('quiz', ['id' => $cm->instance, 'course' => $cm->course], '*', MUST_EXIST);
$PAGE->set_cm($cm, $invitationcourse, $invitationquiz);
// The invitation summary must not include course navigation or the quiz introduction.
$PAGE->set_pagelayout('embedded');
$PAGE->activityheader->disable();
$PAGE->set_title(get_string('invitationaccept', 'quizaccess_presencial'));
$PAGE->set_heading(get_string('invitationaccept', 'quizaccess_presencial'));

$details = invitation::get_acceptance_details($cmid, $token);
$submitted = data_submitted();
$accepted = null;
if ($submitted && !empty($submitted->accept)) {
    require_sesskey();
    $accepted = invitation::accept($cmid, $token);
}

$header = $OUTPUT->header();
// Core may apply the site's referrer policy while building its HTTP headers.
header('Referrer-Policy: no-referrer');
echo $header;
echo $OUTPUT->heading(get_string('invitationaccept', 'quizaccess_presencial'));
if ($accepted) {
    echo $OUTPUT->notification(
        get_string($accepted['created'] ? 'invitationaccepted' : 'invitationalreadyaccepted', 'quizaccess_presencial'),
        \core\output\notification::NOTIFY_SUCCESS,
    );
} else {
    echo html_writer::tag('p', get_string('invitationacceptintro', 'quizaccess_presencial'));
}

$table = new html_table();
$table->data = [
    [get_string('invitationcourse', 'quizaccess_presencial'), $details['course']],
    [get_string('invitationquiz', 'quizaccess_presencial'), $details['quiz']],
    [get_string('invitationperiod', 'quizaccess_presencial'), userdate($details['timeopen']) . ' - ' . userdate($details['timeclose'])],
    [get_string('invitationteacher', 'quizaccess_presencial'), $details['teacher']],
];
echo html_writer::table($table);

if (!$accepted) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => 'accept',
        'value' => get_string('invitationacceptbutton', 'quizaccess_presencial'),
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
