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
 * Validate Moodle's native Quiz checks and create or reuse a release request.
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

$cmid = required_param('cmid', PARAM_INT);
$forcenew = optional_param('forcenew', false, PARAM_BOOL);
$page = optional_param('page', -1, PARAM_INT);
$quizobj = quiz_settings::create_for_cmid($cmid, $USER->id);
require_login($quizobj->get_course(), false, $quizobj->get_cm());
require_sesskey();
require_capability('mod/quiz:attempt', $quizobj->get_context());

if (!$quizobj->has_questions()) {
    throw new moodle_exception('cannotstartnoquestions', 'quiz', $quizobj->view_url());
}

// Skip this rule's own preflight test while asking Moodle to evaluate all native
// access rules and any other access-rule checks before creating the request.
$GLOBALS['QUIZACCESS_PRESENCIAL_REQUEST_FLOW'] = true;
$accessmanager = $quizobj->get_access_manager(time());
[$currentattemptid, $attemptnumber, $lastattempt, $messages, $page] =
    quiz_validate_new_attempt($quizobj, $accessmanager, $forcenew, $page, true);

if (!$quizobj->is_preview_user() && $messages) {
    $output = $PAGE->get_renderer('mod_quiz');
    throw new moodle_exception('attempterror', 'quiz', $quizobj->view_url(), $output->access_messages($messages));
}

if ($currentattemptid) {
    if ($lastattempt->state === quiz_attempt::OVERDUE) {
        redirect($quizobj->summary_url($lastattempt->id));
    }
    redirect($quizobj->attempt_url($currentattemptid, $page));
}

if ($quizobj->is_preview_user()) {
    redirect($quizobj->start_attempt_url($page));
}

// Leave any other Moodle preflight interaction to the core's standard route.
if ($accessmanager->is_preflight_check_required(null)) {
    redirect($quizobj->start_attempt_url($page));
}

$service = new release_request_service();
try {
    $request = $service->ensure_pending($quizobj->get_quizid(), (int) $USER->id, $attemptnumber);
} catch (moodle_exception $exception) {
    if ($exception->errorcode !== 'requestattemptalreadyexists') {
        throw $exception;
    }
    $attempt = $service->find_attempt($quizobj->get_quizid(), (int) $USER->id, $attemptnumber);
    if ($attempt) {
        redirect($quizobj->attempt_url($attempt->id, $page));
    }
    throw $exception;
}

redirect(new moodle_url('/mod/quiz/accessrule/presencial/wait.php', ['requestid' => $request->id]));
