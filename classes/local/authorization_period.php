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

namespace quizaccess_presencial\local;

/**
 * Validates the finite period in which in-person releases can be granted.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class authorization_period {
    /**
     * Validate a proposed authorization period against quiz availability.
     *
     * @param bool $enabled Whether the rule is enabled.
     * @param int $start Proposed authorization period start.
     * @param int $end Proposed authorization period end.
     * @param int $quizstart Native quiz availability start.
     * @param int $quizend Native quiz availability end.
     * @param int $now Current time.
     * @param bool $requirefuture Whether the period must end in the future.
     * @return array Validation errors indexed by form field.
     */
    public static function validate(
        bool $enabled,
        int $start,
        int $end,
        int $quizstart,
        int $quizend,
        int $now,
        bool $requirefuture,
    ): array {
        if (!$enabled) {
            return [];
        }

        $errors = [];
        if (!$start) {
            $errors['presencial_timeopen'] = 'authorizationperiodstartrequired';
        }
        if (!$end) {
            $errors['presencial_timeclose'] = 'authorizationperiodendrequired';
        }
        if ($errors) {
            return $errors;
        }
        if ($end <= $start) {
            $errors['presencial_timeclose'] = 'authorizationperiodordered';
        }
        if ($requirefuture && $end <= $now) {
            $errors['presencial_timeclose'] = 'authorizationperiodfuture';
        }
        if (($quizstart && $start < $quizstart) || ($quizend && $end > $quizend)) {
            $errors['presencial_timeclose'] = 'authorizationperiodavailability';
        }
        return $errors;
    }
}
