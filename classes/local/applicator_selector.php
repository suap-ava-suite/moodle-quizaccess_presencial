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
     * Render the native selector with search controls before the inclusion action and list.
     *
     * @param string $button Inclusion button HTML.
     * @return string Selector markup.
     */
    public function display_for_inclusion(string $button): string {
        $html = parent::display(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="selector-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $wrapper = $document->getElementById($this->get_name() . '_wrapper');
        $select = $document->getElementById($this->get_name());
        $search = $document->getElementById($this->get_name() . '_searchtext');
        if (!$wrapper || !$select || !$search || $search->parentNode->parentNode !== $wrapper) {
            throw new \coding_exception('Unexpected Moodle user selector markup');
        }
        $controls = $search->parentNode;
        $wrapper->insertBefore($controls, $select);
        $buttondocument = new \DOMDocument('1.0', 'UTF-8');
        $buttondocument->loadHTML('<?xml encoding="UTF-8">' . $button, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $wrapper->insertBefore($document->importNode($buttondocument->documentElement, true), $select);
        $label = $document->createElement('label');
        $label->setAttribute('for', $this->get_name());
        $label->setAttribute('class', 'd-block');
        $label->appendChild($document->createTextNode(get_string('applicators', 'quizaccess_presencial')));
        $wrapper->insertBefore($label, $select);
        $root = $document->getElementById('selector-root');
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }
        return $result;
    }

    /**
     * Find only confirmed, active, non-guest accounts with an enabled login method.
     *
     * @param string $search Search text.
     * @return array Grouped eligible users.
     */
    public function find_users($search): array {
        global $DB;

        require_capability('mod/quiz:manage', $this->accesscontext);
        [$wherecondition, $params] = $this->search_sql($search, 'u');
        [$eligibilitycondition, $eligibilityparams] = applicator_eligibility::sql_condition('u');
        $params = array_merge($params, $this->userfieldsparams, $eligibilityparams);

        $fields = 'SELECT u.id, ' . $this->userfieldsselects;
        $from = " FROM {user} u {$this->userfieldsjoin}";
        $where = " WHERE {$wherecondition}
                         AND {$eligibilitycondition}";
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
        $users = $DB->get_records_sql(
            $fields . $from . $where . ' ORDER BY ' . $sort,
            array_merge($params, $sortparams),
        );
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
