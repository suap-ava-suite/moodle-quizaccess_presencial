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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use quizaccess_presencial\local\delegation_manager;
use quizaccess_presencial\local\invitation;
use quizaccess_presencial\local\release_request_service;
use quizaccess_presencial\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

#[\PHPUnit\Framework\Attributes\CoversClass(delegation_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(invitation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
/**
 * Interaction between the application team and the invitation of one quiz.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\delegation_manager
 * @covers     \quizaccess_presencial\local\invitation
 * @covers     \quizaccess_presencial\privacy\provider
 */
final class delegation_invitation_integration_test extends provider_testcase {
    /**
     * A failure in the enclosing Moodle transaction restores every kind of erased personal data.
     *
     * @dataProvider erasure_scopes
     * @param string $scope Approved erasure scope.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('erasure_scopes')]
    public function test_erasure_rollback_restores_personal_data_and_invitation(string $scope): void {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$quiz, $teacher, $revoked] = $this->create_quiz_with_team_and_invitation($issued);
        $context = \context_module::instance($quiz->cmid);
        delegation_manager::include_users($quiz->id, [$teacher->id], $teacher->id);
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $teacher->id, 1);
        $team = delegation_manager::list_current($quiz->id);
        $invitation = invitation::get_status($quiz->cmid);
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        $manager = get_log_manager(true);
        $store = $manager->get_readers()['logstore_standard'];
        $eventfilter = 'component = :component AND contextid = :contextid';
        $eventparams = ['component' => 'quizaccess_presencial', 'contextid' => $context->id];
        $events = $store->get_events_select($eventfilter, $eventparams, 'id ASC', 0, 0);
        $transaction = null;
        try {
            $transaction = $DB->start_delegated_transaction();
            if ($scope === 'context') {
                provider::delete_data_for_all_users_in_context($context);
            } else if ($scope === 'batch') {
                provider::delete_data_for_users(new approved_userlist($context, 'quizaccess_presencial', [$teacher->id]));
            } else {
                provider::delete_data_for_user(new approved_contextlist($teacher, 'quizaccess_presencial', [$context->id]));
            }
            $this->assertEmpty(provider::get_contexts_for_userid($teacher->id)->get_contextids());
            $this->assertSame('disabled', invitation::get_status($quiz->cmid)['state']);
            $failure = new \moodle_exception('invalidrequest');
            try {
                $transaction->rollback($failure);
            } catch (\moodle_exception $exception) {
                $this->assertSame($failure, $exception);
            }
            $transaction = null;

            $this->assertEquals($team, delegation_manager::list_current($quiz->id));
            $this->assertSame($invitation, invitation::get_status($quiz->cmid));
            $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
            $this->assertEquals($request, $service->get($request->id));
            $this->assertEquals([$context->id], provider::get_contexts_for_userid($teacher->id)->get_contextids());
            writer::reset();
            provider::export_user_data(new approved_contextlist($revoked, 'quizaccess_presencial', [$context->id]));
            $path = [get_string('pluginname', 'quizaccess_presencial'),
                get_string('privacy:exportpath:delegation', 'quizaccess_presencial'), 1];
            $this->assertEquals($teacher->id, writer::with_context($context)->get_data($path)->revokedby);
            $this->assertEquals($events, $store->get_events_select($eventfilter, $eventparams, 'id ASC', 0, 0),
                'Rolled-back erasure must not publish an invitation invalidation.');
        } finally {
            if ($transaction) {
                try {
                    $transaction->rollback(new \moodle_exception('invalidrequest'));
                } catch (\moodle_exception $ignored) {
                    // Release the enclosing transaction without masking an earlier assertion failure.
                }
            }
            $manager->dispose();
            get_log_manager(true);
        }
    }

    /**
     * Supply the supported Privacy API erasure scopes.
     *
     * @return array Erasure scopes.
     */
    public static function erasure_scopes(): array {
        return ['individual' => ['individual'], 'batch' => ['batch'], 'context' => ['context']];
    }

