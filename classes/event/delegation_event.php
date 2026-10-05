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

namespace quizaccess_presencial\event;

/**
 * Shared event metadata for application delegation events.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class delegation_event extends \core\event\base {
    /**
     * Initialise metadata common to delegation events.
     */
    final protected function init(): void {
        $this->data['objecttable'] = 'quizaccess_presencial_delegation';
        $this->data['crud'] = static::CRUD;
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }
}
