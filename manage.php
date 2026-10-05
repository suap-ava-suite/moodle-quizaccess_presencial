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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Manage the application team for a Quiz.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/user/selector/lib.php');

use quizaccess_presencial\local\applicator_selector;
use quizaccess_presencial\local\delegation_manager;

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = \context_module::instance($cm->id);
require_capability('mod/quiz:manage', $context);

$url = new moodle_url('/mod/quiz/accessrule/presencial/manage.php', ['cmid' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageapplicators', 'quizaccess_presencial'));
$PAGE->set_heading(format_string($quiz->name));
$PAGE->navbar->add(get_string('manageapplicators', 'quizaccess_presencial'));

$selector = new applicator_selector('applicators', ['accesscontext' => $context]);
if (optional_param('addapplicators', false, PARAM_BOOL)) {
    require_sesskey();
    $users = $selector->get_selected_users();
    delegation_manager::include_users(
        (int) $quiz->id,
        array_map(static fn($user): int => (int) $user->id, $users),
        (int) $USER->id,
    );
    redirect(
        $url,
        get_string('delegationincluded', 'quizaccess_presencial'),
        null,
        \core\output\notification::NOTIFY_SUCCESS,
    );
}

$revokeid = optional_param('revoke', 0, PARAM_INT);
if ($revokeid) {
    require_sesskey();
    delegation_manager::revoke((int) $quiz->id, $revokeid, (int) $USER->id);
    redirect(
        $url,
        get_string('delegationrevoked', 'quizaccess_presencial'),
        null,
        \core\output\notification::NOTIFY_SUCCESS,
    );
}

$delegations = delegation_manager::list_current((int) $quiz->id);
$users = $delegations ? $DB->get_records_list('user', 'id', array_column($delegations, 'userid')) : [];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageapplicators', 'quizaccess_presencial'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('label', get_string('applicators', 'quizaccess_presencial'), ['for' => 'applicators']);
echo $selector->display(true);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'addapplicators',
    'value' => get_string('addapplicators', 'quizaccess_presencial'),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->heading(get_string('currentapplicators', 'quizaccess_presencial'), 3);
if (!$delegations) {
    echo html_writer::tag('p', get_string('none'));
} else {
    $table = new html_table();
    $table->attributes['class'] = 'presencial-current-applicators';
    $table->head = [get_string('fullname'), get_string('delegationorigin', 'quizaccess_presencial'),
        get_string('authorizationperiodstart', 'quizaccess_presencial'),
        get_string('authorizationperiodend', 'quizaccess_presencial'), get_string('actions')];
    foreach ($delegations as $delegation) {
        $user = $users[$delegation->userid];
        $originstring = 'delegationorigin_' . $delegation->origin;
        $origin = get_string_manager()->string_exists($originstring, 'quizaccess_presencial')
            ? get_string($originstring, 'quizaccess_presencial')
            : get_string('delegationorigin_unknown', 'quizaccess_presencial');
        $revokeform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
        $revokeform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $revokeform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'revoke', 'value' => $delegation->id]);
        $revokeform .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('revokeapplicator', 'quizaccess_presencial'),
            'class' => 'btn btn-secondary',
        ]);
        $revokeform .= html_writer::end_tag('form');
        $table->data[] = [
            fullname($user),
            $origin,
            userdate($delegation->timeopen),
            userdate($delegation->timeclose),
            $revokeform,
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
