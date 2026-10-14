<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Display and refresh the current student's release request.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\quiz_attempt;
use quizaccess_presencial\local\release_request;
use quizaccess_presencial\local\release_request_service;

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$requestid = required_param('requestid', PARAM_INT);
require_login();
$service = new release_request_service();
$request = $service->get($requestid);
$quiz = $DB->get_record('quiz', ['id' => $request->quizid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
$course = get_course($quiz->course);
require_login($course, false, $cm);
require_capability('mod/quiz:attempt', \context_module::instance($cm->id));

if ($request->userid !== (int) $USER->id) {
    throw new moodle_exception('requestnotfound', 'quizaccess_presencial');
}

// If another tab or device has already created the attempt, resume it directly.
$attempt = $DB->get_record('quiz_attempts', [
    'quiz' => $quiz->id,
    'userid' => $USER->id,
    'attempt' => $request->attemptnumber,
    'preview' => 0,
]);
$quizobj = \mod_quiz\quiz_settings::create($quiz->id, $USER->id);
if ($attempt && in_array($attempt->state, [quiz_attempt::IN_PROGRESS, quiz_attempt::OVERDUE], true)) {
    redirect($quizobj->attempt_url($attempt->id));
}

$request = $service->read_status($requestid, (int) $USER->id);
$context = \context_module::instance($cm->id);
$PAGE->set_url('/mod/quiz/accessrule/presencial/wait.php', ['requestid' => $requestid]);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(get_string('requestwaittitle', 'quizaccess_presencial'));
$PAGE->set_heading(format_string($quiz->name));
$PAGE->set_cacheable(false);

$state = $request->state;
$pending = in_array($state, [release_request::STATE_PENDING, release_request::STATE_STARTING], true);
$data = [
    'requestid' => $request->id,
    'state' => $state,
    'pending' => $pending,
    'authorized' => $state === release_request::STATE_AUTHORIZED,
    'expired' => $state === release_request::STATE_EXPIRED,
    'consumed' => $state === release_request::STATE_CONSUMED,
    'message' => get_string(
        $state === release_request::STATE_STARTING ? 'requestpending' : 'request' . $state,
        'quizaccess_presencial',
    ),
    'expiresat' => $request->expiresat,
    'quizurl' => $quizobj->view_url()->out(false),
    'starturl' => $quizobj->start_attempt_url()->out(false),
    'startlabel' => get_string('requeststartattempt', 'quizaccess_presencial'),
    'sesskey' => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('requestwaittitle', 'quizaccess_presencial'));
echo $OUTPUT->render_from_template('quizaccess_presencial/waiting', $data);
if ($data['pending'] || $data['authorized']) {
    $PAGE->requires->js_call_amd('quizaccess_presencial/waiting', 'init', [$requestid]);
}
echo $OUTPUT->footer();
