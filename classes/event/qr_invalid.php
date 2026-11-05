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

namespace quizaccess_presencial\event;

/**
 * An invalid QR authorization was presented.
 *
 * @package quizaccess_presencial
 * @copyright 2026 SUAP AVA Suite
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qr_invalid extends \core\event\base {
    /**
     * Initialize event data.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get the translated event name.
     *
     * @return string Event name.
     */
    public static function get_name(): string {
        return get_string('eventqrinvalid', 'quizaccess_presencial');
    }

    /**
     * Describe the invalid use without retaining the token.
     *
     * @return string Event description.
     */
    public function get_description(): string {
        return 'An invalid QR authorization was presented.';
    }
}
