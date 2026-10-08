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

use quizaccess_presencial\local\applications;
use quizaccess_presencial\local\delegation_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

#[\PHPUnit\Framework\Attributes\CoversClass(applications::class)]
/**
 * Authorization and visibility through the public application-panel boundary.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\local\applications
 */
final class applications_test extends \advanced_testcase {
    /**
     * An unenrolled applicator can find current and future delegated quizzes.
     */
    public function test_unenrolled_applicator_finds_current_and_future_quizzes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $current = $this->create_quiz('Current quiz', -HOURSECS);
        $future = $this->create_quiz('Future quiz', HOURSECS);
        $unrelated = $this->create_quiz('Unrelated quiz', -HOURSECS);
        $user = self::getDataGenerator()->create_user();
        delegation_manager::include_users($current->id, [$user->id], $GLOBALS['USER']->id);
        delegation_manager::include_users($future->id, [$user->id], $GLOBALS['USER']->id);
        $this->setUser($user);

        $page = applications::get_page();

        $this->assertSame(2, $page['total']);
        $this->assertSame(['Current quiz', 'Future quiz'], array_column($page['items'], 'quizname'));
        $this->assertSame(['current', 'future'], array_column($page['items'], 'state'));
        $this->assertSame([true, false], array_column($page['items'], 'canoperate'));
        $this->assertSame([null, null], array_column($page['items'], 'pendingcount'));
        $this->assertNotContains((int) $unrelated->id, array_column($page['items'], 'quizid'));
        $this->assertFalse(is_enrolled(\context_module::instance($current->cmid), $user->id));
        $this->assertFalse(has_capability('mod/quiz:view', \context_module::instance($current->cmid)));
    }

    /**
     * A delegated user opens the standalone application without course access.
     */
    public function test_unenrolled_applicator_opens_the_application(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz('Delegated quiz', -HOURSECS);
        $user = self::getDataGenerator()->create_user();
        delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $this->setUser($user);

        $application = applications::get_application($quiz->cmid);

        $this->assertSame('Delegated quiz', $application['quizname']);
        $this->assertSame('Course for Delegated quiz', $application['coursename']);
        $this->assertSame(applications::get_page()['items'][0], $application);
        $this->assertTrue($application['canoperate']);
        $this->assertFalse(is_enrolled(\context_module::instance($quiz->cmid), $user->id));
        $this->assertEmpty(get_user_roles(\context_module::instance($quiz->cmid), $user->id, false));
    }

    /**
     * A professor uses the same panel through contextual quiz management authority.
     */
    public function test_professor_lists_and_opens_managed_quizzes_without_delegation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz('Managed quiz', -HOURSECS);
        $this->create_quiz('Other course quiz', -HOURSECS);
        $teacher = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');
        $this->setUser($teacher);

        $page = applications::get_page();

        $this->assertSame(1, $page['total']);
        $this->assertSame('Managed quiz', $page['items'][0]['quizname']);
        $this->assertSame('teacher', $page['items'][0]['state']);
        $this->assertTrue(applications::get_application($quiz->cmid)['canoperate']);
        $this->setAdminUser();
        $this->assertEmpty(delegation_manager::list_current($quiz->id));
    }

    /**
     * Pagination returns the selected page and does not prevent opening later items.
     */
    public function test_pagination_keeps_total_and_all_authorized_applications_accessible(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $first = $this->create_quiz('First quiz', -HOURSECS);
        $second = $this->create_quiz('Second quiz', 0);
        $third = $this->create_quiz('Third quiz', HOURSECS);
        $user = self::getDataGenerator()->create_user();
        foreach ([$first, $second, $third] as $quiz) {
            delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        }
        $this->setUser($user);

        $page = applications::get_page(1, 1);

        $this->assertSame(3, $page['total']);
        $this->assertSame(['Second quiz'], array_column($page['items'], 'quizname'));
        $this->assertSame(['Third quiz'], array_column(applications::get_page(2, 1)['items'], 'quizname'));
        $this->assertEmpty(applications::get_page(3, 1)['items']);
        $this->assertSame('Third quiz', applications::get_application($third->cmid)['quizname']);
    }

    /**
     * Losing authority removes the listing and denies a previously known application URL.
     *
     * @dataProvider authority_loss_provider
     * @param string $change Public change terminating authority.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('authority_loss_provider')]
    public function test_authority_loss_is_immediate(string $change): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz('Private application', -HOURSECS);
        $user = self::getDataGenerator()->create_user();
        $actorid = $GLOBALS['USER']->id;
        $delegations = delegation_manager::include_users($quiz->id, [$user->id], $actorid);
        $this->setUser($user);
        $this->assertTrue(applications::get_application($quiz->cmid)['canoperate']);

        switch ($change) {
            case 'revocation':
                delegation_manager::revoke($quiz->id, $delegations[$user->id]->id, $actorid);
                break;
            case 'expiration':
                $this->mock_clock_with_frozen((int) $quiz->presencial_timeclose);
                break;
            case 'suspension':
                user_update_user((object) ['id' => $user->id, 'suspended' => 1]);
                break;
            case 'deletion':
                user_delete_user($user);
                break;
            case 'authentication disabled':
                user_update_user((object) ['id' => $user->id, 'auth' => 'nologin']);
                break;
            case 'configuration disabled':
                $this->setAdminUser();
                $quiz->presencial_enabled = 0;
                \quizaccess_presencial::save_settings($quiz);
                $this->setUser($user);
                break;
            case 'new period':
                $this->setAdminUser();
                $quiz->presencial_timeopen = time() + HOURSECS;
                $quiz->presencial_timeclose = time() + (2 * HOURSECS);
                \quizaccess_presencial::save_settings($quiz);
                $this->setUser($user);
                break;
        }

        $this->assertSame(['total' => 0, 'items' => []], applications::get_page());
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('applicationaccessdenied', 'quizaccess_presencial'));
        applications::get_application($quiz->cmid);
    }

    /**
     * Authority termination cases exercised through Moodle and plugin public operations.
     *
     * @return array
     */
    public static function authority_loss_provider(): array {
        return [
            'revoked delegation' => ['revocation'],
            'authorization end is exclusive' => ['expiration'],
            'suspended session' => ['suspension'],
            'deleted account' => ['deletion'],
            'non-login account' => ['authentication disabled'],
            'disabled rule' => ['configuration disabled'],
            'replacement future period' => ['new period'],
        ];
    }

    /**
     * Future applications expose preparation metadata but become operable only at their start.
     */
    public function test_future_application_is_not_operable_until_the_period_starts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz('Future application', HOURSECS);
        $user = self::getDataGenerator()->create_user();
        delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $this->setUser($user);

        $this->assertFalse(applications::get_application($quiz->cmid)['canoperate']);
        $this->mock_clock_with_frozen((int) $quiz->presencial_timeopen);
        $this->assertTrue(applications::get_application($quiz->cmid)['canoperate']);
        $this->assertSame('current', applications::get_page()['items'][0]['state']);
    }

    /**
     * Removing suspension restores the same delegation only before its expiration.
     */
    public function test_unsuspending_restores_the_same_valid_delegation_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz('Recoverable application', -HOURSECS);
        $user = self::getDataGenerator()->create_user();
        $delegations = delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $this->setUser($user);

        user_update_user((object) ['id' => $user->id, 'suspended' => 1]);
        $this->assertEmpty(applications::get_page()['items']);
        user_update_user((object) ['id' => $user->id, 'suspended' => 0]);
        $this->assertTrue(applications::get_application($quiz->cmid)['canoperate']);
        $this->setAdminUser();
        $current = delegation_manager::list_current($quiz->id);
        $this->assertCount(1, $current);
        $this->assertSame((int) $delegations[$user->id]->id, (int) reset($current)->id);

        user_update_user((object) ['id' => $user->id, 'suspended' => 1]);
        $this->mock_clock_with_frozen((int) $quiz->presencial_timeclose);
        user_update_user((object) ['id' => $user->id, 'suspended' => 0]);
        $this->setUser($user);
        $this->assertEmpty(applications::get_page()['items']);
        $this->expectException(\moodle_exception::class);
        applications::get_application($quiz->cmid);
    }

    /**
     * Course membership alone does not authorize an application, nor does a different delegation.
     */
    public function test_unrelated_and_enrolled_users_cannot_open_another_application(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz('Restricted application', -HOURSECS);
        $otherquiz = $this->create_quiz('Allowed application', -HOURSECS);
        $user = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');
        delegation_manager::include_users($otherquiz->id, [$user->id], $GLOBALS['USER']->id);
        $this->setUser($user);

        $this->assertSame(['Allowed application'], array_column(applications::get_page()['items'], 'quizname'));
        $this->expectException(\moodle_exception::class);
        applications::get_application($quiz->cmid);
    }

    /**
     * Module-scoped quiz management is sufficient and losing it denies the panel.
     */
    public function test_module_management_authority_is_contextual_and_revocable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz('Module-managed application', -HOURSECS);
        $this->create_quiz('Unmanaged application', -HOURSECS);
        $user = self::getDataGenerator()->create_user();
        $context = \context_module::instance($quiz->cmid);
        $roleid = self::getDataGenerator()->create_role();
        assign_capability('mod/quiz:manage', CAP_ALLOW, $roleid, $context);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);

        $this->assertSame(['Module-managed application'], array_column(applications::get_page()['items'], 'quizname'));
        $this->assertTrue(applications::get_application($quiz->cmid)['canoperate']);
        role_unassign($roleid, $user->id, $context->id);
        $this->assertEmpty(applications::get_page()['items']);
        $this->expectException(\moodle_exception::class);
        applications::get_application($quiz->cmid);
    }

    /**
     * Create a quiz and configure its finite authorization period through Moodle.
     *
     * @param string $name Quiz name.
     * @param int $startoffset Authorization start relative to now.
     * @return \stdClass Quiz.
     */
    private function create_quiz(string $name, int $startoffset): \stdClass {
        $course = self::getDataGenerator()->create_course(['fullname' => 'Course for ' . $name]);
        $quiz = self::getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => $name,
            'timeopen' => time() - (2 * HOURSECS),
            'timeclose' => time() + (4 * HOURSECS),
        ]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = time() + $startoffset;
        $quiz->presencial_timeclose = time() + (3 * HOURSECS);
        \quizaccess_presencial::save_settings($quiz);
        return $quiz;
    }
}