    /**
     * Disabling or regenerating the invitation never revokes or changes a delegation.
     */
    public function test_invitation_lifecycle_leaves_delegations_untouched(): void {
        $this->resetAfterTest();
        $quiz = $this->create_configured_quiz();
        $applicator = self::getDataGenerator()->create_user();
        $included = delegation_manager::include_users($quiz->id, [$applicator->id], $GLOBALS['USER']->id);
        invitation::generate($quiz->cmid, 0);

        invitation::disable($quiz->cmid, 1);
        $this->assertSame('disabled', invitation::get_status($quiz->cmid)['state']);
        $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $applicator->id));

        invitation::generate($quiz->cmid, 1);
        $current = delegation_manager::list_current($quiz->id);
        $this->assertCount(1, $current);
        $this->assertSame((int) $included[$applicator->id]->id, (int) reset($current)->id);
        $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
    }

    /**
     * Including and revoking applicators does not alter the current invitation.
     */
    public function test_delegation_changes_leave_the_invitation_untouched(): void {
        $this->resetAfterTest();
        $quiz = $this->create_configured_quiz();
        $applicator = self::getDataGenerator()->create_user();
        $issued = invitation::generate($quiz->cmid, 0);
        $before = invitation::get_status($quiz->cmid);

        $included = delegation_manager::include_users($quiz->id, [$applicator->id], $GLOBALS['USER']->id);
        $this->assertSame($before, invitation::get_status($quiz->cmid));
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));

        $this->assertTrue(delegation_manager::revoke($quiz->id, $included[$applicator->id]->id, $GLOBALS['USER']->id));
        $this->assertSame($before, invitation::get_status($quiz->cmid));
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
    }

    /**
     * Disabling the rule ends the invitation for good but only suspends the same period's delegations.
     */
    public function test_disabling_and_reenabling_the_rule_resumes_delegations_but_not_the_invitation(): void {
        $this->resetAfterTest();
        $quiz = $this->create_configured_quiz();
        $applicator = self::getDataGenerator()->create_user();
        $included = delegation_manager::include_users($quiz->id, [$applicator->id], $GLOBALS['USER']->id);
        $issued = invitation::generate($quiz->cmid, 0);

        $quiz->presencial_enabled = 0;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));

        $quiz->presencial_enabled = 1;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
        $current = delegation_manager::list_current($quiz->id);
        $this->assertSame((int) $included[$applicator->id]->id, (int) reset($current)->id);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertSame('disabled', invitation::get_status($quiz->cmid)['state']);

        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
    }

    /**
     * Privacy discovery and export cover both kinds of data once per quiz and never export the secret.
     */
    public function test_privacy_discovers_and_exports_delegations_and_the_invitation_together(): void {
        $this->resetAfterTest();
        [$quiz, $teacher, $revoked] = $this->create_quiz_with_team_and_invitation($issued);
        $context = \context_module::instance($quiz->cmid);

        $this->assertEqualsCanonicalizing([$context->id], provider::get_contexts_for_userid($teacher->id)->get_contextids());
        $this->assertEqualsCanonicalizing([$context->id], provider::get_contexts_for_userid($revoked->id)->get_contextids());
        $users = new userlist($context, 'quizaccess_presencial');
        provider::get_users_in_context($users);
        $this->assertCount(3, $users->get_userids());
        $this->assertContainsEquals($teacher->id, $users->get_userids());
        $this->assertContainsEquals($revoked->id, $users->get_userids());

        provider::export_user_data(new approved_contextlist($teacher, 'quizaccess_presencial', [$context->id]));
        $writer = writer::with_context($context);
        $invitationdata = $writer->get_data([get_string('pluginname', 'quizaccess_presencial')]);
        $this->assertSame('active', $invitationdata->state);
        $this->assertSame(1, $invitationdata->generation);
        $path = [get_string('pluginname', 'quizaccess_presencial'),
            get_string('privacy:exportpath:delegation', 'quizaccess_presencial')];
        $delegationdata = $writer->get_data([...$path, 1]);
        $this->assertSame((int) $revoked->id, (int) $delegationdata->userid);
        $this->assertSame((int) $teacher->id, (int) $delegationdata->revokedby);
        $this->assertSame([], (array) $writer->get_data([...$path, 2]));
        $this->assertStringNotContainsString($issued['token'], json_encode([$invitationdata, $delegationdata]));
    }

    /**
     * Erasing one user removes that user's delegations and anonymizes their invitation, not other users' data.
     */
    public function test_individual_erasure_handles_delegations_and_the_invitation_together(): void {
        $this->resetAfterTest();
        [$quiz, $teacher, $revoked, $current] = $this->create_quiz_with_team_and_invitation($issued);
        $context = \context_module::instance($quiz->cmid);

        provider::delete_data_for_user(new approved_contextlist($teacher, 'quizaccess_presencial', [$context->id]));

        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $status = invitation::get_status($quiz->cmid);
        $this->assertSame('disabled', $status['state']);
        $this->assertSame(1, $status['generation']);
        $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $current->id));
        $this->assertEmpty(provider::get_contexts_for_userid($teacher->id)->get_contextids());
        provider::export_user_data(new approved_contextlist($revoked, 'quizaccess_presencial', [$context->id]));
        $path = [get_string('pluginname', 'quizaccess_presencial'),
            get_string('privacy:exportpath:delegation', 'quizaccess_presencial'), 1];
        $this->assertSame(0, (int) writer::with_context($context)->get_data($path)->revokedby);

        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertSame(2, $replacement['generation']);
        try {
            invitation::disable($quiz->cmid, 1);
            $this->fail('A form rendered before the erasure must not alter the replacement invitation.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invitationstale', $exception->errorcode);
        }
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
    }

    /**
     * Batch and context erasure remove delegations and invalidate the invitation without losing the generation.
     */
    public function test_batch_and_context_erasure_handle_delegations_and_the_invitation_together(): void {
        $this->resetAfterTest();
        [$quiz, $teacher, $revoked, $current] = $this->create_quiz_with_team_and_invitation($issued);
        $context = \context_module::instance($quiz->cmid);

        provider::delete_data_for_users(new approved_userlist($context, 'quizaccess_presencial', [$current->id]));
        $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $current->id));
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));

        provider::delete_data_for_all_users_in_context($context);
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $status = invitation::get_status($quiz->cmid);
        $this->assertSame('disabled', $status['state']);
        $this->assertSame(1, $status['generation']);
        $this->assertTrue($status['canissue']);
        $this->assertEmpty(provider::get_contexts_for_userid($teacher->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid($revoked->id)->get_contextids());
    }

    /**
     * Deleting the quiz removes the delegations and the invitation together with the configuration.
     */
    public function test_deleting_the_quiz_removes_delegations_and_the_invitation(): void {
        global $DB;

        $this->resetAfterTest();
        $quiz = $this->create_configured_quiz();
        $applicator = self::getDataGenerator()->create_user();
        delegation_manager::include_users($quiz->id, [$applicator->id], $GLOBALS['USER']->id);
        invitation::generate($quiz->cmid, 0);

        course_delete_module($quiz->cmid);

        $this->assertFalse($DB->record_exists('quizaccess_presencial', ['quizid' => $quiz->id]));
        $this->assertFalse($DB->record_exists('quizaccess_presencial_delegation', ['quizid' => $quiz->id]));
        $this->assertFalse($DB->record_exists('quizaccess_presencial_invite', ['quizid' => $quiz->id]));
    }

    /**
     * Create a quiz whose teacher generated the invitation, revoked one applicator and kept another.
     *
     * @param array|null $issued Receives the issued invitation, including the secret shown once.
     * @return array The quiz, the teacher, the revoked applicator and the current applicator.
     */
    private function create_quiz_with_team_and_invitation(?array &$issued): array {
        $quiz = $this->create_configured_quiz();
        $teacher = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');
        $revoked = self::getDataGenerator()->create_user();
        $current = self::getDataGenerator()->create_user();

        $this->setUser($teacher);
        $issued = invitation::generate($quiz->cmid, 0);
        $included = delegation_manager::include_users($quiz->id, [$revoked->id], $teacher->id);
        delegation_manager::revoke($quiz->id, $included[$revoked->id]->id, $teacher->id);
        delegation_manager::include_users($quiz->id, [$current->id], $teacher->id);
        return [$quiz, $teacher, $revoked, $current];
    }

    /**
     * Create a quiz with an enabled authorization period, as the site administrator.
     *
     * @return \stdClass Quiz record.
     */
    private function create_configured_quiz(): \stdClass {
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'timeopen' => time() - HOURSECS,
            'timeclose' => time() + (4 * HOURSECS),
        ]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = time() - HOURSECS;
        $quiz->presencial_timeclose = time() + (2 * HOURSECS);
        \quizaccess_presencial::save_settings($quiz);
        return $quiz;
    }
}
