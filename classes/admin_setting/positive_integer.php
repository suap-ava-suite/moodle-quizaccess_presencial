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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace quizaccess_presencial\admin_setting;

defined('MOODLE_INTERNAL') || die();

/**
 * Administration setting that accepts positive whole integers only.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class positive_integer extends \admin_setting_configtext {
    /**
     * Validate a submitted value as a positive whole integer.
     *
     * @param mixed $data Submitted value.
     * @return true|string True when valid, otherwise the standard admin error.
     */
    public function validate($data) {
        if (!is_string($data) && !is_int($data)) {
            return get_string('validateerror', 'admin');
        }

        if (!preg_match('/^[1-9][0-9]*$/', (string) $data)) {
            return get_string('validateerror', 'admin');
        }

        return true;
    }
}