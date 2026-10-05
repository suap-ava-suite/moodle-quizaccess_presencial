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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace quizaccess_presencial\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for quiz application delegations.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe delegation data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection Metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'quizaccess_presencial_delegation',
            [
                'quizid' => 'privacy:metadata:delegation:quizid',
                'userid' => 'privacy:metadata:delegation:userid',
                'timeopen' => 'privacy:metadata:delegation:timeopen',
                'timeclose' => 'privacy:metadata:delegation:timeclose',
                'origin' => 'privacy:metadata:delegation:origin',
                'timecreated' => 'privacy:metadata:delegation:timecreated',
                'timemodified' => 'privacy:metadata:delegation:timemodified',
                'timerevoked' => 'privacy:metadata:delegation:timerevoked',
                'revokedby' => 'privacy:metadata:delegation:revokedby',
            ],
            'privacy:metadata:delegation',
        );
        return $collection;
    }

    /**
     * Find quiz contexts containing data for a user.
     *
     * @param int $userid User id.
     * @return contextlist Contexts containing data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            self::context_sql('d.userid = :userid OR d.revokedby = :userid2'),
            [
                'userid' => $userid,
                'userid2' => $userid,
                'modulename' => 'quiz',
                'contextlevel' => CONTEXT_MODULE,
            ],
        );
        return $contextlist;
    }

    /**
     * List users with data in a quiz module context.
     *
     * @param userlist $userlist User list.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!self::is_quiz_context($context)) {
            return;
        }
        $quizid = self::quizid_from_context($context);
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {quizaccess_presencial_delegation} WHERE quizid = :quizid',
            ['quizid' => $quizid],
        );
        $userlist->add_from_sql(
            'revokedby',
            'SELECT revokedby FROM {quizaccess_presencial_delegation}
              WHERE quizid = :quizid AND revokedby <> 0',
            ['quizid' => $quizid],
        );
    }

    /**
     * Export a user's delegation data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $quizid = self::quizid_from_context($context);
            $records = $DB->get_records_select(
                'quizaccess_presencial_delegation',
                'quizid = :quizid AND (userid = :userid OR revokedby = :userid2)',
                ['quizid' => $quizid, 'userid' => $userid, 'userid2' => $userid],
                'id ASC',
            );
            $index = 0;
            foreach ($records as $record) {
                $index++;
                writer::with_context($context)->export_data(
                    ['delegations', $index],
                    (object) [
                        'quizid' => $record->quizid,
                        'userid' => $record->userid,
                        'timeopen' => transform::datetime($record->timeopen),
                        'timeclose' => transform::datetime($record->timeclose),
                        'origin' => $record->origin,
                        'timecreated' => transform::datetime($record->timecreated),
                        'timemodified' => transform::datetime($record->timemodified),
                        'timerevoked' => $record->timerevoked ? transform::datetime($record->timerevoked) : null,
                        'revokedby' => $record->revokedby,
                    ],
                );
            }
        }
    }

    /**
     * Delete all delegation data in a module context.
     *
     * @param \context $context Module context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (self::is_quiz_context($context)) {
            $DB->delete_records('quizaccess_presencial_delegation', ['quizid' => self::quizid_from_context($context)]);
        }
    }

    /**
     * Delete one user's data in approved module contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $quizid = self::quizid_from_context($context);
            $DB->delete_records('quizaccess_presencial_delegation', ['quizid' => $quizid, 'userid' => $userid]);
            $DB->set_field_select(
                'quizaccess_presencial_delegation',
                'revokedby',
                0,
                'quizid = :quizid AND revokedby = :userid',
                ['quizid' => $quizid, 'userid' => $userid],
            );
        }
    }

    /**
     * Delete multiple users' data in one context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $userids = $userlist->get_userids();
        if (!$userids || !self::is_quiz_context($userlist->get_context())) {
            return;
        }
        $quizid = self::quizid_from_context($userlist->get_context());
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
        $params['quizid'] = $quizid;
        $DB->delete_records_select(
            'quizaccess_presencial_delegation',
            "quizid = :quizid AND userid {$insql}",
            $params,
        );
        $DB->set_field_select(
            'quizaccess_presencial_delegation',
            'revokedby',
            0,
            "quizid = :quizid AND revokedby {$insql}",
            $params,
        );
    }

    /**
     * Build the context query shared by privacy lookups.
     *
     * @param string $condition User data condition.
     * @return string SQL query.
     */
    private static function context_sql(string $condition): string {
        return "SELECT c.id
                  FROM {quizaccess_presencial_delegation} d
                  JOIN {quiz} q ON q.id = d.quizid
                  JOIN {course_modules} cm ON cm.instance = q.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {context} c ON c.instanceid = cm.id AND c.contextlevel = :contextlevel
                 WHERE {$condition}";
    }

    /**
     * Resolve the quiz id for a module context.
     *
     * @param \context $context Module context.
     * @return int Quiz id.
     */
    private static function quizid_from_context(\context $context): int {
        global $DB;

        $cm = get_coursemodule_from_id('quiz', $context->instanceid, 0, false, MUST_EXIST);
        return (int) $DB->get_field('quiz', 'id', ['id' => $cm->instance], MUST_EXIST);
    }

    /**
     * Check whether a context belongs to a Quiz activity.
     *
     * @param \context $context Context to check.
     * @return bool Whether the context is a Quiz module context.
     */
    private static function is_quiz_context(\context $context): bool {
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return false;
        }
        return (bool) get_coursemodule_from_id('quiz', $context->instanceid, 0, false);
    }
}
