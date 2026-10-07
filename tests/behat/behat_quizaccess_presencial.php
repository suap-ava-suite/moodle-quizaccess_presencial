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
 * @copyright  2026 SUAP AVA Suite
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_quizaccess_presencial extends behat_base {
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
        $form = $page->find('css', 'form[action*="manage.php"]');
        $sesskeyinput = $form->find('css', 'input[name="sesskey"]');
        $selectedaccount = $form->find('css', 'select[name^="applicators"]');
        if (!$sesskeyinput || !$selectedaccount) {
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
}
