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
use quizaccess_presencial\local\release_request;
use quizaccess_presencial\local\release_request_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/**
 * Operational decisions exercised through the public application boundary.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\release_request_service
 */
final class operational_queue_test extends \advanced_testcase {
    /**
     * A delegated account can authorize without enrolment or an academic role.
     */
    public function test_delegated_applicator_can_authorize_without_enrolment(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $this->setUser($operator);

        $authorized = $service->authorize($request->id);

        $this->assertSame(release_request::STATE_AUTHORIZED, $authorized->state);
        $this->assertFalse(is_enrolled(\context_course::instance($quiz->course), $operator));
    }

    /**
     * The latest decision replaces an unused grant and its student-facing reason.
     */
    public function test_decisions_can_be_revised_until_a_new_request_replaces_them(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $this->setUser($operator);
        $service->authorize($request->id);
        $reason = "Identity must be checked.\nPlease speak to the operator. <script>alert(1)</script>";
        $rejected = $service->reject($request->id, $reason);
        $this->assertSame('rejected', $rejected->state);
        $this->assertSame($reason, $service->read_status($request->id, $student->id)->rejectionreason);
        $this->assertFalse($service->is_authorized($quiz->id, $student->id, 1));
        $clock->bump(10);
        $authorized = $service->authorize($request->id);
        $this->assertSame('authorized', $authorized->state);
        $this->assertSame('', $authorized->rejectionreason);
        $service->reject($request->id, 'Try again');
        $next = $service->ensure_pending($quiz->id, $student->id, 1);
        $this->assertNotSame($request->id, $next->id);
        $this->expectExceptionMessage(get_string('requestnotrevisable', 'quizaccess_presencial'));
        $service->authorize($request->id);
    }

