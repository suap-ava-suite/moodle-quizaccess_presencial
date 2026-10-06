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
 * Manage the invitation for a quiz.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use quizaccess_presencial\form\invitation as invitation_form;
use quizaccess_presencial\local\invitation;

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/formslib.php');

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
$url = new moodle_url('/mod/quiz/accessrule/presencial/manage.php', ['cmid' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_cacheable(false);
header('Referrer-Policy: no-referrer');
require_login($course, false, $cm);
require_capability('mod/quiz:manage', $context);

$PAGE->set_cm($cm, $course);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('invitationmanage', 'quizaccess_presencial'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => context_course::instance($course->id)]));

$status = invitation::get_status($cm->id);
$form = new invitation_form($url, ['cmid' => $cm->id, 'status' => $status]);
$issued = null;
if ($data = $form->get_data()) {
    require_sesskey();
    if (!empty($data->generate)) {
        $issued = invitation::generate($cm->id, $data->generation);
    } else if (!empty($data->disable)) {
        invitation::disable($cm->id, $data->generation);
        redirect($url, get_string('invitationdisabled', 'quizaccess_presencial'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

$header = $OUTPUT->header();
// Core may apply the site's referrer policy while building its HTTP headers.
header('Referrer-Policy: no-referrer');
echo $header;
echo $OUTPUT->heading(get_string('invitationmanage', 'quizaccess_presencial'));
if ($issued !== null) {
    // The complete link exists only in this response; never retain its secret in the session.
    $link = new moodle_url('/mod/quiz/accessrule/presencial/invite.php', [
        'cmid' => $cm->id,
        'token' => $issued['token'],
    ]);
    echo $OUTPUT->render_from_template('quizaccess_presencial/invitation', [
        'generated' => true,
        'link' => $link->out(false),
        'returnurl' => $url->out(false),
    ]);
} else {
    echo $OUTPUT->render_from_template('quizaccess_presencial/invitation', [
        'state' => get_string('invitationstate_' . $status['state'], 'quizaccess_presencial'),
        'expires' => $status['timeexpires'] ? get_string('invitationexpires', 'quizaccess_presencial',
            userdate($status['timeexpires'])) : '',
        'unavailable' => !$status['canissue'],
    ]);
    $form->display();
}
echo $OUTPUT->footer();
