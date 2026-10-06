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

use quizaccess_presencial\form\invitation;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Invitation actions at Moodle's public form submission boundary.
 *
 * @package    quizaccess_presencial
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_presencial\form\invitation
 */
final class invitation_form_test extends \basic_testcase {
    /**
     * A stale form keeps the submitted generation so the domain can reject it.
     */
    public function test_submission_preserves_the_generation_seen_by_the_manager(): void {
        invitation::mock_submit(['cmid' => 123, 'generation' => 3, 'generate' => 'Regenerate invitation']);
        $form = $this->create_form();

        $data = $form->get_data();
        $this->assertNotNull($data);
        $this->assertSame(3, $data->generation);
    }

    /**
     * Putting form action parameters in the query string is not a submission.
     */
    public function test_get_parameters_cannot_submit_an_invitation_action(): void {
        invitation::mock_submit(['cmid' => 123, 'generation' => 4, 'generate' => 'Regenerate invitation'], [], 'get');

        $this->assertNull($this->create_form()->get_data());
    }

    /**
     * Moodle rejects a submitted action when its session key is invalid.
     */
    public function test_submission_requires_a_valid_session_key(): void {
        invitation::mock_submit(['cmid' => 123, 'generation' => 4, 'disable' => 'Disable invitation']);
        $_POST['sesskey'] = 'invalid';

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidsesskey', 'error'));
        $this->create_form();
    }

    /**
     * Create a form using only the public, non-secret invitation status.
     *
     * @return invitation
     */
    private function create_form(): invitation {
        return new invitation(new \moodle_url('/mod/quiz/accessrule/presencial/manage.php', ['cmid' => 123]), [
            'cmid' => 123,
            'status' => ['state' => 'active', 'generation' => 4, 'timeexpires' => 2000000000, 'canissue' => true],
        ]);
    }
}
