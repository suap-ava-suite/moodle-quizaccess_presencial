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

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new \quizaccess_presencial\admin_setting\positive_integer(
        'quizaccess_presencial/requestvalidity',
        get_string('requestvalidity', 'quizaccess_presencial'),
        get_string('requestvalidity_desc', 'quizaccess_presencial'),
        '15'
    ));

    $settings->add(new \quizaccess_presencial\admin_setting\positive_integer(
        'quizaccess_presencial/authorisationvalidity',
        get_string('authorisationvalidity', 'quizaccess_presencial'),
        get_string('authorisationvalidity_desc', 'quizaccess_presencial'),
        '5'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'quizaccess_presencial/rejectionjustificationrequired',
        get_string('rejectionjustificationrequired', 'quizaccess_presencial'),
        get_string('rejectionjustificationrequired_desc', 'quizaccess_presencial'),
        '0'
    ));
}