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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/**
 * QR authorization through the public request operations.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\qr_authorization
 */
final class qr_authorization_test extends \advanced_testcase {
    /**
     * Every valid use is auditable, including an unchanged authorization.
     */
    public function test_each_valid_qr_use_emits_a_scoped_event_without_the_secret(): void {
        [$request, $student, $operator, , $quiz] = $this->request_fixture();
        $sink = $this->redirectEvents();
        for ($i = 0; $i < 2; $i++) {
            $this->setUser($student);
            $token = \quizaccess_presencial\local\qr_authorization::issue($request->id);
            $this->setUser($operator);
            \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
            $events = array_values(array_filter(
                $sink->get_events(),
                fn($event) => $event instanceof \quizaccess_presencial\event\qr_authorized,
            ));
            $this->assertCount($i + 1, $events);
            $data = $events[$i]->get_data();
            $this->assertSame(\context_module::instance($quiz->cmid)->id, (int) $data['contextid']);
            $this->assertSame($request->id, (int) $data['objectid']);
            $this->assertSame((int) $student->id, (int) $data['relateduserid']);
            $this->assertSame((int) $operator->id, (int) $data['userid']);
            $serialized = json_encode($data, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString($token, $serialized);
            $this->assertStringNotContainsString(hash('sha256', 'quizaccess_presencial:qr:' . $token), $serialized);
        }
        $sink->close();
    }

    /**
     * Invalid codes with a known request are visible in the activity log.
     */
    public function test_expired_qr_use_emits_a_scoped_event_without_changing_the_request(): void {
        [$request, $student, $operator, $requests, $quiz] = $this->request_fixture();
        $this->setUser($student);
        $token = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        \core\di::get(\core\clock::class)->bump(60);
        $this->setUser($operator);
        $sink = $this->redirectEvents();
        try {
            \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
            $this->fail('Expired QR must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('qrinvalid', $exception->errorcode);
        }
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof \quizaccess_presencial\event\qr_invalid,
        ));
        $this->assertCount(1, $events);
        $data = $events[0]->get_data();
        $this->assertSame(\context_module::instance($quiz->cmid)->id, (int) $data['contextid']);
        $this->assertSame($request->id, (int) $data['objectid']);
        $this->assertSame((int) $student->id, (int) $data['relateduserid']);
        $this->assertSame((int) $operator->id, (int) $data['userid']);
        $serialized = json_encode($data, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($token, $serialized);
        $this->assertStringNotContainsString(hash('sha256', 'quizaccess_presencial:qr:' . $token), $serialized);
        $this->assertSame('pending', $requests->get($request->id)->state);
        $sink->close();
    }

    /**
     * Unknown requests are logged without assuming an activity context.
     */
    public function test_unknown_request_emits_a_system_scoped_invalid_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEvents();
        try {
            \quizaccess_presencial\local\qr_authorization::authorize(99999999, 'q1_invalid');
            $this->fail('An unknown request must be rejected.');
        } catch (\moodle_exception $exception) {
            $events = array_values(array_filter(
                $sink->get_events(),
                fn($event) => $event instanceof \quizaccess_presencial\event\qr_invalid,
            ));
            $this->assertCount(1, $events);
            $this->assertSame(\context_system::instance()->id, (int) $events[0]->contextid);
        }
        $sink->close();
    }

    public function test_qr_is_single_use_and_authorizes_the_request(): void {
        [$request, $student, $operator, $requests] = $this->request_fixture();
        $this->setUser($student);
        $token = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        $this->setUser($operator);
        \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
        $this->assertSame('authorized', $requests->get($request->id)->state);
        $this->expectException(\moodle_exception::class);
        \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
    }

