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

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use quizaccess_presencial\event\configuration_updated;
use quizaccess_presencial\local\authorization_period;

/**
 * Presencial quiz access rule.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_presencial extends access_rule_base {
    /**
     * Create the rule when Presencial release is configured for the quiz.
     *
     * The initial plugin is deliberately inert. A later delivery will create
     * a rule instance only when the quiz explicitly enables Presencial release.
     *
     * @param quiz_settings $quizobj Quiz settings.
     * @param int $timenow Current time.
     * @param bool $canignoretimelimits Whether the current user can ignore time limits.
     * @return self|null The active rule, or null when it does not apply.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->presencial_enabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Add the in-person release fields to the quiz settings form.
     *
     * @param \mod_quiz_mod_form $quizform Quiz settings form.
     * @param \MoodleQuickForm $mform Wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(\mod_quiz_mod_form $quizform, \MoodleQuickForm $mform): void {
        if (!has_capability('mod/quiz:manage', $quizform->get_context())) {
            return;
        }

        $mform->addElement('header', 'presencialsettings', get_string('pluginname', 'quizaccess_presencial'));
        $mform->addElement('selectyesno', 'presencial_enabled', get_string('enable', 'quizaccess_presencial'));
        $mform->setDefault('presencial_enabled', 0);
        $mform->addElement(
            'date_time_selector',
            'presencial_timeopen',
            get_string('authorizationperiodstart', 'quizaccess_presencial'),
            ['optional' => true],
        );
        $mform->addElement(
            'date_time_selector',
            'presencial_timeclose',
            get_string('authorizationperiodend', 'quizaccess_presencial'),
            ['optional' => true],
        );
        $mform->hideIf('presencial_timeopen', 'presencial_enabled', 'eq', 0);
        $mform->hideIf('presencial_timeclose', 'presencial_enabled', 'eq', 0);
    }

    /**
     * Validate configuration before Moodle persists either quiz or plugin settings.
     *
     * @param array $errors Existing validation errors.
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @param \mod_quiz_mod_form $quizform Quiz settings form.
     * @return array Validation errors.
     */
    public static function validate_settings_form_fields(
        array $errors,
        array $data,
        $files,
        \mod_quiz_mod_form $quizform,
    ): array {
        global $DB;

        if (!has_capability('mod/quiz:manage', $quizform->get_context())) {
            return $errors;
        }

        $enabled = !empty($data['presencial_enabled']);
        $instance = $quizform->get_instance();
        // The form's current record only contains fields from {quiz}.
        $configuration = $instance
            ? $DB->get_record('quizaccess_presencial', ['quizid' => $instance->id])
            : null;
        $start = (int) ($data['presencial_timeopen'] ?? 0);
        $end = (int) ($data['presencial_timeclose'] ?? 0);
        if ($enabled && empty($quizform->get_instance())) {
            [$start, $end] = self::initial_authorization_period(
                $start,
                $end,
                (int) ($data['timeopen'] ?? 0),
                (int) ($data['timeclose'] ?? 0),
            );
        }
        $requirefuture = $enabled && (
            empty($configuration->enabled) ||
            $start !== (int) ($configuration->timeopen ?? 0) ||
            $end !== (int) ($configuration->timeclose ?? 0)
        );
        $perioderrors = authorization_period::validate(
            $enabled,
            $start,
            $end,
            (int) ($data['timeopen'] ?? 0),
            (int) ($data['timeclose'] ?? 0),
            time(),
            $requirefuture,
        );
        foreach ($perioderrors as $field => $string) {
            $errors[$field] = get_string($string, 'quizaccess_presencial');
        }
        return $errors;
    }

    /**
     * Save the in-person release settings or suspend the rule.
     *
     * @param \stdClass $quiz Quiz record and submitted settings.
     */
    public static function save_settings($quiz): void {
        global $DB;

        $context = \context_module::instance($quiz->coursemodule);
        if (!has_capability('mod/quiz:manage', $context)) {
            return;
        }

        $existing = $DB->get_record('quizaccess_presencial', ['quizid' => $quiz->id]) ?: null;
        if (empty($quiz->presencial_enabled)) {
            if ($existing && !empty($existing->enabled)) {
                $previous = self::configuration_state($existing);
                $existing->enabled = 0;
                $existing->timemodified = time();
                $DB->update_record('quizaccess_presencial', $existing);
                self::trigger_configuration_event(
                    $quiz,
                    'disabled',
                    $previous,
                    self::configuration_state($existing),
                );
            }
            return;
        }

        $now = time();
        $start = (int) ($quiz->presencial_timeopen ?? 0);
        $end = (int) ($quiz->presencial_timeclose ?? 0);
        if (!$existing) {
            [$start, $end] = self::initial_authorization_period(
                $start,
                $end,
                (int) ($quiz->timeopen ?? 0),
                (int) ($quiz->timeclose ?? 0),
            );
        }
        $record = (object) [
            'quizid' => $quiz->id,
            'enabled' => 1,
            'timeopen' => $start,
            'timeclose' => $end,
            'timemodified' => $now,
        ];
        $previous = self::configuration_state($existing);
        $current = self::configuration_state($record);
        if ($existing) {
            if ($previous === $current) {
                return;
            }
            $record->id = $existing->id;
            $DB->update_record('quizaccess_presencial', $record);
            $action = $previous['enabled'] ? 'period_changed' : 'enabled';
        } else {
            $record->timecreated = $now;
            $DB->insert_record('quizaccess_presencial', $record);
            $action = 'enabled';
        }
        self::trigger_configuration_event($quiz, $action, $previous, $current);
    }

    /**
     * Use native availability for the missing bounds of an initial period.
     *
     * @param int $start Submitted authorization period start.
     * @param int $end Submitted authorization period end.
     * @param int $quizstart Native quiz availability start.
     * @param int $quizend Native quiz availability end.
     * @return int[] Authorization period bounds.
     */
    private static function initial_authorization_period(int $start, int $end, int $quizstart, int $quizend): array {
        return [$start ?: $quizstart, $end ?: $quizend];
    }

    /**
     * Convert a persisted configuration to the audit state included in events.
     *
     * @param \stdClass|null $configuration Persisted configuration, if any.
     * @return array Configuration state.
     */
    private static function configuration_state(?\stdClass $configuration): array {
        return [
            'enabled' => (bool) ($configuration->enabled ?? false),
            'timeopen' => (int) ($configuration->timeopen ?? 0),
            'timeclose' => (int) ($configuration->timeclose ?? 0),
        ];
    }

    /**
     * Remove settings when the quiz is deleted.
     *
     * @param \stdClass $quiz Quiz record.
     */
    public static function delete_settings($quiz): void {
        global $DB;
        $DB->delete_records('quizaccess_presencial', ['quizid' => $quiz->id]);
    }

    /**
     * Include this rule's fields when loading quiz settings.
     *
     * @param int $quizid Quiz identifier.
     * @return array SQL fields, joins, and parameters.
     */
    public static function get_settings_sql($quizid): array {
        return [
            'COALESCE(presencial.enabled, 0) AS presencial_enabled, ' .
                'COALESCE(presencial.timeopen, quiz.timeopen) AS presencial_timeopen, ' .
                'COALESCE(presencial.timeclose, quiz.timeclose) AS presencial_timeclose',
            'LEFT JOIN {quizaccess_presencial} presencial ON presencial.quizid = quiz.id',
            [],
        ];
    }

    /**
     * Trigger the Moodle event for a configuration transition.
     *
     * @param \stdClass $quiz Quiz record.
     * @param string $action Transition action.
     * @param array $previous Configuration state before the transition.
     * @param array $current Configuration state after the transition.
     */
    private static function trigger_configuration_event($quiz, string $action, array $previous, array $current): void {
        $event = configuration_updated::create([
            'context' => \context_module::instance($quiz->coursemodule),
            'objectid' => $quiz->id,
            'other' => ['action' => $action, 'previous' => $previous, 'current' => $current],
        ]);
        $event->trigger();
    }
}
