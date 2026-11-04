<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial\local;

/**
 * Temporary, single-use codes for a student's release request.
 *
 * @package quizaccess_presencial
 * @copyright 2026 SUAP AVA Suite
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qr_authorization {
    /** Issue a fresh code for the current student's actionable request. */
    public static function issue(int $requestid): string {
        global $DB, $USER;
        $service = new release_request_service();
        $request = $service->get($requestid);
        return quiz_lock::execute($request->quizid, function () use ($DB, $USER, $requestid) {
            $record = $DB->get_record_sql(
                'SELECT * FROM {quizaccess_presencial_req} WHERE id = :id FOR UPDATE', ['id' => $requestid], MUST_EXIST
            );
            $now = \core\di::get(\core\clock::class)->time();
            if ((int) $record->userid !== (int) $USER->id || !self::revisable($record, $now)) {
                throw new \moodle_exception('requestnotrevisable', 'quizaccess_presencial');
            }
            $token = 'q1_' . bin2hex(random_bytes(32));
            $DB->update_record('quizaccess_presencial_req', (object) [
                'id' => $requestid, 'qrtokenhash' => self::hash($token), 'qrexpiresat' => $now + 60,
            ]);
            return $token;
        });
    }

    /** Validate and consume the code in the same transaction as the decision. */
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
            $result = quiz_lock::execute($request->quizid, function () use ($DB, $service, $requestid, $token) {
                $record = $DB->get_record_sql(
                    'SELECT * FROM {quizaccess_presencial_req} WHERE id = :id FOR UPDATE', ['id' => $requestid], MUST_EXIST
                );
                $now = \core\di::get(\core\clock::class)->time();
                if (!preg_match('/^q1_[0-9a-f]{64}$/D', $token) || empty($record->qrtokenhash) ||
                        !hash_equals($record->qrtokenhash, self::hash($token)) ||
                        (int) $record->qrexpiresat <= $now || !self::revisable($record, $now)) {
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

    /** Record an invalid use without retaining its secret. */
    private static function log_invalid(): void {
        \quizaccess_presencial\event\qr_invalid::create(['context' => \context_system::instance()])->trigger();
    }

    /** Whether a decision may still be revised. */
    private static function revisable(\stdClass $record, int $now): bool {
        return !empty($record->active) && in_array($record->state, ['pending', 'authorized', 'rejected'], true) &&
            ($record->state === 'rejected' || (int) $record->expiresat > $now);
    }

    /** Domain-separated hash of a QR token. */
    private static function hash(string $token): string {
        return hash('sha256', 'quizaccess_presencial:qr:' . $token);
    }
}
