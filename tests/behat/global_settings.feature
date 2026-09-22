@quizaccess @quizaccess_presencial @mod_quiz
Feature: Configure the global In-person release policies
  To define the In-person release operational behaviour
  As an administrator
  I need to configure the deadlines and justification policy in Moodle administration

  Scenario: Update global policies
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Quiz > In-person release" in site administration
    When I set the field "Release request validity" to "20"
    And I set the field "Attempt authorisation validity" to "10"
    And I set the field "Require a rejection justification" to "1"
    And I press "Save changes"
    Then I should see "Changes saved"
    And the "value" attribute of "#id_s_quizaccess_presencial_requestvalidity" "css_element" should contain "20"
    And the "value" attribute of "#id_s_quizaccess_presencial_authorisationvalidity" "css_element" should contain "10"

  Scenario: Reject an invalid deadline
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Quiz > In-person release" in site administration
    When I set the field "Release request validity" to "0"
    And I press "Save changes"
    Then I should see "This value is not valid"
