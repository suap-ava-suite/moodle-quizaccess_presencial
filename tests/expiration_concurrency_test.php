<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace quizaccess_presencial;

use quizaccess_presencial\local\release_request;
use quizaccess_presencial\local\release_request_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/**
 * Expiration stays irreversible across concurrent polling and configuration saves.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\release_request_service
 */
final class expiration_concurrency_test extends \advanced_testcase {
    /**
     * Privacy erasure waits for an ongoing quiz operation before removing any personal data.
     */
    public function test_privacy_erasure_waits_for_quiz_operation(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        if (!function_exists('proc_open') || $DB->get_dbfamily() !== 'postgres') {
            $this->markTestSkipped('This test requires processes and PostgreSQL lock monitoring.');
        }
        $this->preventResetByRollback();
        $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = 1_799_999_000;
        $quiz->presencial_timeclose = 1_800_003_600;
        \quizaccess_presencial::save_settings($quiz);
        $applicator = self::getDataGenerator()->create_user();
        $student = self::getDataGenerator()->create_user();
        \quizaccess_presencial\local\delegation_manager::include_users($quiz->id, [$applicator->id], $USER->id);
        $context = \context_module::instance($quiz->cmid);
        $transaction = $DB->start_delegated_transaction();
        $worker = null;
        try {
            (new release_request_service())->ensure_pending($quiz->id, $student->id, 1);
            $worker = $this->start_worker([
                'operation' => 'privacy', 'actorid' => $USER->id,
                'userid' => $applicator->id, 'contextid' => $context->id,
            ]);
            [$process, $pipes] = $worker;
            $connection = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $deadline = microtime(true) + 10;
            do {
                $waiting = $DB->get_field_sql('SELECT 1 WHERE pg_backend_pid() = ANY(pg_blocking_pids(:connectionid))',
                    ['connectionid' => $connection['connectionid']]);
                if ($waiting) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertNotFalse($waiting);
            $this->assertEquals([$context->id],
                \quizaccess_presencial\privacy\provider::get_contexts_for_userid($applicator->id)->get_contextids());
            $transaction->allow_commit();
            $transaction = null;
            $this->assertNotFalse(fgets($pipes[1]));
            $this->assertSame('', stream_get_contents($pipes[2]));
            $this->assertEmpty(\quizaccess_presencial\privacy\provider::get_contexts_for_userid($applicator->id)->get_contextids());
        } finally {
            if ($worker) {
                [$process, $pipes] = $worker;
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($process);
            }
            if ($transaction) {
                try {
                    $transaction->rollback(new \Exception('Privacy race cleanup.'));
                } catch (\Exception $ignored) {
                    // Preserve the original test failure after releasing the lock.
                }
            }
        }
    }

    /**
     * Disablement cannot skip a request while a status poll waits for its row.
     *
     * @dataProvider request_states
     * @param string $state Initial request state.
     */
    public function test_poll_cannot_preserve_request_across_disablement(string $state): void {
        global $DB, $USER;

        $this->resetAfterTest();
        if (!function_exists('proc_open') || $DB->get_dbfamily() !== 'postgres') {
            $this->markTestSkipped('This test requires processes and PostgreSQL lock monitoring.');
        }
        $this->preventResetByRollback();
        $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = 1_799_999_000;
        $quiz->presencial_timeclose = 1_800_003_600;
        \quizaccess_presencial::save_settings($quiz);
        $student = self::getDataGenerator()->create_user();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        if ($state === release_request::STATE_AUTHORIZED) {
            $service->authorize($request->id);
        }
        $sink = $this->redirectEvents();
        $workers = [];
        $transaction = null;
        try {
            // Hold only the request row, so the poll reaches its expiration path and waits.
            $transaction = $DB->start_delegated_transaction();
            $DB->get_record_sql(
                'SELECT * FROM {quizaccess_presencial_req} WHERE id = :id FOR UPDATE',
                ['id' => $request->id],
            );
            $workers[] = $this->start_worker([
                'operation' => 'status', 'actorid' => $student->id, 'requestid' => $request->id,
            ]);
            $connection = json_decode(fgets($workers[0][1][1]), true, 512, JSON_THROW_ON_ERROR);
            $deadline = microtime(true) + 10;
            do {
                // Database lock ownership is observed only to coordinate the public operations.
                $waiting = $DB->get_field_sql(
                    'SELECT 1 WHERE pg_backend_pid() = ANY(pg_blocking_pids(:connectionid))',
                    ['connectionid' => $connection['connectionid']],
                );
                if ($waiting) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertNotFalse($waiting, 'The status poll must wait for the request row.');
            $workers[] = $this->start_worker([
                'operation' => 'settings', 'actorid' => $USER->id, 'requestid' => $request->id, 'quiz' => $quiz,
            ]);
            $connection = json_decode(fgets($workers[1][1][1]), true, 512, JSON_THROW_ON_ERROR);
            // Accept either a completed save or one blocked by the poll; release the row in both cases.
            $deadline = microtime(true) + 10;
            do {
                $waiting = $DB->get_field_sql(
                    'SELECT 1 WHERE cardinality(pg_blocking_pids(:connectionid)) > 0',
                    ['connectionid' => $connection['connectionid']],
                );
                $read = [$workers[1][1][1]];
                $write = $except = [];
                $completed = stream_select($read, $write, $except, 0);
                if ($waiting || $completed) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertTrue((bool) $waiting || $completed === 1, 'The save must reach the concurrent operation.');
            $transaction->allow_commit();
            $transaction = null;
            $expired = 0;
            foreach ($workers as [$process, $pipes]) {
                $line = fgets($pipes[1]);
                $this->assertNotFalse($line, 'Concurrent operation did not complete within 30 seconds.');
                $expired += json_decode($line, true, 512, JSON_THROW_ON_ERROR)['expired'];
                $this->assertSame('', stream_get_contents($pipes[2]));
            }
            $this->assertSame(release_request::STATE_EXPIRED, $service->get($request->id)->state);
            $this->assertSame(release_request::STATE_EXPIRED, $service->read_status($request->id, $student->id)->state);
            $this->assertFalse($service->is_authorized($quiz->id, $student->id, 1));
            $this->assertFalse($service->begin_authorized_attempt($quiz->id, $student->id, 1));
            $task = new \quizaccess_presencial\task\expire_requests();
            $task->execute();
            $task->execute();
            $expired += count(array_filter($sink->get_events(), static fn($event): bool =>
                $event instanceof \quizaccess_presencial\event\request_expired && $event->objectid == $request->id));
            $this->assertSame(1, $expired);
        } finally {
            foreach ($workers as [$process, $pipes]) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($process);
            }
            if ($transaction) {
                try {
                    $transaction->rollback(new \Exception('Expiration race cleanup.'));
                } catch (\Exception $ignored) {
                    // Preserve the original test failure after releasing the row.
                    $transaction = null;
                }
            }
        }
    }

    /**
     * Supply requests and unused authorizations.
     * @return array Initial states.
     */
    public static function request_states(): array {
        return ['pending' => [release_request::STATE_PENDING], 'authorized' => [release_request::STATE_AUTHORIZED]];
    }

    /**
     * Start an independent Moodle connection executing a public operation.
     * @param array $params Operation parameters.
     * @return array Process and communication pipes.
     */
    private function start_worker(array $params): array {
        global $CFG;

        $bootstrap = <<<'PHP'
        $autoload = $argv[1] . '/vendor/autoload.php';
        if (!is_readable($autoload)) {
            $autoload = dirname($argv[1]) . '/vendor/autoload.php';
        }
        require($autoload);
        define('PHPUNIT_UTIL', true);
        require($argv[1] . '/lib/phpunit/bootstrap.php');
        require($argv[1] . '/mod/quiz/accessrule/presencial/tests/fixtures/expiration_worker.php');
        PHP;
        $process = proc_open(
            [PHP_BINARY, '-r', $bootstrap, $CFG->dirroot],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($process);
        stream_set_timeout($pipes[1], 30);
        stream_set_timeout($pipes[2], 30);
        fwrite($pipes[0], json_encode($params, JSON_THROW_ON_ERROR) . "\n");
        fflush($pipes[0]);
        return [$process, $pipes];
    }
}
