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

namespace quizaccess_presencial\event;

/**
 * Event emitted when a quiz application delegation changes.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class delegation_updated extends delegation_event {
    /** CRUD operation represented by this event. */
    protected const CRUD = 'u';

    /**
     * Get the event name.
     *
     * @return string Event name.
     */
    public static function get_name(): string {
        return get_string('eventdelegationupdated', 'quizaccess_presencial');
    }

    /**
     * Get the event description.
     *
     * @return string Event description.
     */
    public function get_description(): string {
        $action = $this->other['action'] ?? 'updated';
        return "The application delegation '{$this->objectid}' was {$action}.";
    }
}
