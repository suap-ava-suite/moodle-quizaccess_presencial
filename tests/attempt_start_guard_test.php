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

namespace quizaccess_presencial;

use quizaccess_presencial\local\attempt_start_guard;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the attempt-start lock boundary.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\attempt_start_guard
 */
final class attempt_start_guard_test extends \advanced_testcase {
    /**
     * A held request lock rejects a claim from another PHP process.
     */
    public function test_acquire_is_exclusive_between_processes(): void {
        global $CFG;

        $this->resetAfterTest();
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('This PHP runtime cannot start a second process.');
        }

        $requestid = random_int(1_000_000, 2_000_000);
        $this->assertTrue(attempt_start_guard::acquire($requestid));

        $bootstrap = var_export($CFG->dirroot . '/config.php', true);
        $childcode = 'require_once(' . $bootstrap . ');'
            . '$requestid = (int) $argv[1];'
            . 'if (\\quizaccess_presencial\\local\\attempt_start_guard::acquire($requestid)) {'
            . '\\quizaccess_presencial\\local\\attempt_start_guard::release($requestid);'
            . 'fwrite(STDOUT, "ACQUIRED\\n");'
            . '} else {'
            . 'fwrite(STDOUT, "BUSY\\n");'
            . '}';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = false;
        try {
            $process = proc_open(
                [PHP_BINARY, '-r', $childcode, (string) $requestid],
                $descriptors,
                $pipes,
                $CFG->dirroot,
            );
            $this->assertIsResource($process, 'Could not start the competing PHP process.');
            stream_set_timeout($pipes[1], 10);
            $result = fgets($pipes[1]);
            $this->assertSame("BUSY\n", $result, 'A second PHP process must not acquire the held request lock.');
        } finally {
            if (is_resource($process)) {
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
            }
            attempt_start_guard::release($requestid);
        }
    }
}
