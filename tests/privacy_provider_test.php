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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace quizaccess_presencial;

use core_privacy\local\request\userlist;
use quizaccess_presencial\local\delegation_manager;
use quizaccess_presencial\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/**
 * Privacy provider boundary tests.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    /**
     * Privacy callbacks ignore contexts for activities other than a Quiz.
     */
    public function test_non_quiz_module_context_is_ignored(): void {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $forum = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_instance([
            'course' => $course->id,
        ]);
        $context = \context_module::instance($forum->cmid);
        $userlist = new userlist($context, 'quizaccess_presencial');

        provider::get_users_in_context($userlist);

        $this->assertEmpty($userlist->get_userids());
    }

    /**
     * Privacy discovery returns the Quiz context containing the user's delegation.
     */
    public function test_user_delegation_context_is_discovered(): void {
        $this->resetAfterTest();
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
        $user = self::getDataGenerator()->create_user();
        delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertEquals([(int) \context_module::instance($quiz->cmid)->id], $contextlist->get_contextids());
    }

    /**
     * Deleting privacy data in a Quiz context removes its current delegations.
     */
    public function test_delete_all_users_in_quiz_context_removes_delegations(): void {
        $this->resetAfterTest();
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
        $user = self::getDataGenerator()->create_user();
        delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);
        $context = \context_module::instance($quiz->cmid);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEmpty(delegation_manager::list_current($quiz->id));
    }

    /**
     * Deleting a Quiz removes its delegation data before the module context disappears.
     */
    public function test_deleting_quiz_removes_delegation_data(): void {
        global $DB;

        $this->resetAfterTest();
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
        $user = self::getDataGenerator()->create_user();
        delegation_manager::include_users($quiz->id, [$user->id], $GLOBALS['USER']->id);

        course_delete_module($quiz->cmid);

        $this->assertFalse($DB->record_exists('quiz', ['id' => $quiz->id]));
        $this->assertFalse($DB->record_exists('quizaccess_presencial', ['quizid' => $quiz->id]));
        $this->assertFalse($DB->record_exists('quizaccess_presencial_delegation', ['quizid' => $quiz->id]));
    }
}
