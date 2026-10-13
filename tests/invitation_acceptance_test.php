<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial;

use quizaccess_presencial\local\delegation_manager;
use quizaccess_presencial\local\invitation;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/**
 * Public invitation acceptance tests.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\invitation
 */
final class invitation_acceptance_test extends \advanced_testcase {
    /**
     * An eligible authenticated account can accept before the period starts.
     */
    public function test_eligible_account_accepts_an_open_invitation_before_the_period(): void {
        $this->resetAfterTest();
        $quiz = $this->create_configured_quiz(time() + HOURSECS, time() + (2 * HOURSECS));
        $teacher = self::getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Teacher']);
        self::getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');
        $applicator = self::getDataGenerator()->create_user();

        $this->setUser($teacher);
        $issued = invitation::generate($quiz->cmid, 0);
        $this->setUser($applicator);

        $sink = $this->redirectEvents();
        $accepted = invitation::accept($quiz->cmid, $issued['token']);

        $this->assertTrue($accepted['created']);
        $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $applicator->id));
        $this->assertTrue(delegation_manager::is_active_applicator(
            $quiz->id,
            $applicator->id,
            $quiz->presencial_timeopen,
        ));
        $events = array_values(array_filter($sink->get_events(), static fn($event): bool =>
            $event instanceof \quizaccess_presencial\event\delegation_created));
        $this->assertCount(1, $events);
        $this->assertSame('invite', $events[0]->get_data()['other']['origin']);
    }

    /**
     * A valid invitation exposes only the acceptance summary.
     */
    public function test_valid_invitation_exposes_only_acceptance_safe_details(): void {
        $this->resetAfterTest();
        $quiz = $this->create_configured_quiz(time() - HOURSECS, time() + HOURSECS);
        $teacher = self::getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Teacher']);
        self::getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');

        $this->setUser($teacher);
        $issued = invitation::generate($quiz->cmid, 0);
        $details = invitation::get_acceptance_details($quiz->cmid, $issued['token']);

        $this->assertSame(['course', 'quiz', 'timeopen', 'timeclose', 'teacher'], array_keys($details));
        $this->assertSame('Ada Teacher', $details['teacher']);
        $this->assertSame($quiz->presencial_timeopen, $details['timeopen']);
        $this->assertSame($quiz->presencial_timeclose, $details['timeclose']);
    }

    /**
     * Acceptance details use every name field needed by the configured display format.
     */
    public function test_acceptance_details_identify_the_teacher_with_an_alternative_name_format(): void {
        $this->resetAfterTest();
        set_config('fullnamedisplay', 'alternatename middlename lastname firstname');
        $quiz = $this->create_configured_quiz(time() - HOURSECS, time() + HOURSECS);
        $teacher = self::getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'middlename' => 'Byron',
            'alternatename' => 'Lovelace',
            'lastname' => 'Teacher',
        ]);
        self::getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');

        $this->setUser($teacher);
        $issued = invitation::generate($quiz->cmid, 0);
        $details = invitation::get_acceptance_details($quiz->cmid, $issued['token']);

        $this->assertSame('Lovelace Byron Teacher Ada', $details['teacher']);
        $this->assertDebuggingNotCalled();
    }

    /**
     * A refused acceptance retains the expired transition and invalid-use audit.
     */
    public function test_refused_acceptance_commits_expiry_and_invalid_use_audit(): void {
        $this->resetAfterTest();
        $now = strtotime('2030-01-01 09:00:00 UTC');
        $clock = $this->mock_clock_with_frozen($now);
        $quiz = $this->create_configured_quiz($now - HOURSECS, $now + HOURSECS);
        $teacher = self::getDataGenerator()->create_user();
        $applicator = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');

        $this->setUser($teacher);
        $issued = invitation::generate($quiz->cmid, 0);
        $clock->set_to($issued['timeexpires']);
        $this->setUser($applicator);
        $sink = $this->redirectEvents();

        try {
            invitation::accept($quiz->cmid, $issued['token']);
            $this->fail('An expired invitation was accepted.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invitationinvalid', $exception->errorcode);
        }

        $events = $sink->get_events();
        $this->assertCount(2, $events);
        $this->assertSame('expired', $events[0]->get_data()['other']['action']);
        $this->assertInstanceOf(\quizaccess_presencial\event\invitation_invalid::class, $events[1]);

        $this->setUser($teacher);
        $this->assertSame('expired', invitation::get_status($quiz->cmid)['state']);
        $this->assertCount(2, $sink->get_events());
    }

    /**
     * Accepting again reports the current delegation without changing it.
     */
    public function test_reaccepting_an_invitation_is_idempotent(): void {
        $this->resetAfterTest();
        $quiz = $this->create_configured_quiz(time() - HOURSECS, time() + HOURSECS);
        $teacher = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');
        $applicator = self::getDataGenerator()->create_user();

        $this->setUser($teacher);
        $issued = invitation::generate($quiz->cmid, 0);
        $this->setUser($applicator);
        invitation::accept($quiz->cmid, $issued['token']);
        $sink = $this->redirectEvents();
        $again = invitation::accept($quiz->cmid, $issued['token']);

        $this->assertFalse($again['created']);
        $this->setUser($teacher);
        $this->assertCount(1, delegation_manager::list_current($quiz->id));
        $events = array_values(array_filter($sink->get_events(), static fn($event): bool =>
            $event instanceof \quizaccess_presencial\event\delegation_idempotent));
        $this->assertCount(1, $events);
        $this->assertSame('invite', $events[0]->get_data()['other']['origin']);
    }

    /**
     * Invalid invitations and ineligible accounts cannot produce delegations.
     */
    public function test_invalid_invitation_and_ineligible_account_are_refused(): void {
        $this->resetAfterTest();
        $quiz = $this->create_configured_quiz(time() - HOURSECS, time() + HOURSECS);
        $teacher = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');
        $applicator = self::getDataGenerator()->create_user();
        $ineligible = self::getDataGenerator()->create_user(['suspended' => 1]);

        $this->setUser($teacher);
        $issued = invitation::generate($quiz->cmid, 0);

        $this->setUser($applicator);
        try {
            invitation::accept($quiz->cmid, 'i1_' . str_repeat('0', 64));
            $this->fail('An invalid invitation was accepted.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invitationinvalid', $exception->errorcode);
        }

        $this->setUser($ineligible);
        try {
            invitation::accept($quiz->cmid, $issued['token']);
            $this->fail('An ineligible account accepted the invitation.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('ineligibleuser', $exception->errorcode);
        }
    }

    /**
     * Disabled, replaced, expired, and rule-disabled invitations are refused.
     */
    public function test_non_open_invitation_states_are_refused(): void {
        $this->resetAfterTest();
        $teacher = self::getDataGenerator()->create_user();
        $applicator = self::getDataGenerator()->create_user();

        $disabledquiz = $this->create_configured_quiz(time() - HOURSECS, time() + HOURSECS);
        self::getDataGenerator()->enrol_user($teacher->id, $disabledquiz->course, 'editingteacher');
        $this->setUser($teacher);
        $disabled = invitation::generate($disabledquiz->cmid, 0);
        invitation::disable($disabledquiz->cmid, 1);
        $this->assert_invitation_refused($disabledquiz->cmid, $disabled['token'], $applicator);

        $replacedquiz = $this->create_configured_quiz(time() - HOURSECS, time() + HOURSECS);
        self::getDataGenerator()->enrol_user($teacher->id, $replacedquiz->course, 'editingteacher');
        $this->setUser($teacher);
        $replaced = invitation::generate($replacedquiz->cmid, 0);
        invitation::generate($replacedquiz->cmid, 1);
        $this->assert_invitation_refused($replacedquiz->cmid, $replaced['token'], $applicator);

        $ruleoffquiz = $this->create_configured_quiz(time() - HOURSECS, time() + HOURSECS);
        self::getDataGenerator()->enrol_user($teacher->id, $ruleoffquiz->course, 'editingteacher');
        $this->setUser($teacher);
        $ruleoff = invitation::generate($ruleoffquiz->cmid, 0);
        $ruleoffquiz->presencial_enabled = 0;
        \quizaccess_presencial::save_settings($ruleoffquiz);
        $this->assert_invitation_refused($ruleoffquiz->cmid, $ruleoff['token'], $applicator);

        $now = time();
        $clock = $this->mock_clock_with_frozen($now);
        $expiredquiz = $this->create_configured_quiz($now - HOURSECS, $now + HOURSECS);
        self::getDataGenerator()->enrol_user($teacher->id, $expiredquiz->course, 'editingteacher');
        $this->setUser($teacher);
        $expired = invitation::generate($expiredquiz->cmid, 0);
        $clock->set_to($now + HOURSECS);
        $this->assert_invitation_refused($expiredquiz->cmid, $expired['token'], $applicator);
    }

    /**
     * Assert that an account cannot use an invitation and receives no delegation.
     *
     * @param int $cmid Quiz course module.
     * @param string $token Invitation secret.
     * @param \stdClass $applicator Authenticated account.
     * @return void
     */
    private function assert_invitation_refused(int $cmid, string $token, \stdClass $applicator): void {
        $this->setUser($applicator);
        try {
            invitation::accept($cmid, $token);
            $this->fail('A non-open invitation was accepted.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invitationinvalid', $exception->errorcode);
        }
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $this->assertFalse(delegation_manager::is_active_applicator($cm->instance, $applicator->id));
    }

    /**
     * Create a configured quiz with the requested authorization period.
     *
     * @param int $timeopen Authorization period start.
     * @param int $timeclose Authorization period end.
     * @return \stdClass Quiz record.
     */
    private function create_configured_quiz(int $timeopen, int $timeclose): \stdClass {
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'timeopen' => time() - DAYSECS,
            'timeclose' => time() + DAYSECS,
        ]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = $timeopen;
        $quiz->presencial_timeclose = $timeclose;
        \quizaccess_presencial::save_settings($quiz);
        return $quiz;
    }
}
