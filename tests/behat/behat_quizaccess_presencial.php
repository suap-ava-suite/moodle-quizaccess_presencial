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
 * Public invitation management browser steps.
 *
 * @package    quizaccess_presencial
 * @category   test
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Behat loads step definitions before Moodle's config.php.
require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Navigate directly to invitation management and compare displayed links.
 */
class behat_quizaccess_presencial extends behat_base {
    /** @var string|null A link observed in the browser, retained only by this test. */
    private ?string $previouslink = null;

    /**
     * Resolve invitation management for the named quiz.
     *
     * @param string $type Page type.
     * @param string $identifier Quiz name.
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        if (strtolower($type) !== 'invitation') {
            throw new coding_exception('Unknown invitation page type: ' . $type);
        }
        $quiz = $DB->get_record('quiz', ['name' => $identifier], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
        return new moodle_url('/mod/quiz/accessrule/presencial/manage.php', ['cmid' => $cm->id]);
    }

    /**
     * Retain the link presented by the browser for comparison after regeneration.
     *
     * @Given /^I remember the generated invitation link$/
     */
    public function i_remember_the_generated_invitation_link(): void {
        $this->previouslink = $this->find_field('Invitation link')->getValue();
    }

    /**
     * Compare the regenerated link through the public output field.
     *
     * @Then /^the generated invitation link should differ from the previous link$/
     */
    public function the_generated_invitation_link_should_differ(): void {
        $currentlink = $this->find_field('Invitation link')->getValue();
        if ($this->previouslink === null || !$currentlink || $currentlink === $this->previouslink) {
            throw new ExpectationException('The regenerated invitation must have a different link.', $this->getSession());
        }
    }

    /**
     * Arrange elapsed time through Moodle's clock and public expiry task, without editing invitation storage.
     *
     * @Given /^the generated invitation for "([^"]+)" has expired$/
     * @param string $quizname Quiz name.
     */
    public function the_generated_invitation_has_expired(string $quizname): void {
        global $CFG, $DB;

        $quizid = $DB->get_field('quiz', 'id', ['name' => $quizname], MUST_EXIST);
        $quiz = \mod_quiz\quiz_settings::create($quizid)->get_quiz();
        require_once($CFG->libdir . '/testing/classes/frozen_clock.php');
        $clock = \core\di::get(\core\clock::class);
        try {
            \core\di::set(\core\clock::class, new \frozen_clock((int) $quiz->presencial_timeclose));
            (new \quizaccess_presencial\task\expire_invitations())->execute();
        } finally {
            \core\di::set(\core\clock::class, $clock);
        }
    }
}