    /**
     * Rejection policy is checked on every submission, without truncating explanations.
     */
    public function test_rejection_policy_events_and_expiry(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $this->setUser($operator);
        set_config('rejectionjustificationrequired', 1, 'quizaccess_presencial');
        try {
            $service->reject($request->id, " \n ");
            $this->fail('The policy must require a nonblank explanation.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('rejectionreasonrequired', $exception->errorcode);
        }
        $sink = $this->redirectEvents();
        $reason = str_repeat('Complete explanation. ', 200);
        $service->reject($request->id, $reason);
        $this->assertSame($reason, $service->read_status($request->id, $student->id)->rejectionreason);
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof \quizaccess_presencial\event\request_rejected
        ));
        $this->assertCount(1, $events);
        $this->assertSame((int) $operator->id, (int) $events[0]->userid);
        $this->assertSame((int) $student->id, (int) $events[0]->relateduserid);
        $clock->bump(900);
        $this->assertSame('expired', $service->read_status($request->id, $student->id)->state);
    }

    /**
     * Revocation, suspension, and the exact closing boundary stop both decisions.
     */
    public function test_authority_is_rechecked_for_each_decision(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $delegation = array_values(delegation_manager::list_current($quiz->id, $clock->time()))[0];
        delegation_manager::revoke($quiz->id, $delegation->id, get_admin()->id, $clock->time());
        $this->setUser($operator);
        foreach (['authorize', 'reject'] as $method) {
            try {
                $service->$method($request->id);
                $this->fail('Revocation must stop operational decisions.');
            } catch (\required_capability_exception $exception) {
                $this->assertSame('pending', $service->get($request->id)->state);
            }
        }
        $this->setAdminUser();
        $clock->bump(10_000);
        $this->expectExceptionMessage(get_string('authorizationperiodinactive', 'quizaccess_presencial'));
        $service->authorize($request->id);
    }

    /**
     * The queue pages pending oldest first and then decisions newest first.
     */
    public function test_queue_orders_and_pages_only_actionable_requests(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $first = $service->ensure_pending($quiz->id, $student->id, 1);
        $clock->bump(1);
        $second = $service->ensure_pending($quiz->id, self::getDataGenerator()->create_user()->id, 1);
        $clock->bump(1);
        $third = $service->ensure_pending($quiz->id, self::getDataGenerator()->create_user()->id, 1);
        $clock->bump(1);
        $fourth = $service->ensure_pending($quiz->id, self::getDataGenerator()->create_user()->id, 1);
        $service->authorize($first->id);
        $clock->bump(1);
        $service->reject($third->id, 'Check identity');
        $this->setUser($operator);
        $page = \quizaccess_presencial\local\operational_queue::get_page($quiz->cmid, 0, 2);
        $this->assertSame(4, $page['total']);
        $this->assertSame([$second->id, $fourth->id], array_column($page['items'], 'id'));
        $page = \quizaccess_presencial\local\operational_queue::get_page($quiz->cmid, 1, 2);
        $this->assertSame([$third->id, $first->id], array_column($page['items'], 'id'));
        $clock->bump(900);
        $this->assertSame(0, \quizaccess_presencial\local\operational_queue::get_page($quiz->cmid)['total']);
    }

    /**
     * Identity contains only configured, visible fields and queues stay scoped to one quiz.
     */
    public function test_queue_scopes_identity_and_request_data(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        [$otherquiz, $otherstudent] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $service->ensure_pending($otherquiz->id, $otherstudent->id, 1);
        require_once($CFG->dirroot . '/user/lib.php');
        user_update_user((object) ['id' => $student->id, 'idnumber' => 'Institution-7', 'phone1' => 'Private phone']);
        $CFG->showuseridentity = 'idnumber,phone1';
        $CFG->hiddenuserfields = 'phone1';
        $this->setUser($operator);
        $page = \quizaccess_presencial\local\operational_queue::get_page($quiz->cmid);
        $this->assertSame([$request->id], array_column($page['items'], 'id'));
        $this->assertEmpty($page['items'][0]['identity']);
        $roleid = create_role('Identity reader', 'identityreader', '');
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $operator->id, \context_system::instance());
        $this->setUser($operator);
        $page = \quizaccess_presencial\local\operational_queue::get_page($quiz->cmid);
        $this->assertSame(['Institution-7'], array_column($page['items'][0]['identity'], 'value'));
        $this->assertArrayNotHasKey('email', $page['items'][0]);
        $this->expectExceptionMessage(get_string('applicationaccessdenied', 'quizaccess_presencial'));
        \quizaccess_presencial\local\operational_queue::get_page($otherquiz->cmid);
    }

    /**
     * Future delegations expose preparation metadata without student identities.
     */
    public function test_future_application_does_not_expose_requests_or_allow_decisions(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_799_998_999);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $this->setUser($operator);
        $this->assertSame(
            ['total' => 0, 'items' => []],
            \quizaccess_presencial\local\operational_queue::get_page($quiz->cmid)
        );
        $this->expectException(\required_capability_exception::class);
        $service->reject($request->id);
    }

    /**
     * Suspended accounts cannot read identities or make a decision.
     */
    public function test_suspension_blocks_queue_and_decisions(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        require_once($CFG->dirroot . '/user/lib.php');
        user_update_user((object) ['id' => $operator->id, 'suspended' => 1]);
        $this->setUser($operator);
        foreach (
            [fn() => \quizaccess_presencial\local\operational_queue::get_page($quiz->cmid),
                fn() => $service->reject($request->id)] as $operation
        ) {
            try {
                $operation();
                $this->fail('A suspended account accessed operational data.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('applicationaccessdenied', $exception->errorcode);
            }
        }
    }

    /**
     * The attempt-start claim wins over any subsequent decision and leaves the queue.
     */
    public function test_attempt_start_finalizes_the_decision(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $service->authorize($request->id);
        $service->begin_authorized_attempt($quiz->id, $student->id, 1);
        try {
            $this->setUser($operator);
            $this->assertSame(0, \quizaccess_presencial\local\operational_queue::get_page($quiz->cmid)['total']);
            $this->expectExceptionMessage(get_string('requestnotrevisable', 'quizaccess_presencial'));
            $service->reject($request->id);
        } finally {
            \quizaccess_presencial\local\attempt_start_guard::release($request->id);
        }
    }

    /**
     * The Moodle external authorization boundary also accepts an unenrolled applicator.
     */
    public function test_external_authorization_accepts_operational_delegation(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $this->setUser($operator);
        $this->assertSame('authorized', \quizaccess_presencial\external\authorize_request::execute($request->id)['state']);
    }

    /**
     * Optional explanations can be omitted and a subsequent rejection wins the start race.
     */
    public function test_optional_rejection_prevents_an_authorized_attempt_start(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $this->setUser($operator);
        $service->authorize($request->id);
        $service->reject($request->id);
        $this->assertSame('', $service->read_status($request->id, $student->id)->rejectionreason);
        $this->assertFalse($service->begin_authorized_attempt($quiz->id, $student->id, 1));
        $this->assertSame('rejected', $service->get($request->id)->state);
    }

    /**
     * Competing connections honor the first committed rejection or attempt-start claim.
     *
     * @dataProvider winning_operation_provider
     * @param string $winner Operation held uncommitted while the competitor starts.
     */
    public function test_competing_rejection_and_start_respect_the_first_commit(string $winner): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('This runtime cannot start competing connections.');
        }
        $this->preventResetByRollback();
        $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student, $operator] = $this->application();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $service->authorize($request->id);
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
            $this->setUser($operator);
            if ($winner === 'reject') {
                $service->reject($request->id);
            } else {
                $this->assertTrue($service->begin_authorized_attempt($quiz->id, $student->id, 1));
            }
            fwrite($pipes[0], json_encode([
                'operation' => $winner === 'reject' ? 'start' : 'reject',
                'requestid' => $request->id,
                'quizid' => (int) $quiz->id,
                'studentid' => (int) $student->id,
                'actorid' => (int) $operator->id,
            ], JSON_THROW_ON_ERROR) . "\n");
            fflush($pipes[0]);
            $this->assertSame("ATTEMPTING\n", fgets($pipes[1]));
            $read = [$pipes[1]];
            $write = $except = [];
            $completedearly = stream_select($read, $write, $except, 0, 200000);
            $transaction->allow_commit();
            $transaction = null;
            $result = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(0, $completedearly, 'The competitor must wait for the winning transaction.');
            $this->assertSame($winner === 'reject' ? ['started' => false] : ['error' => 'requestnotrevisable'], $result);
            $this->assertSame($winner === 'reject' ? 'rejected' : 'starting', $service->get($request->id)->state);
            $this->assertFalse($service->find_attempt($quiz->id, $student->id, 1));
            $this->assertSame('', stream_get_contents($pipes[2]));
        } finally {
            if ($transaction) {
                try {
                    $transaction->rollback(new \Exception('Concurrent test cleanup.'));
                } catch (\Exception $ignored) {
                    // Keep the original test failure while releasing the transaction.
                    $transaction = null;
                }
            }
            \quizaccess_presencial\local\attempt_start_guard::release($request->id);
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
     * Both possible first commits must be covered.
     *
     * @return array Winning operations.
     */
    public static function winning_operation_provider(): array {
        return ['rejection commits first' => ['reject'], 'attempt start commits first' => ['start']];
    }

    /**
     * Prepare a configured quiz, a student and an unenrolled applicator.
     *
     * @return array Quiz, student and applicator.
     */
    private function application(): array {
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
        delegation_manager::include_users($quiz->id, [$operator->id], get_admin()->id, 'direct', 1_800_000_000);
        return [$quiz, $student, $operator];
    }
}
