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

namespace quizaccess_presencial\local;

/**
 * Temporary, single-use codes for a student's release request.
 *
 * @package quizaccess_presencial
 * @copyright 2026 SUAP AVA Suite
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qr_authorization {
    /**
     * Issue a fresh code for the current student's actionable request.
     *
     * @param int $requestid Release request identifier.
     * @return string Temporary QR token.
     */
    public static function issue(int $requestid): string {
        global $DB, $USER;
        $service = new release_request_service();
        $request = $service->get($requestid);
        $result = quiz_lock::execute($request->quizid, function () use ($DB, $USER, $request, $requestid) {
            $configuration = self::lock_configuration($request->quizid);
            $record = $DB->get_record_sql(
                'SELECT * FROM {quizaccess_presencial_req} WHERE id = :id FOR UPDATE',
                ['id' => $requestid],
                MUST_EXIST
            );
            $now = \core\di::get(\core\clock::class)->time();
            if (!authorization_period::is_active($configuration, $now)) {
                return new \moodle_exception('authorizationperiodinactive', 'quizaccess_presencial');
            }
            if ((int) $record->userid !== (int) $USER->id || !self::revisable($record, $now)) {
                return new \moodle_exception('requestnotrevisable', 'quizaccess_presencial');
            }
            $token = 'q1_' . bin2hex(random_bytes(32));
            $DB->update_record('quizaccess_presencial_req', (object) [
                'id' => $requestid, 'qrtokenhash' => self::hash($token), 'qrexpiresat' => $now + 60,
            ]);
            return $token;
        });
        if ($result instanceof \moodle_exception) {
            throw $result;
        }
        return $result;
    }

    /**
     * Validate and consume the code in the same transaction as the decision.
     *
     * @param int $requestid Release request identifier.
     * @param string $token Presented QR token.
     * @return release_request Authorized request.
     */
    public static function authorize(int $requestid, string $token): release_request {
        global $DB;
        $service = new release_request_service();
        try {
            $request = $service->get($requestid);
        } catch (\moodle_exception $exception) {
            self::log_invalid();
            throw $exception;
        }
        try {
            $result = quiz_lock::execute($request->quizid, function () use ($DB, $service, $request, $requestid, $token) {
                // Match request creation: configuration before request, then validate time.
                self::lock_configuration($request->quizid);
                $record = $DB->get_record_sql(
                    'SELECT * FROM {quizaccess_presencial_req} WHERE id = :id FOR UPDATE',
                    ['id' => $requestid],
                    MUST_EXIST
                );
                $now = \core\di::get(\core\clock::class)->time();
                if (
                    !preg_match('/^q1_[0-9a-f]{64}$/D', $token) || empty($record->qrtokenhash) ||
                        !hash_equals($record->qrtokenhash, self::hash($token)) ||
                        (int) $record->qrexpiresat <= $now || !self::revisable($record, $now)
                ) {
                    self::log_invalid();
                    return null;
                }
                try {
                    $result = $service->authorize($requestid);
                } catch (\moodle_exception $exception) {
                    return $exception;
                }
                if ($result->state !== release_request::STATE_AUTHORIZED) {
                    return null;
                }
                $DB->update_record('quizaccess_presencial_req', (object) [
                    'id' => $requestid, 'qrtokenhash' => null, 'qrexpiresat' => 0,
                ]);
                return $result;
            });
        } catch (\moodle_exception $exception) {
            self::log_invalid();
            throw $exception;
        }
        if ($result instanceof \moodle_exception) {
            self::log_invalid();
            throw $result;
        }
        if ($result === null) {
            throw new \moodle_exception('qrinvalid', 'quizaccess_presencial');
        }
        return $result;
    }

    /**
     * Lock the configuration before any request row, matching request creation and decisions.
     *
     * @param int $quizid Quiz identifier.
     * @return \stdClass|null Current configuration, if any.
     */
    private static function lock_configuration(int $quizid): ?\stdClass {
        global $DB;
        return $DB->get_record_sql(
            'SELECT * FROM {quizaccess_presencial} WHERE quizid = :quizid FOR UPDATE',
            ['quizid' => $quizid],
        ) ?: null;
    }

    /**
     * Record an invalid use without retaining its secret.
     */
    private static function log_invalid(): void {
        \quizaccess_presencial\event\qr_invalid::create(['context' => \context_system::instance()])->trigger();
    }

    /**
     * Check whether a decision may still be revised.
     *
     * @param \stdClass $record Current locked request.
     * @param int $now Current Unix time.
     * @return bool Whether the request accepts a QR decision.
     */
    private static function revisable(\stdClass $record, int $now): bool {
        // QR refusal must not materialize an expiry, unlike an ordinary decision.
        return !empty($record->active) && in_array($record->state, release_request::revisable_states(), true) &&
            ($record->state === release_request::STATE_REJECTED || (int) $record->expiresat > $now);
    }

    /**
     * Hash a QR token using its own purpose identifier.
     *
     * @param string $token QR token.
     * @return string One-way token hash.
     */
    private static function hash(string $token): string {
        return hash('sha256', 'quizaccess_presencial:qr:' . $token);
    }
}
