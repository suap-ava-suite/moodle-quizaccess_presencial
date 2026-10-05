<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_presencial\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/selector/lib.php');

/**
 * Moodle user selector restricted to eligible application accounts.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class applicator_selector extends \user_selector_base {
    /**
     * Constructor.
     *
     * @param string $name Selector name.
     * @param array $options Selector options.
     */
    public function __construct(string $name, array $options = []) {
        $options['multiselect'] = true;
        $options['includecustomfields'] = true;
        parent::__construct($name, $options);
    }

    /**
     * Find only confirmed, active, non-guest accounts with an enabled login method.
     *
     * @param string $search Search text.
     * @return array Grouped eligible users.
     */
    public function find_users($search): array {
        global $CFG, $DB;

        [$wherecondition, $params] = $this->search_sql($search, 'u');
        $params = array_merge($params, $this->userfieldsparams);
        $enabledauths = get_enabled_auth_plugins();
        if (!$enabledauths) {
            return [];
        }
        [$authsql, $authparams] = $DB->get_in_or_equal($enabledauths, SQL_PARAMS_NAMED, 'auth');
        $params += $authparams;
        $params['deleted'] = 0;
        $params['suspended'] = 0;
        $params['confirmed'] = 1;
        $params['nologin'] = 'nologin';

        $fields = 'SELECT u.id, ' . $this->userfieldsselects;
        $from = " FROM {user} u {$this->userfieldsjoin}";
        $where = " WHERE {$wherecondition}
                         AND u.deleted = :deleted
                         AND u.suspended = :suspended
                         AND u.confirmed = :confirmed
                         AND u.auth <> :nologin
                         AND u.auth {$authsql}";
        if ($this->exclude) {
            [$excludesql, $excludeparams] = $DB->get_in_or_equal($this->exclude, SQL_PARAMS_NAMED, 'exclude', false);
            $where .= " AND u.id {$excludesql}";
            $params += $excludeparams;
        }

        [$sort, $sortparams] = users_order_by_sql('u', $search, $this->accesscontext, $this->userfieldsmappings);
        if (!$this->is_validating()) {
            $count = $DB->count_records_sql('SELECT COUNT(1)' . $from . $where, $params);
            if ($count > $this->maxusersperpage) {
                return $this->too_many_results($search, $count);
            }
        }
        $users = $DB->get_records_sql($fields . $from . $where . ' ORDER BY ' . $sort,
            array_merge($params, $sortparams));
        if (!$users) {
            return [];
        }
        $groupname = $search
            ? get_string('potusersmatching', 'core_role', $search)
            : get_string('potusers', 'core_role');
        return [$groupname => $users];
    }

    /**
     * Include this class file when Moodle recreates the selector for AJAX search.
     *
     * @return array Selector options.
     */
    protected function get_options(): array {
        $options = parent::get_options();
        $options['file'] = 'mod/quiz/accessrule/presencial/classes/local/applicator_selector.php';
        return $options;
    }
}
