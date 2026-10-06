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

/**
 * Independent Moodle connection used to exercise concurrent public invite issuance.
 *
 * @package    quizaccess_presencial
 * @category   test
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    die;
}

$autoload = $argv[1] . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    // Moodle 5.1 keeps Composer outside its public document root.
    $autoload = dirname($argv[1]) . '/vendor/autoload.php';
}
require($autoload);
// Utility bootstrap selects the isolated PHPUnit database without resetting the parent's data.
define('PHPUNIT_UTIL', true);
require($argv[1] . '/lib/phpunit/bootstrap.php');
\advanced_testcase::setUser((int) $argv[3]);
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
fgets(STDIN);
try {
    $result = \quizaccess_presencial\local\invitation::generate((int) $argv[2], 0);
    echo json_encode(['token' => $result['token']]);
} catch (\moodle_exception $exception) {
    echo json_encode(['error' => $exception->errorcode]);
}
