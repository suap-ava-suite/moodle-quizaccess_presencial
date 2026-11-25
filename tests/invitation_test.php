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

#[\PHPUnit\Framework\Attributes\CoversClass(invitation::class)]
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
     * A manager can recover the current token without changing its validity.
     */
    public function test_manager_can_recover_active_invitation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $this->assertSame($issued['token'], invitation::get_management_details($quiz->cmid)['token']);
        $this->assertSame($issued['token'], invitation::get_management_details($quiz->cmid)['token']);
        $status = invitation::get_status($quiz->cmid);
        $this->assertSame(1, $status['generation']);
        $this->assertSame($issued['timeexpires'], $status['timeexpires']);
        invitation::disable($quiz->cmid, 1);
        $this->assertNull(invitation::get_management_details($quiz->cmid)['token']);
    }

    /**
     * Recovering a valid link records its generation without leaking the secret.
     */
    public function test_recovery_emits_a_secret_free_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $sink = $this->redirectEvents();
        invitation::get_status($quiz->cmid);
        $this->assertCount(0, $sink->get_events());
        $details = invitation::get_management_details($quiz->cmid);
        $this->assertSame($issued['token'], $details['token']);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\quizaccess_presencial\event\invitation_link_retrieved::class, $events[0]);
        $data = $events[0]->get_data();
        $this->assertSame(1, $data['other']['generation']);
        $this->assertSame(['generation'], array_keys($data['other']));
        $this->assertEquals($quiz->id, $data['objectid']);
        $this->assertEquals(\context_module::instance($quiz->cmid)->id, $data['contextid']);
        $this->assertEquals(2, $data['userid']);
        $this->assertStringNotContainsString($issued['token'], json_encode($data));
        $sink->close();
    }

    /**
     * Management receives state, generation, expiry and token for the same invitation.
     */
    public function test_management_details_follow_regeneration_and_disabling(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $first = invitation::generate($quiz->cmid, 0);
        $details = invitation::get_management_details($quiz->cmid);
        $this->assertSame('active', $details['state']);
        $this->assertSame(1, $details['generation']);
        $this->assertSame($first['timeexpires'], $details['timeexpires']);
        $this->assertSame($first['token'], $details['token']);

        $second = invitation::generate($quiz->cmid, 1);
        $details = invitation::get_management_details($quiz->cmid);
        $this->assertSame('active', $details['state']);
        $this->assertSame(2, $details['generation']);
        $this->assertSame($second['timeexpires'], $details['timeexpires']);
        $this->assertSame($second['token'], $details['token']);
        $this->assertNotSame($first['token'], $details['token']);

        invitation::disable($quiz->cmid, 2);
        $details = invitation::get_management_details($quiz->cmid);
        $this->assertSame('disabled', $details['state']);
        $this->assertSame(2, $details['generation']);
        $this->assertNull($details['token']);
    }

    /**
     * An invitation created before the upgrade remains valid until explicitly replaced.
     */
    public function test_legacy_invitation_requires_regeneration_for_recovery(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        // Simulate the nullable column added to a pre-upgrade invitation record.
        $DB->set_field('quizaccess_presencial_invite', 'tokenencrypted', null, ['quizid' => $quiz->id]);

        $details = invitation::get_management_details($quiz->cmid);
        $this->assertNull($details['token']);
        $this->assertTrue($details['copylegacy']);
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertSame(1, invitation::get_status($quiz->cmid)['generation']);
        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertSame($replacement['token'], invitation::get_management_details($quiz->cmid)['token']);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
    }

    /**
     * Invalid encrypted data cannot disclose or silently replace a valid invitation.
     */
    public function test_unreadable_encrypted_token_does_not_invalidate_invitation(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $hadsecretroot = isset($CFG->secretdataroot);
        $originalsecretroot = $CFG->secretdataroot ?? null;
        $newroot = make_temp_directory('quizaccess_presencial_wrongkey_' . bin2hex(random_bytes(8)));
        try {
            $CFG->secretdataroot = $newroot;
            \core\encryption::create_key();
            $sink = $this->redirectEvents();
            $details = invitation::get_management_details($quiz->cmid);
            $this->assertNull($details['token']);
            $this->assertSame('recoveryfailed', $details['copyreason']);
            $this->assertSame('active', $details['state']);
            $this->assertCount(0, $sink->get_events());
            $sink->close();
            $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        } finally {
            if ($hadsecretroot) {
                $CFG->secretdataroot = $originalsecretroot;
            } else {
                unset($CFG->secretdataroot);
            }
            remove_dir($newroot);
        }
        $this->assertSame($issued['token'], invitation::get_management_details($quiz->cmid)['token']);
    }

    /**
     * Reading an invitation without its server key must not create a replacement key.
     */
    public function test_missing_encryption_key_does_not_create_a_replacement(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $hadsecretroot = isset($CFG->secretdataroot);
        $originalsecretroot = $CFG->secretdataroot ?? null;
        $emptyroot = make_temp_directory('quizaccess_presencial_keytest_' . bin2hex(random_bytes(8)));
        try {
            $CFG->secretdataroot = $emptyroot;
            $this->assertFalse(\core\encryption::key_exists());
            $details = invitation::get_management_details($quiz->cmid);
            $this->assertNull($details['token']);
            $this->assertFalse($details['copylegacy']);
            $this->assertFalse(\core\encryption::key_exists());
            $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        } finally {
            if ($hadsecretroot) {
                $CFG->secretdataroot = $originalsecretroot;
            } else {
                unset($CFG->secretdataroot);
            }
            remove_dir($emptyroot);
        }
        $this->assertSame($issued['token'], invitation::get_management_details($quiz->cmid)['token']);
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
    }

    /**
     * A recoverable ciphertext must belong to the same invitation as the stored hash.
     */
    public function test_recovery_rejects_another_invitations_ciphertext(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $other = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        invitation::generate($other->cmid, 0);
        $ciphertext = $DB->get_field('quizaccess_presencial_invite', 'tokenencrypted', ['quizid' => $other->id], MUST_EXIST);
        $DB->set_field('quizaccess_presencial_invite', 'tokenencrypted', $ciphertext, ['quizid' => $quiz->id]);

        $this->assertNull(invitation::get_management_details($quiz->cmid)['token']);
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
    }

    /**
     * A generated invitation is valid only for its quiz and is not recoverable from status.
     */
    public function test_generation_scopes_secret_to_the_quiz_and_keeps_status_secret_free(): void {
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
        $this->assertStringNotContainsString($persisted->tokenencrypted, json_encode($events[0]->get_data()));
    }

    /**
     * The token is protected in storage and validation uses its one-way derivation.
     */
    public function test_secret_is_not_stored_in_plaintext(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();

        $first = invitation::generate($quiz->cmid, 0);
        $firstrow = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $quiz->id], '*', MUST_EXIST);
        $this->assert_secret_is_not_stored_in_plaintext($first['token'], $firstrow, $quiz->cmid);

        // Regeneration replaces the derivation; no trace of the previous one survives.
        $second = invitation::generate($quiz->cmid, 1);
        $secondrow = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $quiz->id], '*', MUST_EXIST);
        $this->assert_secret_is_not_stored_in_plaintext($second['token'], $secondrow, $quiz->cmid);
        $this->assertNotSame($firstrow->tokenhash, $secondrow->tokenhash);
        $this->assertStringNotContainsString($firstrow->tokenhash, json_encode($secondrow));
        $this->assertFalse(invitation::validate($quiz->cmid, $first['token']));
    }

    /**
     * Regeneration atomically replaces the secret and refuses stale management forms.
     */
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
        $this->assertSame($second['token'], invitation::get_management_details($quiz->cmid)['token']);
        $this->assertSame(2, invitation::get_status($quiz->cmid)['generation']);
        $transitions = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \quizaccess_presencial\event\invitation_updated
        ));
        $this->assertSame('regenerated', $transitions[1]->get_data()['other']['action']);
        try {
            invitation::generate($quiz->cmid, 1);
            $this->fail('A stale form must not replace the invitation.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invitationstale', $exception->errorcode);
        }
        $this->assertTrue(invitation::validate($quiz->cmid, $second['token']));
    }

    /**
     * Disabling is idempotent; only explicitly regenerating restores link access.
     */
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
        $transitions = array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof \quizaccess_presencial\event\invitation_updated
        );
        $this->assertCount(1, $transitions);
        $this->assertSame('disabled', reset($transitions)->get_data()['other']['action']);
        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
    }

    /**
     * Expected stale-form refusal does not roll back an enclosing Moodle transaction.
     */
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

    /**
     * Expiry at the exact boundary is synchronous and the scheduled task is idempotent.
     */
    public function test_expiry_is_immediate_and_scheduled_processing_is_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $clock = $this->mock_clock_with_frozen(strtotime('2030-01-01 09:00:00 UTC'));
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $sink = $this->redirectEvents();
        $this->assertSame($issued['token'], invitation::get_management_details($quiz->cmid)['token']);
        $clock->set_to($issued['timeexpires']);

        $this->assertNull(invitation::get_management_details($quiz->cmid)['token']);
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

    /**
     * Task processing expires an unused link, without a prior validation request.
     */
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

    /**
     * Shortening irreversibly caps expiry; extending the period requires a new secret.
     */
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
        $this->assertSame($issued['token'], invitation::get_management_details($quiz->cmid)['token']);
        $clock->set_to(strtotime('2030-01-01 15:00:00 UTC'));
        $this->assertNull(invitation::get_management_details($quiz->cmid)['token']);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertSame($quiz->presencial_timeclose, $replacement['timeexpires']);
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
    }

    /**
     * Extending an already ended period before cron runs cannot revive an old invitation.
     */
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

    /**
     * Re-enabling the rule preserves configuration but cannot restore an old invitation.
     */
    public function test_rule_disable_and_reenable_require_regeneration(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $issued = invitation::generate($quiz->cmid, 0);
        $quiz->presencial_enabled = 0;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertNull(invitation::get_management_details($quiz->cmid)['token']);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertFalse(invitation::get_status($quiz->cmid)['canissue']);
        $quiz->presencial_enabled = 1;
        \quizaccess_presencial::save_settings($quiz);

        $this->assertSame('disabled', invitation::get_status($quiz->cmid)['state']);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
    }

    /**
     * User-facing validation never returns data or records a submitted secret.
     */
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

    /**
     * Quiz management capability is checked in each module, not just the course.
     */
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
        $this->assertSame($issued['token'], invitation::get_management_details($quiz->cmid)['token']);

        foreach (
            [
            fn() => invitation::get_status($other->cmid),
            fn() => invitation::get_management_details($other->cmid)['token'],
            fn() => invitation::generate($other->cmid, 0),
            fn() => invitation::disable($other->cmid, 0),
            ] as $operation
        ) {
            try {
                $operation();
                $this->fail('Management requires permission in the target quiz.');
            } catch (\required_capability_exception $exception) {
                $this->assertSame('nopermissions', $exception->errorcode);
            }
        }
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
    }

    /**
     * Concurrent first-generation requests yield one usable token and one stale-form rejection.
     */
    public function test_concurrent_generation_preserves_one_current_invitation(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open(
                    $this->worker_command($quiz),
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes
                );
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

    /**
     * A waiting management read returns the committed generation and its matching token.
     */
    public function test_concurrent_management_details_match_the_committed_generation(): void {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $quiz = $this->configured_quiz();
        $first = invitation::generate($quiz->cmid, 0);
        $process = proc_open(
            $this->worker_command($quiz, 'details'),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        $transaction = null;
        try {
            stream_set_timeout($pipes[1], 30);
            $this->assertSame("READY\n", fgets($pipes[1]));
            $transaction = $DB->start_delegated_transaction();
            $second = invitation::generate($quiz->cmid, 1);
            fwrite($pipes[0], "GO\n");
            fflush($pipes[0]);
            $this->assertSame("ATTEMPTING\n", fgets($pipes[1]));

            // The independent request must wait for the outer commit, not return the old generation.
            $read = [$pipes[1]];
            $write = $except = [];
            $this->assertSame(0, stream_select($read, $write, $except, 1));
            $this->assertSame(2, invitation::get_status($quiz->cmid)['generation']);
            $this->assertTrue(invitation::validate($quiz->cmid, $second['token']));
            $this->assertFalse(invitation::validate($quiz->cmid, $first['token']));
            $transaction->allow_commit();
            $transaction = null;

            $result = json_decode(stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('active', $result['state']);
            $this->assertSame(2, $result['generation']);
            $this->assertSame($second['timeexpires'], $result['timeexpires']);
            $this->assertSame($second['token'], $result['token']);
            $this->assertSame('', stream_get_contents($pipes[2]));
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
     * Deleting quiz plugin settings also invalidates its current invitation.
     */
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
     * Assert that the persisted invitation does not contain the plaintext secret and retains the validation hash.
     *
     * @param string $token Complete invitation secret.
     * @param \stdClass $row Persisted invitation record.
     * @param int $cmid Quiz course module.
     */
    private function assert_secret_is_not_stored_in_plaintext(string $token, \stdClass $row, int $cmid): void {
        $this->assertSame(1, preg_match('/\Ai1_([0-9a-f]{64})\z/', $token, $matches));
        $body = $matches[1];

        // Neither the complete secret nor its hexadecimal body is stored in any column, in any letter case.
        foreach ((array) $row as $column => $value) {
            $this->assertStringNotContainsStringIgnoringCase($body, (string) $value, "Column {$column} holds the secret.");
        }
        // The stored value is neither the secret nor a trivial, reversible rewriting of it.
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $row->tokenhash);
        foreach ([$token, $body, strrev($body)] as $trivial) {
            $this->assertNotSame($trivial, $row->tokenhash);
        }
        // It is exactly the expected one-way derivation of this secret.
        $this->assertSame($this->expected_derivation($token), $row->tokenhash);
        // Knowing what is persisted does not grant access, in any plausible form of presenting it.
        $this->assertFalse(invitation::validate($cmid, $row->tokenhash));
        $this->assertFalse(invitation::validate($cmid, 'i1_' . $row->tokenhash));
        // The secret itself is still accepted, so validation uses the derivation.
        $this->assertTrue(invitation::validate($cmid, $token));
    }

    /**
     * Reproduce the persisted-format contract: SHA-256 over the secret, bound to its purpose and format version.
     *
     * Changing this on purpose requires an upgrade step that invalidates the links already issued, and this test.
     *
     * @param string $token Complete invitation secret.
     * @return string Expected persisted derivation.
     */
    private function expected_derivation(string $token): string {
        return hash('sha256', 'quizaccess_presencial:invite:v1:' . $token);
    }

    /**
     * Bootstrap an independent Moodle PHPUnit connection before loading the internal worker fixture.
     *
     * @param \stdClass $quiz Configured quiz.
     * @param string $mode Public invitation operation to exercise.
     * @return array Command arguments for the child PHP process.
     */
    private function worker_command(\stdClass $quiz, string $mode = 'generate'): array {
        global $CFG, $USER;

        $bootstrap = <<<'PHP'
        $autoload = $argv[1] . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            // Moodle 5.1 keeps Composer outside its public document root.
            $autoload = dirname($argv[1]) . '/vendor/autoload.php';
        }
        require($autoload);
        // Select the isolated PHPUnit database without resetting the parent's data.
        define('PHPUNIT_UTIL', true);
        require($argv[1] . '/lib/phpunit/bootstrap.php');
        require($argv[1] . '/mod/quiz/accessrule/presencial/tests/fixtures/invitation_worker.php');
        PHP;

        return [PHP_BINARY, '-d', 'max_input_vars=5000', '-r', $bootstrap, $CFG->dirroot, $quiz->cmid, $USER->id, $mode];
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
