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

use quizaccess_presencial\local\delegation_manager;
use quizaccess_presencial\local\invitation;

/**
 * Concurrent invitation acceptance and direct inclusion through independent connections.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\delegation_manager
 * @covers     \quizaccess_presencial\local\invitation
 */
final class delegation_concurrency_test extends \advanced_testcase {
    /**
     * Both grant paths must wait for the outer commit and preserve one delegation's lifecycle.
     *
     * @dataProvider outer_operation_provider
     * @param string $outeroperation Operation performed inside the parent's outer transaction.
     */
    public function test_concurrent_grants_preserve_one_delegation(string $outeroperation): void {
        global $CFG, $DB, $USER;

        $this->resetAfterTest();
        // Independent connections need committed fixtures and cannot participate in the reset transaction.
        $this->preventResetByRollback();
        $this->setAdminUser();
        $teacherid = (int) $USER->id;
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timeopen' => time() - HOURSECS,
            'timeclose' => time() + (2 * HOURSECS),
        ]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = time() - HOURSECS;
        $quiz->presencial_timeclose = time() + HOURSECS;
        \quizaccess_presencial::save_settings($quiz);
        $applicator = self::getDataGenerator()->create_user();
        $issued = invitation::generate($quiz->cmid, 0);

        $bootstrap = <<<'PHP'
        $autoload = $argv[1] . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            $autoload = dirname($argv[1]) . '/vendor/autoload.php';
        }
        require($autoload);
        define('PHPUNIT_UTIL', true);
        require($argv[1] . '/lib/phpunit/bootstrap.php');
        require($argv[1] . '/mod/quiz/accessrule/presencial/tests/fixtures/delegation_worker.php');
        PHP;
        $process = proc_open(
            [PHP_BINARY, '-d', 'max_input_vars=5000', '-r', $bootstrap, $CFG->dirroot],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($process);
        $transaction = null;
        try {
            stream_set_timeout($pipes[1], 30);
            stream_set_timeout($pipes[2], 30);
            $this->assertSame("READY\n", fgets($pipes[1]));
            $transaction = $DB->start_delegated_transaction();
            if ($outeroperation === 'accept') {
                $this->setUser($applicator);
                $accepted = invitation::accept($quiz->cmid, $issued['token']);
                $first = $accepted['delegation'];
                $this->assertTrue($accepted['created']);
            } else {
                $included = delegation_manager::include_users($quiz->id, [$applicator->id], $teacherid);
                $first = $included[$applicator->id];
            }
            $childoperation = $outeroperation === 'accept' ? 'include' : 'accept';
            fwrite($pipes[0], json_encode([
                'operation' => $childoperation,
                'quizid' => (int) $quiz->id,
                'cmid' => (int) $quiz->cmid,
                'userid' => (int) $applicator->id,
                'actorid' => $childoperation === 'accept' ? (int) $applicator->id : $teacherid,
                'token' => $issued['token'],
            ], JSON_THROW_ON_ERROR) . "\n");
            fflush($pipes[0]);
            $this->assertSame("ATTEMPTING\n", fgets($pipes[1]));
            $read = [$pipes[1]];
            $write = $except = [];
            $completedearly = stream_select($read, $write, $except, 1);

            // Re-enter both public grant paths on the owning connection while the other connection waits.
            $this->setAdminUser();
            $nested = delegation_manager::include_users($quiz->id, [$applicator->id], $teacherid);
            $this->assertEquals($first, $nested[$applicator->id]);
            $this->setUser($applicator);
            $again = invitation::accept($quiz->cmid, $issued['token']);
            $this->assertFalse($again['created']);
            $this->assertEquals($first, $again['delegation']);
            $transaction->allow_commit();
            $transaction = null;

            $line = fgets($pipes[1]);
            $this->assertNotFalse($line, 'The concurrent grant did not finish within 30 seconds.');
            $second = json_decode($line, false, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('', stream_get_contents($pipes[2]));
            $this->assertEquals($first, $second, 'Both grant paths must return the same delegation.');
            $this->assertSame(0, $completedearly, 'The concurrent grant must wait for the outer commit.');
            $this->setAdminUser();
            $current = delegation_manager::list_current($quiz->id);
            $this->assertCount(1, $current);
            $this->assertEquals($first, reset($current));

            $this->setUser($applicator);
            $again = invitation::accept($quiz->cmid, $issued['token']);
            $this->assertFalse($again['created']);
            $this->assertEquals($first, $again['delegation']);
            $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
            $this->setAdminUser();
            $this->assertTrue(delegation_manager::revoke($quiz->id, $first->id, $teacherid));
            $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
            $this->assertEmpty(delegation_manager::list_current($quiz->id));

            $this->setUser($applicator);
            $replacement = invitation::accept($quiz->cmid, $issued['token']);
            $this->assertTrue($replacement['created']);
            $this->assertNotEquals($first->id, $replacement['delegation']->id);
            $this->setAdminUser();
            // The old delegation still exists and stays revoked after the new grant.
            $this->assertFalse(delegation_manager::revoke($quiz->id, $first->id, $teacherid));
            $current = delegation_manager::list_current($quiz->id);
            $this->assertCount(1, $current);
            $this->assertEquals($replacement['delegation'], reset($current));
            $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
        } catch (\Throwable $exception) {
            if ($transaction !== null) {
                $transaction->rollback($exception);
            }
            throw $exception;
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    /**
     * Exercise either grant path as the owner of the outer transaction.
     *
     * @return array Named operation scenarios.
     */
    public static function outer_operation_provider(): array {
        return [
            'acceptance holds the outer transaction' => ['accept'],
            'direct inclusion holds the outer transaction' => ['include'],
        ];
    }
}
