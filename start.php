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
 * Confirm an authorized release request using Moodle's native access and preflight checks.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use quizaccess_presencial\local\release_request_service;

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    throw new moodle_exception('invalidrequest');
}
$requestid = required_param('requestid', PARAM_INT);
require_login();
require_sesskey();
$service = new release_request_service();
$request = $service->get($requestid);
if ($request->userid !== (int) $USER->id) {
    throw new moodle_exception('requestnotfound', 'quizaccess_presencial');
}
$quizobj = quiz_settings::create($request->quizid, $USER->id);
require_login($quizobj->get_course(), false, $quizobj->get_cm());
require_capability('mod/quiz:attempt', $quizobj->get_context());
$url = new moodle_url('/mod/quiz/accessrule/presencial/start.php', ['requestid' => $requestid]);
$waiturl = new moodle_url('/mod/quiz/accessrule/presencial/wait.php', ['requestid' => $requestid]);
$PAGE->set_url($url);
$PAGE->set_cacheable(false);
$PAGE->set_heading($quizobj->get_course()->fullname);

// A replay always addresses the original numbered attempt, including a finished attempt.
$existing = $service->find_attempt($request->quizid, $request->userid, $request->attemptnumber);
if ($existing) {
    if (in_array($existing->state, [quiz_attempt::FINISHED, quiz_attempt::ABANDONED], true)) {
        redirect($quizobj->review_url($existing->id));
    }
    redirect($quizobj->attempt_url($existing->id));
}
if (!$service->is_authorized($request->quizid, $request->userid, $request->attemptnumber)) {
    redirect($waiturl);
}
if (!$quizobj->has_questions()) {
    throw new moodle_exception('cannotstartnoquestions', 'quiz', $quizobj->view_url());
}

// This endpoint owns the release gate; keep all other Moodle preflight rules active.
$GLOBALS['QUIZACCESS_PRESENCIAL_REQUEST_FLOW'] = true;
$accessmanager = $quizobj->get_access_manager(time());
[$currentattemptid, , , $messages] = quiz_validate_new_attempt($quizobj, $accessmanager, false, -1, true);
if ($messages) {
    $output = $PAGE->get_renderer('mod_quiz');
    throw new moodle_exception('attempterror', 'quiz', $quizobj->view_url(), $output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($currentattemptid)) {
    $mform = $accessmanager->get_preflight_check_form($url, $currentattemptid);
    if ($mform->is_cancelled()) {
        redirect($waiturl);
    }
    if (!$mform->get_data()) {
        $PAGE->set_title($quizobj->get_quiz_name());
        $accessmanager->setup_attempt_page($PAGE);
        if (empty($quizobj->get_quiz()->showblocks)) {
            $PAGE->blocks->show_only_fake_blocks();
        }
        echo $PAGE->get_renderer('mod_quiz')->start_attempt_page($quizobj, $mform);
        exit;
    }
    $accessmanager->notify_preflight_check_passed($currentattemptid);
}
$attempt = $service->start_authorized_attempt($requestid);
if (!$attempt) {
    redirect($waiturl);
}
if ($attempt->state === quiz_attempt::OVERDUE) {
    redirect($quizobj->summary_url($attempt->id));
}
redirect($quizobj->attempt_url($attempt->id));
