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
 * Behat steps for Liberação Presencial.
 *
 * @package    quizaccess_presencial
 * @category   test
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Behat loads step definitions before Moodle's config.php.
require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;
use quizaccess_presencial\external\authorize_request as authorize_request_endpoint;

/**
 * Steps for application-team management, invitations, and release requests.
 */
class behat_quizaccess_presencial extends behat_base {
    /** @var string|null A link observed in the browser, retained only by this test. */
    private ?string $previouslink = null;

    /**
     * Resolve the management page, which hosts the invitation, for the named quiz.
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
     * Verifies that direct access to the application-team management page is denied.
     *
     * @When I try to open the application-team management page for :quizidnumber without permission
     * @param string $quizidnumber Quiz activity idnumber.
     */
    public function i_try_to_open_the_application_team_management_page_for_without_permission(string $quizidnumber): void {
        $cm = $this->get_cm_by_activity_name('quiz', $quizidnumber);
        $url = new moodle_url('/mod/quiz/accessrule/presencial/manage.php', ['cmid' => $cm->id]);
        $previousurl = $this->getSession()->getCurrentUrl();
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));

        $error = $this->getSession()->getPage()->find('css', '[data-rel="fatalerror"]');
        if (!$error || !str_contains($error->getText(), 'Sorry, but you do not currently have permissions to do that')) {
            throw new \Exception('Direct access to the application-team management page was not denied.');
        }

        // Leave the expected error page before Moodle's automatic exception check runs.
        $this->getSession()->visit($previousurl);
    }

    /**
     * Tries to include the account selected in the management form with a GET request.
     *
     * @When I request inclusion of the selected application accounts with GET
     */
    public function i_request_inclusion_of_the_selected_application_accounts_with_get(): void {
        $page = $this->getSession()->getPage();
        $form = null;
        $selectedaccount = null;
        // The management page also hosts the invitation form, so locate the form owning the account selector.
        foreach ($page->findAll('css', 'form[action*="manage.php"]') as $candidate) {
            $selectedaccount = $candidate->find('css', 'select[name^="applicators"]');
            if ($selectedaccount) {
                $form = $candidate;
                break;
            }
        }
        $sesskeyinput = $form ? $form->find('css', 'input[name="sesskey"]') : null;
        if (!$form || !$sesskeyinput || !$selectedaccount) {
            throw new \coding_exception('The management form must expose its selected account and session token.');
        }

        $params = [
            'addapplicators' => 1,
            'sesskey' => $sesskeyinput->getValue(),
        ];
        foreach ($selectedaccount->getValue() as $index => $userid) {
            $params['applicators[' . $index . ']'] = $userid;
        }
        $url = new moodle_url($form->getAttribute('action'), $params);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Tries to revoke the displayed current delegation with a GET request.
     *
     * @When I request revocation of the current application delegation with GET
     */
    public function i_request_revocation_of_the_current_application_delegation_with_get(): void {
        $page = $this->getSession()->getPage();
        $form = null;
        $sesskeyinput = null;
        $revokeinput = null;
        foreach ($page->findAll('css', 'form[action*="manage.php"]') as $candidate) {
            $revokeinput = $candidate->find('css', 'input[name="revoke"]');
            if ($revokeinput) {
                $form = $candidate;
                $sesskeyinput = $candidate->find('css', 'input[name="sesskey"]');
                break;
            }
        }
        if (!$form || !$sesskeyinput || !$revokeinput) {
            throw new \coding_exception('The management form must expose its revocation and session token.');
        }

        $url = new moodle_url($form->getAttribute('action'), [
            'revoke' => $revokeinput->getValue(),
            'sesskey' => $sesskeyinput->getValue(),
        ]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Retain the link presented by the browser for comparison after regeneration.
     *
     * @Given /^I remember the generated invitation link$/
     */
    public function i_remember_the_generated_invitation_link(): void {
        $this->previouslink = $this->find_field('Invitation link')->getAttribute('value');
    }

    /**
     * Compare the regenerated link through the public output field.
     *
     * @Then /^the generated invitation link should differ from the previous link$/
     */
    public function the_generated_invitation_link_should_differ(): void {
        $currentlink = $this->find_field('Invitation link')->getAttribute('value');
        if ($this->previouslink === null || !$currentlink || $currentlink === $this->previouslink) {
            throw new ExpectationException('The regenerated invitation must have a different link.', $this->getSession());
        }
    }

    /**
     * Require the expected Moodle permission error and no invitation management controls.
     *
     * @Then /^accessing invitation management for "([^"]+)" should be denied$/
     * @param string $quizname Quiz name.
     */
    public function invitation_management_should_be_denied(string $quizname): void {
        $denied = false;
        try {
            $this->execute('behat_navigation::i_am_on_page_instance', [$quizname, 'quizaccess_presencial > Invitation']);
        } catch (\Exception $exception) {
            if (!preg_match('/(?:^|\n)Error code: nopermissions(?:\n|$)/', $exception->getMessage())) {
                throw $exception;
            }
            $page = $this->getSession()->getPage();
            $error = $page->find('css', '[data-rel="fatalerror"]');
            if (
                !$error ||
                !str_contains($error->getText(), 'Sorry, but you do not currently have permissions to do that') ||
                $page->findButton('Generate invitation') ||
                $page->findButton('Regenerate invitation') ||
                $page->findButton('Disable invitation') ||
                $page->findField('Invitation link')
            ) {
                throw new ExpectationException('Expected a permission error without invitation controls.', $this->getSession());
            }
            $denied = true;
        } finally {
            // Leave the verified error response before Moodle checks the next step for unexpected exceptions.
            $this->execute('behat_navigation::i_am_on_page_instance', [$quizname, 'mod_quiz > View']);
        }
        if (!$denied) {
            throw new ExpectationException('Invitation management allowed an unauthorised user.', $this->getSession());
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

    /**
     * Assert that the logged-in student has not received an attempt prematurely.
     *
     * @Then /^there should be no attempt for "([^"]*)" as "([^"]*)"$/
     * @param string $quizname Quiz name.
     * @param string $username Student username.
     */
    public function assert_no_attempt_for_quiz(string $quizname, string $username): void {
        global $DB;
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $count = $DB->count_records('quiz_attempts', [
            'quiz' => $quiz->id,
            'userid' => $user->id,
            'preview' => 0,
        ]);
        if ($count) {
            throw new ExpectationException('A quiz attempt was created before release.', $this->getSession());
        }
    }

    /**
     * Assert that a student's next attempt has one pending release request.
     *
     * @Then /^the release request for "([^"]*)" for "([^"]*)" should be pending$/
     * @param string $quizname Quiz name.
     * @param string $username Student username.
     */
    public function assert_request_is_pending(string $quizname, string $username): void {
        global $DB;
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $request = $DB->get_record('quizaccess_presencial_req', [
            'quizid' => $quiz->id,
            'userid' => $user->id,
            'attemptnumber' => 1,
            'active' => 1,
        ], '*', MUST_EXIST);
        if ($request->state !== 'pending') {
            throw new ExpectationException('The release request is not pending.', $this->getSession());
        }
    }

    /**
     * Assert that pending users have no manual polling control.
     *
     * @Then /^the waiting page should not contain a manual check button$/
     */
    public function assert_no_manual_check_button(): void {
        $buttons = $this->getSession()->getPage()->findAll('css', '.quizaccess-presencial-waiting button');
        if ($buttons) {
            throw new ExpectationException('The pending page contains a manual check button.', $this->getSession());
        }
    }

    /**
     * Authorize the current student's request in the fixture for the UI transition test.
     *
     * @When /^I authorize the release request for "([^"]*)" for "([^"]*)"$/
     * @param string $quizname Quiz name.
     * @param string $username Student username.
     */
    public function authorize_request(string $quizname, string $username): void {
        global $DB, $USER;
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $request = $DB->get_record('quizaccess_presencial_req', [
            'quizid' => $quiz->id,
            'userid' => $user->id,
            'attemptnumber' => 1,
            'state' => 'pending',
        ], '*', MUST_EXIST);
        $student = $USER;
        \core\session\manager::set_user(get_admin());
        try {
            authorize_request_endpoint::execute((int) $request->id);
        } finally {
            \core\session\manager::set_user($student);
        }
    }

    /**
     * Move the expiry into the past so the browser's next normal poll processes it.
     *
     * @When /^I make the release request for "([^"]*)" for "([^"]*)" due$/
     * @param string $quizname Quiz name.
     * @param string $username Student username.
     */
    public function make_request_due(string $quizname, string $username): void {
        global $DB;
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $request = $DB->get_record('quizaccess_presencial_req', [
            'quizid' => $quiz->id,
            'userid' => $user->id,
            'attemptnumber' => 1,
            'state' => 'pending',
        ], '*', MUST_EXIST);
        $now = \core\di::get(\core\clock::class)->time();
        $DB->set_field('quizaccess_presencial_req', 'expiresat', $now - 1, ['id' => $request->id]);
    }

    /**
     * Wait for the scheduled browser poll to reload after a state change.
     *
     * @When /^I wait for the release request poll "([^"]*)"$/
     * @param string $state Expected state rendered after the poll.
     */
    public function wait_for_request_poll(string $state): void {
        $success = $this->getSession()->wait(
            10000,
            "document.querySelector('[data-request-id]')?.getAttribute('data-state') === '" . $state . "'",
        );
        if (!$success) {
            throw new ExpectationException(
                'The automatic release request poll did not show ' . $state . '.',
                $this->getSession(),
            );
        }
    }

    /**
     * Assert that no request exists for the given next attempt number.
     *
     * @Then /^there should be no release request for attempt (\d+) of "([^"]*)" for "([^"]*)"$/
     * @param int $attemptnumber Attempt number.
     * @param string $quizname Quiz name.
     * @param string $username Student username.
     */
    public function assert_no_request_for_attempt(int $attemptnumber, string $quizname, string $username): void {
        global $DB;
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        if ($DB->record_exists('quizaccess_presencial_req', [
            'quizid' => $quiz->id,
            'userid' => $user->id,
            'attemptnumber' => $attemptnumber,
        ])) {
            throw new ExpectationException(
                'An unexpected release request exists for attempt ' . $attemptnumber . '.',
                $this->getSession(),
            );
        }
    }

    /**
     * Assert that the newly created attempt consumed its release authorization.
     *
     * @Then /^the release request for attempt (\d+) of "([^"]*)" for "([^"]*)" should be consumed$/
     * @param int $attemptnumber Attempt number.
     * @param string $quizname Quiz name.
     * @param string $username Student username.
     */
    public function assert_request_consumed(int $attemptnumber, string $quizname, string $username): void {
        global $DB;
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $request = $DB->get_record('quizaccess_presencial_req', [
            'quizid' => $quiz->id,
            'userid' => $user->id,
            'attemptnumber' => $attemptnumber,
        ], '*', MUST_EXIST);
        if ($request->state !== 'consumed') {
            throw new ExpectationException('The attempt did not consume its release request.', $this->getSession());
        }
    }
}
