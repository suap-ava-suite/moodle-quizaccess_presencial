<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial\local;

/**
 * Cross-request lock held from authorization claim until Moodle starts the attempt.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_start_guard {
    /** @var array<int, \core\lock\lock> Locks held by this PHP request. */
    private static array $locks = [];

    /**
     * Acquire a request-specific lock.
     *
     * @param int $requestid Request id.
     * @return bool Whether this process acquired the lock.
     */
    public static function acquire(int $requestid): bool {
        if (isset(self::$locks[$requestid])) {
            return false;
        }
        $factory = \core\lock\lock_config::get_lock_factory('quizaccess_presencial_attempt_start');
        $lock = $factory->get_lock('quizaccess_presencial_request_' . $requestid, 0, DAYSECS);
        if (!$lock) {
            return false;
        }
        self::$locks[$requestid] = $lock;
        return true;
    }

    /**
     * Release a request-specific lock if this process holds it.
     *
     * @param int $requestid Request id.
     */
    public static function release(int $requestid): void {
        if (!isset(self::$locks[$requestid])) {
            return;
        }
        self::$locks[$requestid]->release();
        unset(self::$locks[$requestid]);
    }
}
