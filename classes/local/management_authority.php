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

namespace quizaccess_presencial\local;

/**
 * Revalidate quiz management authority after waiting for a lock.
 *
 * @package quizaccess_presencial
 * @copyright 2026 SUAP AVA Suite
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class management_authority {
    /**
     * Recheck management authority without trusting role data cached before a lock wait.
     *
     * @param \context_module $context Quiz context.
     * @param int $actorid User performing the management operation.
     * @return bool Whether the actor has current management authority.
     */
    public static function recheck(\context_module $context, int $actorid): bool {
        global $CFG, $DB, $USER;

        if (!has_capability('mod/quiz:manage', $context, $actorid)) {
            return false;
        }
        if (!$DB->record_exists('user', ['id' => $actorid, 'deleted' => 0])) {
            return false;
        }
        $roles = null;
        if ((int) $USER->id === $actorid && !empty($USER->access['rsw'])) {
            foreach (array_reverse($context->get_parent_context_paths(true)) as $path) {
                if (isset($USER->access['rsw'][$path])) {
                    $roles = [(int) $USER->access['rsw'][$path], (int) $CFG->defaultuserroleid];
                    break;
                }
            }
        }
        $assignments = null;
        $siteadmins = self::current_core_setting('siteadmins');
        if ($roles === null) {
            // Match is_siteadmin(), including numeric ids with whitespace in config.php.
            if (in_array($actorid, explode(',', $siteadmins))) {
                return true;
            }
            $assignments = get_user_roles_with_special($context, $actorid);
            $roles = array_column($assignments, 'roleid');
        }
        [$allowed, $forbidden] = get_roles_with_cap_in_context($context, 'mod/quiz:manage');
        if (array_intersect($roles, $forbidden)) {
            return false;
        }
        if (array_intersect($roles, $allowed)) {
            return true;
        }
        if ($assignments === null || (int) $USER->id !== $actorid) {
            return false;
        }
        $roles = self::renew_temporary_roles($context, $assignments, $siteadmins);
        return !array_intersect($roles, $forbidden) && (bool) array_intersect($roles, $allowed);
    }

    /**
     * Read a current core setting, respecting values forced by config.php.
     *
     * @param string $name Core setting name.
     * @return string Current value without the request's configuration cache.
     */
    private static function current_core_setting(string $name): string {
        global $CFG, $DB;

        if (array_key_exists($name, $CFG->config_php_settings)) {
            return (string) $CFG->config_php_settings[$name];
        }
        return (string) $DB->get_field('config', 'value', ['name' => $name]);
    }

    /**
     * Ask active enrolment plugins to renew temporary roles on a clean copy of the session.
     *
     * Cached roles have no provenance: a missing database assignment may be revoked rather
     * than temporary. Only a plugin that grants access again can establish current authority.
     *
     * @param \context_module $context Quiz context.
     * @param array $assignments Current persisted and implicit Moodle role assignments.
     * @param string $siteadmins Current administrator list.
     * @return int[] Roles resulting from a currently valid temporary grant.
     */
    private static function renew_temporary_roles(\context_module $context, array $assignments, string $siteadmins): array {
        global $CFG, $USER;

        $originaluser = $USER;
        $originalconfig = $CFG;
        $access = ['ra' => [], 'rsw' => [], 'time' => time()];
        foreach ($assignments as $assignment) {
            $path = \context::instance_by_id($assignment->contextid)->path;
            $access['ra'][$path][(int) $assignment->roleid] = (int) $assignment->roleid;
        }
        try {
            $USER = clone $originaluser;
            $CFG = clone $originalconfig;
            $CFG->siteadmins = $siteadmins;
            $CFG->enrol_plugins_enabled = self::current_core_setting('enrol_plugins_enabled');
            $CFG->guestroleid = (int) self::current_core_setting('guestroleid');
            $plugins = enrol_get_plugins(true);
            $coursecontext = $context->get_course_context();
            $roles = [];
            foreach (enrol_get_instances($coursecontext->instanceid, true) as $instance) {
                if (!isset($plugins[$instance->enrol])) {
                    continue;
                }
                // Discard both old session roles and grants from a plugin that refused access.
                $USER->access = $access;
                $until = $plugins[$instance->enrol]->try_guestaccess($instance);
                if ($until === false || ($until !== 0 && $until <= time())) {
                    continue;
                }
                foreach ($context->get_parent_context_paths(true) as $path) {
                    $roles = array_merge($roles, array_values($USER->access['ra'][$path] ?? []));
                }
            }
            return array_values(array_unique($roles));
        } finally {
            $USER = $originaluser;
            $CFG = $originalconfig;
        }
    }

}
