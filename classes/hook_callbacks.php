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

/**
 * Navigation callbacks for Liberação Presencial.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Adds the application-team management page to Quiz navigation.
     *
     * @param \core\hook\output\before_http_headers $hook Before output starts.
     */
    public static function add_quiz_settings_link(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;

        $cm = $PAGE->cm;
        if (!$cm || $cm->modname !== 'quiz') {
            return;
        }

        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/quiz:manage', $context)) {
            return;
        }

        $modulesettings = $PAGE->settingsnav->find('modulesettings', \navigation_node::TYPE_SETTING);
        if (!$modulesettings || $modulesettings->get('quizaccess_presencial_manageapplicators', \navigation_node::TYPE_SETTING)) {
            return;
        }

        $modulesettings->add(
            get_string('manageapplicators', 'quizaccess_presencial'),
            new \moodle_url('/mod/quiz/accessrule/presencial/manage.php', ['cmid' => $cm->id]),
            \navigation_node::TYPE_SETTING,
            null,
            'quizaccess_presencial_manageapplicators',
        );
    }
}
