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
 * Read a QR authorization without requiring course enrolment.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

$requestid = required_param('requestid', PARAM_INT);

$url = new moodle_url('/mod/quiz/accessrule/presencial/qr.php', ['requestid' => $requestid]);
$PAGE->set_url($url);
$PAGE->set_cacheable(false);
header('Referrer-Policy: no-referrer');
require_login(null, false);
if (isguestuser()) {
    $SESSION->wantsurl = $url->out(false);
    redirect(get_login_url());
}
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('qrtitle', 'quizaccess_presencial'));
$PAGE->set_heading(get_string('qrtitle', 'quizaccess_presencial'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $token = required_param('token', PARAM_ALPHANUMEXT);
    \quizaccess_presencial\local\qr_authorization::authorize($requestid, $token);
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('qrsuccess', 'quizaccess_presencial'), \core\output\notification::NOTIFY_SUCCESS);
    echo $OUTPUT->footer();
    exit;
}
$PAGE->requires->js_call_amd('quizaccess_presencial/qr_submit', 'init', [$requestid]);
$header = $OUTPUT->header();
header('Referrer-Policy: no-referrer');
echo $header;
echo html_writer::start_tag('form', ['id' => 'presencial-qr-submit', 'method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'token', 'id' => 'presencial-qr-token']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
