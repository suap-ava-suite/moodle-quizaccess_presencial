<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial;

use quizaccess_presencial\local\delegation_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/**
 * Public delegation application boundary tests.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class delegation_manager_test extends \advanced_testcase {
    /**
     * Direct inclusion creates delegations without Moodle enrolments or roles.
     */
    public function test_include_eligible_users_creates_delegations_without_enrolment_or_role(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_configured_quiz();
        $first = self::getDataGenerator()->create_user(['firstname' => 'Aplicador', 'lastname' => 'Um']);
        $second = self::getDataGenerator()->create_user(['firstname' => 'Aplicador', 'lastname' => 'Dois']);

        $sink = $this->redirectEvents();
        $delegations = delegation_manager::include_users($quiz->id, [$first->id, $second->id], $GLOBALS['USER']->id);

        $this->assertCount(2, $delegations);
        $this->assertCount(2, delegation_manager::list_current($quiz->id));
        $context = \context_module::instance($quiz->cmid);
        $this->assertFalse(is_enrolled($context, $first->id));
        $this->assertEmpty(get_user_roles($context, $first->id, false));
        $this->assertEquals('direct', $delegations[$first->id]->origin);
        $this->assertCount(2, $sink->get_events());
        foreach ($sink->get_events() as $event) {
            $this->assertInstanceOf(\quizaccess_presencial\event\delegation_created::class, $event);
            $this->assertSame('created', $event->get_data()['other']['action']);
            $this->assertSame('c', $event->get_data()['crud']);
        }
    }

    /**
     * Repeating inclusion is idempotent for a current delegation.
     */
    public function test_repeating_current_inclusion_does_not_create_or_change_delegation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_configured_quiz();
        $user = self::getDataGenerator()->create_user();
        $now = time();
        $firstsink = $this->redirectEvents();
        $first = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id, 'direct', $now);
        $firstsink->close();

        $sink = $this->redirectEvents();
        $second = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id, 'direct', $now + 60);

        $this->assertSame((int)$first[$user->id]->id, (int)$second[$user->id]->id);
        $this->assertSame((int)$first[$user->id]->timecreated, (int)$second[$user->id]->timecreated);
        $this->assertCount(1, delegation_manager::list_current($quiz->id, $now + 60));
        $this->assertSame('idempotent', $sink->get_events()[0]->get_data()['other']['action']);
        $this->assertInstanceOf(\quizaccess_presencial\event\delegation_idempotent::class, $sink->get_events()[0]);
        $this->assertSame('r', $sink->get_events()[0]->get_data()['crud']);
    }

    /**
     * Revocation is immediate and a later inclusion creates a new record.
     */
    public function test_revocation_preserves_history_and_reinclusion_creates_new_delegation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_configured_quiz();
        $user = self::getDataGenerator()->create_user();
        $now = time();
        $created = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id, 'direct', $now);
        $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $user->id, $now));

        $sink = $this->redirectEvents();
        $this->assertTrue(delegation_manager::revoke($quiz->id, $created[$user->id]->id, $GLOBALS['USER']->id, $now + 60));
        $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $user->id, $now + 60));
        $this->assertFalse(delegation_manager::revoke(
            $quiz->id,
            $created[$user->id]->id,
            $GLOBALS['USER']->id,
            $now + 90,
        ));
        $reincluded = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id, 'direct', $now + 120);
        $this->assertTrue(delegation_manager::is_active_applicator($quiz->id, $user->id, $now + 120));

        $this->assertNotSame((int)$created[$user->id]->id, (int)$reincluded[$user->id]->id);
        $this->assertCount(2, $sink->get_events());
        $this->assertCount(1, delegation_manager::list_current($quiz->id, $now + 120));
        $this->assertSame('revoked', $sink->get_events()[0]->get_data()['other']['action']);
        $this->assertSame('created', $sink->get_events()[1]->get_data()['other']['action']);
        $this->assertInstanceOf(\quizaccess_presencial\event\delegation_updated::class, $sink->get_events()[0]);
        $this->assertInstanceOf(\quizaccess_presencial\event\delegation_created::class, $sink->get_events()[1]);
        $this->assertSame('u', $sink->get_events()[0]->get_data()['crud']);
        $this->assertSame('c', $sink->get_events()[1]->get_data()['crud']);

        $context = \context_module::instance($quiz->cmid);
        $approvedcontextlist = new \core_privacy\local\request\approved_contextlist(
            $user,
            'quizaccess_presencial',
            [$context->id],
        );
        \quizaccess_presencial\privacy\provider::export_user_data($approvedcontextlist);
        $writer = \core_privacy\local\request\writer::with_context($context);
        $revoked = $writer->get_data(['delegations', 1]);
        $active = $writer->get_data(['delegations', 2]);
        $this->assertNotEmpty($revoked->timerevoked);
        $this->assertSame((int)$user->id, (int)$active->userid);
        $this->assertNull($active->timerevoked);
    }

    /**
     * A delegation from another quiz cannot be revoked through this quiz's management page.
     */
    public function test_delegation_from_another_quiz_cannot_be_revoked(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_configured_quiz();
        $otherquiz = $this->create_configured_quiz();
        $user = self::getDataGenerator()->create_user();
        $delegation = delegation_manager::include_users($otherquiz->id, [$user->id], $GLOBALS['USER']->id);

        try {
            delegation_manager::revoke($quiz->id, $delegation[$user->id]->id, $GLOBALS['USER']->id);
            $this->fail('A delegation from another quiz was revoked.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('delegationnotfound', $exception->errorcode);
        }

        $this->assertTrue(delegation_manager::is_active_applicator($otherquiz->id, $user->id));
    }

    /**
     * A new authorization period does not revive a delegation from the previous period.
     */
    public function test_new_period_requires_a_new_delegation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_configured_quiz();
        $user = self::getDataGenerator()->create_user();
        $now = time();
        $first = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id, 'direct', $now);

        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = $now + HOURSECS;
        $quiz->presencial_timeclose = $now + (2 * HOURSECS);
        \quizaccess_presencial::save_settings($quiz);

        $this->assertEmpty(delegation_manager::list_current($quiz->id, $now));
        $second = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id, 'direct', $now);

        $this->assertNotSame((int)$first[$user->id]->id, (int)$second[$user->id]->id);
        $current = delegation_manager::list_current($quiz->id, $now);
        $this->assertCount(1, $current);
        $this->assertSame((int)$second[$user->id]->id, (int)reset($current)->id);
    }

    /**
     * Users without quiz management capability cannot change the application team.
     */
    public function test_user_without_quiz_management_capability_cannot_include_users(): void {
        $this->resetAfterTest();
        $quiz = $this->create_configured_quiz();
        $user = self::getDataGenerator()->create_user();
        $actor = self::getDataGenerator()->create_user();
        $this->setUser($actor);

        $this->expectException(\required_capability_exception::class);
        delegation_manager::include_users($quiz->id, [$user->id], $actor->id);
    }

    /**
     * Ineligible accounts cannot receive a delegation.
     */
    public function test_deleted_suspended_unconfirmed_and_non_login_accounts_are_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('auth', 'manual,webservice');
        $quiz = $this->create_configured_quiz();
        $users = [
            self::getDataGenerator()->create_user(),
            self::getDataGenerator()->create_user(['suspended' => 1]),
            self::getDataGenerator()->create_user(['confirmed' => 0]),
            self::getDataGenerator()->create_user(['auth' => 'nologin']),
            self::getDataGenerator()->create_user(['auth' => 'webservice']),
            self::getDataGenerator()->create_user(['auth' => 'email']),
        ];
        user_delete_user($users[0]);

        foreach ($users as $user) {
            try {
                delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
                $this->fail('An ineligible account received a delegation.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('ineligibleuser', $exception->errorcode);
            }
        }
    }

    /**
     * The site guest account cannot receive a delegation.
     */
    public function test_site_guest_account_is_rejected(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_configured_quiz();

        try {
            delegation_manager::include_users($quiz->id, [(int)$CFG->siteguest], $GLOBALS['USER']->id);
            $this->fail('The site guest account received an application delegation.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('ineligibleuser', $exception->errorcode);
        }
    }

    /**
     * Suspending an account immediately suspends its ability to operate as an applicator.
     */
    public function test_suspended_account_cannot_operate_with_an_existing_delegation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_configured_quiz();
        $user = self::getDataGenerator()->create_user();
        $now = time();
        delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id, 'direct', $now);

        user_update_user((object) ['id' => $user->id, 'suspended' => 1]);

        $this->assertFalse(delegation_manager::is_active_applicator($quiz->id, $user->id, $now + 60));
    }

    /**
     * Create a quiz with an enabled future authorization period.
     *
     * @return \stdClass Quiz record.
     */
    private function create_configured_quiz(): \stdClass {
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
