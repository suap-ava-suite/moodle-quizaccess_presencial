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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace quizaccess_presencial\local;

/**
 * Shared account eligibility rules for the selector and delegation boundary.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class applicator_eligibility {
    /**
     * Return the SQL condition and parameters for eligible Moodle accounts.
     *
     * @param string $useralias User table alias used by the caller.
     * @return array SQL condition and named parameters.
     */
    public static function sql_condition(string $useralias): array {
        global $CFG, $DB;

        if (!preg_match('/^[a-z][a-z0-9_]*$/i', $useralias)) {
            throw new \coding_exception('Invalid user table alias.');
        }
        $enabledauth = get_enabled_auth_plugins();
        if (!$enabledauth) {
            return ['1 = 0', []];
        }
        [$authsql, $authparams] = $DB->get_in_or_equal($enabledauth, SQL_PARAMS_NAMED, 'eligibleauth');
        $params = array_merge($authparams, [
            'eligibledeleted' => 0,
            'eligiblesuspended' => 0,
            'eligibleconfirmed' => 1,
            'eligibleguestid' => (int) $CFG->siteguest,
            'eligiblenologin' => 'nologin',
            'eligiblewebservice' => 'webservice',
        ]);
        $condition = "{$useralias}.deleted = :eligibledeleted
            AND {$useralias}.suspended = :eligiblesuspended
            AND {$useralias}.confirmed = :eligibleconfirmed
            AND {$useralias}.id <> :eligibleguestid
            AND {$useralias}.auth <> :eligiblenologin
            AND {$useralias}.auth <> :eligiblewebservice
            AND {$useralias}.auth {$authsql}";

        return [$condition, $params];
    }

    /**
     * Check whether a Moodle account meets the shared eligibility rules.
     *
     * @param int $userid User id.
     * @return bool Whether the account is eligible.
     */
    public static function is_eligible_user(int $userid): bool {
        global $DB;

        [$condition, $params] = self::sql_condition('u');
        $params['eligibleuserid'] = $userid;
        return $DB->record_exists_sql(
            "SELECT 1 FROM {user} u WHERE u.id = :eligibleuserid AND {$condition}",
            $params,
        );
    }
}
