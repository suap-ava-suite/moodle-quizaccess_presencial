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
 * Authorized, server-paginated operational requests for a single quiz.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class operational_queue {
    /**
     * Read actionable requests and only the permitted operational identity.
     *
     * @param int $cmid Quiz course-module id.
     * @param int $page Zero-based page.
     * @param int $pagesize Page size, at most 100.
     * @return array Total and ordered items. Future applications expose no student data.
     */
    public static function get_page(int $cmid, int $page = 0, int $pagesize = 20): array {
        global $DB, $PAGE;

        $application = applications::get_application($cmid);
        if (!$application['canoperate']) {
            return ['total' => 0, 'items' => []];
        }
        $now = \core\di::get(\core\clock::class)->time();
        $from = "FROM {quizaccess_presencial_req} r
                 JOIN {user} u ON u.id = r.userid
                WHERE r.quizid = :quizid AND r.active = 1 AND r.expiresat > :now
                  AND r.state IN (:pending, :authorized, :rejected)
                  AND NOT EXISTS (SELECT 1 FROM {quiz_attempts} a
                       WHERE a.quiz = r.quizid AND a.userid = r.userid AND a.attempt = r.attemptnumber AND a.preview = 0)";
        $params = ['quizid' => $application['quizid'], 'now' => $now,
            'pending' => release_request::STATE_PENDING, 'authorized' => release_request::STATE_AUTHORIZED,
            'rejected' => release_request::STATE_REJECTED];
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) {$from}", $params);
        // Apply pagination before loading identity fields, including custom profile fields.
        $records = $DB->get_records_sql(
            "SELECT r.* {$from}
             ORDER BY CASE WHEN r.state = 'pending' THEN 0 ELSE 1 END ASC,
                      CASE WHEN r.state = 'pending' THEN r.timecreated ELSE 0 END ASC,
                      CASE WHEN r.state = 'pending' THEN 0 ELSE r.timemodified END DESC, r.id ASC",
            $params,
            max(0, $page) * max(1, min(100, $pagesize)),
            max(1, min(100, $pagesize)),
        );
        $context = \context_module::instance($cmid);
        $fields = \core_user\fields::for_identity($context)->with_name()->with_userpic()->get_sql('u', true);
        $identityfields = \core_user\fields::get_identity_fields($context);
        $items = [];
        foreach ($records as $record) {
            $user = $DB->get_record_sql(
                "SELECT u.id {$fields->selects}
                FROM {user} u {$fields->joins} WHERE u.id = :studentid",
                array_merge($fields->params, ['studentid' => $record->userid]),
                MUST_EXIST
            );
            $identity = [];
            foreach ($identityfields as $field) {
                if (isset($user->$field) && $user->$field !== '') {
                    $identity[] = ['label' => \core_user\fields::get_display_name($field), 'value' => $user->$field];
                }
            }
            $picture = new \user_picture($user);
            $items[] = [
                'id' => (int) $record->id,
                'fullname' => fullname($user),
                'pictureurl' => $picture->get_url($PAGE)->out(false),
                'identity' => $identity,
                'state' => $record->state,
                'rejectionreason' => (string) ($record->rejectionreason ?? ''),
                'timecreated' => (int) $record->timecreated,
                'expiresat' => (int) $record->expiresat,
            ];
        }
        return ['total' => $total, 'items' => $items];
    }
}
