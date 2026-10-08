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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace quizaccess_presencial\local;

use core\clock;
use quizaccess_presencial\event\request_authorized;
use quizaccess_presencial\event\request_created;
use quizaccess_presencial\event\request_consumed;
use quizaccess_presencial\event\request_expired;
use quizaccess_presencial\event\request_attempt_starting;

/**
 * Transactional application service for release request transitions.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class release_request_service {
    /** @var clock */
    private clock $clock;

    /**
     * Create the service with Moodle's clock or an injected test clock.
     *
     * @param clock|null $clock Moodle clock, replaceable by deterministic test clocks.
     */
    public function __construct(?clock $clock = null) {
        $this->clock = $clock ?? \core\di::get(clock::class);
    }

    /**
     * Return the active request for an identity or create one exactly once.
     *
     * The configuration row is locked before checking and inserting. The unique index
     * remains the database-level guard against duplicate active requests.
     *
     * @param int $quizid Quiz id.
     * @param int $userid Student id.
     * @param int $attemptnumber Next attempt number.
     * @return release_request Active request.
     */
    public function ensure_pending(int $quizid, int $userid, int $attemptnumber): release_request {
        global $DB;

        if ($attemptnumber < 1) {
            throw new \invalid_parameter_exception('Attempt number must be positive.');
        }
        $now = $this->clock->time();
        $createdid = null;
        $expiredid = null;
        $tx = $DB->start_delegated_transaction();

        // This existing row serializes requests for the quiz on both supported DBs.
        $configuration = $DB->get_record_sql(
            'SELECT id, enabled FROM {quizaccess_presencial} WHERE quizid = :quizid FOR UPDATE',
            ['quizid' => $quizid],
        );
        if (!$configuration || empty($configuration->enabled)) {
            throw new \moodle_exception('ruleisdisabled', 'quizaccess_presencial');
        }

        if (
            $DB->record_exists('quiz_attempts', [
                'quiz' => $quizid,
                'userid' => $userid,
                'attempt' => $attemptnumber,
                'preview' => 0,
            ])
        ) {
            throw new \moodle_exception('requestattemptalreadyexists', 'quizaccess_presencial');
        }

        // Lock an existing active request too: expiry, authorization, and another
        // create attempt must serialize on the same row, not only on the quiz config.
        $active = $DB->get_record_sql(
            'SELECT * FROM {quizaccess_presencial_req}
              WHERE quizid = :quizid AND userid = :userid AND attemptnumber = :attemptnumber
                AND active = 1 FOR UPDATE',
            ['quizid' => $quizid, 'userid' => $userid, 'attemptnumber' => $attemptnumber],
        );
        if (
            $active && in_array($active->state, release_request::expirable_states(), true) &&
                (int) $active->expiresat <= $now
        ) {
            // A claimed Moodle start may still be creating its attempt. Its guard
            // lock keeps it active until the attempt event or the process exits.
            if ($active->state === release_request::STATE_STARTING) {
                $tx->allow_commit();
                return new release_request($active);
            }
            $this->transition($active, release_request::STATE_EXPIRED, $now);
            $expiredid = (int) $active->id;
            $active = null;
        }

        if ($active) {
            $tx->allow_commit();
            return new release_request($active);
        }

        $validity = (int) get_config('quizaccess_presencial', 'requestvalidity');
        if ($validity < 1) {
            $validity = 15;
        }
        $id = $DB->insert_record('quizaccess_presencial_req', (object) [
            'quizid' => $quizid,
            'userid' => $userid,
            'attemptnumber' => $attemptnumber,
            'state' => release_request::STATE_PENDING,
            'active' => 1,
            'timecreated' => $now,
            'expiresat' => $now + $validity * MINSECS,
            'timemodified' => $now,
        ]);
        $createdid = (int) $id;
        if ($expiredid) {
            $this->trigger_expired($expiredid);
        }
        $this->trigger_created($createdid);
        $tx->allow_commit();
        return $this->get($createdid);
    }

    /**
     * Return an owned request, expiring it synchronously if its deadline passed.
     *
     * @param int $requestid Request id.
     * @param int $userid Owning student id.
     * @return release_request Request after expiration processing.
     */
    public function read_status(int $requestid, int $userid): release_request {
        $request = $this->get($requestid);
        if ($request->userid !== $userid) {
            throw new \moodle_exception('requestnotfound', 'quizaccess_presencial');
        }
        $now = $this->clock->time();
        if (in_array($request->state, release_request::expirable_states(), true) && $request->expiresat <= $now) {
            $this->expire_one($requestid, $now);
        }
        return $this->get($requestid);
    }

    /**
     * Fetch a request without performing a state transition.
     *
     * @param int $requestid Request id.
     * @return release_request Persisted request.
     */
    public function get(int $requestid): release_request {
        global $DB;
        return new release_request($DB->get_record('quizaccess_presencial_req', ['id' => $requestid], '*', MUST_EXIST));
    }

    /**
     * Determine the next numbered, non-preview attempt for a user and quiz.
     *
     * @param int $quizid Quiz id.
     * @param int $userid User id.
     * @return int Next attempt number.
     */
    public function next_attempt_number(int $quizid, int $userid): int {
        global $DB;
        $lastnumber = $DB->get_field_sql(
            'SELECT MAX(attempt) FROM {quiz_attempts} WHERE quiz = :quizid AND userid = :userid AND preview = 0',
            ['quizid' => $quizid, 'userid' => $userid],
        );
        return (int) $lastnumber + 1;
    }

    /**
     * Find an already created non-preview attempt by its Moodle attempt number.
     *
     * @param int $quizid Quiz id.
     * @param int $userid User id.
     * @param int $attemptnumber Attempt number.
     * @return \stdClass|false Attempt record or false.
     */
    public function find_attempt(int $quizid, int $userid, int $attemptnumber) {
        global $DB;
        return $DB->get_record('quiz_attempts', [
            'quiz' => $quizid,
            'userid' => $userid,
            'attempt' => $attemptnumber,
            'preview' => 0,
        ]);
    }

    /**
     * Authorise a pending request. Repeated authorization is idempotent.
     *
     * @param int $requestid Request id.
     * @return release_request Updated request.
     */
    public function authorize(int $requestid): release_request {
        global $DB;
        $now = $this->clock->time();
        $existing = $this->get($requestid);
        $cm = get_coursemodule_from_instance('quiz', $existing->quizid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        require_capability('mod/quiz:manage', $context);
        $tx = $DB->start_delegated_transaction();
        // Serialize authorization against rule disablement and request creation.
        $configuration = $DB->get_record_sql(
            'SELECT * FROM {quizaccess_presencial} WHERE quizid = :quizid FOR UPDATE',
            ['quizid' => $existing->quizid],
        );
        if (
            !$configuration || empty($configuration->enabled) ||
                (int) $configuration->timeopen > $now || (int) $configuration->timeclose < $now
        ) {
            throw new \moodle_exception('authorizationperiodinactive', 'quizaccess_presencial');
        }
        $changed = false;
        $record = $DB->get_record_sql(
            'SELECT * FROM {quizaccess_presencial_req} WHERE id = :id FOR UPDATE',
            ['id' => $requestid],
            MUST_EXIST,
        );
        if ($record->state === release_request::STATE_PENDING && (int) $record->expiresat > $now) {
            $this->transition($record, release_request::STATE_AUTHORIZED, $now);
            $validity = (int) get_config('quizaccess_presencial', 'authorisationvalidity');
            if ($validity < 1) {
                $validity = 5;
            }
            $record->expiresat = $now + $validity * MINSECS;
            $DB->set_field('quizaccess_presencial_req', 'expiresat', $record->expiresat, ['id' => $requestid]);
            $changed = true;
        } else if ($record->state === release_request::STATE_PENDING) {
            $this->transition($record, release_request::STATE_EXPIRED, $now);
            $changed = true;
        }
        if ($changed && $record->state === release_request::STATE_AUTHORIZED) {
            $this->trigger_authorized($requestid);
        } else if ($changed) {
            $this->trigger_expired($requestid);
        }
        $tx->allow_commit();
        return $this->get($requestid);
    }

    /**
     * Claim an unexpired authorization immediately before Moodle creates the attempt.
     *
     * The short starting lease prevents the scheduled expiry task from invalidating a
     * grant between Moodle's preflight callback and its attempt-created event.
     *
     * @param int $quizid Quiz id.
     * @param int $userid Student id.
     * @param int $attemptnumber Next attempt number.
     * @return bool Whether the caller may proceed with normal attempt creation.
     */
    public function begin_authorized_attempt(int $quizid, int $userid, int $attemptnumber): bool {
        global $DB;
        $now = $this->clock->time();
        $requestid = $DB->get_field('quizaccess_presencial_req', 'id', [
            'quizid' => $quizid,
            'userid' => $userid,
            'attemptnumber' => $attemptnumber,
            'active' => 1,
            'state' => release_request::STATE_AUTHORIZED,
        ]);
        if (!$requestid || !attempt_start_guard::acquire((int) $requestid)) {
            return false;
        }
        $tx = null;
        try {
            $tx = $DB->start_delegated_transaction();
            $record = $DB->get_record_sql(
                'SELECT * FROM {quizaccess_presencial_req}
                  WHERE quizid = :quizid AND userid = :userid AND attemptnumber = :attemptnumber
                    AND active = 1 AND state = :state FOR UPDATE',
                [
                    'quizid' => $quizid,
                    'userid' => $userid,
                    'attemptnumber' => $attemptnumber,
                    'state' => release_request::STATE_AUTHORIZED,
                ],
            );
            if (!$record) {
                $tx->allow_commit();
                attempt_start_guard::release((int) $requestid);
                return false;
            }
            if (
                $DB->record_exists('quiz_attempts', [
                    'quiz' => $quizid,
                    'userid' => $userid,
                    'attempt' => $attemptnumber,
                    'preview' => 0,
                ])
            ) {
                $tx->allow_commit();
                attempt_start_guard::release((int) $requestid);
                return false;
            }
            if ((int) $record->expiresat <= $now) {
                $this->transition($record, release_request::STATE_EXPIRED, $now);
                $this->trigger_expired((int) $record->id);
                $tx->allow_commit();
                attempt_start_guard::release((int) $requestid);
                return false;
            }
            $record->state = release_request::STATE_STARTING;
            $record->expiresat = $now + 2 * MINSECS;
            $record->timemodified = $now;
            $DB->update_record('quizaccess_presencial_req', $record);
            $this->trigger_attempt_starting((int) $record->id);
            $tx->allow_commit();
            // The lock remains held until attempt_started consumes the authorization.
            return true;
        } catch (\Throwable $exception) {
            attempt_start_guard::release((int) $requestid);
            throw $exception;
        }
    }

    /**
     * Expire due pending requests. Concurrent callers may select the same ids, but
     * the row lock and state check make the transition and its event happen once.
     *
     * @return int Number of requests transitioned to expired.
     */
    public function expire_due(): int {
        global $DB;
        $now = $this->clock->time();
        [$statesql, $stateparams] = $DB->get_in_or_equal(
            release_request::expirable_states(),
            SQL_PARAMS_NAMED,
            'state',
        );
        $ids = $DB->get_fieldset_select(
            'quizaccess_presencial_req',
            'id',
            "state {$statesql} AND expiresat <= :now",
            array_merge($stateparams, ['now' => $now]),
        );
        $expired = 0;
        foreach ($ids as $id) {
            if ($this->expire_one((int) $id, $now)) {
                $expired++;
            }
        }
        return $expired;
    }

    /**
     * End pending requests and unused grants when the quiz's in-person rule is disabled.
     *
     * A starting request has already crossed the authorization gate and is in Moodle's
     * normal attempt-creation path. Keep its short lease so the attempt-started observer
     * can consume it instead of leaving a created attempt paired with an expired request.
     *
     * @param int $quizid Quiz id.
     * @return int Number of requests ended.
     */
    public function expire_for_quiz(int $quizid): int {
        global $DB;
        $ids = $DB->get_fieldset_select(
            'quizaccess_presencial_req',
            'id',
            'quizid = :quizid AND state IN (:pending, :authorized)',
            [
                'quizid' => $quizid,
                'pending' => release_request::STATE_PENDING,
                'authorized' => release_request::STATE_AUTHORIZED,
            ],
        );
        $expired = 0;
        foreach ($ids as $id) {
            if ($this->expire_one((int) $id, $this->clock->time(), true)) {
                $expired++;
            }
        }
        return $expired;
    }

    /**
     * Check whether this request is the active authorization for a quiz attempt.
     *
     * @param int $quizid Quiz id.
     * @param int $userid Student id.
     * @param int $attemptnumber Attempt number.
     * @return bool Whether the pending request was authorized.
     */
    public function is_authorized(int $quizid, int $userid, int $attemptnumber): bool {
        global $DB;
        $record = $DB->get_record('quizaccess_presencial_req', [
            'quizid' => $quizid,
            'userid' => $userid,
            'attemptnumber' => $attemptnumber,
            'active' => 1,
            'state' => release_request::STATE_AUTHORIZED,
        ]);
        if ($record && (int) $record->expiresat <= $this->clock->time()) {
            $this->expire_one((int) $record->id, $this->clock->time());
            return false;
        }
        return (bool) $record;
    }

    /**
     * Consume an active authorization after Moodle has persisted the attempt.
     *
     * Moodle emits attempt_started immediately after inserting quiz_attempts. The observer
     * retires the authorization so a deleted/reset attempt cannot reuse the same grant.
     *
     * @param int $attemptid Newly created attempt id.
     * @return bool Whether an authorization was consumed.
     */
    public function consume_for_attempt(int $attemptid): bool {
        global $DB;
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        if (!empty($attempt->preview)) {
            return false;
        }
        $now = $this->clock->time();
        $tx = $DB->start_delegated_transaction();
        $request = $DB->get_record_sql(
            'SELECT * FROM {quizaccess_presencial_req}
              WHERE quizid = :quizid AND userid = :userid AND attemptnumber = :attemptnumber
                AND active = 1 AND state IN (:authorized, :starting) FOR UPDATE',
            [
                'quizid' => $attempt->quiz,
                'userid' => $attempt->userid,
                'attemptnumber' => $attempt->attempt,
                'authorized' => release_request::STATE_AUTHORIZED,
                'starting' => release_request::STATE_STARTING,
            ],
        );
        if (!$request) {
            $tx->allow_commit();
            return false;
        }
        if ($request->state === release_request::STATE_AUTHORIZED && (int) $request->expiresat <= $now) {
            $this->transition($request, release_request::STATE_EXPIRED, $now);
            $this->trigger_expired((int) $request->id);
            $tx->allow_commit();
            attempt_start_guard::release((int) $request->id);
            return false;
        }
        $this->transition($request, release_request::STATE_CONSUMED, $now);
        $this->trigger_consumed((int) $request->id);
        $tx->allow_commit();
        attempt_start_guard::release((int) $request->id);
        return true;
    }

    /**
     * Perform one locked, conditional pending-to-expired transition.
     *
     * @param int $requestid Request id.
     * @param int $now Current Unix time.
     * @param bool $force End even if not due (for disabled quiz).
     * @return bool Whether the row changed.
     */
    private function expire_one(int $requestid, int $now, bool $force = false): bool {
        global $DB;
        if (!attempt_start_guard::acquire($requestid)) {
            return false;
        }
        $tx = null;
        try {
            $tx = $DB->start_delegated_transaction();
            $record = $DB->get_record_sql(
                'SELECT * FROM {quizaccess_presencial_req} WHERE id = :id FOR UPDATE',
                ['id' => $requestid],
            );
            if (
                !$record || !in_array($record->state, release_request::expirable_states(), true) ||
                    (!$force && (int) $record->expiresat > $now)
            ) {
                $tx->allow_commit();
                return false;
            }
            $this->transition($record, release_request::STATE_EXPIRED, $now);
            $this->trigger_expired($requestid);
            $tx->allow_commit();
            return true;
        } finally {
            attempt_start_guard::release($requestid);
        }
    }

    /**
     * Persist a state transition and release the identity's active unique key.
     *
     * @param \stdClass $record Locked database row.
     * @param string $state New state.
     * @param int $now Transition time.
     */
    private function transition(\stdClass $record, string $state, int $now): void {
        global $DB;
        $record->state = $state;
        $isactive = in_array($state, release_request::active_states(), true);
        $record->active = $isactive ? 1 : null;
        $record->timemodified = $now;
        $DB->update_record('quizaccess_presencial_req', $record);
    }

    /**
     * Trigger the creation event inside the creating transaction.
     *
     * @param int $id Request id.
     */
    private function trigger_created(int $id): void {
        $this->trigger_for_request(request_created::class, $id);
    }

    /**
     * Trigger authorization after the state transition.
     *
     * @param int $id Request id.
     */
    private function trigger_authorized(int $id): void {
        $this->trigger_for_request(request_authorized::class, $id);
    }

    /**
     * Trigger expiration after the state transition.
     *
     * @param int $id Request id.
     */
    private function trigger_expired(int $id): void {
        $this->trigger_for_request(request_expired::class, $id);
    }

    /**
     * Trigger the consumption event.
     *
     * @param int $id Request id.
     */
    private function trigger_consumed(int $id): void {
        $this->trigger_for_request(request_consumed::class, $id);
    }

    /**
     * Trigger the attempt-starting event.
     *
     * @param int $id Request id.
     */
    private function trigger_attempt_starting(int $id): void {
        $this->trigger_for_request(request_attempt_starting::class, $id);
    }

    /**
     * Trigger the selected event with the request's quiz context.
     *
     * @param string $eventclass Event class to trigger.
     * @param int $id Request id.
     * @return void
     */
    private function trigger_for_request(string $eventclass, int $id): void {
        global $DB;
        $request = $DB->get_record('quizaccess_presencial_req', ['id' => $id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $request->quizid, 0, false, MUST_EXIST);
        $event = $eventclass::create([
            'context' => \context_module::instance($cm->id),
            'objectid' => $id,
            'relateduserid' => (int) $request->userid,
            'other' => ['attemptnumber' => (int) $request->attemptnumber],
        ]);
        $event->trigger();
    }
}
