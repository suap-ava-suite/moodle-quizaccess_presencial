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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Privacy provider for the Presencial quiz access rule.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_presencial\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use quizaccess_presencial\local\invitation;

/**
 * Describe, export, and remove persisted personal data stored by the plugin.
 *
 * This includes delegations, invitation lifecycle data, and student release requests.
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
     * Describe personal data stored for delegations, invitations, and release requests.
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
        $collection->add_database_table('quizaccess_presencial_invite', [
            'createdby' => 'privacy:metadata:invite:createdby',
            'generation' => 'privacy:metadata:invite:generation',
            'state' => 'privacy:metadata:invite:state',
            'timecreated' => 'privacy:metadata:invite:timecreated',
            'timemodified' => 'privacy:metadata:invite:timemodified',
            'timeexpires' => 'privacy:metadata:invite:timeexpires',
        ], 'privacy:metadata:invite');
        $collection->add_database_table('quizaccess_presencial_req', [
            'userid' => 'privacy:metadata:request:userid',
            'quizid' => 'privacy:metadata:request:quizid',
            'attemptnumber' => 'privacy:metadata:request:attemptnumber',
            'state' => 'privacy:metadata:request:state',
            'timecreated' => 'privacy:metadata:request:timecreated',
            'expiresat' => 'privacy:metadata:request:expiresat',
            'timemodified' => 'privacy:metadata:request:timemodified',
        ], 'privacy:metadata:request');
        return $collection;
    }

    /**
     * Find quiz contexts containing a user's delegations, invitation, or release requests.
     *
     * @param int $userid User id.
     * @return contextlist Contexts containing data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if ($userid <= 0) {
            return $contextlist;
        }
        $contextlist->add_from_sql(
            self::context_sql('d.userid = :userid OR d.revokedby = :userid2'),
            [
                'userid' => $userid,
                'userid2' => $userid,
                'modulename' => 'quiz',
                'contextlevel' => CONTEXT_MODULE,
            ],
        );
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {quizaccess_presencial_invite} i ON i.quizid = cm.instance
                 WHERE ctx.contextlevel = :contextlevel
                   AND m.name = :modname AND i.createdby = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'quiz',
            'userid' => $userid,
        ]);
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {course_modules} cm ON cm.id = ctx.instanceid
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
               JOIN {quizaccess_presencial_req} r ON r.quizid = cm.instance
              WHERE ctx.contextlevel = :contextlevel AND r.userid = :userid",
            [
                'contextlevel' => CONTEXT_MODULE,
                'modname' => 'quiz',
                'userid' => $userid,
            ],
        );
        return $contextlist;
    }

    /**
     * List users with delegation data or the current invitation in a quiz module context.
     *
     * @param userlist $userlist User list.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $quizid = self::quizid_for_context($userlist->get_context());
        if (!$quizid) {
            return;
        }
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
        $userlist->add_from_sql(
            'createdby',
            'SELECT createdby FROM {quizaccess_presencial_invite} WHERE quizid = :quizid AND createdby > 0',
            ['quizid' => $quizid],
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {quizaccess_presencial_req} WHERE quizid = :quizid',
            ['quizid' => $quizid],
        );
    }

    /**
     * Export a user's delegation data and the lifecycle data of the user's invitation, never its secret.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            $quizid = self::quizid_for_context($context);
            if (!$quizid) {
                continue;
            }
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

            $record = $DB->get_record('quizaccess_presencial_invite', [
                'quizid' => $quizid, 'createdby' => $userid,
            ], 'state,generation,timecreated,timemodified,timeexpires');
            if ($record) {
                $data = (object) [
                    'state' => $record->state,
                    'generation' => (int) $record->generation,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                    'timeexpires' => transform::datetime($record->timeexpires),
                ];
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'quizaccess_presencial')],
                    $data,
                );
            }
            $requests = $DB->get_records('quizaccess_presencial_req', [
                'quizid' => $quizid,
                'userid' => $userid,
            ], 'timecreated ASC');
            $index = 0;
            foreach ($requests as $request) {
                $index++;
                writer::with_context($context)->export_data(
                    [
                        get_string('pluginname', 'quizaccess_presencial'),
                        get_string('privacy:exportpath:request', 'quizaccess_presencial'),
                        $index,
                    ],
                    (object) [
                        'quizid' => (int) $request->quizid,
                        'attemptnumber' => (int) $request->attemptnumber,
                        'state' => $request->state,
                        'timecreated' => transform::datetime($request->timecreated),
                        'expiresat' => transform::datetime($request->expiresat),
                        'timemodified' => transform::datetime($request->timemodified),
                    ],
                );
            }
        }
    }

    /**
     * Delete all plugin data in a quiz module context and anonymize invitation data.
     *
     * @param \context $context Module context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        $quizid = self::quizid_for_context($context);
        if (!$quizid) {
            return;
        }
        $DB->delete_records('quizaccess_presencial_delegation', ['quizid' => $quizid]);
        $DB->delete_records('quizaccess_presencial_req', ['quizid' => $quizid]);
        invitation::erase_user_data($context->instanceid);
    }

    /**
     * Delete one user's delegation data and anonymize the user's invitation in approved quiz contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            $quizid = self::quizid_for_context($context);
            if (!$quizid) {
                continue;
            }
            $DB->delete_records('quizaccess_presencial_delegation', ['quizid' => $quizid, 'userid' => $userid]);
            $DB->set_field_select(
                'quizaccess_presencial_delegation',
                'revokedby',
                0,
                'quizid = :quizid AND revokedby = :userid',
                ['quizid' => $quizid, 'userid' => $userid],
            );
            $DB->delete_records('quizaccess_presencial_req', ['quizid' => $quizid, 'userid' => $userid]);
            invitation::erase_user_data($context->instanceid, [$userid]);
        }
    }

    /**
     * Delete multiple users' plugin data and anonymize their invitation in one quiz context.
     *
     * Invitation data is erased only if the current invitation still belongs to an approved user.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $userids = array_map('intval', $userlist->get_userids());
        $quizid = self::quizid_for_context($context);
        if (!$userids || !$quizid) {
            return;
        }
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
        $DB->delete_records_select(
            'quizaccess_presencial_req',
            "quizid = :quizid AND userid {$insql}",
            $params,
        );
        invitation::erase_user_data($context->instanceid, $userids);
    }

    /**
     * Build the delegation context query shared by privacy lookups.
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
     * Resolve only module contexts belonging to an actual quiz.
     *
     * @param \context $context Requested context.
     * @return int Quiz ID, or zero for another context.
     */
    private static function quizid_for_context(\context $context): int {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return 0;
        }
        $sql = "SELECT cm.instance
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid AND m.name = :modname";
        return (int) $DB->get_field_sql($sql, ['cmid' => $context->instanceid, 'modname' => 'quiz']);
    }
}
