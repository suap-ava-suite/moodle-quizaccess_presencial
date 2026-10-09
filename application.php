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
 * Protected standalone application panel for one quiz.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use quizaccess_presencial\local\applications;

require_once(__DIR__ . '/../../../../config.php');

require_login(null, false);
$cmid = required_param('cmid', PARAM_INT);
$url = new moodle_url('/mod/quiz/accessrule/presencial/application.php', ['cmid' => $cmid]);
$listurl = new moodle_url('/mod/quiz/accessrule/presencial/applications.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('applicationpanel', 'quizaccess_presencial'));
$PAGE->set_heading(get_string('applicationpanel', 'quizaccess_presencial'));
$application = applications::get_application($cmid);
$PAGE->navbar->add(get_string('myapplications', 'quizaccess_presencial'), $listurl);
$PAGE->navbar->add(get_string('applicationpanel', 'quizaccess_presencial'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('applicationpanel', 'quizaccess_presencial'));
echo html_writer::tag('p', format_string(
    $application['coursename'],
    true,
    ['context' => context_course::instance($application['courseid'])],
));
echo $OUTPUT->heading(format_string(
    $application['quizname'],
    true,
    ['context' => context_module::instance($cmid)],
), 3);
echo html_writer::tag('p', get_string('applicationperiod', 'quizaccess_presencial', (object) [
    'start' => userdate($application['timeopen']),
    'end' => userdate($application['timeclose']),
]));
echo html_writer::tag('p', get_string(
    $application['canoperate'] ? 'applicationrequestsnotavailable' : 'applicationnotstarted',
    'quizaccess_presencial',
));
echo html_writer::link($url, get_string('refresh'));
echo html_writer::tag('p', html_writer::link($listurl, get_string('myapplications', 'quizaccess_presencial')));
echo $OUTPUT->footer();
