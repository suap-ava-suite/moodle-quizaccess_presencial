<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial\local;

defined('MOODLE_INTERNAL') || die();

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
     * @return array field name => language string identifier.
     */
    public static function validate(
            bool $enabled, int $start, int $end, int $quizstart, int $quizend, int $now): array {
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
        if ($end <= $now) {
            $errors['presencial_timeclose'] = 'authorizationperiodfuture';
        }
        if (($quizstart && $start < $quizstart) || ($quizend && $end > $quizend)) {
            $errors['presencial_timeclose'] = 'authorizationperiodavailability';
        }
        return $errors;
    }
}
