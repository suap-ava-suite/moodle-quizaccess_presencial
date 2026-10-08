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

use quizaccess_presencial\event\invitation_invalid;
use quizaccess_presencial\event\invitation_updated;

/**
 * Public domain boundary for issuing and validating quiz invitations.
 *
 * Validation returns only validity. Account eligibility and acceptance belong to
 * the subsequent acceptance workflow; no delegation is created here.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class invitation {
    /**
     * Generate a secret, returning it only to this caller.
     *
     * @param int $cmid Quiz course module.
     * @param int $expectedgeneration Generation observed by the manager.
     * @return array Token, generation and expiry.
     */
    public static function generate(int $cmid, int $expectedgeneration): array {
        global $DB, $USER;

        $cm = self::managed_quiz($cmid);
        $result = quiz_lock::execute($cm->instance, function () use ($cm, $expectedgeneration, $DB, $USER) {
            $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $cm->instance]);
            $record = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $cm->instance]);
            if ($expectedgeneration !== (int) ($record->generation ?? 0)) {
                return new \moodle_exception('invitationstale', 'quizaccess_presencial');
            }
            self::reconcile($cm, $record ?: null, $configuration ?: null);
            if (!self::can_issue($cm, $configuration ?: null)) {
                return new \moodle_exception('invitationunavailable', 'quizaccess_presencial');
            }
            $token = 'i1_' . bin2hex(random_bytes(32));
            $now = \core\di::get(\core\clock::class)->time();
            $new = (object) [
                'quizid' => $cm->instance,
                'tokenhash' => self::derive($token),
                'createdby' => $USER->id,
                'generation' => (int) ($record->generation ?? 0) + 1,
                'state' => 'active',
                'timeexpires' => (int) $configuration->timeclose,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            if ($record) {
                $new->id = $record->id;
                $DB->update_record('quizaccess_presencial_invite', $new);
            } else {
                $new->id = $DB->insert_record('quizaccess_presencial_invite', $new);
            }
            self::transition_event($cm, $new, $record ? 'regenerated' : 'generated');
            return ['token' => $token, 'generation' => $new->generation, 'timeexpires' => $new->timeexpires];
        });
        // Expected refusals must not roll back an outer Moodle transaction or an expiry event.
        if ($result instanceof \moodle_exception) {
            throw $result;
        }
        return $result;
    }

    /**
     * Get management state without returning any token derivative.
     * @param int $cmid Quiz course module.
     * @return array Safe state for the management page.
     */
    public static function get_status(int $cmid): array {
        global $DB;

        $cm = self::managed_quiz($cmid);
        return quiz_lock::execute($cm->instance, function () use ($cm, $DB): array {
            $record = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $cm->instance]);
            $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $cm->instance]);
            self::reconcile($cm, $record ?: null, $configuration ?: null);
            return [
                'state' => $record->state ?? 'none',
                'generation' => (int) ($record->generation ?? 0),
                'timeexpires' => (int) ($record->timeexpires ?? 0),
                'canissue' => self::can_issue($cm, $configuration ?: null),
            ];
        });
    }

    /**
     * Validate a token for its quiz without exposing restricted information.
     * @param int $cmid Quiz course module.
     * @param string $token Untrusted secret.
     * @return bool Validity only.
     */
    public static function validate(int $cmid, string $token): bool {
        global $DB;

        $cm = get_coursemodule_from_id('quiz', $cmid);
        if (!$cm) {
            invitation_invalid::create(['context' => \context_system::instance()])->trigger();
            return false;
        }
        return quiz_lock::execute($cm->instance, function () use ($cm, $token, $DB): bool {
            $record = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $cm->instance]);
            $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $cm->instance]);
            self::reconcile($cm, $record ?: null, $configuration ?: null);
            $valid = $record && $record->state === 'active' && self::can_issue($cm, $configuration ?: null)
                && $record->timeexpires > \core\di::get(\core\clock::class)->time()
                && preg_match('/\Ai1_[0-9a-f]{64}\z/', $token)
                && hash_equals($record->tokenhash, self::derive($token));
            if (!$valid) {
                invitation_invalid::create(['context' => \context_module::instance($cm->id)])->trigger();
            }
            return (bool) $valid;
        });
    }

    /**
     * Disable the current invitation without affecting delegations.
     * @param int $cmid Quiz course module.
     * @param int $expectedgeneration Generation observed by the manager.
     * @return void
     */
    public static function disable(int $cmid, int $expectedgeneration): void {
        global $DB;

        $cm = self::managed_quiz($cmid);
        $result = quiz_lock::execute($cm->instance, function () use ($cm, $expectedgeneration, $DB) {
            $record = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $cm->instance]);
            if ($expectedgeneration !== (int) ($record->generation ?? 0)) {
                return new \moodle_exception('invitationstale', 'quizaccess_presencial');
            }
            $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $cm->instance]);
            self::reconcile($cm, $record ?: null, $configuration ?: null);
            if ($record && $record->state === 'active') {
                self::end_invitation($cm, $record, 'disabled');
            }
            return null;
        });
        if ($result instanceof \moodle_exception) {
            throw $result;
        }
    }

    /**
     * Synchronize an invitation with a configuration transition under the same quiz lock.
     * @param int $cmid Quiz course module.
     * @param int $previousend Previous period end, so an extension cannot revive an expired secret.
     * @return void
     */
    public static function synchronize_configuration(int $cmid, int $previousend = 0): void {
        global $DB;

        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        quiz_lock::execute($cm->instance, function () use ($cm, $previousend, $DB): void {
            $record = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $cm->instance]);
            $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $cm->instance]);
            self::reconcile($cm, $record ?: null, $configuration ?: null, $previousend);
        });
    }

    /**
     * Erase approved creator data while retaining only the nonpersonal generation marker.
     *
     * The Privacy API supplies the approved quiz context and, for user erasure, the approved creators.
     * This is not a management action and does not require the erased user's management capability.
     *
     * @param int $cmid Quiz course module.
     * @param array|null $userids Approved creators, or null for all users in the quiz context.
     * @return void
     */
    public static function erase_user_data(int $cmid, ?array $userids = null): void {
        global $DB;

        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        quiz_lock::execute($cm->instance, function () use ($cm, $userids, $DB): void {
            $record = $DB->get_record('quizaccess_presencial_invite', ['quizid' => $cm->instance]);
            if (
                !$record || ($userids !== null && ((int) $record->createdby <= 0
                    || !in_array((int) $record->createdby, $userids, true)))
            ) {
                return;
            }
            $configuration = $DB->get_record('quizaccess_presencial', ['quizid' => $cm->instance]);
            self::reconcile($cm, $record, $configuration ?: null);
            if ($record->state === 'active' && self::can_issue($cm, $configuration ?: null)) {
                self::end_invitation($cm, $record, 'disabled');
            }
            // Keep the marker even for context-wide erasure so an old form cannot target a later invitation.
            $record->createdby = 0;
            $record->tokenhash = '';
            if ($record->state === 'active') {
                $record->state = 'disabled';
            }
            $record->timecreated = 0;
            $record->timemodified = 0;
            $record->timeexpires = 0;
            $DB->update_record('quizaccess_presencial_invite', $record);
        });
    }

    /**
     * Materialize due expirations; synchronous validation uses the same transition.
     * @return void
     */
    public static function expire_due(): void {
        global $DB;

        $records = $DB->get_recordset_select(
            'quizaccess_presencial_invite',
            'state = :state AND timeexpires <= :now',
            ['state' => 'active', 'now' => \core\di::get(\core\clock::class)->time()],
            '',
            'id,quizid'
        );
        try {
            foreach ($records as $record) {
                $cm = get_coursemodule_from_instance('quiz', $record->quizid);
                if ($cm) {
                    // Re-read after acquiring the lock: another process may have regenerated the invitation.
                    self::synchronize_configuration($cm->id);
                }
            }
        } finally {
            $records->close();
        }
    }

    /**
     * Irreversibly shorten or end the current invitation under the quiz lock.
     * @param \stdClass $cm Course module.
     * @param \stdClass|null $record Invitation, updated in place.
     * @param \stdClass|null $configuration Current configuration.
     * @param int $previousend Previous period end.
     * @return void
     */
    private static function reconcile(
        \stdClass $cm,
        ?\stdClass $record,
        ?\stdClass $configuration,
        int $previousend = 0,
    ): void {
        global $DB;

        if (!$record || $record->state !== 'active') {
            return;
        }
        if (!$configuration || !$configuration->enabled) {
            self::end_invitation($cm, $record, 'disabled');
            return;
        }
        $expiry = min((int) $record->timeexpires, (int) $configuration->timeclose);
        if ($previousend > 0) {
            $expiry = min($expiry, $previousend);
        }
        $changed = $expiry !== (int) $record->timeexpires;
        $record->timeexpires = $expiry;
        if ($expiry <= \core\di::get(\core\clock::class)->time()) {
            self::end_invitation($cm, $record, 'expired');
        } else if ($changed) {
            $record->timemodified = \core\di::get(\core\clock::class)->time();
            $DB->update_record('quizaccess_presencial_invite', $record);
        }
    }

    /**
     * End a live invitation and erase its derivative, emitting exactly one event.
     * @param \stdClass $cm Course module.
     * @param \stdClass $record Invitation.
     * @param string $state Final state.
     * @return void
     */
    private static function end_invitation(\stdClass $cm, \stdClass $record, string $state): void {
        global $DB;

        $record->state = $state;
        $record->tokenhash = '';
        $record->timemodified = \core\di::get(\core\clock::class)->time();
        $DB->update_record('quizaccess_presencial_invite', $record);
        self::transition_event($cm, $record, $state);
    }

    /**
     * Require management permission in the actual quiz context.
     * @param int $cmid Quiz course module.
     * @return \stdClass Course module.
     */
    private static function managed_quiz(int $cmid): \stdClass {
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        require_capability('mod/quiz:manage', \context_module::instance($cm->id));
        return $cm;
    }

    /**
     * Determine whether a finite valid configuration can issue a new invitation.
     * @param \stdClass $cm Course module.
     * @param \stdClass|null $configuration Configuration.
     * @return bool Whether issuing is allowed, including before the start.
     */
    private static function can_issue(\stdClass $cm, ?\stdClass $configuration): bool {
        global $DB;

        if (
            !$configuration || !$configuration->enabled
                || $configuration->timeclose <= \core\di::get(\core\clock::class)->time()
        ) {
            return false;
        }
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id,timeopen,timeclose', MUST_EXIST);
        return !authorization_period::validate(
            true,
            (int) $configuration->timeopen,
            (int) $configuration->timeclose,
            (int) $quiz->timeopen,
            (int) $quiz->timeclose,
            \core\di::get(\core\clock::class)->time(),
            false
        );
    }

    /**
     * Domain-separated irreversible token derivative.
     * @param string $token Token.
     * @return string SHA-256 derivative.
     */
    private static function derive(string $token): string {
        return hash('sha256', "quizaccess_presencial:invite:v1:" . $token);
    }

    /**
     * Trigger an event containing no secret.
     * @param \stdClass $cm Course module.
     * @param \stdClass $record Invitation.
     * @param string $action Transition.
     * @return void
     */
    private static function transition_event(\stdClass $cm, \stdClass $record, string $action): void {
        invitation_updated::create([
            'context' => \context_module::instance($cm->id),
            'objectid' => $cm->instance,
            'other' => ['action' => $action, 'generation' => (int) $record->generation,
                'timeexpires' => (int) $record->timeexpires],
        ])->trigger();
    }
}
