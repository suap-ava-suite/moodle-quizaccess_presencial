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

use mod_quiz\quiz_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');
require_once($CFG->dirroot . '/mod/quiz/mod_form.php');

/**
 * Tests for the Presencial access rule.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial
 */
final class rule_test extends \advanced_testcase {
    /**
     * Test that an unconfigured rule initially exposes the native quiz availability.
     */
    public function test_unconfigured_rule_uses_native_availability_as_initial_period(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();

        $settings = quiz_settings::create($quiz->id)->get_quiz();

        $this->assertEmpty($settings->presencial_enabled);
        $this->assertEquals($quiz->timeopen, $settings->presencial_timeopen);
        $this->assertEquals($quiz->timeclose, $settings->presencial_timeclose);
    }

    /**
     * Test that saving an enabled configuration persists its authorization period.
     */
    public function test_saving_enabled_configuration_persists_the_authorization_period(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        [$start, $end] = $this->enable_configuration(
            $quiz,
            $quiz->timeopen + HOURSECS,
            $quiz->timeclose - HOURSECS,
        );

        $sink = $this->redirectEvents();
        \quizaccess_presencial::save_settings($quiz);
        $events = $sink->get_events();

        $settings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEquals(1, $settings->presencial_enabled);
        $this->assertEquals($start, $settings->presencial_timeopen);
        $this->assertEquals($end, $settings->presencial_timeclose);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\quizaccess_presencial\event\configuration_updated::class, $events[0]);
        $this->assertSame('enabled', $events[0]->get_data()['other']['action']);
        $this->assertSame(
            ['enabled' => false, 'timeopen' => 0, 'timeclose' => 0],
            $events[0]->get_data()['other']['previous'],
        );
        $this->assertSame(
            ['enabled' => true, 'timeopen' => $start, 'timeclose' => $end],
            $events[0]->get_data()['other']['current'],
        );
    }

    /**
     * Test that initial configuration uses native availability for missing bounds.
     */
    public function test_initial_configuration_copies_native_quiz_availability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = 0;
        $quiz->presencial_timeclose = 0;

        \quizaccess_presencial::save_settings($quiz);

        $settings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEquals($quiz->timeopen, $settings->presencial_timeopen);
        $this->assertEquals($quiz->timeclose, $settings->presencial_timeclose);
    }

    /**
     * Test that an initial configuration preserves submitted custom bounds.
     */
    public function test_initial_configuration_preserves_submitted_custom_authorization_period(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $start = $quiz->timeopen + HOURSECS;
        $end = $quiz->timeclose - HOURSECS;
        $this->enable_configuration($quiz, $start, $end);

        \quizaccess_presencial::save_settings($quiz);

        $settings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEquals($start, $settings->presencial_timeopen);
        $this->assertEquals($end, $settings->presencial_timeclose);
    }

    /**
     * Test that disabling and re-enabling the rule preserves a custom period.
     */
    public function test_disabling_and_reenabling_configuration_preserves_the_authorization_period(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        [$start, $end] = $this->enable_configuration(
            $quiz,
            $quiz->timeopen + HOURSECS,
            $quiz->timeclose - HOURSECS,
        );
        \quizaccess_presencial::save_settings($quiz);

        $quiz->presencial_enabled = 0;
        $sink = $this->redirectEvents();
        \quizaccess_presencial::save_settings($quiz);

        $settings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEmpty($settings->presencial_enabled);
        $this->assertEquals($start, $settings->presencial_timeopen);
        $this->assertEquals($end, $settings->presencial_timeclose);
        $this->assertCount(1, $sink->get_events());
        $this->assertSame('disabled', $sink->get_events()[0]->get_data()['other']['action']);
        $this->assertSame(
            ['enabled' => true, 'timeopen' => $start, 'timeclose' => $end],
            $sink->get_events()[0]->get_data()['other']['previous'],
        );
        $this->assertSame(
            ['enabled' => false, 'timeopen' => $start, 'timeclose' => $end],
            $sink->get_events()[0]->get_data()['other']['current'],
        );

        $settings->coursemodule = $quiz->coursemodule;
        $settings->presencial_enabled = 1;
        $sink->clear();
        \quizaccess_presencial::save_settings($settings);

        $reenabledsettings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEquals(1, $reenabledsettings->presencial_enabled);
        $this->assertEquals($start, $reenabledsettings->presencial_timeopen);
        $this->assertEquals($end, $reenabledsettings->presencial_timeclose);
        $this->assertSame('enabled', $sink->get_events()[0]->get_data()['other']['action']);
        $this->assertSame(
            ['enabled' => false, 'timeopen' => $start, 'timeclose' => $end],
            $sink->get_events()[0]->get_data()['other']['previous'],
        );
        $this->assertSame(
            ['enabled' => true, 'timeopen' => $start, 'timeclose' => $end],
            $sink->get_events()[0]->get_data()['other']['current'],
        );
    }

    /**
     * Test that a user without quiz management capability cannot change settings.
     */
    public function test_user_without_quiz_management_capability_cannot_change_configuration(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        [, $end] = $this->enable_configuration($quiz);
        \quizaccess_presencial::save_settings($quiz);

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $quiz->presencial_enabled = 0;
        \quizaccess_presencial::save_settings($quiz);

        $settings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEquals($end, $settings->presencial_timeclose);
    }

    /**
     * Test that both authorization period bounds are required when enabling the rule.
     */
    public function test_validation_requires_both_authorization_period_bounds(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();

        $errors = $this->validate_configuration($quiz, 0, 0, $quiz->timeopen, $quiz->timeclose);

        $this->assertArrayHasKey('presencial_timeopen', $errors);
        $this->assertArrayHasKey('presencial_timeclose', $errors);
    }

    /**
     * Test that initial validation uses native availability for missing bounds.
     */
    public function test_initial_validation_uses_native_quiz_availability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();

        $errors = $this->validate_configuration(
            $quiz,
            0,
            0,
            $quiz->timeopen,
            $quiz->timeclose,
            true,
        );

        $this->assertEmpty($errors);
    }

    /**
     * Test that the authorization period is ordered and ends in the future.
     */
    public function test_validation_requires_an_ordered_future_authorization_period(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $now = time();

        $orderederrors = $this->validate_configuration(
            $quiz,
            $now + (2 * HOURSECS),
            $now + HOURSECS,
            $quiz->timeopen,
            $quiz->timeclose,
        );
        $pasterrors = $this->validate_configuration(
            $quiz,
            $now - (2 * HOURSECS),
            $now - HOURSECS,
            $now - (3 * HOURSECS),
            $now + HOURSECS,
        );

        $this->assertArrayHasKey('presencial_timeclose', $orderederrors);
        $this->assertArrayHasKey('presencial_timeclose', $pasterrors);
    }

    /**
     * Test that editing an unrelated quiz setting preserves an expired period.
     */
    public function test_validation_allows_an_unchanged_expired_authorization_period(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        $quiz = $this->create_quiz($now - (3 * HOURSECS), $now + HOURSECS);
        $start = $now - (2 * HOURSECS);
        $end = $now - HOURSECS;
        $this->enable_configuration($quiz, $start, $end);
        \quizaccess_presencial::save_settings($quiz);

        global $DB;

        // Moodleform_mod::get_current() contains only the native quiz record.
        $current = $DB->get_record('quiz', ['id' => $quiz->id], '*', MUST_EXIST);
        $current->coursemodule = $quiz->coursemodule;
        $this->assertFalse(property_exists($current, 'presencial_enabled'));
        $this->assertFalse(property_exists($current, 'presencial_timeopen'));
        $this->assertFalse(property_exists($current, 'presencial_timeclose'));

        $errors = $this->validate_configuration(
            $current,
            $start,
            $end,
            $current->timeopen,
            $current->timeclose,
        );

        $this->assertEmpty($errors);
    }

    /**
     * Test that a valid period can be enabled after the quiz has opened.
     */
    public function test_validation_allows_enabling_after_quiz_opens(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $now = time();

        $errors = $this->validate_configuration(
            $quiz,
            $now - HOURSECS,
            $now + HOURSECS,
            $now - (2 * HOURSECS),
            $now + (2 * HOURSECS),
        );

        $this->assertEmpty($errors);
    }

    /**
     * Test that a proposed period is contained in native quiz availability.
     */
    public function test_validation_rejects_a_period_outside_quiz_availability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $now = time();

        $errors = $this->validate_configuration(
            $quiz,
            $now + HOURSECS,
            $now + (3 * HOURSECS),
            $now + (2 * HOURSECS),
            $now + (4 * HOURSECS),
        );

        $this->assertArrayHasKey('presencial_timeclose', $errors);
    }

    /**
     * Test that native availability cannot exclude an already configured period.
     */
    public function test_validation_rejects_native_availability_changed_after_configuration(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        [$start, $end] = $this->enable_configuration($quiz);
        \quizaccess_presencial::save_settings($quiz);

        $errors = $this->validate_configuration($quiz, $start, $end, $start + 1, $quiz->timeclose);

        $this->assertArrayHasKey('presencial_timeclose', $errors);
    }

    /**
     * Test that changing a period persists replacement values and emits an event.
     */
    public function test_changing_authorization_period_persists_new_values_and_emits_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        [$oldstart, $oldend] = $this->enable_configuration($quiz);
        \quizaccess_presencial::save_settings($quiz);

        [$newstart, $newend] = $this->enable_configuration($quiz, time() + HOURSECS, time() + (3 * HOURSECS));
        $sink = $this->redirectEvents();
        \quizaccess_presencial::save_settings($quiz);

        $event = $sink->get_events()[0];
        $settings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEquals($newend, $settings->presencial_timeclose);
        $this->assertInstanceOf(\quizaccess_presencial\event\configuration_updated::class, $event);
        $this->assertSame('period_changed', $event->get_data()['other']['action']);
        $this->assertSame(
            ['enabled' => true, 'timeopen' => $oldstart, 'timeclose' => $oldend],
            $event->get_data()['other']['previous'],
        );
        $this->assertSame(
            ['enabled' => true, 'timeopen' => $newstart, 'timeclose' => $newend],
            $event->get_data()['other']['current'],
        );
    }

    /**
     * Create a quiz record suitable for the rule lifecycle hooks.
     *
      * @param int|null $timeopen Native quiz availability start.
      * @param int|null $timeclose Native quiz availability end.
     * @return \stdClass Quiz record.
     */
    private function create_quiz(?int $timeopen = null, ?int $timeclose = null): \stdClass {
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'timeopen' => $timeopen ?? time() + HOURSECS,
            'timeclose' => $timeclose ?? time() + (4 * HOURSECS),
        ]);
        $quiz->coursemodule = $quiz->cmid;
        return $quiz;
    }

    /**
     * Enable a valid period on a quiz and return its bounds.
     *
     * @param \stdClass $quiz Quiz record.
     * @param int|null $start Authorization period start.
     * @param int|null $end Authorization period end.
     * @return int[] Authorization period bounds.
     */
    private function enable_configuration(\stdClass $quiz, ?int $start = null, ?int $end = null): array {
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = $start ?? time() + HOURSECS;
        $quiz->presencial_timeclose = $end ?? time() + (2 * HOURSECS);
        return [$quiz->presencial_timeopen, $quiz->presencial_timeclose];
    }

    /**
     * Submit settings to the rule's public Moodle form validation boundary.
     *
     * @param \stdClass $quiz Quiz record.
     * @param int $start Authorization period start.
     * @param int $end Authorization period end.
     * @param int $quizstart Native quiz availability start.
     * @param int $quizend Native quiz availability end.
      * @param bool $isnew Whether the quiz is being created.
     * @return array Validation errors indexed by form field.
     */
    private function validate_configuration(
        \stdClass $quiz,
        int $start,
        int $end,
        int $quizstart,
        int $quizend,
        bool $isnew = false,
    ): array {
        $form = $this->createMock(\mod_quiz_mod_form::class);
        $form->method('get_context')->willReturn(\context_module::instance($quiz->coursemodule));
        $form->method('get_instance')->willReturn($isnew ? null : $quiz->id);
        $form->method('get_current')->willReturn($isnew ? null : $quiz);
        return \quizaccess_presencial::validate_settings_form_fields(
            [],
            [
                'presencial_enabled' => 1,
                'presencial_timeopen' => $start,
                'presencial_timeclose' => $end,
                'timeopen' => $quizstart,
                'timeclose' => $quizend,
            ],
            [],
            $form,
        );
    }
}
