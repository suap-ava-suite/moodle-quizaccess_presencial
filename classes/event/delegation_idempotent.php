<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial\event;

/**
 * Event emitted when an already-current application delegation is included again.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class delegation_idempotent extends delegation_event {
    /** CRUD operation represented by this event. */
    protected const CRUD = 'r';

    /**
     * Get the event name.
     *
     * @return string Event name.
     */
    public static function get_name(): string {
        return get_string('eventdelegationidempotent', 'quizaccess_presencial');
    }

    /**
     * Get the event description.
     *
     * @return string Event description.
     */
    public function get_description(): string {
        return "Application delegation '{$this->objectid}' was included again without changes.";
    }
}
