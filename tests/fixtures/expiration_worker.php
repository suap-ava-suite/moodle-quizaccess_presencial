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
 * Independent status poll or configuration save for expiration races.
 *
 * @package    quizaccess_presencial
 * @category   test
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (PHP_SAPI !== 'cli') {
    die;
}

stream_set_timeout(STDIN, 30);
$params = json_decode(fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
\advanced_testcase::setUser($params['actorid']);
require_once($CFG->dirroot . '/lib/testing/classes/frozen_clock.php');
\core\di::set(\core\clock::class, new \frozen_clock(1_800_000_000));
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');
$sink = \phpunit_util::start_event_redirection();
echo json_encode(['connectionid' => (int) $DB->get_field_sql('SELECT pg_backend_pid()')]) . "\n";
fflush(STDOUT);
if ($params['operation'] === 'status') {
    (new \quizaccess_presencial\local\release_request_service())->read_status($params['requestid'], $params['actorid']);
} else {
    $quiz = (object) $params['quiz'];
    $quiz->presencial_enabled = 0;
    \quizaccess_presencial::save_settings($quiz);
    $quiz->presencial_enabled = 1;
    \quizaccess_presencial::save_settings($quiz);
}
$events = array_filter($sink->get_events(), static fn($event): bool =>
    $event instanceof \quizaccess_presencial\event\request_expired && $event->objectid == $params['requestid']);
echo json_encode(['expired' => count($events)], JSON_THROW_ON_ERROR) . "\n";
