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
use quizaccess_presencial\local\operational_queue;
use quizaccess_presencial\local\release_request_service;

require_once(__DIR__ . '/../../../../config.php');

require_login(null, false);
$cmid = required_param('cmid', PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$url = new moodle_url('/mod/quiz/accessrule/presencial/application.php', ['cmid' => $cmid]);
$listurl = new moodle_url('/mod/quiz/accessrule/presencial/applications.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('applicationpanel', 'quizaccess_presencial'));
$PAGE->set_heading(get_string('applicationpanel', 'quizaccess_presencial'));
$application = applications::get_application($cmid);
$error = null;
$submittedid = 0;
$submittedreason = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_sesskey();
    $submittedid = required_param('requestid', PARAM_INT);
    $decision = required_param('decision', PARAM_ALPHA);
    $submittedreason = optional_param('reason', '', PARAM_RAW);
    $service = new release_request_service();
    if ($service->get($submittedid)->quizid !== (int) $application['quizid']) {
        throw new moodle_exception('requestnotfound', 'quizaccess_presencial');
    }
    try {
        if ($decision === 'authorize') {
            $service->authorize($submittedid);
        } else if ($decision === 'reject') {
            $service->reject($submittedid, $submittedreason);
        } else {
            throw new invalid_parameter_exception('Unknown decision.');
        }
        redirect(new moodle_url($url, ['page' => $page]));
    } catch (moodle_exception $exception) {
        if (!in_array($exception->errorcode, ['rejectionreasonrequired', 'requestnotrevisable'], true)) {
            throw $exception;
        }
        $error = $exception->getMessage();
    }
}
$queue = operational_queue::get_page($cmid, $page);
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
if (!$application['canoperate']) {
    echo html_writer::tag('p', get_string('applicationnotstarted', 'quizaccess_presencial'));
} else {
    if ($error !== null) {
        echo $OUTPUT->notification($error, 'notifyproblem');
    }
    echo $OUTPUT->heading(get_string('operationalqueue', 'quizaccess_presencial'), 3);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable presencial-queue';
    $table->head = array_map(
        fn($key) => get_string($key, 'quizaccess_presencial'),
        ['queuestudent', 'queuestatus', 'queuerequested', 'queueactions']
    );
    foreach ($queue['items'] as $item) {
        $identity = html_writer::empty_tag('img', ['src' => $item['pictureurl'], 'alt' => '',
            'width' => 35, 'height' => 35, 'class' => 'userpicture']);
        $identity .= ' ' . s($item['fullname']);
        foreach ($item['identity'] as $field) {
            $identity .= html_writer::tag('div', s($field['label']) . ': ' . s($field['value']));
        }
        $reasonid = 'rejectionreason-' . $item['id'];
        $reason = $submittedid === $item['id'] ? $submittedreason : $item['rejectionreason'];
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url]);
        foreach (['cmid' => $cmid, 'page' => $page, 'requestid' => $item['id'], 'sesskey' => sesskey()] as $name => $value) {
            $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        $form .= html_writer::tag('label', get_string('rejectionreason', 'quizaccess_presencial'), ['for' => $reasonid]);
        $form .= html_writer::tag('textarea', s($reason), ['name' => 'reason', 'id' => $reasonid,
            'rows' => 2, 'class' => 'form-control']);
        foreach (['authorize', 'reject'] as $decision) {
            $form .= html_writer::tag(
                'button',
                get_string('queue' . $decision, 'quizaccess_presencial'),
                ['type' => 'submit', 'name' => 'decision', 'value' => $decision, 'class' => 'btn btn-secondary mt-1']
            );
        }
        $form .= html_writer::end_tag('form');
        $table->data[] = [$identity, get_string('queuestate' . $item['state'], 'quizaccess_presencial'),
            userdate($item['timecreated']), $form];
    }
    if ($queue['total']) {
        echo html_writer::table($table);
        echo $OUTPUT->paging_bar($queue['total'], $page, 20, $url);
    } else {
        echo html_writer::tag('p', get_string('queueempty', 'quizaccess_presencial'));
    }
}
echo html_writer::link(new moodle_url($url, ['page' => $page]), get_string('refresh'));
echo html_writer::tag('p', html_writer::link($listurl, get_string('myapplications', 'quizaccess_presencial')));
echo $OUTPUT->footer();
