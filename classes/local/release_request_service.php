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
use quizaccess_presencial\event\request_rejected;
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

        if ($active && $active->state === release_request::STATE_REJECTED) {
            // A new student click permanently retires the previous decision.
            $DB->set_field('quizaccess_presencial_req', 'active', null, ['id' => $active->id]);
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
     * Authorize an actionable request; an unchanged grant keeps its deadline.
     *
     * @param int $requestid Request id.
     * @return release_request Updated request.
     */
    public function authorize(int $requestid): release_request {
        return $this->decide($requestid, release_request::STATE_AUTHORIZED, '');
    }

    /**
     * Reject an actionable request using the current institutional policy.
     *
     * @param int $requestid Request id.
     * @param string $reason Complete plain-text explanation for the student.
     * @return release_request Updated request.
     */
    public function reject(int $requestid, string $reason = ''): release_request {
        return $this->decide($requestid, release_request::STATE_REJECTED, $reason);
    }

    /**
     * Replace the current decision atomically with delegation and configuration changes.
     *
     * @param int $requestid Request id.
     * @param string $state New decision.
     * @param string $reason Complete rejection explanation.
     * @return release_request Updated request.
     */
    private function decide(int $requestid, string $state, string $reason): release_request {
        global $DB, $USER;

        $existing = $this->get($requestid);
        $result = quiz_lock::execute($existing->quizid, function () use ($requestid, $existing, $state, $reason, $DB, $USER) {
            $cm = get_coursemodule_from_instance('quiz', $existing->quizid, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            // Use the same configuration/request lock order as request creation.
            $configuration = $DB->get_record_sql(
                'SELECT * FROM {quizaccess_presencial} WHERE quizid = :quizid FOR UPDATE',
                ['quizid' => $existing->quizid],
            );
            $record = $DB->get_record_sql(
                'SELECT * FROM {quizaccess_presencial_req} WHERE id = :id FOR UPDATE',
                ['id' => $requestid],
                MUST_EXIST,
            );
            // Lock waits can cross a deadline; evaluate authority and validity only after both waits.
            $now = $this->clock->time();
            if (!applicator_eligibility::is_eligible_user((int) $USER->id)) {
                return new \moodle_exception('applicationaccessdenied', 'quizaccess_presencial');
            }
            if (
                !delegation_manager::is_active_applicator($existing->quizid, (int) $USER->id, $now) &&
                    !$this->can_manage_quiz($context)
            ) {
                return new \required_capability_exception($context, 'mod/quiz:manage', 'nopermissions', '');
            }
            if (
                !$configuration || empty($configuration->enabled) ||
                    (int) $configuration->timeopen > $now || (int) $configuration->timeclose <= $now
            ) {
                return new \moodle_exception('authorizationperiodinactive', 'quizaccess_presencial');
            }
            if (
                empty($record->active) || !in_array($record->state, [
                release_request::STATE_PENDING, release_request::STATE_AUTHORIZED, release_request::STATE_REJECTED,
                ], true)
            ) {
                return new \moodle_exception('requestnotrevisable', 'quizaccess_presencial');
            }
            if (
                in_array($record->state, release_request::expirable_states(), true) && (int) $record->expiresat <= $now
            ) {
                $this->transition($record, release_request::STATE_EXPIRED, $now);
                $this->trigger_expired($requestid);
                return $this->get($requestid);
            }
            if (
                $state === release_request::STATE_REJECTED && trim($reason) === '' &&
                    get_config('quizaccess_presencial', 'rejectionjustificationrequired')
            ) {
                return new \moodle_exception('rejectionreasonrequired', 'quizaccess_presencial');
            }
            if ($state === release_request::STATE_AUTHORIZED && $record->state === $state) {
                return $this->get($requestid);
            }
            if ($state === release_request::STATE_AUTHORIZED) {
                $validity = (int) get_config('quizaccess_presencial', 'authorisationvalidity');
                $record->expiresat = $now + ($validity > 0 ? $validity : 5) * MINSECS;
            }
            $record->rejectionreason = $reason;
            $this->transition($record, $state, $now);
            $this->trigger_for_request(
                $state === release_request::STATE_AUTHORIZED ? request_authorized::class : request_rejected::class,
                $requestid,
            );
            return $this->get($requestid);
        });
        if ($result instanceof \moodle_exception) {
            throw $result;
        }
        return $result;
    }

    /**
     * Confirm management authority without trusting access data loaded before a lock wait.
     *
     * @param \context_module $context Quiz context.
     * @return bool Whether the current account still has quiz management authority.
     */
    private function can_manage_quiz(\context_module $context): bool {
        global $DB, $USER;

        // Keep Moodle's session restrictions, including switching to a less privileged role.
        if (!has_capability('mod/quiz:manage', $context)) {
            return false;
        }
        // Site administrators have implicit privileges outside role assignments.
        if (is_siteadmin()) {
            return true;
        }
        // This public Moodle API reads current assignments and overrides instead of cached access data.
        $authority = get_with_capability_join($context, 'mod/quiz:manage', 'u.id');
        if ($authority->cannotmatchanyrows) {
            return false;
        }
        return $DB->record_exists_sql(
            "SELECT 1 FROM {user} u {$authority->joins}
              WHERE u.id = :decisionactor AND ({$authority->wheres})",
            array_merge($authority->params, ['decisionactor' => (int) $USER->id]),
        );
    }

    /**
     * Confirm a grant after native preflight, atomically creating its one Moodle attempt.
     *
     * Replays return the attempt identified by the original request, even after it closes.
     * The quiz and request locks serialize confirmation with decisions and configuration changes.
     *
     * @param int $requestid Request confirmed by the current student.
     * @return \stdClass|null Existing or newly started attempt, or null for an unusable grant.
     */
    public function start_authorized_attempt(int $requestid): ?\stdClass {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $request = $this->get($requestid);
        if ($request->userid !== (int) $USER->id) {
            throw new \moodle_exception('requestnotfound', 'quizaccess_presencial');
        }
        return quiz_lock::execute($request->quizid, function () use ($request, $DB, $USER) {
            $quizobj = \mod_quiz\quiz_settings::create($request->quizid, $USER->id);
            require_capability('mod/quiz:attempt', $quizobj->get_context());
            $configuration = $DB->get_record_sql(
                'SELECT * FROM {quizaccess_presencial} WHERE quizid = :quizid FOR UPDATE',
                ['quizid' => $request->quizid],
            );
            $record = $DB->get_record_sql(
                'SELECT * FROM {quizaccess_presencial_req} WHERE id = :id FOR UPDATE',
                ['id' => $request->id],
                MUST_EXIST,
            );
            $existing = $this->find_attempt($request->quizid, $request->userid, $request->attemptnumber);
            if ($existing) {
                return $existing;
            }
            $now = $this->clock->time();
            if (
                !$configuration || empty($configuration->enabled) || empty($record->active) ||
                    $record->state !== release_request::STATE_AUTHORIZED
            ) {
                return null;
            }
            if ((int) $record->expiresat <= $now) {
                $this->transition($record, release_request::STATE_EXPIRED, $now);
                $this->trigger_expired($request->id);
                return null;
            }

            // Recheck Moodle access after lock waits, before consuming any authorization.
            $accessmanager = $quizobj->get_access_manager(time());
            [$currentid, $attemptnumber, $lastattempt, $messages] =
                quiz_validate_new_attempt($quizobj, $accessmanager, false, -1, false);
            if ($messages) {
                throw new \moodle_exception('attempterror', 'quiz', $quizobj->view_url(), implode(' ', $messages));
            }
            if ($currentid || $attemptnumber !== $request->attemptnumber) {
                return null;
            }
            $this->transition($record, release_request::STATE_STARTING, $now);
            $this->trigger_attempt_starting($request->id);
            $attempt = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);
            // Event observers are best effort in Moodle. An explicit transition here also
            // guarantees consumption when events are redirected or an observer fails.
            $this->consume_for_attempt((int) $attempt->id);
            if ($this->get($request->id)->state !== release_request::STATE_CONSUMED) {
                throw new \coding_exception('Attempt creation must consume its authorization.');
            }
            return $attempt;
        });
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
            "active = 1 AND state {$statesql} AND expiresat <= :now",
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
     * Finalize current requests and unused grants when the quiz's in-person rule is disabled.
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
            'quizid = :quizid AND active = 1 AND state IN (:pending, :authorized, :rejected)',
            [
                'quizid' => $quizid,
                'pending' => release_request::STATE_PENDING,
                'authorized' => release_request::STATE_AUTHORIZED,
                'rejected' => release_request::STATE_REJECTED,
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
     * Perform one locked expiration or finalization of a current request.
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
            // Disablement finalizes current rejections too, but deadlines never expire a rejection.
            $states = $force
                ? [release_request::STATE_PENDING, release_request::STATE_AUTHORIZED, release_request::STATE_REJECTED]
                : release_request::expirable_states();
            if (
                !$record || empty($record->active) || !in_array($record->state, $states, true) ||
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
