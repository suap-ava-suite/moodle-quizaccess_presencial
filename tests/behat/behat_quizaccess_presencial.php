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
     * Prepare valid delegations through the public domain operation.
     *
     * @Given application account :username is delegated to the quizzes in course :shortname
     * @param string $username Applicator username.
     * @param string $shortname Course short name.
     */
    public function the_account_is_delegated_to_course_quizzes(string $username, string $shortname): void {
        global $DB, $USER;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $quizzes = $DB->get_records('quiz', ['course' => $courseid], 'id ASC', 'id');
        $previoususer = $USER;
        try {
            \core\session\manager::set_user(get_admin());
            foreach ($quizzes as $quiz) {
                \quizaccess_presencial\local\delegation_manager::include_users($quiz->id, [$userid], $USER->id);
            }
        } finally {
            \core\session\manager::set_user($previoususer);
        }
    }

    /**
     * Reach an element with Tab or menu arrow keys, without calling focus() or clicking it.
     *
     * @When I reach :element :selectortype using only the keyboard
     * @param string $element Element identifier.
     * @param string $selectortype Moodle selector type.
     */
    public function i_reach_using_only_the_keyboard(string $element, string $selectortype): void {
        self::require_javascript_in_session($this->getSession());
        $node = $this->get_selected_node($selectortype, $element);
        $key = $node->getAttribute('role') === 'menuitem' ? behat_keys::ARROW_DOWN : behat_keys::TAB;
        $xpath = json_encode($node->getXpath());
        $script = "return document.activeElement === document.evaluate({$xpath}, document,
            null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;";
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if ($this->evaluate_script($script)) {
                return;
            }
            self::type_keys($this->getSession(), [$key]);
        }
        throw new ExpectationException("Cannot reach {$element} using the keyboard.", $this->getSession());
    }

    /**
     * Verify the native course and quiz routes still demand enrolment.
     *
     * @Then academic access to :quizname should still require enrolment
     * @param string $quizname Quiz name.
     */
    public function academic_access_should_still_require_enrolment(string $quizname): void {
        global $DB;

        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $previousurl = $this->getSession()->getCurrentUrl();
        $urls = [
            new moodle_url('/course/view.php', ['id' => $quiz->course]),
            new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]),
        ];
        try {
            foreach ($urls as $url) {
                $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
                $path = parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH);
                if (
                    !str_ends_with($path, '/enrol/index.php') ||
                    !str_contains($this->getSession()->getPage()->getText(), get_string('enrolmentoptions', 'enrol'))
                ) {
                    throw new ExpectationException('Academic access must still require enrolment.', $this->getSession());
                }
            }
        } finally {
            $this->getSession()->visit($previousurl);
        }
    }

    /**
     * Suspend an account through Moodle without logging out the browser session.
     *
     * @When the application account :username is suspended
     * @param string $username Applicator username.
     */
    public function the_application_account_is_suspended(string $username): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        user_update_user((object) ['id' => $userid, 'suspended' => 1]);
    }

    /**
     * Revoke through the public delegation boundary while the browser session remains open.
     *
     * @When the application delegation of :username for :quizname is revoked
     * @param string $username Applicator username.
     * @param string $quizname Quiz name.
     */
    public function the_application_delegation_is_revoked(string $username, string $quizname): void {
        global $DB, $USER;

        $quizid = $DB->get_field('quiz', 'id', ['name' => $quizname], MUST_EXIST);
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $previoususer = $USER;
        try {
            \core\session\manager::set_user(get_admin());
            $delegations = \quizaccess_presencial\local\delegation_manager::list_current($quizid);
            foreach ($delegations as $delegation) {
                if ((int) $delegation->userid === (int) $userid) {
                    \quizaccess_presencial\local\delegation_manager::revoke($quizid, $delegation->id, $USER->id);
                    return;
                }
            }
            throw new coding_exception('The applicator must have a current delegation to revoke.');
        } finally {
            \core\session\manager::set_user($previoususer);
        }
    }

    /**
     * A direct application URL must deny access before revealing application metadata.
     *
     * @Then accessing the application panel for :quizname should be denied
     * @param string $quizname Quiz name.
     */
    public function application_panel_access_should_be_denied(string $quizname): void {
        global $DB;

        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, 0, false, MUST_EXIST);
        $url = new moodle_url('/mod/quiz/accessrule/presencial/application.php', ['cmid' => $cm->id]);
        try {
            $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
            $page = $this->getSession()->getPage();
            $error = $page->find('css', '[data-rel="fatalerror"]');
            if (
                !$error ||
                !str_contains($error->getText(), get_string('applicationaccessdenied', 'quizaccess_presencial')) ||
                str_contains($page->getText(), $quizname)
            ) {
                throw new ExpectationException('Expected access denial without application metadata.', $this->getSession());
            }
        } finally {
            // Leave the expected error page before Moodle's after-step exception check.
            $this->getSession()->visit($this->locate_path('/'));
        }
    }

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
     * Open the invitation previously observed in the generated-link response.
     *
     * @When /^I open the remembered invitation link$/
     */
    public function i_open_the_remembered_invitation_link(): void {
        if (!$this->previouslink) {
            throw new \coding_exception('No generated invitation link has been remembered.');
        }
        $this->getSession()->visit($this->locate_path($this->previouslink));
    }

    /**
     * Arrange a current or future period through the public quiz settings boundary.
     *
     * @Given the authorization period for :quizname starts :period
     * @param string $quizname Quiz name.
     * @param string $period Whether authorization starts now or later.
     */
    public function the_authorization_period_starts(string $quizname, string $period): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/accessrule/presencial/rule.php');
        $quizid = $DB->get_field('quiz', 'id', ['name' => $quizname], MUST_EXIST);
        $quiz = \mod_quiz\quiz_settings::create($quizid)->get_quiz();
        $cm = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);
        $quiz->coursemodule = $cm->id;
        $quiz->presencial_timeopen = time() + ($period === 'later' ? HOURSECS : -HOURSECS);
        $quiz->presencial_timeclose = time() + (2 * HOURSECS);
        \quizaccess_presencial::save_settings($quiz);
    }

    /**
     * Start from either a logged-out browser or Moodle's visitor account.
     *
     * @Given I start the invitation journey with :session
     * @param string $session Initial authentication state.
     */
    public function i_start_the_invitation_journey_with(string $session): void {
        if ($session === 'guest') {
            $this->execute('behat_general::i_visit', ['/login/index.php']);
            $this->execute('behat_forms::press_button', ['loginguestbtn']);
        } else if ($session !== 'no session') {
            throw new \coding_exception('Unknown invitation authentication state: ' . $session);
        }
    }

    /**
     * Check the real login returned to the original URL, including its validation parameters.
     *
     * @Then I should be on the remembered invitation page
     */
    public function i_should_be_on_the_remembered_invitation_page(): void {
        if ($this->getSession()->getCurrentUrl() !== $this->previouslink) {
            throw new ExpectationException('Login did not return to the original invitation.', $this->getSession());
        }
    }

    /**
     * Verify the browser's acceptance form exposes the CSRF-protected POST boundary.
     *
     * @Then the invitation acceptance form should require POST and a session key
     */
    public function the_invitation_acceptance_form_should_require_post_and_a_session_key(): void {
        $form = $this->getSession()->getPage()->find('css', 'form[action*="invite.php"]');
        $sesskey = $form ? $form->find('css', 'input[type="hidden"][name="sesskey"]') : null;
        if (!$form || strtolower($form->getAttribute('method')) !== 'post' || !$sesskey || !$sesskey->getValue()) {
            throw new ExpectationException('Invitation acceptance must use POST with a session key.', $this->getSession());
        }
    }

    /**
     * Try to accept via GET using the browser's valid session key.
     *
     * @When I request invitation acceptance with GET
     */
    public function i_request_invitation_acceptance_with_get(): void {
        $sesskey = $this->getSession()->getPage()->find('css', 'input[name="sesskey"]');
        if (!$this->previouslink || !$sesskey) {
            throw new \coding_exception('An invitation acceptance form must be open.');
        }
        $url = new moodle_url($this->previouslink, ['accept' => 1, 'sesskey' => $sesskey->getValue()]);
        $this->getSession()->visit($this->locate_path($url->out(false)));
    }

    /**
     * Check the complete response for academic details and navigation, even when hidden by CSS.
     *
     * @Then the invitation page should not expose restricted academic information
     */
    public function the_invitation_page_should_not_expose_restricted_academic_information(): void {
        $page = $this->getSession()->getPage();
        $html = $page->getContent();
        $restrictedcontent = [
            'Student One', 'Secret questions', 'Private question', 'Secret question text',
            'Private material', 'Secret course content', 'Secret quiz introduction', 'Secret course summary',
            'Private grade', 'Secret grading feedback', '73.25',
        ];
        foreach ($restrictedcontent as $restricted) {
            if (str_contains($html, $restricted)) {
                throw new ExpectationException('The invitation exposed: ' . $restricted, $this->getSession());
            }
        }
        $restrictedpaths = [
            '/question/', '/grade/', '/user/index.php', '/mod/quiz/view.php',
            '/mod/quiz/report.php', '/mod/quiz/edit.php', '/course/view.php',
        ];
        foreach ($restrictedpaths as $restrictedpath) {
            foreach ($page->findAll('css', 'a[href]') as $link) {
                if (str_contains($link->getAttribute('href'), $restrictedpath)) {
                    throw new ExpectationException('The invitation exposed academic navigation.', $this->getSession());
                }
            }
        }
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
        if (
            $DB->record_exists('quizaccess_presencial_req', [
                'quizid' => $quiz->id,
                'userid' => $user->id,
                'attemptnumber' => $attemptnumber,
            ])
        ) {
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
    /**
     * Inspect all rendered operational identities for profile links.
     *
     * @Then the operational identity should not link to student profiles
     */
    public function operational_identity_has_no_profile_links(): void {
        $links = $this->getSession()->getPage()->findAll('css', '.presencial-queue a[href*="/user/"]');
        if ($links) {
            throw new ExpectationException('Operational identities must not link to profiles.', $this->getSession());
        }
    }

    /**
     * Return to the student's most recent request without creating another request.
     *
     * @When I open the latest release request for :quizname for :username
     * @param string $quizname Quiz name.
     * @param string $username Student username.
     */
    public function open_latest_release_request(string $quizname, string $username): void {
        global $DB;
        $quizid = $DB->get_field('quiz', 'id', ['name' => $quizname], MUST_EXIST);
        $studentid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $requests = $DB->get_records(
            'quizaccess_presencial_req',
            ['quizid' => $quizid, 'userid' => $studentid],
            'id DESC',
            'id',
            0,
            1
        );
        $request = reset($requests);
        $url = new moodle_url('/mod/quiz/accessrule/presencial/wait.php', ['requestid' => $request->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * An authenticated applicator changes a decision while the student's browser waits.
     *
     * @When applicator :operator rejects the request for :quizname for :username with :reason
     * @When applicator :operator authorizes the request for :quizname for :username
     * @param string $operator Applicator username.
     * @param string $quizname Quiz name.
     * @param string $username Student username.
     * @param string|null $reason Explanation, or null to authorize.
     */
    public function applicator_decides_request(string $operator, string $quizname, string $username, ?string $reason = null): void {
        global $DB, $USER;
        $quizid = $DB->get_field('quiz', 'id', ['name' => $quizname], MUST_EXIST);
        $studentid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $applicator = $DB->get_record('user', ['username' => $operator], '*', MUST_EXIST);
        $requests = $DB->get_records(
            'quizaccess_presencial_req',
            ['quizid' => $quizid, 'userid' => $studentid],
            'id DESC',
            'id',
            0,
            1
        );
        $request = reset($requests);
        $previoususer = $USER;
        try {
            \core\session\manager::set_user($applicator);
            $service = new \quizaccess_presencial\local\release_request_service();
            if ($reason === null) {
                $service->authorize($request->id);
            } else {
                $service->reject($request->id, $reason);
            }
        } finally {
            \core\session\manager::set_user($previoususer);
        }
    }

    /**
     * Send a realistic multiline textarea explanation, including markup as plain text.
     *
     * @When applicator :operator rejects the request for :quizname for :username with a multiline explanation
     * @param string $operator Applicator username.
     * @param string $quizname Quiz name.
     * @param string $username Student username.
     */
    public function applicator_rejects_with_multiline_explanation(string $operator, string $quizname, string $username): void {
        $this->applicator_decides_request(
            $operator,
            $quizname,
            $username,
            "First line.\r\nSecond line.\r\n<script>alert('unsafe')</script>"
        );
    }

    /**
     * A status poll without a decision change must leave the current document intact.
     *
     * @Then the waiting page should remain stable across the next status poll
     */
    public function waiting_page_remains_stable(): void {
        $this->getSession()->executeScript('window.setTimeout(() => { window.presencialStablePoll = true; }, 6000);');
        if (!$this->getSession()->wait(8000, 'window.presencialStablePoll === true')) {
            throw new ExpectationException('The waiting page reloaded without a decision change.', $this->getSession());
        }
    }
}
