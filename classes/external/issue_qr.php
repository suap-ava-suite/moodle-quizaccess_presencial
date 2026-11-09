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

namespace quizaccess_presencial\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use quizaccess_presencial\local\qr_authorization;

/**
 * Issue an image of the current student's short-lived QR code.
 *
 * @package quizaccess_presencial
 * @copyright 2026 SUAP AVA Suite
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class issue_qr extends external_api {
    /**
     * Describe the issuance parameters.
     *
     * @return external_function_parameters Parameters for issuance.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(['requestid' => new external_value(PARAM_INT, 'Release request id')]);
    }

    /**
     * Issue the current student's temporary QR image.
     *
     * @param int $requestid Release request identifier.
     * @return array QR image as a data URI.
     */
    public static function execute(int $requestid): array {
        global $CFG;
        $params = self::validate_parameters(self::execute_parameters(), ['requestid' => $requestid]);
        $request = (new \quizaccess_presencial\local\release_request_service())->get($params['requestid']);
        $cm = get_coursemodule_from_instance('quiz', $request->quizid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:attempt', $context);
        $token = qr_authorization::issue($params['requestid']);
        $url = new \moodle_url('/mod/quiz/accessrule/presencial/qr_landing.php', ['requestid' => $requestid]);
        require_once($CFG->libdir . '/classes/qrcode.php');
        $qr = new \core_qrcode($url->out(false) . '#' . $token);
        return ['image' => 'data:image/svg+xml;base64,' . base64_encode($qr->getBarcodeSVGcode(4, 4))];
    }

    /**
     * Describe the issuance result.
     *
     * @return external_single_structure Result containing the QR image.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['image' => new external_value(PARAM_RAW, 'QR image')]);
    }
}
