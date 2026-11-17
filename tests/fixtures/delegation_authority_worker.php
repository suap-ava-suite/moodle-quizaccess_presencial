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
 * Independent Moodle connection for a delayed direct inclusion.
 *
 * @package    quizaccess_presencial
 * @category   test
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$request = json_decode(fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
\advanced_testcase::setUser($request['sessionid']);
$context = \context_module::instance($request['cmid']);
if (!empty($request['switchrole'])) {
    role_switch($request['switchrole'], $context);
}
has_capability('mod/quiz:manage', $context, $request['actorid']);
echo json_encode(['connectionid' => (int) $DB->get_field_sql('SELECT pg_backend_pid()')]) . "\n";
flush();
try {
    $result = \quizaccess_presencial\local\delegation_manager::include_users(
        $request['quizid'], [$request['userid']], $request['actorid'],
    );
    echo json_encode(['delegationid' => (int) $result[$request['userid']]->id]) . "\n";
} catch (\Throwable $exception) {
    echo json_encode(['permissiondenied' => $exception instanceof \required_capability_exception]) . "\n";
}
flush();
