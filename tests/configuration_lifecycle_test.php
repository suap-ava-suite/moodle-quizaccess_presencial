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

use quizaccess_presencial\local\applications;
use quizaccess_presencial\local\delegation_manager;
use quizaccess_presencial\local\invitation;
use quizaccess_presencial\local\release_request;
use quizaccess_presencial\local\release_request_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\quizaccess_presencial::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(delegation_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(release_request_service::class)]
/**
 * Configuration transitions through the public Moodle integration boundaries.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial
 * @covers     \quizaccess_presencial\local\delegation_manager
 * @covers     \quizaccess_presencial\local\release_request_service
 */
final class configuration_lifecycle_test extends \advanced_testcase {
    /**
     * A shortened period preserves access until the new end and never extends an old grant.
     */
    public function test_shortening_preserves_access_until_the_new_end(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        $original = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $quiz->presencial_timeclose = 1_800_000_120;
        \quizaccess_presencial::save_settings($quiz);

        $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $user->id));
        $this->setUser($user);
        $this->assertTrue(applications::get_application($quiz->cmid)['canoperate']);
        $this->setAdminUser();
        $current = delegation_manager::list_current($quiz->id);
        $this->assertEquals($original[$user->id]->id, reset($current)->id);
        $clock->bump(120);
        $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $user->id));
        $quiz->presencial_timeclose = 1_800_003_600;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
        $this->setUser($user);
        $this->assertFalse(applications::has_delegations());
    }

    /**
     * Disablement suspends valid current or future delegations and never restores a revoked one.
     *
     * @dataProvider delegation_states
     * @param bool $future Whether the period has not started.
     * @param bool $revoked Whether the delegation was revoked before disablement.
     * @param bool $elapsed Whether its deadline passes while disabled.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('delegation_states')]
    public function test_reenable_recovers_only_valid_delegations(bool $future, bool $revoked, bool $elapsed): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        if ($future) {
            $quiz->presencial_timeopen = 1_800_000_600;
            \quizaccess_presencial::save_settings($quiz);
        }
        $user = self::getDataGenerator()->create_user();
        $delegations = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $id = $delegations[$user->id]->id;
        if ($revoked) {
            delegation_manager::revoke($quiz->id, $id, $GLOBALS['USER']->id);
        }
        $sink = $this->redirectEvents();
        $quiz->presencial_enabled = 0;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
        $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $user->id));
        $this->setUser($user);
        $this->assertFalse(applications::has_delegations());
        $this->assertEmpty(applications::get_page()['items']);
        $this->setAdminUser();
        if ($elapsed) {
            $clock->bump(3600);
        }
        $quiz->presencial_enabled = 1;
        \quizaccess_presencial::save_settings($quiz);
        $current = delegation_manager::list_current($quiz->id);
        $valid = !$revoked && !$elapsed;
        $this->assertCount($valid ? 1 : 0, $current);
        if ($valid) {
            $this->assertEquals($id, reset($current)->id);
        }
        $this->setUser($user);
        $this->assertSame($valid, applications::has_delegations());
        $this->assertSame($valid && !$future, delegation_manager::is_active_applicator($quiz->id, $user->id));
        $suspended = $this->delegation_events($sink->get_events(), 'suspended');
        $resumed = $this->delegation_events($sink->get_events(), 'resumed');
        $this->assertCount($revoked ? 0 : 1, $suspended);
        $this->assertCount($valid ? 1 : 0, $resumed);
    }

    /**
     * Supply every combination of delegation validity and suspension.
     *
     * @return array All combinations of delegation validity and configuration suspension.
     */
    public static function delegation_states(): array {
        $cases = [];
        foreach ([false, true] as $future) {
            foreach ([false, true] as $revoked) {
                foreach ([false, true] as $elapsed) {
                    $cases[] = [$future, $revoked, $elapsed];
                }
            }
        }
        return $cases;
    }

    /**
     * New periods retire the old team and link, even if the old dates are restored.
     */
    public function test_new_future_period_requires_new_delegations_and_invitation(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        $issued = invitation::generate($quiz->cmid, 0);
        $this->setUser($user);
        $original = invitation::accept($quiz->cmid, $issued['token']);
        $this->setAdminUser();
        $student = self::getDataGenerator()->create_user();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $quiz->presencial_timeopen = 1_800_000_600;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertSame(release_request::STATE_EXPIRED, $service->get($request->id)->state);
        $quiz->presencial_timeopen = 1_799_999_000;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
        $replacement = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $this->assertNotEquals($original['delegation']->id, $replacement[$user->id]->id);
    }

    /**
     * Status validation and scheduled maintenance emit one expiration per delegation.
     *
     * @dataProvider expiration_paths
     * @param bool $taskfirst Whether cron processes the expiration before an access probe.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('expiration_paths')]
    public function test_delegation_expiration_is_audited_once(bool $taskfirst): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $sink = $this->redirectEvents();
        $clock->bump(3600);
        $task = new \quizaccess_presencial\task\expire_delegations();
        if ($taskfirst) {
            $task->execute();
        }
        $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $user->id));
        $task->execute();
        $task->execute();
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
        $this->assertCount(1, $this->delegation_events($sink->get_events(), 'expired'));
    }

    /**
     * Supply scheduled and synchronous expiration orderings.
     *
     * @return array Scheduled and synchronous expiration orderings.
     */
    public static function expiration_paths(): array {
        return ['validation then cron' => [false], 'cron then validation' => [true]];
    }

    /**
     * Disablement irreversibly ends each revisable request state and blocks every mutation.
     *
     * @dataProvider revisable_states
     * @param string $state Decision before disablement.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('revisable_states')]
    public function test_disablement_ends_requests_and_blocks_new_operations(string $state): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $user->id, 1);
        if ($state === 'authorized') {
            $service->authorize($request->id);
        } else if ($state === 'rejected') {
            $service->reject($request->id, 'Identity not confirmed.');
        }
        $issued = invitation::generate($quiz->cmid, 0);
        $sink = $this->redirectEvents();
        $quiz->presencial_enabled = 0;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertSame(release_request::STATE_EXPIRED, $service->read_status($request->id, $user->id)->state);
        $this->assertFalse($service->begin_authorized_attempt($quiz->id, $user->id, 1));
        $this->assertFalse($service->is_authorized($quiz->id, $user->id, 1));
        $this->assert_refused(fn() => $service->ensure_pending($quiz->id, $user->id, 1), 'ruleisdisabled');
        $this->assert_refused(fn() => $service->authorize($request->id), 'authorizationperiodinactive');
        $this->assert_refused(fn() => $service->reject($request->id), 'authorizationperiodinactive');
        $this->assert_refused(fn() => invitation::accept($quiz->cmid, $issued['token']), 'invitationinvalid');
        $quiz->presencial_enabled = 1;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertSame(release_request::STATE_EXPIRED, $service->read_status($request->id, $user->id)->state);
        $replacement = $service->ensure_pending($quiz->id, $user->id, 1);
        $this->assertNotSame($request->id, $replacement->id);
        (new \quizaccess_presencial\task\expire_requests())->execute();
        $events = array_filter($sink->get_events(), fn($event) =>
            $event instanceof \quizaccess_presencial\event\request_expired && $event->objectid == $request->id);
        $this->assertCount(1, $events);
    }

    /**
     * Supply all decisions that can still be revised.
     *
     * @return array All decisions still revisable before disablement.
     */
    public static function revisable_states(): array {
        return [['pending'], ['authorized'], ['rejected']];
    }

    /**
     * Ending a shortened period expires requests before their administrative deadline.
     *
     * @dataProvider revisable_states
     * @param string $state Decision before the period ends.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('revisable_states')]
    public function test_period_end_expires_requests_before_extension(string $state): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $user->id, 1);
        if ($state === 'authorized') {
            $service->authorize($request->id);
        } else if ($state === 'rejected') {
            $service->reject($request->id);
        }
        $quiz->presencial_timeclose = 1_800_000_120;
        \quizaccess_presencial::save_settings($quiz);
        $clock->bump(120);
        $this->assertSame(release_request::STATE_EXPIRED, $service->read_status($request->id, $user->id)->state);
        $quiz->presencial_timeclose = 1_800_003_600;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertSame(release_request::STATE_EXPIRED, $service->get($request->id)->state);
    }

    /**
     * Refusing a new request after the period ends immediately audits all unused releases once.
     */
    public function test_ended_period_refusal_materializes_request_expiration(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $quiz->presencial_timeclose = 1_800_000_120;
        \quizaccess_presencial::save_settings($quiz);
        $pendingstudent = self::getDataGenerator()->create_user();
        $authorizedstudent = self::getDataGenerator()->create_user();
        $newstudent = self::getDataGenerator()->create_user();
        $service = new release_request_service();
        $pending = $service->ensure_pending($quiz->id, $pendingstudent->id, 1);
        $authorized = $service->ensure_pending($quiz->id, $authorizedstudent->id, 1);
        $service->authorize($authorized->id);
        $this->assertSame(release_request::STATE_PENDING, $service->get($pending->id)->state);
        $this->assertSame(release_request::STATE_AUTHORIZED, $service->get($authorized->id)->state);
        $this->assertEquals(0, $quiz->timeclose);
        $sink = $this->redirectEvents();

        $clock->bump(120);
        $this->assert_refused(fn() => $service->ensure_pending($quiz->id, $newstudent->id, 1), 'authorizationperiodinactive');
        // Read persisted state before any query that could itself materialize expiration.
        foreach ([$pending, $authorized] as $request) {
            $this->assertSame(release_request::STATE_EXPIRED, $service->get($request->id)->state);
            $this->assertCount(1, array_filter($sink->get_events(), fn($event) =>
                $event instanceof \quizaccess_presencial\event\request_expired && $event->objectid == $request->id));
        }

        $task = new \quizaccess_presencial\task\expire_requests();
        foreach ([$pending, $authorized] as $request) {
            $this->assertSame(release_request::STATE_EXPIRED, $service->read_status($request->id, $request->userid)->state);
            $this->assert_refused(fn() => $service->ensure_pending($quiz->id, $request->userid, 1), 'authorizationperiodinactive');
        }
        $task->execute();
        $task->execute();
        $quiz->presencial_timeclose = 1_800_003_600;
        \quizaccess_presencial::save_settings($quiz);
        foreach ([$pending, $authorized] as $request) {
            $this->assertSame(release_request::STATE_EXPIRED, $service->get($request->id)->state);
            $this->assertNotSame($request->id, $service->ensure_pending($quiz->id, $request->userid, 1)->id);
        }
        $this->assertCount(2, array_filter($sink->get_events(), fn($event) =>
            $event instanceof \quizaccess_presencial\event\request_expired));
    }

    /**
     * Period-end refusal protects claimed authorizations and keeps created attempts resumable.
     */
    public function test_ended_period_refusal_preserves_attempt_creation_and_resumption(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $quiz->presencial_timeclose = 1_800_000_120;
        \quizaccess_presencial::save_settings($quiz);
        $student = self::getDataGenerator()->create_user();
        $claimant = self::getDataGenerator()->create_user();
        foreach ([$student, $claimant] as $user) {
            self::getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');
        }
        $service = new release_request_service();
        $created = $service->ensure_pending($quiz->id, $student->id, 1);
        $service->authorize($created->id);
        $this->setUser($student);
        $attempt = $service->start_authorized_attempt($created->id);
        $this->assertNotNull($attempt);
        $this->setAdminUser();
        $claimed = $service->ensure_pending($quiz->id, $claimant->id, 1);
        $service->authorize($claimed->id);
        $this->assertTrue($service->begin_authorized_attempt($quiz->id, $claimant->id, 1));
        $sink = $this->redirectEvents();

        $clock->bump(181);
        $this->assert_refused(fn() => $service->ensure_pending($quiz->id, $student->id, 2), 'authorizationperiodinactive');
        (new \quizaccess_presencial\task\expire_requests())->execute();
        $this->assertSame(release_request::STATE_STARTING, $service->read_status($claimed->id, $claimant->id)->state);
        $this->setUser($claimant);
        $claimedattempt = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($quiz->id, $claimant->id);
        $service->consume_for_attempt($claimedattempt->id);
        $this->assertSame(release_request::STATE_CONSUMED, $service->get($claimed->id)->state);
        $this->setUser($student);
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
        $rule = new \quizaccess_presencial($quizobj, $clock->time());
        $this->assertFalse($rule->is_preflight_check_required($attempt->id));
        $this->assertEquals($attempt->id, $service->start_authorized_attempt($created->id)->id);
        $this->assertSame(release_request::STATE_CONSUMED, $service->get($created->id)->state);
        $this->assertEmpty(array_filter($sink->get_events(), fn($event) =>
            $event instanceof \quizaccess_presencial\event\request_expired));
    }

    /**
     * Changing rule settings or period never adds a release gate to an existing attempt.
     */
    public function test_created_attempt_remains_resumable_after_all_configuration_transitions(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $student = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($student->id, $quiz->course, 'student');
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $service->authorize($request->id);
        $this->setUser($student);
        $attempt = $service->start_authorized_attempt($request->id);
        $this->assertNotNull($attempt);
        foreach ([0, 1] as $enabled) {
            $this->setAdminUser();
            $quiz->presencial_enabled = $enabled;
            $quiz->presencial_timeopen = 1_800_000_600;
            $quiz->presencial_timeclose = 1_800_001_200;
            \quizaccess_presencial::save_settings($quiz);
            $this->setUser($student);
            $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
            $rule = new \quizaccess_presencial($quizobj, time());
            $this->assertFalse($rule->is_preflight_check_required($attempt->id));
            $this->assertEquals($attempt->id, $service->start_authorized_attempt($request->id)->id);
            $this->assertSame(release_request::STATE_CONSUMED, $service->get($request->id)->state);
        }
    }

    /**
     * Cron expires every revisable state at the configured end, exactly once.
     *
     * @dataProvider revisable_states
     * @param string $state Decision before the shortened period ends.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('revisable_states')]
    public function test_request_task_materializes_the_period_end(string $state): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $user->id, 1);
        if ($state === 'authorized') {
            $service->authorize($request->id);
        } else if ($state === 'rejected') {
            $service->reject($request->id);
        }
        $quiz->presencial_timeclose = 1_800_000_120;
        \quizaccess_presencial::save_settings($quiz);
        $sink = $this->redirectEvents();
        $clock->bump(120);
        $task = new \quizaccess_presencial\task\expire_requests();
        $task->execute();
        $task->execute();
        $this->assertSame(release_request::STATE_EXPIRED, $service->get($request->id)->state);
        $this->assertCount(1, array_filter($sink->get_events(), fn($event) =>
            $event instanceof \quizaccess_presencial\event\request_expired));
    }

    /**
     * Extending an ended period before cron cannot revive its delegations or requests.
     */
    public function test_extension_before_cron_retires_the_previous_period(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $issued = invitation::generate($quiz->cmid, 0);
        $quiz->presencial_timeclose = 1_800_000_120;
        \quizaccess_presencial::save_settings($quiz);
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $user->id, 1);
        $service->reject($request->id);
        $clock->bump(120);
        $sink = $this->redirectEvents();
        $quiz->presencial_timeclose = 1_800_003_600;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
        $this->assertFalse(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertSame(release_request::STATE_EXPIRED, $service->get($request->id)->state);
        $this->assertCount(1, $this->delegation_events($sink->get_events(), 'expired'));
    }

    /**
     * Invitation limits are audited without removing an accepted delegation early.
     */
    public function test_shortened_invitation_does_not_remove_accepted_delegation(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        $issued = invitation::generate($quiz->cmid, 0);
        $this->setUser($user);
        $accepted = invitation::accept($quiz->cmid, $issued['token']);
        $this->setAdminUser();
        $sink = $this->redirectEvents();
        $quiz->presencial_timeclose = 1_800_000_120;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $user->id));
        $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
        $this->assertCount(1, $this->delegation_events($sink->get_events(), 'shortened'));
        $this->assertCount(1, array_filter($sink->get_events(), fn($event) =>
            $event instanceof \quizaccess_presencial\event\invitation_updated && $event->other['action'] === 'shortened'));
        invitation::disable($quiz->cmid, $issued['generation']);
        $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $user->id));
        $current = delegation_manager::list_current($quiz->id);
        $this->assertEquals($accepted['delegation']->id, reset($current)->id);
        $clock->bump(120);
        $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $user->id));
    }

    /**
     * Correcting an ongoing start preserves the current team, requests and invitation.
     */
    public function test_correcting_an_ongoing_start_preserves_existing_releases(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        $delegated = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $service = new release_request_service();
        $request = $service->ensure_pending($quiz->id, $user->id, 1);
        $issued = invitation::generate($quiz->cmid, 0);
        foreach ([1_799_998_000, 1_799_999_900] as $start) {
            $quiz->presencial_timeopen = $start;
            \quizaccess_presencial::save_settings($quiz);
            $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $user->id));
            $current = delegation_manager::list_current($quiz->id);
            $this->assertEquals($delegated[$user->id]->id, reset($current)->id);
            $this->assertTrue(invitation::validate($quiz->cmid, $issued['token']));
            $this->assertSame(release_request::STATE_PENDING, $service->read_status($request->id, $user->id)->state);
        }
    }

    /**
     * The public applicator check evaluates the deadline after waiting for a settings save.
     */
    public function test_access_check_rechecks_the_clock_after_a_lock_wait(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $user = self::getDataGenerator()->create_user();
        delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $clockfile = tempnam(sys_get_temp_dir(), 'presencial-clock-');
        file_put_contents($clockfile, '1800000000');
        $bootstrap = <<<'PHP'
        $autoload = $argv[1] . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
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
            \quizaccess_presencial::save_settings($quiz);
            fwrite($pipes[0], json_encode([
                'operation' => 'active', 'quizid' => (int) $quiz->id,
                'actorid' => (int) $user->id, 'requestid' => 0, 'clockfile' => $clockfile,
            ], JSON_THROW_ON_ERROR) . "\n");
            fflush($pipes[0]);
            $this->assertSame("ATTEMPTING\n", fgets($pipes[1]));
            $read = [$pipes[1]];
            $write = $except = [];
            $this->assertSame(0, stream_select($read, $write, $except, 1));
            file_put_contents($clockfile, '1800003600');
            $transaction->allow_commit();
            $transaction = null;
            $result = json_decode(fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(['active' => false], $result);
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
            unlink($clockfile);
        }
    }

    /**
     * Select delegation transitions from Moodle's redirected events.
     * @param array $events Captured events.
     * @param string $action Transition to select.
     * @return array Matching events.
     */
    private function delegation_events(array $events, string $action): array {
        return array_filter($events, fn($event) =>
            $event instanceof \quizaccess_presencial\event\delegation_updated && $event->other['action'] === $action);
    }

    /**
     * Assert a public operation refuses its expected business condition.
     * @param callable $operation Public operation.
     * @param string $code Moodle exception error code.
     */
    private function assert_refused(callable $operation, string $code): void {
        try {
            $operation();
            $this->fail('The operation should have been refused.');
        } catch (\moodle_exception $exception) {
            $this->assertSame($code, $exception->errorcode);
        }
    }

    /**
     * Create a quiz with an active finite period through Moodle's settings hook.
     */
    private function create_quiz(): \stdClass {
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_test_quiz(
            [['Question 1', 1, 'truefalse']],
            ['course' => $course->id],
        )->get_quiz();
        $quiz->cmid = get_coursemodule_from_instance('quiz', $quiz->id)->id;
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = 1_799_999_000;
        $quiz->presencial_timeclose = 1_800_003_600;
        \quizaccess_presencial::save_settings($quiz);
        return $quiz;
    }
}
