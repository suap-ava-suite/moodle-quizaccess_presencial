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
     * Add the application-team and invitation management page before navigation is rendered.
     *
     * @param \core\hook\output\before_http_headers $hook Before output starts.
     */
    public static function add_quiz_settings_link(\core\hook\output\before_http_headers $hook): void {
        $page = $hook->renderer->get_page();
        if (!$page->cm || $page->cm->modname !== 'quiz' || $page->context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $context = \context_module::instance($page->cm->id);
        if ($page->context->id !== $context->id || !has_capability('mod/quiz:manage', $context)) {
            return;
        }

        $settings = $page->settingsnav->find('modulesettings', \navigation_node::TYPE_SETTING);
        if (!$settings || $settings->find('quizaccess_presencial_manageapplicators', \navigation_node::TYPE_SETTING)) {
            return;
        }
        $url = new \moodle_url('/mod/quiz/accessrule/presencial/manage.php', ['cmid' => $page->cm->id]);
        $node = $settings->add(
            get_string('manageapplicators', 'quizaccess_presencial'),
            $url,
            \navigation_node::TYPE_SETTING,
            null,
            'quizaccess_presencial_manageapplicators'
        );
        $node->set_force_into_more_menu(true);
        if ($page->url->compare($url, URL_MATCH_EXACT)) {
            $node->make_active();
        }
    }
}
