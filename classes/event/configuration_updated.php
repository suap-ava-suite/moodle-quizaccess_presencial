<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when an in-person release configuration changes.
 *
 * @package    quizaccess_presencial
 */
final class configuration_updated extends \core\event\base {
    /** Initialise event data. */
    protected function init(): void {
        $this->data['objecttable'] = 'quiz';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /** @return string event name. */
    public static function get_name(): string {
        return get_string('eventconfigurationupdated', 'quizaccess_presencial');
    }

    /** @return string event description. */
    public function get_description(): string {
        return "The in-person release configuration for quiz '{$this->objectid}' was updated.";
    }
}
