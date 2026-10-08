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

use quizaccess_presencial\event\request_created;
use quizaccess_presencial\event\request_authorized;
use quizaccess_presencial\event\request_consumed;
use quizaccess_presencial\event\request_expired;
use quizaccess_presencial\external\authorize_request;
use quizaccess_presencial\local\release_request;
use quizaccess_presencial\local\release_request_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/**
 * Tests for the release request application boundary.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\release_request_service
 */
final class release_request_service_test extends \advanced_testcase {
    /**
     * A request is created with the configured absolute expiration time.
     */
    public function test_ensure_pending_creates_request_with_default_expiration(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $sink = $this->redirectEvents();

        $request = (new release_request_service($clock))->ensure_pending($quiz->id, $student->id, 1);

        $this->assertSame((int) $quiz->id, $request->quizid);
        $this->assertSame((int) $student->id, $request->userid);
        $this->assertSame(1, $request->attemptnumber);
        $this->assertSame(release_request::STATE_PENDING, $request->state);
        $this->assertSame(1_800_000_000, $request->timecreated);
        $this->assertSame(1_800_000_900, $request->expiresat);
        $this->assertSame(0, $this->count_attempts($quiz->id, $student->id));
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof request_created,
        ));
        $this->assertCount(1, $events);
        $this->assertSame($request->id, $events[0]->objectid);
    }

    /**
     * Repeated calls return the same request without extending its lifetime.
     */
    public function test_ensure_pending_is_idempotent_for_the_same_next_attempt(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $first = $service->ensure_pending($quiz->id, $student->id, 1);
        $clock->bump(300);
        $sink = $this->redirectEvents();

        $repeated = $service->ensure_pending($quiz->id, $student->id, 1);

        $this->assertSame($first->id, $repeated->id);
        $this->assertSame($first->timecreated, $repeated->timecreated);
        $this->assertSame($first->expiresat, $repeated->expiresat);
        $this->assertEmpty(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof request_created,
        ));
    }

    /**
     * The database rejects a second active request for one student, quiz and attempt.
     */
    public function test_database_enforces_active_request_uniqueness(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $request = (new release_request_service($clock))->ensure_pending($quiz->id, $student->id, 1);

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('quizaccess_presencial_req', (object) [
            'quizid' => $quiz->id,
            'userid' => $student->id,
            'attemptnumber' => 1,
            'state' => release_request::STATE_PENDING,
            'active' => 1,
            'timecreated' => $request->timecreated,
            'expiresat' => $request->expiresat,
            'timemodified' => $request->timecreated,
        ]);
    }

    /**
     * The active identity includes the student, quiz and next attempt number.
     */
    public function test_active_request_identity_uses_student_quiz_and_attempt_number(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $firststudent] = $this->create_enabled_quiz_and_student();
        $secondstudent = self::getDataGenerator()->create_user();
        $secondquiz = $this->create_enabled_quiz();
        $service = new release_request_service($clock);

        $first = $service->ensure_pending($quiz->id, $firststudent->id, 1);
        $otherstudent = $service->ensure_pending($quiz->id, $secondstudent->id, 1);
        $otherquiz = $service->ensure_pending($secondquiz->id, $firststudent->id, 1);
        $otherattempt = $service->ensure_pending($quiz->id, $firststudent->id, 2);

        $this->assertCount(4, array_unique([
            $first->id,
            $otherstudent->id,
            $otherquiz->id,
            $otherattempt->id,
        ]));
    }

    /**
     * Reading a due request expires it once and emits one transition event.
     */
    public function test_read_status_expires_due_request_idempotently(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $clock->bump(901);
        $sink = $this->redirectEvents();

        $expired = $service->read_status($request->id, $student->id);
        $repeated = $service->read_status($request->id, $student->id);

        $this->assertSame(release_request::STATE_EXPIRED, $expired->state);
        $this->assertSame(release_request::STATE_EXPIRED, $repeated->state);
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof request_expired,
        ));
        $this->assertCount(1, $events);
        $this->assertSame($request->id, $events[0]->objectid);
    }

    /**
     * A new request can replace an expired request for the same next attempt.
     */
    public function test_new_request_can_be_created_after_expiration(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $first = $service->ensure_pending($quiz->id, $student->id, 1);
        $clock->bump(901);

        $replacement = $service->ensure_pending($quiz->id, $student->id, 1);

        $this->assertNotSame($first->id, $replacement->id);
        $this->assertSame(1_800_000_901, $replacement->timecreated);
        $this->assertSame(1_800_001_801, $replacement->expiresat);
        $this->assertSame(
            release_request::STATE_EXPIRED,
            $service->get($first->id)->state,
        );
    }

    /**
     * Scheduled expiration changes each due request once.
     */
    public function test_expire_due_is_idempotent(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $service->ensure_pending($quiz->id, $student->id, 1);
        $clock->bump(901);
        $sink = $this->redirectEvents();

        $this->assertSame(1, $service->expire_due());
        $this->assertSame(0, $service->expire_due());

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof request_expired,
        ));
        $this->assertCount(1, $events);
    }

    /**
     * The configured global policy controls the absolute deadline.
     */
    public function test_configured_request_validity_is_stored_as_absolute_deadline(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        set_config('requestvalidity', 20, 'quizaccess_presencial');
        [$quiz, $student] = $this->create_enabled_quiz_and_student();

        $request = (new release_request_service($clock))->ensure_pending($quiz->id, $student->id, 1);

        $this->assertSame(1_800_001_200, $request->expiresat);
    }

    /**
     * Authorization changes state once and can only happen before expiration.
     */
    public function test_authorization_is_idempotent_and_expired_requests_cannot_be_authorized(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $sink = $this->redirectEvents();

        $authorized = $service->authorize($request->id);
        $repeated = $service->authorize($request->id);

        $this->assertSame(release_request::STATE_AUTHORIZED, $authorized->state);
        $this->assertSame(release_request::STATE_AUTHORIZED, $repeated->state);
        $this->assertSame(1_800_000_300, $authorized->expiresat);
        $this->assertTrue($service->is_authorized($quiz->id, $student->id, 1));
        $authorizedevents = array_values(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof request_authorized,
        ));
        $this->assertCount(1, $authorizedevents);
        $this->assertSame(get_admin()->id, $authorizedevents[0]->userid);

        $second = $service->ensure_pending($quiz->id, $student->id, 2);
        $clock->bump(901);
        $expired = $service->authorize($second->id);
        $this->assertSame(release_request::STATE_EXPIRED, $expired->state);
    }

    /**
     * An unused authorization expires at its persisted deadline and emits one event.
     */
    public function test_unused_authorization_expires_idempotently(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $authorized = $service->authorize($request->id);
        $clock->bump(301);
        $sink = $this->redirectEvents();

        $expired = $service->read_status($authorized->id, $student->id);
        $repeated = $service->read_status($authorized->id, $student->id);

        $this->assertSame(release_request::STATE_EXPIRED, $expired->state);
        $this->assertSame(release_request::STATE_EXPIRED, $repeated->state);
        $this->assertFalse($service->is_authorized($quiz->id, $student->id, 1));
        $this->assertCount(1, array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof request_expired,
        ));
    }

    /**
     * A claimed attempt remains protected from expiry until Moodle creates it.
     */
    public function test_inflight_attempt_start_is_not_expired_by_poll_or_task(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $service->authorize($request->id);
        $this->assertTrue($service->begin_authorized_attempt($quiz->id, $student->id, 1));

        $clock->bump(181);

        $status = $service->read_status($request->id, $student->id);
        $expiredcount = $service->expire_due();

        $this->assertSame(release_request::STATE_STARTING, $status->state);
        $this->assertSame(0, $expiredcount);

        $this->setUser($student);
        self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($quiz->id, $student->id);
        $this->assertSame(release_request::STATE_CONSUMED, $service->get($request->id)->state);
    }

    /**
     * The registered Moodle external operation authorizes for quiz managers only.
     */
    public function test_external_authorization_operation_checks_capability_and_updates_status(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        self::getDataGenerator()->enrol_user($student->id, $quiz->course, 'student');

        $this->setUser($student);
        try {
            authorize_request::execute($request->id);
            $this->fail('A student without quiz management capability authorized a request.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame(release_request::STATE_PENDING, $service->get($request->id)->state);
        }

        $this->setAdminUser();
        $result = authorize_request::execute($request->id);

        $this->assertSame(release_request::STATE_AUTHORIZED, $result['state']);
        $this->assertGreaterThan($clock->time(), $result['expiresat']);
    }

    /**
     * The public Quiz external start API cannot bypass the pending release gate.
     */
    public function test_quiz_external_start_api_does_not_create_an_attempt_while_pending(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $this->remove_core_quiz_time_gates($quiz);
        self::getDataGenerator()->enrol_user($student->id, $quiz->course, 'student');
        $this->setUser($student);

        // Core's external API invokes the same access-rule callback before its
        // quiz_prepare_and_start_new_attempt() call. The plugin stops it by raising
        // the normal pending response (or redirect in a non-WS test context).
        $exception = null;
        try {
            \mod_quiz_external::start_attempt($quiz->id);
        } catch (\moodle_exception $caught) {
            // The endpoint is expected to stop before returning an attempt.
            $exception = $caught;
        }
        $this->assertInstanceOf(\moodle_exception::class, $exception);

        $request = $DB->get_record('quizaccess_presencial_req', [
            'quizid' => $quiz->id,
            'userid' => $student->id,
            'attemptnumber' => 1,
            'active' => 1,
        ], '*', MUST_EXIST);
        $this->assertSame(release_request::STATE_PENDING, $request->state);
        $this->assertSame(0, $this->count_attempts($quiz->id, $student->id));
    }

    /**
     * A native Quiz password check runs before the plugin can create a request.
     */
    public function test_native_quiz_preflight_must_pass_before_external_api_creates_request(): void {
        global $DB, $SESSION;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $quiz = $this->create_enabled_quiz(['quizpassword' => 'door-code']);
        $this->assertSame('door-code', $quiz->password, 'The test must exercise the native Quiz password rule.');
        $this->remove_core_quiz_time_gates($quiz);
        $student = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($student->id, $quiz->course, 'student');
        $this->setUser($student);
        unset($SESSION->passwordcheckedquizzes[$quiz->id]);

        $exception = null;
        try {
            \mod_quiz_external::start_attempt($quiz->id);
        } catch (\moodle_exception $caught) {
            // The core rejects the request at its unsatisfied password preflight.
            $exception = $caught;
        }
        $this->assertInstanceOf(\moodle_exception::class, $exception);
        $this->assertSame(get_string('passworderror', 'quizaccess_password'), $exception->errorcode);

        $this->assertFalse($DB->record_exists('quizaccess_presencial_req', [
            'quizid' => $quiz->id,
            'userid' => $student->id,
            'attemptnumber' => 1,
        ]));
        $this->assertSame(0, $this->count_attempts($quiz->id, $student->id));
    }

    /**
     * Authorization is limited to quiz managers and the configured authorization period.
     */
    public function test_authorization_checks_operator_capability_and_period(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);

        $this->setUser($student);
        try {
            $service->authorize($request->id);
            $this->fail('A student without quiz management capability authorized a request.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame(release_request::STATE_PENDING, $service->get($request->id)->state);
        }

        $this->setAdminUser();
        $clock->bump(10_001);
        try {
            $service->authorize($request->id);
            $this->fail('A request was authorized outside the configured period.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('authorizationperiodinactive', $exception->errorcode);
        }
    }

    /**
     * A newly created Moodle attempt consumes its authorization once.
     */
    public function test_attempt_creation_consumes_authorization(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $service->authorize($request->id);
        $this->assertTrue($service->begin_authorized_attempt($quiz->id, $student->id, 1));
        $this->assertFalse($service->begin_authorized_attempt($quiz->id, $student->id, 1));
        $this->setUser($student);

        $attempt = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt(
            $quiz->id,
            $student->id,
        );

        $this->assertSame(release_request::STATE_CONSUMED, $service->get($request->id)->state);
        $this->assertFalse($service->is_authorized($quiz->id, $student->id, 1));
        $this->assertSame(1, $attempt->attempt);
    }

    /**
     * Consuming an authorization emits one event for a real state transition.
     */
    public function test_consumption_emits_one_event_for_one_transition(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $service->authorize($request->id);
        $service->begin_authorized_attempt($quiz->id, $student->id, 1);
        $this->setUser($student);
        $sink = $this->redirectEvents();
        $attempt = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt(
            $quiz->id,
            $student->id,
        );

        // Redirected Moodle events are collected for assertions but not dispatched
        // to observers, so consume the request through the public service boundary.
        $this->assertTrue($service->consume_for_attempt((int) $attempt->id));
        $this->assertFalse($service->consume_for_attempt((int) $attempt->id));
        $this->assertSame(release_request::STATE_CONSUMED, $service->get($request->id)->state);
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof request_consumed,
        ));
        $this->assertCount(1, $events);
        $this->assertSame($request->id, $events[0]->objectid);
        $this->assertSame($attempt->attempt, $events[0]->other['attemptnumber']);
        $this->assertSame($student->id, $events[0]->userid);
    }

    /**
     * A user cannot expire another student's request by guessing its id.
     */
    public function test_read_status_checks_owner_before_synchronous_expiration(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $otherstudent = self::getDataGenerator()->create_user();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $clock->bump(901);

        try {
            $service->read_status($request->id, $otherstudent->id);
            $this->fail('A student read another student\'s request.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('requestnotfound', $exception->errorcode);
        }
        $this->assertSame(release_request::STATE_PENDING, $service->get($request->id)->state);
    }

    /**
     * Disabling the rule closes every pending request without reviving it later.
     */
    public function test_disabling_quiz_rule_expires_pending_requests(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $sink = $this->redirectEvents();

        $quiz->presencial_enabled = 0;
        \quizaccess_presencial::save_settings($quiz);

        $this->assertSame(release_request::STATE_EXPIRED, $service->get($request->id)->state);
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof request_expired,
        ));
        $this->assertCount(1, $events);
    }

    /**
     * Disabling the rule does not interrupt a creation that already passed authorization.
     */
    public function test_disabling_rule_during_attempt_start_lets_inflight_attempt_finish(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $service = new release_request_service($clock);
        $request = $service->ensure_pending($quiz->id, $student->id, 1);
        $service->authorize($request->id);
        $this->assertTrue($service->begin_authorized_attempt($quiz->id, $student->id, 1));

        $quiz->presencial_enabled = 0;
        \quizaccess_presencial::save_settings($quiz);
        $this->assertSame(release_request::STATE_STARTING, $service->get($request->id)->state);

        $this->setUser($student);
        self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($quiz->id, $student->id);

        $this->assertSame(release_request::STATE_CONSUMED, $service->get($request->id)->state);
    }

    /**
     * A Moodle attempt created before the request wins the race.
     */
    public function test_existing_attempt_prevents_request_for_the_same_attempt_number(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $this->setUser($student);
        self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($quiz->id, $student->id);
        $service = new release_request_service($clock);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('requestattemptalreadyexists', 'quizaccess_presencial'));
        $service->ensure_pending($quiz->id, $student->id, 1);
    }

    /**
     * The public Moodle access-rule hook bypasses release for a resumable attempt.
     */
    public function test_resumable_attempt_does_not_create_or_require_a_request(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$quiz, $student] = $this->create_enabled_quiz_and_student();
        $this->setUser($student);
        $attempt = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt(
            $quiz->id,
            $student->id,
        );
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
        $rule = new \quizaccess_presencial($quizobj, time());

        $this->assertFalse($rule->is_preflight_check_required((int) $attempt->id));
        $this->assertSame(2, (new release_request_service())->next_attempt_number($quiz->id, $student->id));
        $this->assertFalse($DB->record_exists('quizaccess_presencial_req', [
            'quizid' => $quiz->id,
            'userid' => $student->id,
        ]));
    }

    /**
     * Create a configured quiz and student.
     *
     * @return array{0: \stdClass, 1: \stdClass}
     */
    private function create_enabled_quiz_and_student(): array {
        return [$this->create_enabled_quiz(), self::getDataGenerator()->create_user()];
    }

    /**
     * Create a quiz with the access rule enabled through its public lifecycle hook.
     *
     * @param array $quizsettings Additional quiz settings.
     * @return \stdClass Quiz record.
     */
    private function create_enabled_quiz(array $quizsettings = []): \stdClass {
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_test_quiz(
            [['First question', 1, 'truefalse']],
            array_merge([
                'course' => $course->id,
                'timeopen' => 1_799_999_000,
                'timeclose' => 1_800_010_000,
            ], $quizsettings),
        )->get_quiz();
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $quiz->coursemodule = $cm->id;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = 1_799_999_000;
        $quiz->presencial_timeclose = 1_800_010_000;
        \quizaccess_presencial::save_settings($quiz);
        return $quiz;
    }

    /**
     * Count a user's non-preview Moodle attempts for a quiz.
     *
     * @param int $quizid Quiz id.
     * @param int $userid User id.
     * @return int Attempt count.
     */
    private function count_attempts(int $quizid, int $userid): int {
        global $DB;
        return $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'userid' => $userid, 'preview' => 0]);
    }

    /**
     * Keep native availability from interfering with tests of plugin preflight.
     *
     * @param \stdClass $quiz Quiz record.
     */
    private function remove_core_quiz_time_gates(\stdClass $quiz): void {
        global $DB;
        $DB->set_field('quiz', 'timeopen', 0, ['id' => $quiz->id]);
        $DB->set_field('quiz', 'timeclose', 0, ['id' => $quiz->id]);
    }
}
