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

namespace quizaccess_presencial\event;

/**
 * Event emitted when a new attempt release request is created.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_created extends \core\event\base {
    /**
     * Initialise event data.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'quizaccess_presencial_req';
    }

    /**
     * Get the localized event name.
     *
     * @return string Event name.
     */
    public static function get_name() {
        return get_string('eventrequestcreated', 'quizaccess_presencial');
    }

    /**
     * Get the event description.
     *
     * @return string Event description.
     */
    public function get_description() {
        return get_string('eventrequestcreateddesc', 'quizaccess_presencial', (object) [
            'requestid' => $this->objectid,
            'studentid' => $this->relateduserid,
        ]);
    }

    /**
     * Get the quiz page URL for the event.
     *
     * @return \moodle_url Event URL.
     */
    public function get_url() {
        return new \moodle_url('/mod/quiz/view.php', ['id' => $this->context->instanceid]);
    }
}
