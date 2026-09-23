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
        [$start, $end] = $this->enable_configuration($quiz);

        $sink = $this->redirectEvents();
        \quizaccess_presencial::save_settings($quiz);
        $events = $sink->get_events();

        $settings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEquals(1, $settings->presencial_enabled);
        $this->assertEquals($start, $settings->presencial_timeopen);
        $this->assertEquals($end, $settings->presencial_timeclose);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\quizaccess_presencial\event\configuration_updated::class, $events[0]);
    }

    /**
     * Test that disabling the rule removes its configuration and emits an event.
     */
    public function test_disabling_configuration_removes_the_authorization_period(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $quiz = $this->create_quiz();
        $this->enable_configuration($quiz);
        \quizaccess_presencial::save_settings($quiz);

        $quiz->presencial_enabled = 0;
        $sink = $this->redirectEvents();
        \quizaccess_presencial::save_settings($quiz);

        $settings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEmpty($settings->presencial_enabled);
        $this->assertCount(1, $sink->get_events());
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
        $this->enable_configuration($quiz);
        \quizaccess_presencial::save_settings($quiz);

        [, $newend] = $this->enable_configuration($quiz, time() + HOURSECS, time() + (3 * HOURSECS));
        $sink = $this->redirectEvents();
        \quizaccess_presencial::save_settings($quiz);

        $event = $sink->get_events()[0];
        $settings = quiz_settings::create($quiz->id)->get_quiz();
        $this->assertEquals($newend, $settings->presencial_timeclose);
        $this->assertInstanceOf(\quizaccess_presencial\event\configuration_updated::class, $event);
        $this->assertEquals($newend, $event->get_data()['other']['timeclose']);
    }

    /**
     * The rule is inactive until a quiz explicitly enables it.
     */
    public function test_rule_is_inactive_without_configuration(): void {
        $quizsettings = $this->createStub(quiz_settings::class);
        $quizsettings->method('get_quiz')->willReturn((object) ['presencial_enabled' => null]);

        $this->assertNull(\quizaccess_presencial::make($quizsettings, time(), false));
    }

    /**
     * Create a quiz record suitable for the rule lifecycle hooks.
     *
     * @return \stdClass Quiz record.
     */
    private function create_quiz(): \stdClass {
        $course = self::getDataGenerator()->create_course();
        $quiz = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'timeopen' => time() + HOURSECS,
            'timeclose' => time() + (4 * HOURSECS),
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
     * @return array Validation errors indexed by form field.
     */
    private function validate_configuration(
        \stdClass $quiz,
        int $start,
        int $end,
        int $quizstart,
        int $quizend,
    ): array {
        $form = $this->createMock(\mod_quiz_mod_form::class);
        $form->method('get_context')->willReturn(\context_module::instance($quiz->coursemodule));
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
