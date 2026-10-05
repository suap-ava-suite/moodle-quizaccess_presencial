<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial\local;

use quizaccess_presencial\event\delegation_created;
use quizaccess_presencial\event\delegation_idempotent;
use quizaccess_presencial\event\delegation_updated;

defined('MOODLE_INTERNAL') || die();

/**
 * Application boundary for creating and revoking quiz delegations.
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

        $now ??= time();
        $context = self::quiz_context($quizid);
        require_capability('mod/quiz:manage', $context, $actorid);

        $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $quizid], '*', MUST_EXIST);
        if (!$configuration->enabled || $configuration->timeclose <= $now) {
            throw new \moodle_exception('delegationperiodclosed', 'quizaccess_presencial');
        }

        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if (!$userids) {
            return [];
        }
        foreach ($userids as $userid) {
            if (!self::is_eligible_user($userid)) {
                throw new \moodle_exception('ineligibleuser', 'quizaccess_presencial');
            }
        }

        sort($userids, SORT_NUMERIC);
        $lockfactory = \core\lock\lock_config::get_lock_factory('quizaccess_presencial');
        $locks = [];
        try {
            foreach ($userids as $userid) {
                $lock = $lockfactory->get_lock("delegation:{$quizid}:{$userid}", 10);
                if (!$lock) {
                    throw new \moodle_exception('delegationlocktimeout', 'quizaccess_presencial');
                }
                $locks[] = $lock;
            }

            $transaction = $DB->start_delegated_transaction();
            $result = [];
            foreach ($userids as $userid) {
                $current = $DB->get_record_select(
                    'quizaccess_presencial_delegation',
                    'quizid = :quizid AND userid = :userid AND timerevoked = 0 AND timeclose > :now
                        AND timeopen = :timeopen AND timeclose = :timeclose',
                    [
                        'quizid' => $quizid,
                        'userid' => $userid,
                        'now' => $now,
                        'timeopen' => $configuration->timeopen,
                        'timeclose' => $configuration->timeclose,
                    ],
                    '*',
                );
                if ($current) {
                    $result[$userid] = $current;
                    self::trigger_event($current, $actorid, 'idempotent', $origin);
                    continue;
                }

                $record = (object) [
                    'quizid' => $quizid,
                    'userid' => $userid,
                    'timeopen' => $configuration->timeopen,
                    'timeclose' => $configuration->timeclose,
                    'origin' => $origin,
                    'timecreated' => $now,
                    'timemodified' => $now,
                    'timerevoked' => 0,
                    'revokedby' => 0,
                ];
                $record->id = $DB->insert_record('quizaccess_presencial_delegation', $record);
                $result[$userid] = $record;
                self::trigger_event($record, $actorid, 'created', $origin);
            }
            $transaction->allow_commit();
            return $result;
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * Revoke a delegation immediately.
     *
     * @param int $delegationid Delegation id.
     * @param int $actorid User performing the operation.
     * @param int|null $now Current timestamp, for deterministic tests.
     * @return bool Whether a current delegation was revoked.
     */
    public static function revoke(int $delegationid, int $actorid, ?int $now = null): bool {
        global $DB;

        $now ??= time();
        $delegation = $DB->get_record('quizaccess_presencial_delegation', ['id' => $delegationid], '*', MUST_EXIST);
        $context = self::quiz_context((int) $delegation->quizid);
        require_capability('mod/quiz:manage', $context, $actorid);
        if ($delegation->timerevoked) {
            return false;
        }

        $delegation->timerevoked = $now;
        $delegation->revokedby = $actorid;
        $delegation->timemodified = $now;
        $DB->update_record('quizaccess_presencial_delegation', $delegation);
        self::trigger_event($delegation, $actorid, 'revoked', $delegation->origin);
        return true;
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

        $now ??= time();
        $context = self::quiz_context($quizid);
        require_capability('mod/quiz:manage', $context);
        $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $quizid]);
        if (!$configuration || !$configuration->enabled) {
            return [];
        }
        return $DB->get_records_select(
            'quizaccess_presencial_delegation',
            'quizid = :quizid AND timerevoked = 0 AND timeclose > :now AND timeopen = :timeopen AND timeclose = :timeclose',
            [
                'quizid' => $quizid,
                'now' => $now,
                'timeopen' => $configuration->timeopen,
                'timeclose' => $configuration->timeclose,
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

        $now ??= time();
        if (!self::is_eligible_user($userid)) {
            return false;
        }
        $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $quizid]);
        if (!$configuration || !$configuration->enabled || $now < $configuration->timeopen || $now >= $configuration->timeclose) {
            return false;
        }
        return $DB->record_exists_select(
            'quizaccess_presencial_delegation',
            'quizid = :quizid AND userid = :userid AND timerevoked = 0
                AND timeopen = :timeopen AND timeclose = :timeclose
                AND timeopen <= :nowopen AND timeclose > :nowclose',
            [
                'quizid' => $quizid,
                'userid' => $userid,
                'timeopen' => $configuration->timeopen,
                'timeclose' => $configuration->timeclose,
                'nowopen' => $now,
                'nowclose' => $now,
            ],
        );
    }

    /**
     * Check whether a Moodle account can receive a delegation.
     *
     * @param int $userid User id.
     * @return bool Whether the account is eligible.
     */
    public static function is_eligible_user(int $userid): bool {
        global $CFG, $DB;

        $user = $DB->get_record('user', ['id' => $userid], 'id,deleted,suspended,confirmed,auth');
        if (!$user || $user->deleted || $user->suspended || !$user->confirmed || $user->id == $CFG->siteguest) {
            return false;
        }
        return $user->auth !== 'nologin' && is_enabled_auth($user->auth);
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
