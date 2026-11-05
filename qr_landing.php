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
 * Preserve the QR secret in the browser before authentication.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- This public page preserves the fragment before qr.php requires login.
require_once(__DIR__ . '/../../../../config.php');
$requestid = required_param('requestid', PARAM_INT);
$site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
// Moodle content can contain student-controlled links; only direct navigation is allowed.
if ($site !== 'none') {
    throw new moodle_exception('qrinvalid', 'quizaccess_presencial');
}
$PAGE->set_url('/mod/quiz/accessrule/presencial/qr_landing.php', ['requestid' => $requestid]);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('embedded');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('qrtitle', 'quizaccess_presencial'));
$PAGE->set_heading(get_string('qrtitle', 'quizaccess_presencial'));
$PAGE->requires->js_call_amd('quizaccess_presencial/qr_landing', 'init', [$requestid]);
header('Referrer-Policy: no-referrer');
echo $OUTPUT->header();
echo $OUTPUT->footer();
