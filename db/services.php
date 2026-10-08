<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'quizaccess_presencial_read_request' => [
        'classname' => '\\quizaccess_presencial\\external\\read_request_status',
        'methodname' => 'execute',
        'description' => 'Read and refresh the current user\'s release request state.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'quizaccess_presencial_authorize_request' => [
        'classname' => '\\quizaccess_presencial\\external\\authorize_request',
        'methodname' => 'execute',
        'description' => 'Authorize a student release request during the Quiz authorization period.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
