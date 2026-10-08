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
 * Current user's standalone application-panel module.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class applications {
    /**
     * Whether the current user's menu should expose My applications.
     *
     * Professors without a delegation enter through the quiz's own menu instead.
     *
     * @return bool Whether an eligible account has a current or future delegation.
     */
    public static function has_delegations(): bool {
        global $DB, $USER;

        if (!applicator_eligibility::is_eligible_user((int) $USER->id)) {
            return false;
        }
        [$from, $delegated, $params] = self::query((int) $USER->id, \core\di::get(\core\clock::class)->time());
        return $DB->record_exists_sql("SELECT 1 {$from} AND {$delegated}", $params);
    }

    /**
     * Find current and future authorized applications without requiring enrolment.
     *
     * @param int $page Zero-based page number.
     * @param int $pagesize Items per page, limited to 100.
     * @return array Total and application items, ordered by authorization start and quiz id.
     *     Each item includes state (current, future or teacher), canoperate and pendingcount (null when unavailable).
     */
    public static function get_page(int $page = 0, int $pagesize = 20): array {
        return self::find(max(0, $page), max(1, min(100, $pagesize)));
    }

    /**
     * Share effective delegation and available-application conditions across discovery paths.
     *
     * @param int $userid Current user id.
     * @param int $now Time used consistently for this discovery.
     * @return array Query source, effective delegation expression and named parameters.
     */
    private static function query(int $userid, int $now): array {
        $delegated = "EXISTS (
                        SELECT 1 FROM {quizaccess_presencial_delegation} d
                         WHERE d.quizid = q.id AND d.userid = :userid AND d.timerevoked = 0
                               AND d.timeopen = p.timeopen AND d.timeclose = p.timeclose
                    )";
        $from = "FROM {quizaccess_presencial} p
               JOIN {quiz} q ON q.id = p.quizid
               JOIN {course} c ON c.id = q.course
               JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = c.id
               JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
              WHERE p.enabled = 1 AND p.timeclose > :now AND cm.deletioninprogress = 0";
        return [$from, $delegated, ['modulename' => 'quiz', 'now' => $now, 'userid' => $userid]];
    }

    /**
     * Resolve application authority without requiring academic access.
     *
     * @param \stdClass $application Quiz module and effective delegation from discovery.
     * @return bool Whether delegation or contextual management authorizes access.
     */
    private static function can_access(\stdClass $application): bool {
        return $application->delegated || has_capability('mod/quiz:manage', \context_module::instance($application->cmid));
    }

    /**
     * Resolve authorized quiz ids before applying database pagination.
     *
     * @param int $page Zero-based page.
     * @param int $pagesize Items per page.
     * @param int|null $cmid Restrict to one application when opening its page.
     * @return array Total and paginated items.
     */
    private static function find(int $page, int $pagesize, ?int $cmid = null): array {
        global $DB, $USER;

        if (!applicator_eligibility::is_eligible_user((int) $USER->id)) {
            return ['total' => 0, 'items' => []];
        }
        $now = \core\di::get(\core\clock::class)->time();
        [$from, $delegated, $params] = self::query((int) $USER->id, $now);
        if ($cmid !== null) {
            $from .= ' AND cm.id = :cmid';
            $params['cmid'] = $cmid;
        }
        $candidates = $DB->get_recordset_sql(
            "SELECT q.id AS quizid, cm.id AS cmid, CASE WHEN {$delegated} THEN 1 ELSE 0 END AS delegated {$from}",
            $params,
        );
        $quizids = [];
        try {
            foreach ($candidates as $candidate) {
                if (self::can_access($candidate)) {
                    $quizids[] = (int) $candidate->quizid;
                }
            }
        } finally {
            $candidates->close();
        }
        if (!$quizids) {
            return ['total' => 0, 'items' => []];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'applicationquiz');
        $records = $DB->get_records_sql(
            "SELECT q.id AS quizid, cm.id AS cmid, c.id AS courseid,
                    c.fullname AS coursename, q.name AS quizname, p.timeopen, p.timeclose,
                    CASE WHEN {$delegated} THEN 1 ELSE 0 END AS delegated {$from} AND q.id {$insql}
           ORDER BY p.timeopen ASC, q.id ASC",
            array_merge($params, $inparams),
            $page * $pagesize,
            $pagesize,
        );
        $items = [];
        foreach ($records as $record) {
            // Recheck authority on the selected records, not just the earlier candidate ids.
            if (!self::can_access($record)) {
                continue;
            }
            $item = (array) $record;
            $item['canoperate'] = (int) $record->timeopen <= $now;
            $item['state'] = $record->delegated ? ($item['canoperate'] ? 'current' : 'future') : 'teacher';
            unset($item['delegated']);
            // Release requests are introduced by issue #10; their count is not available in this delivery.
            $item['pendingcount'] = null;
            $items[] = $item;
        }
        return ['total' => count($quizids), 'items' => $items];
    }

    /**
     * Open a current or future application with fresh authorization checks.
     *
     * @param int $cmid Quiz course-module id.
     * @return array Same application metadata as get_page; canoperate is false before the period starts.
     * @throws \moodle_exception When the current user has no operational access.
     */
    public static function get_application(int $cmid): array {
        $items = self::find(0, 1, $cmid)['items'];
        if ($items) {
            return $items[0];
        }
        throw new \moodle_exception('applicationaccessdenied', 'quizaccess_presencial');
    }
}
