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
 * Tests for the Presencial access rule global settings.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class settings_test extends \advanced_testcase {
    /**
     * The operational settings provide safe defaults.
     */
    public function test_operational_settings_have_safe_defaults(): void {
        $this->resetAfterTest();

        $this->assertSame(
            '15',
            $this->duration_setting('quizaccess_presencial/requestvalidity', '15')->get_defaultsetting()
        );
        $this->assertSame(
            '5',
            $this->duration_setting('quizaccess_presencial/authorisationvalidity', '5')->get_defaultsetting()
        );
        $this->assertSame(
            '0',
            $this->rejection_justification_setting()->get_defaultsetting()
        );
    }

    /**
     * Administrators can persist each global policy.
     */
    public function test_administrator_can_persist_global_policies(): void {
        $this->resetAfterTest();

        $this->assertSame(
            '',
            $this->duration_setting('quizaccess_presencial/requestvalidity', '15')->write_setting('20')
        );
        $this->assertSame(
            '',
            $this->duration_setting('quizaccess_presencial/authorisationvalidity', '5')->write_setting('10')
        );
        $this->assertSame(
            '',
            $this->rejection_justification_setting()->write_setting('1')
        );

        $this->assertSame('20', get_config('quizaccess_presencial', 'requestvalidity'));
        $this->assertSame('10', get_config('quizaccess_presencial', 'authorisationvalidity'));
        $this->assertSame('1', get_config('quizaccess_presencial', 'rejectionjustificationrequired'));
    }

    /**
     * Invalid duration values are rejected without replacing the saved policy.
     *
     * @dataProvider invalid_duration_provider
     * @param string $value Invalid duration submitted by the administrator.
     */
    public function test_invalid_duration_does_not_replace_saved_policy(string $value): void {
        $this->resetAfterTest();
        $setting = $this->duration_setting('quizaccess_presencial/requestvalidity', '15');

        $this->assertSame('', $setting->write_setting('20'));
        $this->assertSame(get_string('validateerror', 'admin'), $setting->write_setting($value));
        $this->assertSame('20', get_config('quizaccess_presencial', 'requestvalidity'));
    }

    /**
     * Invalid duration values.
     *
     * @return array<string, array{string}>
     */
    public static function invalid_duration_provider(): array {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'decimal' => ['1.5'],
            'text' => ['five'],
            'empty' => [''],
        ];
    }

    /**
     * Creates a duration setting with positive-integer validation.
     *
     * @param string $name Full configuration name.
     * @param string $default Default duration in minutes.
     * @return positive_integer
     */
    private function duration_setting(string $name, string $default): positive_integer {
        return new positive_integer($name, '', '', $default);
    }

    /**
     * Creates the rejection-justification setting.
     *
     * @return \admin_setting_configcheckbox
     */
    private function rejection_justification_setting(): \admin_setting_configcheckbox {
        return new \admin_setting_configcheckbox(
            'quizaccess_presencial/rejectionjustificationrequired',
            '',
            '',
            '0'
        );
    }
}