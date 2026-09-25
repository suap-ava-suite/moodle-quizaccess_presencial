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

namespace quizaccess_presencial;

use quizaccess_presencial\admin_setting\positive_integer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/classes/admin_setting/positive_integer.php');

/**
 * Tests for Presencial global setting defaults.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class settings_defaults_test extends \basic_testcase {
    /**
     * The operational settings provide safe defaults.
     */
    public function test_operational_settings_have_safe_defaults(): void {
        $this->assertSame(
            '15',
            (new positive_integer('quizaccess_presencial/requestvalidity', '', '', '15'))->get_defaultsetting()
        );
        $this->assertSame(
            '5',
            (new positive_integer('quizaccess_presencial/authorisationvalidity', '', '', '5'))->get_defaultsetting()
        );
        $this->assertSame(
            '0',
            (new \admin_setting_configcheckbox('quizaccess_presencial/rejectionjustificationrequired', '', '', '0'))
                ->get_defaultsetting()
        );
    }
}
