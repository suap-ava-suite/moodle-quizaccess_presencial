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
     * @param string $buttonlabel Inclusion button label.
     * @return string Selector markup.
     */
    public function display_for_inclusion(string $buttonlabel): string {
        $html = parent::display(true);
        $buttonattributes = [
            'type' => 'submit',
            'name' => 'addapplicators',
            'value' => $buttonlabel,
            'class' => 'btn btn-primary mt-3 mb-3',
        ];
        $fallback = \html_writer::label(get_string('applicators', 'quizaccess_presencial'), $this->get_name()) .
            $html . \html_writer::empty_tag('input', $buttonattributes);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
            $loaded = $document->loadHTML(
                '<div id="selector-root">' . $encoded . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
            $root = $document->getElementById('selector-root');
            $wrapper = $document->getElementById($this->get_name() . '_wrapper');
            $select = $document->getElementById($this->get_name());
            $search = $document->getElementById($this->get_name() . '_searchtext');
            if (!$loaded || !$root || !$wrapper || !$select || !$search ||
                    $select->parentNode !== $wrapper || !$search->parentNode ||
                    $search->parentNode->parentNode !== $wrapper) {
                debugging('Unexpected Moodle user selector markup; using the default layout.', DEBUG_DEVELOPER);
                return $fallback;
            }
            $wrapper->insertBefore($search->parentNode, $select);
            $button = $document->createElement('input');
            foreach ($buttonattributes as $name => $value) {
                $button->setAttribute($name, $value);
            }
            $wrapper->insertBefore($button, $select);
            $label = $document->createElement('label');
            $label->setAttribute('for', $this->get_name());
            $label->setAttribute('class', 'd-block');
            $label->appendChild($document->createTextNode(get_string('applicators', 'quizaccess_presencial')));
            $wrapper->insertBefore($label, $select);
            $result = '';
            foreach ($root->childNodes as $child) {
                $fragment = $document->saveHTML($child);
                if ($fragment === false) {
                    debugging('Could not serialize the Moodle user selector; using the default layout.', DEBUG_DEVELOPER);
                    return $fallback;
                }
                $result .= $fragment;
            }
            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
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
