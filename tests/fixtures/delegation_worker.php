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
 * Independent Moodle connection for concurrent public delegation operations.
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
$request = json_decode(fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
\advanced_testcase::setUser($request['actorid']);
fwrite(STDOUT, "ATTEMPTING\n");
fflush(STDOUT);
if ($request['operation'] === 'accept') {
    $result = \quizaccess_presencial\local\invitation::accept($request['cmid'], $request['token']);
    $delegation = $result['delegation'];
} else {
    $result = \quizaccess_presencial\local\delegation_manager::include_users(
        $request['quizid'],
        [$request['userid']],
        $request['actorid'],
    );
    $delegation = $result[$request['userid']];
}
echo json_encode($delegation, JSON_THROW_ON_ERROR) . "\n";
