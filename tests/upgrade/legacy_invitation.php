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
 * Two-phase legacy invitation upgrade test for a disposable Moodle installation.
 *
 * @package    quizaccess_presencial
 * @category   test
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This standalone CLI script loads config.php from the explicitly supplied disposable site below.
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
if (PHP_SAPI !== 'cli' || getenv('PRESENCIAL_DISPOSABLE_SITE') !== 'yes' || $argc !== 3) {
    throw new RuntimeException('Use only on a disposable site: legacy_invitation.php create|verify state-file');
}
$root = getenv('MOODLE_DIR');
if (!$root || !is_file($root . '/config.php')) {
    throw new RuntimeException('MOODLE_DIR must identify the disposable Moodle installation.');
}
define('CLI_SCRIPT', true);
require($root . '/config.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');
\core\session\manager::set_user(get_admin());
$version = (int) get_config('quizaccess_presencial', 'version');
$statefile = $argv[2];
if ($argv[1] === 'create') {
    if (!in_array($version, [2026111100, 2026112600], true) || file_exists($statefile)) {
        throw new RuntimeException('Expected the installed legacy version and a new state file.');
    }
    require_once($CFG->dirroot . '/lib/testing/generator/lib.php');
    $generator = new testing_data_generator();
    $course = $generator->create_course();
    $now = time();
    $quiz = $generator->create_module('quiz', [
        'course' => $course->id,
        'timeopen' => $now - 3600,
        'timeclose' => $now + 86400,
    ]);
    $quiz->coursemodule = $quiz->cmid;
    $quiz->presencial_enabled = 1;
    $quiz->presencial_timeopen = $now - 1800;
    $quiz->presencial_timeclose = $now + 43200;
    \quizaccess_presencial::save_settings($quiz);
    $issued = \quizaccess_presencial\local\invitation::generate($quiz->cmid, 0);
    if (!\quizaccess_presencial\local\invitation::validate($quiz->cmid, $issued['token'])) {
        throw new RuntimeException('The legacy invitation is invalid before the upgrade.');
    }
    umask(0077);
    file_put_contents($statefile, json_encode(['cmid' => $quiz->cmid, 'token' => $issued['token']], JSON_THROW_ON_ERROR));
    echo "Legacy invitation created.\n";
} else if ($argv[1] === 'verify') {
    if ($version < 2026112601 || !is_file($statefile)) {
        throw new RuntimeException('Expected the updated plugin and the legacy state file.');
    }
    $state = json_decode(file_get_contents($statefile), true, 512, JSON_THROW_ON_ERROR);
    $cmid = (int) $state['cmid'];
    $oldtoken = $state['token'];
    $details = \quizaccess_presencial\local\invitation::get_management_details($cmid);
    if (
        $details['state'] !== 'active' || $details['generation'] !== 1 || $details['token'] !== null ||
            $details['copyreason'] !== 'legacy' || !\quizaccess_presencial\local\invitation::validate($cmid, $oldtoken)
    ) {
        throw new RuntimeException('The legacy invitation was not preserved after upgrade.');
    }
    $replacement = \quizaccess_presencial\local\invitation::generate($cmid, 1);
    $current = \quizaccess_presencial\local\invitation::get_management_details($cmid);
    if (
        $current['token'] !== $replacement['token'] ||
            \quizaccess_presencial\local\invitation::validate($cmid, $oldtoken) ||
            !\quizaccess_presencial\local\invitation::validate($cmid, $replacement['token'])
    ) {
        throw new RuntimeException('Regeneration did not replace the legacy invitation.');
    }
    unlink($statefile);
    echo "Legacy invitation preserved and replaced successfully.\n";
} else {
    throw new RuntimeException('Expected create or verify.');
}
