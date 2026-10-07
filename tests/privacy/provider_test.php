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

namespace quizaccess_presencial\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use quizaccess_presencial\local\invitation;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

#[\PHPUnit\Framework\Attributes\Group('quizaccess_presencial')]
#[\PHPUnit\Framework\Attributes\Group('core_privacy')]
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
/**
 * Privacy API behaviour for the current invitation.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      quizaccess_presencial
 * @group      core_privacy
 * @covers     \quizaccess_presencial\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Only the current invitation creator is discoverable in the quiz module.
     */
    public function test_discovery_tracks_only_the_current_creator_in_quiz_context(): void {
        $this->resetAfterTest();
        $creator = self::getDataGenerator()->create_user();
        $replacement = self::getDataGenerator()->create_user();
        $quiz = $this->configured_quiz([$creator, $replacement]);
        $context = \context_module::instance($quiz->cmid);
        $this->setUser($creator);
        invitation::generate($quiz->cmid, 0);

        $this->assertEquals([$context->id], provider::get_contexts_for_userid($creator->id)->get_contextids());
        $users = new userlist($context, 'quizaccess_presencial');
        provider::get_users_in_context($users);
        $this->assertEquals([$creator->id], $users->get_userids());
        $courseusers = new userlist(\context_course::instance($quiz->course), 'quizaccess_presencial');
        provider::get_users_in_context($courseusers);
        $this->assertEmpty($courseusers->get_userids());

        $this->setUser($replacement);
        invitation::generate($quiz->cmid, 1);

        $this->assertEmpty(provider::get_contexts_for_userid($creator->id)->get_contextids());
        $this->assertEquals([$context->id], provider::get_contexts_for_userid($replacement->id)->get_contextids());
        $users = new userlist($context, 'quizaccess_presencial');
        provider::get_users_in_context($users);
        $this->assertEquals([$replacement->id], $users->get_userids());
        $this->assertEmpty(provider::get_contexts_for_userid(0)->get_contextids());
    }

    /**
     * Export contains only lifecycle data and honours both the owner and approved quiz context.
     */
    public function test_export_excludes_secrets_and_respects_approved_user_and_contexts(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1800000000);
        $creator = self::getDataGenerator()->create_user();
        $other = self::getDataGenerator()->create_user();
        $quiz = $this->configured_quiz([$creator]);
        $unapproved = $this->configured_quiz([$creator]);
        $context = \context_module::instance($quiz->cmid);
        $othercontext = \context_module::instance($unapproved->cmid);
        $this->setUser($creator);
        $issued = invitation::generate($quiz->cmid, 0);
        invitation::generate($unapproved->cmid, 0);

        provider::export_user_data(new approved_contextlist($creator, 'quizaccess_presencial', [$context->id]));
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'quizaccess_presencial')]);
        $this->assertEqualsCanonicalizing([
            'state', 'generation', 'timecreated', 'timemodified', 'timeexpires',
        ], array_keys((array) $data));
        $this->assertSame('active', $data->state);
        $this->assertSame(1, $data->generation);
        $this->assertSame(transform::datetime(1800000000), $data->timecreated);
        $this->assertSame(transform::datetime(1800000000), $data->timemodified);
        $this->assertSame(transform::datetime($issued['timeexpires']), $data->timeexpires);
        $this->assertStringNotContainsString($issued['token'], json_encode($data));
        $this->assertFalse(writer::with_context($othercontext)->has_any_data());

        writer::reset();
        provider::export_user_data(new approved_contextlist($other, 'quizaccess_presencial', [$context->id]));
        $this->assertFalse(writer::with_context($context)->has_any_data());
        $coursecontext = \context_course::instance($quiz->course);
        provider::export_user_data(new approved_contextlist($creator, 'quizaccess_presencial', [$coursecontext->id]));
        $this->assertFalse(writer::with_context($coursecontext)->has_any_data());
    }

    /**
     * Erasing one creator disables only their approved invitation and preserves its generation.
     */
    public function test_individual_erasure_invalidates_token_and_preserves_generation_and_configuration(): void {
        $this->resetAfterTest();
        $creator = self::getDataGenerator()->create_user();
        $quiz = $this->configured_quiz([$creator]);
        $other = $this->configured_quiz([$creator]);
        $context = \context_module::instance($quiz->cmid);
        $othercontext = \context_module::instance($other->cmid);
        $this->setUser($creator);
        $issued = invitation::generate($quiz->cmid, 0);
        $otherissued = invitation::generate($other->cmid, 0);

        provider::delete_data_for_user(new approved_contextlist($creator, 'quizaccess_presencial', [$context->id]));

        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertTrue(invitation::validate($other->cmid, $otherissued['token']));
        $status = invitation::get_status($quiz->cmid);
        $this->assertSame('disabled', $status['state']);
        $this->assertSame(1, $status['generation']);
        $this->assertSame(0, $status['timeexpires']);
        $this->assertTrue($status['canissue']);
        $this->assertEquals([$othercontext->id], provider::get_contexts_for_userid($creator->id)->get_contextids());
        $users = new userlist($context, 'quizaccess_presencial');
        provider::get_users_in_context($users);
        $this->assertEmpty($users->get_userids());
        $this->export_context_data_for_user($creator->id, $context, 'quizaccess_presencial');
        $this->assertFalse(writer::with_context($context)->has_any_data());

        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertSame(2, $replacement['generation']);
        $this->assertSame($issued['timeexpires'], $replacement['timeexpires']);
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invitationstale', 'quizaccess_presencial'));
        invitation::disable($quiz->cmid, 1);
    }

    /**
     * Batch erasure checks the current owner, approved users, and the exact module context.
     */
    public function test_batch_erasure_respects_current_creator_and_approved_context(): void {
        $this->resetAfterTest();
        $creator = self::getDataGenerator()->create_user();
        $replacement = self::getDataGenerator()->create_user();
        $quiz = $this->configured_quiz([$creator, $replacement]);
        $other = $this->configured_quiz([$creator]);
        $context = \context_module::instance($quiz->cmid);
        $this->setUser($creator);
        invitation::generate($quiz->cmid, 0);
        $otherissued = invitation::generate($other->cmid, 0);
        $this->setUser($replacement);
        $issued = invitation::generate($quiz->cmid, 1);

        provider::delete_data_for_users(new approved_userlist($context, 'quizaccess_presencial', [$creator->id]));
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        provider::delete_data_for_users(new approved_userlist($context, 'quizaccess_presencial', []));
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        provider::delete_data_for_user(new approved_contextlist($creator, 'quizaccess_presencial', [$context->id]));
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        provider::delete_data_for_users(new approved_userlist(
            \context_course::instance($quiz->course),
            'quizaccess_presencial',
            [$replacement->id]
        ));
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));

        provider::delete_data_for_users(new approved_userlist($context, 'quizaccess_presencial', [
            $creator->id, $replacement->id,
        ]));

        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertTrue(invitation::validate($other->cmid, $otherissued['token']));
        $this->assertEmpty(provider::get_contexts_for_userid($replacement->id)->get_contextids());
        $users = new userlist($context, 'quizaccess_presencial');
        provider::get_users_in_context($users);
        $this->assertEmpty($users->get_userids());
        $status = invitation::get_status($quiz->cmid);
        $this->assertSame('disabled', $status['state']);
        $this->assertSame(2, $status['generation']);
        $this->assertSame(0, $status['timeexpires']);
        $new = invitation::generate($quiz->cmid, 2);
        $this->assertSame(3, $new['generation']);
        $this->assertSame($issued['timeexpires'], $new['timeexpires']);
    }

    /**
     * Context erasure removes only the quiz invitation and retains the public quiz configuration.
     */
    public function test_context_erasure_ignores_other_contexts_and_preserves_quiz_configuration(): void {
        $this->resetAfterTest();
        $creator = self::getDataGenerator()->create_user();
        $quiz = $this->configured_quiz([$creator]);
        $other = $this->configured_quiz([$creator]);
        $forum = self::getDataGenerator()->create_module('forum', ['course' => $quiz->course]);
        $context = \context_module::instance($quiz->cmid);
        $othercontext = \context_module::instance($other->cmid);
        $this->setUser($creator);
        $issued = invitation::generate($quiz->cmid, 0);
        $otherissued = invitation::generate($other->cmid, 0);

        foreach (
            [
            \context_system::instance(),
            \context_course::instance($quiz->course),
            \context_module::instance($forum->cmid),
            ] as $unrelated
        ) {
            $users = new userlist($unrelated, 'quizaccess_presencial');
            provider::get_users_in_context($users);
            $this->assertEmpty($users->get_userids());
            provider::export_user_data(new approved_contextlist($creator, 'quizaccess_presencial', [$unrelated->id]));
            $this->assertFalse(writer::with_context($unrelated)->has_any_data());
            provider::delete_data_for_user(new approved_contextlist($creator, 'quizaccess_presencial', [$unrelated->id]));
            provider::delete_data_for_users(new approved_userlist($unrelated, 'quizaccess_presencial', [$creator->id]));
            provider::delete_data_for_all_users_in_context($unrelated);
            $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        }

        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertTrue(invitation::validate($other->cmid, $otherissued['token']));
        $this->assertEquals([$othercontext->id], provider::get_contexts_for_userid($creator->id)->get_contextids());
        $this->assertSame(1, invitation::get_status($quiz->cmid)['generation']);
        $this->export_context_data_for_user($creator->id, $context, 'quizaccess_presencial');
        $this->assertFalse(writer::with_context($context)->has_any_data());
        $replacement = invitation::generate($quiz->cmid, 1);
        $this->assertSame(2, $replacement['generation']);
        $this->assertSame($issued['timeexpires'], $replacement['timeexpires']);
        $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
    }

    /**
     * Context erasure cannot make a pre-erasure form target the replacement invitation.
     */
    public function test_context_erasure_rejects_old_disable_and_regenerate_forms(): void {
        $this->resetAfterTest();
        $creator = self::getDataGenerator()->create_user();
        $quiz = $this->configured_quiz([$creator]);
        $context = \context_module::instance($quiz->cmid);
        $this->setUser($creator);
        $first = invitation::generate($quiz->cmid, 0);

        provider::delete_data_for_all_users_in_context($context);
        $erased = invitation::get_status($quiz->cmid);
        $replacement = invitation::generate($quiz->cmid, $erased['generation']);
        $current = invitation::get_status($quiz->cmid);

        foreach (['disable', 'generate'] as $action) {
            try {
                invitation::$action($quiz->cmid, $first['generation']);
                $this->fail('A pre-erasure form must not alter the replacement invitation.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('invitationstale', $exception->errorcode);
            }
            $this->assertSame($current, invitation::get_status($quiz->cmid));
            $this->assertTrue(invitation::validate($quiz->cmid, $replacement['token']));
            $this->assertFalse(invitation::validate($quiz->cmid, $first['token']));
        }
    }

    /**
     * Privacy audits a live invalidation once and does not re-audit an already invalid invitation.
     *
     * @dataProvider erasure_provider
     * @param string $scope Approved erasure scope.
     * @param string $state Initial invitation state.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('erasure_provider')]
    public function test_erasure_audits_only_live_invitations(string $scope, string $state): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(strtotime('2030-01-01 09:00:00 UTC'));
        $creator = self::getDataGenerator()->create_user();
        $quiz = $this->configured_quiz([$creator]);
        $context = \context_module::instance($quiz->cmid);
        $this->setUser($creator);
        $issued = invitation::generate($quiz->cmid, 0);
        $hash = $DB->get_field('quizaccess_presencial_invite', 'tokenhash', ['quizid' => $quiz->id], MUST_EXIST);
        if ($state === 'disabled') {
            invitation::disable($quiz->cmid, 1);
        } else if ($state === 'expired' || $state === 'due') {
            $clock->set_to($issued['timeexpires']);
            if ($state === 'expired') {
                (new \quizaccess_presencial\task\expire_invitations())->execute();
            }
        }
        $this->setAdminUser();
        $sink = $this->redirectEvents();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if ($scope === 'context') {
                provider::delete_data_for_all_users_in_context($context);
            } else if ($scope === 'batch') {
                provider::delete_data_for_users(new approved_userlist($context, 'quizaccess_presencial', [$creator->id]));
            } else {
                provider::delete_data_for_user(new approved_contextlist($creator, 'quizaccess_presencial', [$context->id]));
            }
        }

        $events = $sink->get_events();
        $this->assertCount(in_array($state, ['active', 'due'], true) ? 1 : 0, $events);
        if ($state === 'active' || $state === 'due') {
            $this->assertInstanceOf(\quizaccess_presencial\event\invitation_updated::class, $events[0]);
            $data = $events[0]->get_data();
            $this->assertSame($state === 'active' ? 'disabled' : 'expired', $data['other']['action']);
            $this->assertEquals($context->id, $data['contextid']);
            $this->assertEquals($quiz->id, $data['objectid']);
            $this->assertEquals($USER->id, $data['userid']);
            $this->assertEqualsCanonicalizing(['action', 'generation', 'timeexpires'], array_keys($data['other']));
            $this->assertStringNotContainsString($issued['token'], json_encode($data));
            $this->assertStringNotContainsString($hash, json_encode($data));
            $this->assertStringNotContainsString('token=', json_encode($data));
        }
        $this->assertEmpty(provider::get_contexts_for_userid($creator->id)->get_contextids());
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
    }

    /**
     * Erasure scopes, materialized states, and an expiration not yet processed by the task.
     * @return array
     */
    public static function erasure_provider(): array {
        $cases = [];
        foreach (['individual', 'batch', 'context'] as $scope) {
            foreach (['active', 'disabled', 'expired', 'due'] as $state) {
                $cases[$scope . ' ' . $state] = [$scope, $state];
            }
        }
        return $cases;
    }

    /**
     * Create and configure a quiz through public Moodle operations.
     *
     * @param array $teachers Users allowed to manage the quiz.
     * @return \stdClass Configured quiz.
     */
    private function configured_quiz(array $teachers = []): \stdClass {
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'timeopen' => strtotime('2030-01-01 08:00:00 UTC'),
            'timeclose' => strtotime('2030-01-01 22:00:00 UTC'),
        ]);
        foreach ($teachers as $teacher) {
            self::getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        }
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = strtotime('2030-01-01 10:00:00 UTC');
        $quiz->presencial_timeclose = strtotime('2030-01-01 20:00:00 UTC');
        \quizaccess_presencial::save_settings($quiz);
        return $quiz;
    }
}