    public function test_replaced_and_expired_codes_cannot_authorize(): void {
        [$request, $student, $operator, $requests] = $this->request_fixture();
        $this->setUser($student);
        $old = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        $current = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        $this->setUser($operator);
        try {
            \quizaccess_presencial\local\qr_authorization::authorize($request->id, $old);
            $this->fail('A replaced QR code must not authorize.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('qrinvalid', $exception->errorcode);
        }
        $this->assertSame('pending', $requests->get($request->id)->state);
        \core\di::get(\core\clock::class)->bump(60);
        try {
            \quizaccess_presencial\local\qr_authorization::authorize($request->id, $current);
            $this->fail('A QR code must expire after 60 seconds.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('qrinvalid', $exception->errorcode);
        }
        $this->assertSame('pending', $requests->get($request->id)->state);
    }

    public function test_an_unprivileged_user_cannot_consume_the_code(): void {
        [$request, $student, $operator, $requests] = $this->request_fixture();
        $this->setUser($student);
        $token = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        $this->setUser(self::getDataGenerator()->create_user());
        try {
            \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
            $this->fail('An unprivileged user must not authorize.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('pending', $requests->get($request->id)->state);
        }
        $this->setUser($operator);
        \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
        $this->assertSame('authorized', $requests->get($request->id)->state);
    }

    public function test_code_is_valid_at_59_seconds_and_cannot_be_used_for_another_request(): void {
        [$request, $student, $operator, $requests] = $this->request_fixture();
        $other = self::getDataGenerator()->create_user();
        $otherrequest = $requests->ensure_pending($request->quizid, $other->id, 1);
        $this->setUser($student);
        $token = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        \core\di::get(\core\clock::class)->bump(59);
        $this->setUser($operator);
        try {
            \quizaccess_presencial\local\qr_authorization::authorize($otherrequest->id, $token);
            $this->fail('The QR must not authorize a different request.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('qrinvalid', $exception->errorcode);
        }
        \quizaccess_presencial\local\qr_authorization::authorize($request->id, $token);
        $this->assertSame('authorized', $requests->get($request->id)->state);
        $this->assertSame('pending', $requests->get($otherrequest->id)->state);
    }

    /**
     * Concurrent student clicks neither deadlock nor extend QR validity.
     *
     * @dataProvider concurrent_request_provider
     * @param int $elapsed Time elapsed while authorization waits.
     * @param bool $reuse Whether the student reuses the request before committing.
     */
    public function test_qr_authorization_waits_safely_for_concurrent_requests(int $elapsed, bool $reuse): void {
        global $CFG, $DB;

        if (!function_exists('proc_open')) {
            $this->markTestSkipped('This runtime cannot start competing connections.');
        }
        $this->preventResetByRollback();
        [$request, $student, $operator, $requests] = $this->request_fixture();
        $this->setUser($student);
        $token = \quizaccess_presencial\local\qr_authorization::issue($request->id);
        $clockfile = tempnam($CFG->dataroot, 'qr-clock-');
        file_put_contents($clockfile, '1800000000');
        $bootstrap = <<<'PHP'
        $autoload = $argv[1] . '/vendor/autoload.php';
        if (!is_readable($autoload)) {
            $autoload = dirname($argv[1]) . '/vendor/autoload.php';
        }
        require($autoload);
        define('PHPUNIT_UTIL', true);
        require($argv[1] . '/lib/phpunit/bootstrap.php');
        require($argv[1] . '/mod/quiz/accessrule/presencial/tests/fixtures/decision_worker.php');
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
            $this->assertSame("READY\n", fgets($pipes[1]));
            $transaction = $DB->start_delegated_transaction();
            // Pause request creation after its configuration lock, before its request lock.
            $DB->get_record_sql(
                'SELECT id FROM {quizaccess_presencial} WHERE quizid = :quizid FOR UPDATE',
                ['quizid' => $request->quizid],
                MUST_EXIST,
            );
            fwrite($pipes[0], json_encode([
                'operation' => 'authorizeqr',
                'requestid' => $request->id,
                'actorid' => (int) $operator->id,
                'token' => $token,
                'clockfile' => $clockfile,
            ], JSON_THROW_ON_ERROR) . "\n");
            fflush($pipes[0]);
            $this->assertSame("ATTEMPTING\n", fgets($pipes[1]));
            $read = [$pipes[1]];
            $write = $except = [];
            $completedearly = stream_select($read, $write, $except, 0, 200000);
            if ($reuse) {
                $reused = $requests->ensure_pending($request->quizid, $student->id, 1);
                $this->assertSame($request->id, $reused->id);
            }
            file_put_contents($clockfile, (string) (1_800_000_000 + $elapsed));
            $transaction->allow_commit();
            $transaction = null;
            $result = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(0, $completedearly, 'Authorization must wait for request creation.');
            $this->assertSame($elapsed >= 60 ? ['error' => 'qrinvalid'] : ['state' => 'authorized'], $result);
            $this->assertSame($elapsed >= 60 ? 'pending' : 'authorized', $requests->get($request->id)->state);
            $this->assertSame('', stream_get_contents($pipes[2]));
        } finally {
            if ($transaction) {
                try {
                    $transaction->rollback(new \Exception('Concurrent test cleanup.'));
                } catch (\Exception $ignored) {
                    // Preserve the original failure while releasing the transaction.
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
            unlink($clockfile);
        }
    }

    /**
     * Expiration and request reuse must both be serialized with authorization.
     *
     * @return array Scenarios exercising the public request and QR operations.
     */
    public static function concurrent_request_provider(): array {
        return [
            'code expires while waiting' => [61, false],
            'student reuses the pending request' => [0, true],
        ];
    }

    /**
     * A still-pending request must not offer new codes once its authorization period ends.
     */
    public function test_qr_issuance_stops_at_the_end_of_the_authorization_period(): void {
        [$request, $student, , $requests, $quiz] = $this->request_fixture();
        $quiz->presencial_timeclose = 1_800_000_010;
        \quizaccess_presencial::save_settings($quiz);
        $this->setUser($student);
        \quizaccess_presencial\local\qr_authorization::issue($request->id);
        \core\di::get(\core\clock::class)->bump(10);
        try {
            \quizaccess_presencial\local\qr_authorization::issue($request->id);
            $this->fail('A closed authorization period must not issue another code.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('authorizationperiodinactive', $exception->errorcode);
        }
        $this->assertSame('pending', $requests->get($request->id)->state);
    }

    /**
     * Create a request and delegated operator in an active authorization period.
     *
     * @return array Request, student, operator, request service and quiz.
     */
    private function request_fixture(): array {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = 1_799_999_000;
        $quiz->presencial_timeclose = 1_800_010_000;
        \quizaccess_presencial::save_settings($quiz);
        $student = $generator->create_user();
        $operator = $generator->create_user();
        \quizaccess_presencial\local\delegation_manager::include_users(
            $quiz->id,
            [$operator->id],
            get_admin()->id,
            'direct',
            1_800_000_000
        );
        $requests = new \quizaccess_presencial\local\release_request_service();
        $request = $requests->ensure_pending($quiz->id, $student->id, 1);
        return [$request, $student, $operator, $requests, $quiz];
    }
}
