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

use core_privacy\local\request\userlist;
use quizaccess_presencial\local\delegation_manager;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use quizaccess_presencial\local\release_request_service;
use quizaccess_presencial\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

/**
 * Privacy API boundary tests for delegations, invitations, and release requests.
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

    /**
     * Context discovery, export, and deletion expose and remove the request data.
     */
    public function test_context_export_and_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1_800_000_000);
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_test_quiz(
            [['First question', 1, 'truefalse']],
            ['course' => $course->id],
        )->get_quiz();
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $quiz->coursemodule = $cm->id;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = 1_799_999_000;
        $quiz->presencial_timeclose = 1_800_010_000;
        \quizaccess_presencial::save_settings($quiz);
        $student = self::getDataGenerator()->create_user();
        $request = (new release_request_service($clock))->ensure_pending($quiz->id, $student->id, 1);
        $context = \context_module::instance($cm->id);

        $contexts = provider::get_contexts_for_userid($student->id);
        $contextids = array_map('intval', $contexts->get_contextids());
        $this->assertSame([(int) $context->id], $contextids);
        $approvedcontexts = new approved_contextlist($student, 'quizaccess_presencial', [$context->id]);
        writer::reset();
        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        provider::export_user_data($approvedcontexts);

        $export = $writer->get_data([
            get_string('pluginname', 'quizaccess_presencial'),
            get_string('privacy:exportpath:request', 'quizaccess_presencial'),
            '1',
        ]);
        $this->assertNotEmpty($export);
        $this->assertSame((int) $quiz->id, $export->quizid);
        $this->assertSame(1, $export->attemptnumber);
        $this->assertSame('pending', $export->state);
        $this->assertSame(userdate($request->timecreated), $export->timecreated);

        provider::delete_data_for_user($approvedcontexts);
        $this->assertFalse($DB->record_exists('quizaccess_presencial_req', ['id' => $request->id]));

        $secondstudent = self::getDataGenerator()->create_user();
        $secondrequest = (new release_request_service($clock))->ensure_pending($quiz->id, $secondstudent->id, 1);
        provider::delete_data_for_all_users_in_context($context);
        $this->assertFalse($DB->record_exists('quizaccess_presencial_req', ['id' => $secondrequest->id]));
    }
}
