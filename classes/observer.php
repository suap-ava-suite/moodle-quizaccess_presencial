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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace quizaccess_presencial;

use mod_quiz\event\attempt_started;
use quizaccess_presencial\local\release_request_service;

/**
 * Moodle event observers for release-request lifecycle transitions.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Consume the matching authorization after Moodle inserts a real attempt.
     *
     * @param attempt_started $event Attempt-started event.
     */
    public static function attempt_started(attempt_started $event): void {
        (new release_request_service())->consume_for_attempt((int) $event->objectid);
    }
}
