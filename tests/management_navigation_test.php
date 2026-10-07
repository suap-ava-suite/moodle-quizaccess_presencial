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

#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
/**
 * Application-team and invitation management navigation through Moodle's complete header render integration.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\hook_callbacks
 */
final class management_navigation_test extends \advanced_testcase {
    /**
     * Only quiz managers receive the management link when Moodle builds its header.
     *
     * @dataProvider navigation_provider
     * @param string $activity Activity type.
     * @param string $role Course role.
     * @param bool $expected Whether the management link should be rendered.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('navigation_provider')]
    public function test_rendered_activity_navigation(string $activity, string $role, bool $expected): void {
        global $PAGE, $OUTPUT;

        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, $role);
        $module = $generator->create_module($activity, ['course' => $course->id]);
        $this->setUser($user);
        $cm = get_coursemodule_from_id($activity, $module->cmid, 0, false, MUST_EXIST);

        $PAGE->set_cm($cm, $course, $module);
        $PAGE->set_url('/mod/' . $activity . '/view.php', ['id' => $cm->id]);
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_title($module->name);
        $PAGE->set_heading($course->fullname);
        $PAGE->force_theme('boost');
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        // Do not initialise secondary navigation or invoke the callback by hand.
        $header = $OUTPUT->header();
        $OUTPUT->footer();
        $document = new \DOMDocument();
        $document->loadHTML($header, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $url = new \moodle_url('/mod/quiz/accessrule/presencial/manage.php', ['cmid' => $cm->id]);
        $links = $xpath->query('//nav//a[@href="' . $url->out(false) . '"]');
        $this->assertCount($expected ? 1 : 0, $links);
        if ($expected) {
            $this->assertSame(get_string('manageapplicators', 'quizaccess_presencial'), trim($links->item(0)->textContent));
            $this->assertSame('true', $links->item(0)->parentNode->getAttribute('data-forceintomoremenu'));
        }
    }

    /**
     * Contextual navigation cases.
     *
     * @return array
     */
    public static function navigation_provider(): array {
        return [
            'quiz manager' => ['quiz', 'editingteacher', true],
            'quiz student' => ['quiz', 'student', false],
            'other activity manager' => ['assign', 'editingteacher', false],
        ];
    }
}
