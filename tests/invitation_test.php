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

use quizaccess_presencial\local\invitation;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/**
 * Public invitation operations against Moodle persistence.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\invitation
 */
final class invitation_test extends \advanced_testcase {
    /**
     * A generated invitation is valid only for its quiz and is not recoverable from status.
     */
    public function test_generation_exposes_secret_once_and_scopes_it_to_the_quiz(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $other = $this->configured_quiz();
        $sink = $this->redirectEvents();

        $issued = invitation::generate($quiz->cmid, 0);

        $this->assertMatchesRegularExpression('/\Ai1_[0-9a-f]{64}\z/', $issued['token']);
        // This is the security persistence boundary, not a test of incidental SQL layout.
        $persisted = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $quiz->id], '*', MUST_EXIST);
        $this->assertStringNotContainsString($issued['token'], json_encode($persisted));
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $persisted->tokenhash);
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertFalse(invitation::validate($other->cmid, $issued['token']));
        $status = invitation::get_status($quiz->cmid);
        $this->assertSame('active', $status['state']);
        $this->assertSame(1, $status['generation']);
        $this->assertEquals($quiz->presencial_timeclose, $status['timeexpires']);
        $this->assertArrayNotHasKey('token', $status);
        $this->assertArrayNotHasKey('tokenhash', $status);
        $this->assertArrayNotHasKey('url', $status);
        $events = $sink->get_events();
        $this->assertSame('generated', $events[0]->get_data()['other']['action']);
        $this->assertEquals($quiz->cmid, $events[0]->get_data()['contextinstanceid']);
        $this->assertStringNotContainsString($issued['token'], json_encode($events[0]->get_data()));
    }

    /** Regeneration atomically replaces the secret and refuses stale management forms. */
    public function test_regeneration_rejects_previous_token_and_stale_generation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $sink = $this->redirectEvents();
        $first = invitation::generate($quiz->cmid, 0);
        $second = invitation::generate($quiz->cmid, 1);

        $this->assertNotSame($first['token'], $second['token']);
        $this->assertFalse(invitation::validate($quiz->cmid, $first['token']));
        $this->assertTrue(invitation::validate($quiz->cmid, $second['token']));
        $this->assertSame(2, invitation::get_status($quiz->cmid)['generation']);
        $this->assertSame('regenerated', $sink->get_events()[1]->get_data()['other']['action']);
        try {
            invitation::generate($quiz->cmid, 1);
            $this->fail('A stale form must not replace the invitation.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invitationstale', $exception->errorcode);
        }
        $this->assertTrue(invitation::validate($quiz->cmid, $second['token']));
    }

    /** Disabling is idempotent; only explicitly regenerating restores link access. */
    public function test_disabling_requires_regeneration_and_logs_once(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $sink = $this->redirectEvents();

        invitation::disable($quiz->cmid, 1);
        invitation::disable($quiz->cmid, 1);

        $this->assertSame('disabled', invitation::get_status($quiz->cmid)['state']);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $transitions = array_filter($sink->get_events(),
            fn($event) => $event instanceof \quizaccess_presencial\event\invitation_updated);
        $this->assertCount(1, $transitions);
        $this->assertSame('disabled', reset($transitions)->get_data()['other']['action']);
        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
    }

    /** Expected stale-form refusal does not roll back an enclosing Moodle transaction. */
    public function test_stale_form_preserves_an_outer_transaction(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $transaction = $DB->start_delegated_transaction();
        $issued = invitation::generate($quiz->cmid, 0);
        try {
            invitation::generate($quiz->cmid, 0);
            $this->fail('A replayed form must be refused.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invitationstale', $exception->errorcode);
        }
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        $transaction->allow_commit();
        $this->assertSame(1, invitation::get_status($quiz->cmid)['generation']);
    }

    /** Expiry at the exact boundary is synchronous and the scheduled task is idempotent. */
    public function test_expiry_is_immediate_and_scheduled_processing_is_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $clock = $this->mock_clock_with_frozen(strtotime('2030-01-01 09:00:00 UTC'));
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $sink = $this->redirectEvents();
        $clock->set_to($issued['timeexpires']);

        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertSame('expired', invitation::get_status($quiz->cmid)['state']);
        $task = new \quizaccess_presencial\task\expire_invitations();
        $task->execute();
        $task->execute();

        $expired = array_filter($sink->get_events(), fn($event) =>
            $event instanceof \quizaccess_presencial\event\invitation_updated
            && $event->get_data()['other']['action'] === 'expired');
        $this->assertCount(1, $expired);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invitationunavailable', 'quizaccess_presencial'));
        invitation::generate($quiz->cmid, 1);
    }

    /** Task processing expires an unused link, without a prior validation request. */
    public function test_scheduled_task_expires_an_unused_invitation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $clock = $this->mock_clock_with_frozen(strtotime('2030-01-01 09:00:00 UTC'));
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $sink = $this->redirectEvents();
        $clock->set_to($issued['timeexpires']);
        (new \quizaccess_presencial\task\expire_invitations())->execute();

        $this->assertCount(1, $sink->get_events());
        $this->assertSame('expired', $sink->get_events()[0]->get_data()['other']['action']);
        $this->assertSame('expired', invitation::get_status($quiz->cmid)['state']);
    }

    /** Shortening irreversibly caps expiry; extending the period requires a new secret. */
    public function test_period_reduction_and_extension_do_not_resurrect_the_invitation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $clock = $this->mock_clock_with_frozen(strtotime('2030-01-01 09:00:00 UTC'));
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $quiz->presencial_timeclose = strtotime('2030-01-01 15:00:00 UTC');
        \quizaccess_presencial::save_settings($quiz);
        $quiz->presencial_timeclose = strtotime('2030-01-01 21:00:00 UTC');
        \quizaccess_presencial::save_settings($quiz);

        $this->assertSame(strtotime('2030-01-01 15:00:00 UTC'), invitation::get_status($quiz->cmid)['timeexpires']);
        $clock->set_to(strtotime('2030-01-01 15:00:00 UTC'));
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertSame($quiz->presencial_timeclose, $replacement['timeexpires']);
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
    }

    /** Extending an already ended period before cron runs cannot revive an old invitation. */
    public function test_extension_before_expiry_processing_cannot_resurrect_a_secret(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $clock = $this->mock_clock_with_frozen(strtotime('2030-01-01 09:00:00 UTC'));
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $clock->set_to($issued['timeexpires']);
        $quiz->presencial_timeclose = strtotime('2030-01-01 21:00:00 UTC');
        \quizaccess_presencial::save_settings($quiz);

        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertSame('expired', invitation::get_status($quiz->cmid)['state']);
    }

    /** Re-enabling the rule preserves configuration but cannot restore an old invitation. */
    public function test_rule_disable_and_reenable_require_regeneration(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $quiz->presencial_enabled = 0;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertFalse(invitation::get_status($quiz->cmid)['canissue']);
        $quiz->presencial_enabled = 1;
        \quizaccess_presencial::save_settings($quiz);

        $this->assertSame('disabled', invitation::get_status($quiz->cmid)['state']);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
    }

    /** User-facing validation never returns data or records a submitted secret. */
    public function test_invalid_inputs_have_a_uniform_result_and_secret_free_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $sink = $this->redirectEvents();
        foreach (['', 'unexpected', strtoupper($issued['token']), str_repeat('a', 10000)] as $token) {
            $this->assertFalse(invitation::validate($quiz->cmid, $token));
        }
        $this->assertFalse(invitation::validate(-1, $issued['token']));
        $this->assertCount(5, $sink->get_events());
        foreach ($sink->get_events() as $event) {
            $this->assertInstanceOf(\quizaccess_presencial\event\invitation_invalid::class, $event);
            $this->assertStringNotContainsString($issued['token'], json_encode($event->get_data()));
            $this->assertEmpty($event->get_data()['other']);
        }
        $this->setGuestUser();
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
    }

    /** Quiz management capability is checked in each module, not just the course. */
    public function test_management_requires_capability_in_the_target_module(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $other = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $teacher = self::getDataGenerator()->create_user();
        $role = self::getDataGenerator()->create_role();
        assign_capability('mod/quiz:manage', CAP_ALLOW, $role, \context_system::instance());
        role_assign($role, $teacher->id, \context_module::instance($quiz->cmid));
        $this->setUser($teacher);
        $this->assertSame('active', invitation::get_status($quiz->cmid)['state']);

        foreach ([
            fn() => invitation::get_status($other->cmid),
            fn() => invitation::generate($other->cmid, 0),
            fn() => invitation::disable($other->cmid, 0),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Management requires permission in the target quiz.');
            } catch (\required_capability_exception $exception) {
                $this->assertSame('nopermissions', $exception->errorcode);
            }
        }
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
    }

    /** Concurrent first-generation requests yield one usable token and one stale-form rejection. */
    public function test_concurrent_generation_preserves_one_current_invitation(): void {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([
                    PHP_BINARY, '-d', 'max_input_vars=5000',
                    __DIR__ . '/fixtures/invitation_worker.php', $CFG->dirroot, $quiz->cmid, $USER->id,
                ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                $workers[] = ['process' => $process, 'pipes' => $pipes];
                stream_set_timeout($pipes[1], 30);
                $this->assertSame("READY\n", fgets($pipes[1]));
            }
            foreach ($workers as $worker) {
                fwrite($worker['pipes'][0], "GO\n");
                fflush($worker['pipes'][0]);
            }
            $results = [];
            foreach ($workers as $worker) {
                $results[] = json_decode(stream_get_contents($worker['pipes'][1]), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame('', stream_get_contents($worker['pipes'][2]));
            }
            $issued = array_values(array_filter($results, fn($result) => isset($result['token'])));
            $rejected = array_values(array_filter($results, fn($result) => isset($result['error'])));
            $this->assertCount(1, $issued);
            $this->assertCount(1, $rejected);
            $this->assertSame('invitationstale', $rejected[0]['error']);
            $this->assertTrue(invitation::validate($quiz->cmid, $issued[0]['token']));
            $this->assertSame(1, invitation::get_status($quiz->cmid)['generation']);
        } finally {
            foreach ($workers as $worker) {
                foreach ($worker['pipes'] as $pipe) {
                    fclose($pipe);
                }
                proc_terminate($worker['process']);
                proc_close($worker['process']);
            }
            // Child writes are invisible to Moodle's per-process reset tracking.
            // Exercise the public disabling transition here so the parent also tracks this table.
            $status = invitation::get_status($quiz->cmid);
            if ($status['state'] === 'active') {
                invitation::disable($quiz->cmid, $status['generation']);
            }
        }
    }

    /** Deleting quiz plugin settings also invalidates its current invitation. */
    public function test_quiz_deletion_removes_the_invitation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        \quizaccess_presencial::delete_settings($quiz);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertSame('none', invitation::get_status($quiz->cmid)['state']);
    }

    /**
     * Create a quiz through Moodle's generator and public configuration callback.
     *
     * @return \stdClass Configured quiz.
     */
    private function configured_quiz(): \stdClass {
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timeopen' => strtotime('2030-01-01 08:00:00 UTC'),
            'timeclose' => strtotime('2030-01-01 22:00:00 UTC'),
        ]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = strtotime('2030-01-01 10:00:00 UTC');
        $quiz->presencial_timeclose = strtotime('2030-01-01 20:00:00 UTC');
        \quizaccess_presencial::save_settings($quiz);
        return $quiz;
    }
}
