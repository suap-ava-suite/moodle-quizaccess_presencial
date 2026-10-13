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

namespace quizaccess_presencial;

use quizaccess_presencial\local\delegation_manager;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');

#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
/**
 * My applications visibility through Moodle's actual header rendering.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\hook_callbacks
 */
final class applications_navigation_test extends \advanced_testcase {
    /**
     * The user menu link requires a current or future effective delegation.
     *
     * @dataProvider visibility_provider
     * @param string $state Account and delegation state.
     * @param bool $expected Whether the link should appear.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('visibility_provider')]
    public function test_rendered_user_menu(string $state, bool $expected): void {
        global $PAGE, $OUTPUT, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $quiz->coursemodule = $quiz->cmid;
        $quiz->presencial_enabled = 1;
        $quiz->presencial_timeopen = time() + ($state === 'future' ? HOURSECS : -HOURSECS);
        $quiz->presencial_timeclose = time() + (2 * HOURSECS);
        \quizaccess_presencial::save_settings($quiz);
        $user = $generator->create_user();
        if ($state === 'teacher') {
            $generator->enrol_user($user->id, $course->id, 'editingteacher');
        } else if ($state !== 'unassigned') {
            $delegations = delegation_manager::include_users($quiz->id, [$user->id], $USER->id);
            if ($state === 'revoked') {
                delegation_manager::revoke($quiz->id, $delegations[$user->id]->id, $USER->id);
            }
        }
        $this->setUser($user);
        if ($state === 'expired') {
            $this->mock_clock_with_frozen((int) $quiz->presencial_timeclose);
        } else if ($state === 'suspended') {
            user_update_user((object) ['id' => $user->id, 'suspended' => 1]);
        }

        $PAGE->set_course(get_site());
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/');
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title('Site');
        $PAGE->set_heading('Site');
        $PAGE->force_theme('boost');
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $header = $OUTPUT->header();
        $OUTPUT->footer();

        $document = new \DOMDocument();
        $document->loadHTML($header, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $url = new \moodle_url('/mod/quiz/accessrule/presencial/applications.php');
        $links = $xpath->query('//a[@href="' . $url->out(false) . '"]');
        $this->assertCount($expected ? 1 : 0, $links);
        if ($expected) {
            $this->assertSame('My applications', trim($links->item(0)->textContent));
        }
    }

    /**
     * Navigation visibility according to the issue's delegation-only rule.
     *
     * @return array
     */
    public static function visibility_provider(): array {
        return [
            'current' => ['current', true],
            'future' => ['future', true],
            'no delegation' => ['unassigned', false],
            'revoked' => ['revoked', false],
            'expired' => ['expired', false],
            'suspended existing session' => ['suspended', false],
            'professor without delegation' => ['teacher', false],
        ];
    }
}
