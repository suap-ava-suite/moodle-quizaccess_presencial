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

/**
 * A persisted request to release one quiz attempt.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class release_request {
    /** @var string Request is awaiting an in-person decision. */
    public const STATE_PENDING = 'pending';
    /** @var string Request has been authorised. */
    public const STATE_AUTHORIZED = 'authorized';
    /** @var string Moodle is creating the authorized attempt. */
    public const STATE_STARTING = 'starting';
    /** @var string Authorization was consumed by a created attempt. */
    public const STATE_CONSUMED = 'consumed';
    /** @var string Request expired without a decision. */
    public const STATE_EXPIRED = 'expired';

    /**
     * States that retain the database's active identity key.
     *
     * @return string[] Active lifecycle states.
     */
    public static function active_states(): array {
        return [self::STATE_PENDING, self::STATE_AUTHORIZED, self::STATE_STARTING];
    }

    /**
     * States eligible for deadline-based expiration.
     *
     * @return string[] States that can expire when expiresat is reached.
     */
    public static function expirable_states(): array {
        return [self::STATE_PENDING, self::STATE_AUTHORIZED, self::STATE_STARTING];
    }

    /** @var int */
    public int $id;
    /** @var int */
    public int $quizid;
    /** @var int */
    public int $userid;
    /** @var int */
    public int $attemptnumber;
    /** @var string */
    public string $state;
    /** @var int */
    public int $timecreated;
    /** @var int Absolute Unix timestamp persisted at creation. */
    public int $expiresat;

    /**
     * Hydrate the public domain value from a database row.
     *
     * @param \stdClass $record Persisted row.
     */
    public function __construct(\stdClass $record) {
        $this->id = (int) $record->id;
        $this->quizid = (int) $record->quizid;
        $this->userid = (int) $record->userid;
        $this->attemptnumber = (int) $record->attemptnumber;
        $this->state = (string) $record->state;
        $this->timecreated = (int) $record->timecreated;
        $this->expiresat = (int) $record->expiresat;
    }
}
