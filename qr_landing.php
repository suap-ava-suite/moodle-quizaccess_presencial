<?php
// This file is part of Moodle - http://moodle.org/

/** Store the secret in this browser before authentication, without sending it in a URL. */
require_once(__DIR__ . '/../../../../config.php');
$requestid = required_param('requestid', PARAM_INT);
$site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if (!in_array($site, ['none', 'same-origin'], true)) {
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
