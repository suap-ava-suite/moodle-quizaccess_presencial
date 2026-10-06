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

namespace quizaccess_presencial\form;

/**
 * Authenticated POST actions for the current invitation generation.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invitation extends \moodleform {
    /**
     * Define the actions available for the current invitation status.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $status = $this->_customdata['status'];

        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'generation', $status['generation']);
        $mform->setType('generation', PARAM_INT);

        $actions = [];
        if ($status['canissue']) {
            $label = $status['generation'] ? 'invitationregenerate' : 'invitationgenerate';
            $actions[] = $mform->createElement('submit', 'generate', get_string($label, 'quizaccess_presencial'));
        }
        if ($status['state'] === 'active') {
            $actions[] = $mform->createElement('submit', 'disable',
                get_string('invitationdisable', 'quizaccess_presencial'));
        }
        if ($actions) {
            $mform->addGroup($actions, 'actions', '', ' ', false);
        }
    }
}
