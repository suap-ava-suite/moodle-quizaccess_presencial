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
use quizaccess_presencial\local\quiz_lock;

/**
 * Describe and remove personal data associated with the current invitation.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    /**
     * Describe the invitation creator and lifecycle data.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quizaccess_presencial_invite', [
            'createdby' => 'privacy:metadata:invite:createdby',
            'generation' => 'privacy:metadata:invite:generation',
            'state' => 'privacy:metadata:invite:state',
            'timecreated' => 'privacy:metadata:invite:timecreated',
            'timemodified' => 'privacy:metadata:invite:timemodified',
            'timeexpires' => 'privacy:metadata:invite:timeexpires',
        ], 'privacy:metadata:invite');
        return $collection;
    }

    /**
     * Find quiz contexts containing the user's current invitation.
     *
     * @param int $userid User ID.
     * @return contextlist Matching module contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if ($userid <= 0) {
            return $contextlist;
        }
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
        return $contextlist;
    }

    /**
     * Find invitation creators within this quiz context.
     *
     * @param userlist $userlist Users in the requested context.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $quizid = self::quizid_for_context($userlist->get_context());
        if (!$quizid) {
            return;
        }
        $userlist->add_from_sql('createdby',
            'SELECT createdby FROM {quizaccess_presencial_invite} WHERE quizid = :quizid AND createdby > 0',
            ['quizid' => $quizid]);
    }

    /**
     * Export only the approved user's invitation lifecycle data.
     *
     * @param approved_contextlist $contextlist Approved contexts and user.
     * @return void
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
            $record = $DB->get_record('quizaccess_presencial_invite', [
                'quizid' => $quizid, 'createdby' => $userid,
            ], 'state,generation,timecreated,timemodified,timeexpires');
            if (!$record) {
                continue;
            }
            $data = (object) [
                'state' => $record->state,
                'generation' => (int) $record->generation,
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified),
                'timeexpires' => transform::datetime($record->timeexpires),
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'quizaccess_presencial')], $data);
        }
    }

    /**
     * Remove invitations for all users within the requested quiz context.
     *
     * @param \context $context Requested context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        $quizid = self::quizid_for_context($context);
        if (!$quizid) {
            return;
        }
        quiz_lock::execute($quizid, function () use ($quizid, $DB): void {
            $DB->delete_records('quizaccess_presencial_invite', ['quizid' => $quizid]);
        });
    }

    /**
     * Anonymize the user's invitation in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts and user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            self::anonymize_creators($context, [$userid]);
        }
    }

    /**
     * Anonymize invitations created by approved users in this quiz context.
     *
     * @param approved_userlist $userlist Approved users and context.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::anonymize_creators($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Erase the creator only if the current invitation still belongs to an approved user.
     *
     * @param \context $context Requested context.
     * @param array $userids Approved creator IDs.
     * @return void
     */
    private static function anonymize_creators(\context $context, array $userids): void {
        global $DB;

        $quizid = self::quizid_for_context($context);
        if (!$quizid || !$userids) {
            return;
        }
        $userids = array_map('intval', $userids);
        quiz_lock::execute($quizid, function () use ($quizid, $userids, $DB): void {
            $record = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $quizid], 'id,createdby');
            if (!$record || (int) $record->createdby <= 0 || !in_array((int) $record->createdby, $userids, true)) {
                return;
            }
            // Retain the generation so a pre-erasure management form remains stale after regeneration.
            $DB->update_record('quizaccess_presencial_invite', (object) [
                'id' => $record->id,
                'createdby' => 0,
                'state' => 'disabled',
                'tokenhash' => '',
                'timecreated' => 0,
                'timemodified' => 0,
                'timeexpires' => 0,
            ]);
        });
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
