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
        if (empty($quizobj->get_quiz()->presencial_timeclose)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    /** Add the in-person release fields to the quiz settings form. */
    public static function add_settings_form_fields(\mod_quiz_mod_form $quizform, \MoodleQuickForm $mform): void {
        if (!has_capability('mod/quiz:manage', $quizform->get_context())) {
            return;
        }

        $current = $quizform->get_current();
        $mform->addElement('header', 'presencialsettings', get_string('pluginname', 'quizaccess_presencial'));
        $mform->addElement('selectyesno', 'presencial_enabled', get_string('enable', 'quizaccess_presencial'));
        $mform->setDefault('presencial_enabled', !empty($current->presencial_timeclose));
        $mform->addElement('date_time_selector', 'presencial_timeopen',
                get_string('authorizationperiodstart', 'quizaccess_presencial'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'presencial_timeclose',
                get_string('authorizationperiodend', 'quizaccess_presencial'), ['optional' => true]);
        if (empty($current->presencial_timeclose)) {
            $mform->setDefault('presencial_timeopen', $current->timeopen ?? 0);
            $mform->setDefault('presencial_timeclose', $current->timeclose ?? 0);
        }
        $mform->hideIf('presencial_timeopen', 'presencial_enabled', 'eq', 0);
        $mform->hideIf('presencial_timeclose', 'presencial_enabled', 'eq', 0);
    }

    /** Validate configuration before Moodle persists either quiz or plugin settings. */
    public static function validate_settings_form_fields(
            array $errors, array $data, $files, \mod_quiz_mod_form $quizform): array {
        if (!has_capability('mod/quiz:manage', $quizform->get_context())) {
            return $errors;
        }

        $enabled = !empty($data['presencial_enabled']);
        $perioderrors = authorization_period::validate(
                $enabled,
                (int) ($data['presencial_timeopen'] ?? 0),
                (int) ($data['presencial_timeclose'] ?? 0),
                (int) ($data['timeopen'] ?? 0),
                (int) ($data['timeclose'] ?? 0),
                time());
        foreach ($perioderrors as $field => $string) {
            $errors[$field] = get_string($string, 'quizaccess_presencial');
        }
        return $errors;
    }

    /** Save or remove the in-person release settings. */
    public static function save_settings($quiz): void {
        global $DB;

        $context = \context_module::instance($quiz->coursemodule);
        if (!has_capability('mod/quiz:manage', $context)) {
            return;
        }

        $existing = $DB->get_record('quizaccess_presencial', ['quizid' => $quiz->id]);
        if (empty($quiz->presencial_enabled)) {
            if ($existing) {
                $DB->delete_records('quizaccess_presencial', ['id' => $existing->id]);
                self::trigger_configuration_event($quiz, 0, 0);
            }
            return;
        }

        $now = time();
        $record = (object) [
            'quizid' => $quiz->id,
            'timeopen' => (int) $quiz->presencial_timeopen,
            'timeclose' => (int) $quiz->presencial_timeclose,
            'timemodified' => $now,
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('quizaccess_presencial', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('quizaccess_presencial', $record);
        }
        self::trigger_configuration_event($quiz, $record->timeopen, $record->timeclose);
    }

    /** Remove settings when the quiz is deleted. */
    public static function delete_settings($quiz): void {
        global $DB;
        $DB->delete_records('quizaccess_presencial', ['quizid' => $quiz->id]);
    }

    /** Include this rule's fields when loading quiz settings. */
    public static function get_settings_sql($quizid): array {
        return [
            'presencial.timeopen AS presencial_timeopen, presencial.timeclose AS presencial_timeclose',
            'LEFT JOIN {quizaccess_presencial} presencial ON presencial.quizid = quiz.id',
            [],
        ];
    }

    /** Trigger the Moodle event for a saved, changed, or disabled configuration. */
    private static function trigger_configuration_event($quiz, int $start, int $end): void {
        $event = configuration_updated::create([
            'context' => \context_module::instance($quiz->coursemodule),
            'objectid' => $quiz->id,
            'other' => ['timeopen' => $start, 'timeclose' => $end],
        ]);
        $event->trigger();
    }
}
