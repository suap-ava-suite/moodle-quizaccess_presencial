<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial;

use quizaccess_presencial\local\applicator_selector;

defined('MOODLE_INTERNAL') || die();

/**
 * Applicator selector boundary tests.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class applicator_selector_test extends \advanced_testcase {
    /**
     * Search returns only eligible accounts from the institutional identity search.
     */
    public function test_search_only_returns_eligible_accounts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('auth', 'manual');
        $eligible = self::getDataGenerator()->create_user();
        $suspended = self::getDataGenerator()->create_user(['suspended' => 1]);
        $unconfirmed = self::getDataGenerator()->create_user(['confirmed' => 0]);
        $nologin = self::getDataGenerator()->create_user(['auth' => 'nologin']);
        $disabledauth = self::getDataGenerator()->create_user(['auth' => 'email']);
        $deleted = self::getDataGenerator()->create_user();
        user_delete_user($deleted);
        $selector = new applicator_selector('applicators', ['accesscontext' => \context_system::instance()]);
        $groups = $selector->find_users('');
        $users = $groups ? reset($groups) : [];
        $userids = array_map(static fn($user): int => (int) $user->id, $users);

        $this->assertContains((int) $eligible->id, $userids);
        $this->assertNotContains((int) $suspended->id, $userids);
        $this->assertNotContains((int) $unconfirmed->id, $userids);
        $this->assertNotContains((int) $nologin->id, $userids);
        $this->assertNotContains((int) $disabledauth->id, $userids);
        $this->assertNotContains((int) $deleted->id, $userids);
        $this->assertNotContains((int) $GLOBALS['CFG']->siteguest, $userids);
    }
}
