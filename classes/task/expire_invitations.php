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

namespace quizaccess_presencial\task;

/**
 * Materialize expired invitations without duplicating transition events.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class expire_invitations extends \core\task\scheduled_task {
    /**
     * Get the translated task name.
     * @return string Task name.
     */
    public function get_name(): string {
        return get_string('taskexpireinvitations', 'quizaccess_presencial');
    }

    /**
     * Expire due invitations under their quiz locks.
     */
    public function execute(): void {
        \quizaccess_presencial\local\invitation::expire_due();
    }
}
