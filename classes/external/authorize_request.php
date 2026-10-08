<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use quizaccess_presencial\local\release_request_service;

/**
 * Authenticated operation for a Quiz manager to authorize a student's request.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class authorize_request extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid' => new external_value(PARAM_INT, 'Release request id'),
        ]);
    }

    /**
     * Authorize a pending request during the Quiz's configured authorization period.
     *
     * The service validates mod/quiz:manage and serializes authorization with expiry.
     * Registration as an AJAX-only, login-required external function gives Moodle's
     * normal session and sesskey protections to this write operation.
     *
     * @param int $requestid Request id.
     * @return array Request state and absolute expiry.
     */
    public static function execute(int $requestid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['requestid' => $requestid]);
        $service = new release_request_service();
        $request = $service->get($params['requestid']);
        $cm = get_coursemodule_from_instance('quiz', $request->quizid, 0, false, MUST_EXIST);
        self::validate_context(\context_module::instance($cm->id));
        $request = $service->authorize($params['requestid']);
        return ['state' => $request->state, 'expiresat' => $request->expiresat];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'state' => new external_value(PARAM_ALPHANUMEXT, 'Current release request state'),
            'expiresat' => new external_value(PARAM_INT, 'Absolute expiry timestamp'),
        ]);
    }
}
