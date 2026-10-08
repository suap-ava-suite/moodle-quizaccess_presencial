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

namespace quizaccess_presencial\task;

/**
 * Periodically expire due requests and unused authorizations using the application service.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class expire_requests extends \core\task\scheduled_task {
    /**
     * Get the task's localized display name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskexpirerequests', 'quizaccess_presencial');
    }

    /**
     * Expire requests and unused authorizations whose deadline has passed.
     *
     * @return void
     */
    public function execute() {
        (new \quizaccess_presencial\local\release_request_service())->expire_due();
    }
}
