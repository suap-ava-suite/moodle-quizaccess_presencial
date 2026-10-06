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
 * Serialize invitation and configuration transitions for one quiz.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_lock {
    /** @var array<int, bool> Quiz locks held by this request. */
    private static array $held = [];

    /**
     * Run an operation atomically, including when the caller owns an outer transaction.
     *
     * @param int $quizid Quiz identifier.
     * @param callable $operation Operation under the lock.
     * @return mixed Operation result.
     */
    public static function execute(int $quizid, callable $operation): mixed {
        global $DB;

        if (isset(self::$held[$quizid])) {
            return $operation();
        }
        $factory = \core\lock\lock_config::get_lock_factory('quizaccess_presencial');
        $lock = $factory->get_lock('quiz:' . $quizid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            self::$held[$quizid] = true;
            $transaction = $DB->start_delegated_transaction();
            try {
                // Keep the row lock until the outer Moodle transaction commits, on both supported databases.
                $DB->get_records_sql('SELECT id FROM {quiz} WHERE id = :id FOR UPDATE', ['id' => $quizid]);
                $result = $operation();
                $transaction->allow_commit();
                return $result;
            } catch (\Throwable $exception) {
                $transaction->rollback($exception);
            }
        } finally {
            unset(self::$held[$quizid]);
            $lock->release();
        }
    }
}
