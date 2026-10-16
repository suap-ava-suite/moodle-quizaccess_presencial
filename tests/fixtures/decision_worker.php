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
 * Independent connection competing to reject or start an authorized request.
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
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
$params = json_decode(fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
\advanced_testcase::setUser($params['actorid']);
require_once($CFG->dirroot . '/lib/testing/classes/frozen_clock.php');
$service = new \quizaccess_presencial\local\release_request_service(new \frozen_clock(1_800_000_000));
fwrite(STDOUT, "ATTEMPTING\n");
fflush(STDOUT);
try {
    if ($params['operation'] === 'reject') {
        $result = ['state' => $service->reject($params['requestid'])->state];
    } else {
        $result = ['started' => $service->begin_authorized_attempt($params['quizid'], $params['studentid'], 1)];
    }
} catch (\moodle_exception $exception) {
    $result = ['error' => $exception->errorcode];
} finally {
    \quizaccess_presencial\local\attempt_start_guard::release($params['requestid']);
}
echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
