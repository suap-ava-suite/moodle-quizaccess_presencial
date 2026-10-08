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
 * Standalone current and future applications, without course enrolment checks.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use quizaccess_presencial\local\applications;

require_once(__DIR__ . '/../../../../config.php');

require_login(null, false);
$page = max(0, optional_param('page', 0, PARAM_INT));
$pagesize = 20;
$url = new moodle_url('/mod/quiz/accessrule/presencial/applications.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('myapplications', 'quizaccess_presencial'));
$PAGE->set_heading(get_string('myapplications', 'quizaccess_presencial'));
$PAGE->navbar->add(get_string('myapplications', 'quizaccess_presencial'), $url);

$applications = applications::get_page($page, $pagesize);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('myapplications', 'quizaccess_presencial'));
if (!$applications['total']) {
    echo html_writer::tag('p', get_string('noapplications', 'quizaccess_presencial'));
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable presencial-applications';
    $table->head = [
        get_string('course'),
        get_string('modulename', 'quiz'),
        get_string('authorizationperiodstart', 'quizaccess_presencial'),
        get_string('authorizationperiodend', 'quizaccess_presencial'),
        get_string('applicationstate', 'quizaccess_presencial'),
        get_string('pendingrequests', 'quizaccess_presencial'),
        get_string('actions'),
    ];
    foreach ($applications['items'] as $application) {
        $context = context_module::instance($application['cmid']);
        $table->data[] = [
            format_string($application['coursename'], true, ['context' => context_course::instance($application['courseid'])]),
            format_string($application['quizname'], true, ['context' => $context]),
            userdate($application['timeopen']),
            userdate($application['timeclose']),
            get_string('applicationstate_' . $application['state'], 'quizaccess_presencial'),
            $application['pendingcount'] === null
                ? get_string('pendingrequestsunavailable', 'quizaccess_presencial') : $application['pendingcount'],
            html_writer::link(
                new moodle_url('/mod/quiz/accessrule/presencial/application.php', ['cmid' => $application['cmid']]),
                get_string('openapplication', 'quizaccess_presencial'),
            ),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($applications['total'], $page, $pagesize, $url);
}
echo $OUTPUT->footer();
