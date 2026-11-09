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

namespace quizaccess_presencial\local;

use quizaccess_presencial\event\delegation_created;
use quizaccess_presencial\event\delegation_idempotent;
use quizaccess_presencial\event\delegation_updated;

/**
 * Application boundary for creating and revoking quiz delegations.
 *
 * All mutations acquire the quiz row lock before reading the configuration or delegations.
 * The database retains that lock until the outermost transaction ends, including nested calls
 * from invitation acceptance on the same connection.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class delegation_manager {
    /**
     * Include eligible users in the current quiz authorization period.
     *
     * Repeating an inclusion for a current delegation leaves that record
     * unchanged and emits an idempotent event. Expired or revoked records are
     * never reused.
     *
     * @param int $quizid Quiz id.
     * @param int[] $userids Users to include.
     * @param int $actorid User performing the operation.
     * @param string $origin Origin recorded on the delegation.
     * @param int|null $now Current timestamp, for deterministic tests.
     * @return array Delegation records created or already current, keyed by user id.
     */
    public static function include_users(
        int $quizid,
        array $userids,
        int $actorid,
        string $origin = 'direct',
        ?int $now = null,
    ): array {
        global $DB;

        $context = self::quiz_context($quizid);
        require_capability('mod/quiz:manage', $context, $actorid);

        $result = quiz_lock::execute($quizid, function () use ($quizid, $userids, $actorid, $origin, $now, $DB) {
            $now ??= \core\di::get(\core\clock::class)->time();
            self::synchronize_configuration($quizid, null, $now);
            $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $quizid], '*', MUST_EXIST);
            if (!$configuration->enabled || $configuration->timeclose <= $now) {
                return new \moodle_exception('delegationperiodclosed', 'quizaccess_presencial');
            }

            $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
            foreach ($userids as $userid) {
                if (!self::is_eligible_user($userid)) {
                    return new \moodle_exception('ineligibleuser', 'quizaccess_presencial');
                }
            }

            sort($userids, SORT_NUMERIC);
            $result = [];
            foreach ($userids as $userid) {
                $included = self::create_or_find($quizid, $userid, $actorid, $origin, $configuration, $now);
                $result[$userid] = $included['delegation'];
            }
            return $result;
        });
        if ($result instanceof \moodle_exception) {
            throw $result;
        }
        return $result;
    }

    /**
     * Create or find the delegation resulting from a validated invitation.
     *
     * Invitation validation is deliberately owned by the invitation boundary;
     * this method only applies its resulting delegation with the same lifecycle
     * and audit semantics as a direct inclusion.
     *
     * @param int $quizid Quiz id.
     * @param int $userid Authenticated account accepting the invitation.
     * @param int|null $now Current timestamp, for deterministic tests.
     * @return array Delegation record and whether it was created.
     */
    public static function accept_invitation(int $quizid, int $userid, ?int $now = null): array {
        global $DB;

        $result = quiz_lock::execute($quizid, function () use ($quizid, $userid, $now, $DB) {
            $now ??= \core\di::get(\core\clock::class)->time();
            if (!self::is_eligible_user($userid)) {
                return new \moodle_exception('ineligibleuser', 'quizaccess_presencial');
            }
            self::synchronize_configuration($quizid, null, $now);
            $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $quizid], '*', MUST_EXIST);
            if (!$configuration->enabled || $configuration->timeclose <= $now) {
                return new \moodle_exception('delegationperiodclosed', 'quizaccess_presencial');
            }

            return self::create_or_find($quizid, $userid, $userid, 'invite', $configuration, $now);
        });
        if ($result instanceof \moodle_exception) {
            throw $result;
        }
        return $result;
    }

    /**
     * Revoke a delegation immediately.
     *
     * @param int $quizid Quiz owning the delegation.
     * @param int $delegationid Delegation id.
     * @param int $actorid User performing the operation.
     * @param int|null $now Current timestamp, for deterministic tests.
     * @return bool Whether a current delegation was revoked.
     */
    public static function revoke(int $quizid, int $delegationid, int $actorid, ?int $now = null): bool {
        global $DB;

        $context = self::quiz_context($quizid);
        require_capability('mod/quiz:manage', $context, $actorid);
        $result = quiz_lock::execute($quizid, function () use ($quizid, $delegationid, $actorid, $now, $DB) {
            $now ??= \core\di::get(\core\clock::class)->time();
            $delegation = $DB->get_record('quizaccess_presencial_delegation', [
                'id' => $delegationid,
                'quizid' => $quizid,
            ]);
            if (!$delegation) {
                return new \moodle_exception('delegationnotfound', 'quizaccess_presencial');
            }
            if ($delegation->timerevoked) {
                return false;
            }

            $delegation->timerevoked = $now;
            $delegation->revokedby = $actorid;
            $delegation->timemodified = $now;
            $DB->update_record('quizaccess_presencial_delegation', $delegation);
            self::trigger_event($delegation, $actorid, 'revoked', $delegation->origin);
            return true;
        });
        if ($result instanceof \moodle_exception) {
            throw $result;
        }
        return $result;
    }

    /**
     * Return current and future non-revoked delegations for a quiz.
     *
     * @param int $quizid Quiz id.
     * @param int|null $now Current timestamp.
     * @return array Delegations keyed by id.
     */
    public static function list_current(int $quizid, ?int $now = null): array {
        global $DB;

        $context = self::quiz_context($quizid);
        require_capability('mod/quiz:manage', $context);
        self::synchronize_configuration($quizid, null, $now);
        $now ??= \core\di::get(\core\clock::class)->time();
        $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $quizid]);
        if (!$configuration || !$configuration->enabled) {
            return [];
        }
        return $DB->get_records_select(
            'quizaccess_presencial_delegation',
            'quizid = :quizid AND timerevoked = 0 AND timeexpired = 0 AND timeclose > :now AND timeopen = :timeopen',
            [
                'quizid' => $quizid,
                'now' => $now,
                'timeopen' => $configuration->timeopen,
            ],
            'timecreated ASC',
        );
    }

    /**
     * Check whether an account can currently operate as an applicator for a quiz.
     *
     * @param int $quizid Quiz id.
     * @param int $userid User id.
     * @param int|null $now Current timestamp.
     * @return bool Whether a valid, non-revoked delegation covers the current time.
     */
    public static function is_active_applicator(int $quizid, int $userid, ?int $now = null): bool {
        global $DB;

        if (!self::is_eligible_user($userid)) {
            return false;
        }
        self::synchronize_configuration($quizid, null, $now);
        $now ??= \core\di::get(\core\clock::class)->time();
        $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $quizid]);
        if (!$configuration || !$configuration->enabled || $now < $configuration->timeopen || $now >= $configuration->timeclose) {
            return false;
        }
        return $DB->record_exists_select(
            'quizaccess_presencial_delegation',
            'quizid = :quizid AND userid = :userid AND timerevoked = 0 AND timeexpired = 0
                AND timeopen = :timeopen
                AND timeopen <= :nowopen AND timeclose > :nowclose',
            [
                'quizid' => $quizid,
                'userid' => $userid,
                'timeopen' => $configuration->timeopen,
                'nowopen' => $now,
                'nowclose' => $now,
            ],
        );
    }

    /**
     * Materialize irreversible delegation transitions under the quiz lock.
     *
     * Shortening a period never extends a delegation's original deadline. A new future
     * start retires its team; corrections to an ongoing start preserve current delegations.
     * Disablement alone suspends valid delegations without ending them.
     *
     * @param int $quizid Quiz id.
     * @param \stdClass|null $previous Previous configuration when saving settings.
     * @param int|null $now Current timestamp.
     */
    public static function synchronize_configuration(int $quizid, ?\stdClass $previous = null, ?int $now = null): void {
        global $DB, $USER;

        quiz_lock::execute($quizid, function () use ($quizid, $previous, $now, $DB, $USER): void {
            $now ??= \core\di::get(\core\clock::class)->time();
            $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $quizid]);
            if (!$configuration) {
                return;
            }
            $delegations = $DB->get_records('quizaccess_presencial_delegation', [
                'quizid' => $quizid, 'timerevoked' => 0, 'timeexpired' => 0,
            ]);
            $newperiod = $previous && authorization_period::starts_new_period($previous, $configuration, $now);
            foreach ($delegations as $delegation) {
                $end = min((int) $delegation->timeclose, (int) $configuration->timeclose);
                $action = null;
                $startchanged = (int) $delegation->timeopen !== (int) $configuration->timeopen;
                $retired = $startchanged && (!$previous || $newperiod ||
                    (int) $delegation->timeopen !== (int) $previous->timeopen);
                if ($end <= $now || $retired) {
                    $delegation->timeexpired = $now;
                    $action = 'expired';
                } else if ($end < (int) $delegation->timeclose) {
                    $action = 'shortened';
                } else if ($startchanged) {
                    $action = 'period_changed';
                } else if ($previous && (bool) $previous->enabled !== (bool) $configuration->enabled) {
                    $action = $configuration->enabled ? 'resumed' : 'suspended';
                }
                if ($action !== null) {
                    if (!$delegation->timeexpired) {
                        $delegation->timeopen = $configuration->timeopen;
                    }
                    $delegation->timeclose = $end;
                    $delegation->timemodified = $now;
                    $DB->update_record('quizaccess_presencial_delegation', $delegation);
                    self::trigger_event($delegation, (int) $USER->id, $action, $delegation->origin);
                }
            }
        });
    }

    /**
     * Expire due delegations through the same transition used by access validation.
     */
    public static function expire_due(): void {
        global $DB;

        $now = \core\di::get(\core\clock::class)->time();
        $quizids = $DB->get_fieldset_sql(
            'SELECT DISTINCT d.quizid
               FROM {quizaccess_presencial_delegation} d
               JOIN {quizaccess_presencial} p ON p.quizid = d.quizid
              WHERE d.timerevoked = 0 AND d.timeexpired = 0
                    AND (d.timeclose <= :now OR p.timeclose <= :periodend OR d.timeopen <> p.timeopen)',
            ['now' => $now, 'periodend' => $now],
        );
        foreach ($quizids as $quizid) {
            self::synchronize_configuration((int) $quizid);
        }
    }

    /**
     * Check whether a Moodle account can receive a delegation.
     *
     * @param int $userid User id.
     * @return bool Whether the account is eligible.
     */
    public static function is_eligible_user(int $userid): bool {
        return applicator_eligibility::is_eligible_user($userid);
    }

    /**
     * Get the module context for a quiz.
     *
     * @param int $quizid Quiz id.
     * @return \context_module Module context.
     */
    private static function quiz_context(int $quizid): \context_module {
        $cm = get_coursemodule_from_instance('quiz', $quizid, 0, false, MUST_EXIST);
        return \context_module::instance($cm->id);
    }

    /**
     * Create a delegation or return its current equivalent inside the caller's transaction.
     *
     * @param int $quizid Quiz id.
     * @param int $userid Delegated account.
     * @param int $actorid User performing the operation.
     * @param string $origin Delegation origin.
     * @param \stdClass $configuration Current quiz configuration.
     * @param int $now Current timestamp.
     * @return array Delegation record and whether it was created.
     */
    private static function create_or_find(
        int $quizid,
        int $userid,
        int $actorid,
        string $origin,
        \stdClass $configuration,
        int $now,
    ): array {
        global $DB;

        $current = $DB->get_record_select(
            'quizaccess_presencial_delegation',
            'quizid = :quizid AND userid = :userid AND timerevoked = 0 AND timeexpired = 0 AND timeclose > :now
                AND timeopen = :timeopen',
            [
                'quizid' => $quizid,
                'userid' => $userid,
                'now' => $now,
                'timeopen' => $configuration->timeopen,
            ],
            '*',
        );
        if ($current) {
            self::trigger_event($current, $actorid, 'idempotent', $origin);
            return ['delegation' => $current, 'created' => false];
        }
        $delegation = (object) [
            'quizid' => $quizid,
            'userid' => $userid,
            'timeopen' => $configuration->timeopen,
            'timeclose' => $configuration->timeclose,
            'origin' => $origin,
            'timecreated' => $now,
            'timemodified' => $now,
            'timerevoked' => 0,
            'revokedby' => 0,
            'timeexpired' => 0,
        ];
        $delegation->id = $DB->insert_record('quizaccess_presencial_delegation', $delegation);
        self::trigger_event($delegation, $actorid, 'created', $origin);
        return ['delegation' => $delegation, 'created' => true];
    }

    /**
     * Trigger the common delegation event.
     *
     * @param \stdClass $delegation Delegation record.
     * @param int $actorid Acting user.
     * @param string $action Event action.
     * @param string $origin Origin of the operation.
     */
    private static function trigger_event(\stdClass $delegation, int $actorid, string $action, string $origin): void {
        $eventclass = match ($action) {
            'created' => delegation_created::class,
            'idempotent' => delegation_idempotent::class,
            default => delegation_updated::class,
        };
        $event = $eventclass::create([
            'context' => self::quiz_context((int) $delegation->quizid),
            'objectid' => $delegation->id,
            'userid' => $actorid,
            'relateduserid' => $delegation->userid,
            'other' => ['action' => $action, 'origin' => $origin],
        ]);
        $event->trigger();
    }
}
