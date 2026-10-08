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

namespace quizaccess_presencial\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use quizaccess_presencial\local\release_request_service;

/**
 * AJAX endpoint returning the current state of a student's release request.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class read_request_status extends external_api {
    /**
     * Describe the parameters for reading a release request.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid' => new external_value(PARAM_INT, 'Release request id'),
        ]);
    }

    /**
     * Read the current state and synchronously expire it if due.
     *
     * @param int $requestid Request id.
     * @return array Request state and absolute expiry.
     */
    public static function execute(int $requestid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['requestid' => $requestid]);
        $service = new release_request_service();
        $request = $service->get($params['requestid']);
        $cm = get_coursemodule_from_instance('quiz', $request->quizid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:attempt', $context);
        $request = $service->read_status($params['requestid'], (int) $USER->id);
        return ['state' => $request->state, 'expiresat' => $request->expiresat];
    }

    /**
     * Describe the return value for reading a release request.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'state' => new external_value(PARAM_ALPHANUMEXT, 'Current release request state'),
            'expiresat' => new external_value(PARAM_INT, 'Absolute expiry timestamp'),
        ]);
    }
}
