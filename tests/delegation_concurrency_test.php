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
     * A direct inclusion must use the actor's authority after waiting for the quiz lock.
     *
     * @dataProvider delayed_inclusion_provider
     * @param bool $revoke Whether to revoke the teacher's role during the wait.
     * @param bool $othersession Whether the actor differs from the session user.
     * @param bool $switched Whether an administrator has switched to the temporary role.
     * @param bool $switchteacher Whether a non-administrator uses the switched role.
     */
    public function test_inclusion_rechecks_authority_after_lock_wait(
        bool $revoke,
        bool $othersession,
        bool $switched = false,
        bool $switchteacher = false,
    ): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        if (!function_exists('proc_open') || $DB->get_dbfamily() !== 'postgres') {
            $this->markTestSkipped('This test monitors PostgreSQL locks across independent connections.');
        }
        $this->preventResetByRollback();
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = time() - 100;
        $quiz->presencial_timeclose = time() + 3600;
        \quizaccess_presencial::save_settings($quiz);
        $context = \context_module::instance($quiz->cmid);
        $teacher = self::getDataGenerator()->create_user();
        $applicator = self::getDataGenerator()->create_user();
        $role = create_role('Temporary manager', 'temporarymanager', '');
        assign_capability('mod/quiz:manage', CAP_ALLOW, $role, $context->id);
        role_assign($role, $teacher->id, $context->id);
        $this->assertTrue(has_capability('mod/quiz:manage', $context, $teacher->id));
        if ($switchteacher) {
            role_unassign($role, $teacher->id, $context->id);
            $switcher = create_role('Role switcher', 'roleswitcher', '');
            assign_capability('moodle/role:switchroles', CAP_ALLOW, $switcher, $context->id);
            role_assign($switcher, $teacher->id, $context->id);
        }
        $bootstrap = <<<'PHP'
        $autoload = $argv[1] . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            $autoload = dirname($argv[1]) . '/vendor/autoload.php';
        }
        require($autoload);
        define('PHPUNIT_UTIL', true);
        require($argv[1] . '/lib/phpunit/bootstrap.php');
        require($argv[1] . '/mod/quiz/accessrule/presencial/tests/fixtures/delegation_authority_worker.php');
        PHP;
        $process = proc_open(
            [PHP_BINARY, '-r', $bootstrap, $CFG->dirroot],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($process);
        $transaction = null;
        try {
            stream_set_timeout($pipes[1], 30);
            stream_set_timeout($pipes[2], 30);
            $transaction = $DB->start_delegated_transaction();
            \quizaccess_presencial\local\quiz_lock::execute($quiz->id, static function (): void {});
            $actorid = $switched && !$switchteacher ? (int) get_admin()->id : (int) $teacher->id;
            fwrite($pipes[0], json_encode([
                'quizid' => (int) $quiz->id,
                'cmid' => (int) $quiz->cmid,
                'userid' => (int) $applicator->id,
                'actorid' => $actorid,
                'sessionid' => $othersession ? (int) get_admin()->id : $actorid,
                'switchrole' => $switched ? $role : 0,
            ], JSON_THROW_ON_ERROR) . "\n");
            fflush($pipes[0]);
            $connection = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $deadline = microtime(true) + 10;
            do {
                $waiting = $DB->get_field_sql(
                    'SELECT 1 WHERE pg_backend_pid() = ANY(pg_blocking_pids(:connectionid))',
                    ['connectionid' => $connection['connectionid']],
                );
                if ($waiting) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertNotFalse($waiting, 'The inclusion must actually wait for the quiz lock.');
            if ($revoke) {
                if ($switched) {
                    assign_capability('mod/quiz:manage', CAP_PREVENT, $role, $context->id, true);
                } else {
                    role_unassign($role, $teacher->id, $context->id);
                }
            }
            $transaction->allow_commit();
            $transaction = null;
            $result = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('', stream_get_contents($pipes[2]));
            $this->setAdminUser();
            $current = delegation_manager::list_current($quiz->id);
            if ($revoke) {
                $this->assertSame(['permissiondenied' => true], $result);
                $this->assertEmpty($current);
            } else {
                $this->assertCount(1, $current);
                $this->assertSame((int) reset($current)->id, $result['delegationid']);
                if ($switchteacher) {
                    $this->setUser($teacher);
                    role_switch($role, $context);
                }
                $again = delegation_manager::include_users($quiz->id, [$applicator->id], $actorid);
                $this->assertSame($result['delegationid'], (int) $again[$applicator->id]->id);
            }
        } finally {
            if ($transaction) {
                try {
                    $transaction->rollback(new \Exception('Concurrent inclusion test cleanup.'));
                } catch (\Exception $ignored) {
                    $transaction = null;
                }
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
    }

    /**
     * @return array Named authority and session scenarios.
     */
    public static function delayed_inclusion_provider(): array {
        return [
            'actor loses authority' => [true, false],
            'other actor loses authority' => [true, true],
            'actor retains authority' => [false, false],
            'other actor retains authority' => [false, true],
            'switched administrator loses authority' => [true, false, true],
            'switched administrator retains authority' => [false, false, true],
            'switched teacher retains authority' => [false, false, true, true],
        ];
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
