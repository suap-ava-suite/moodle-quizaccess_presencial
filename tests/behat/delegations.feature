@quizaccess @quizaccess_presencial @mod_quiz
Feature: Manage the application team from a quiz
  To prepare an in-person quiz
  As a teacher
  I need to include and revoke eligible Moodle accounts without enrolment changes

  Background:
    Given the following "users" exist:
      | username    | firstname  | lastname | email                  |
      | teacher1    | Professor  | Um       | teacher1@example.com   |
      | applicator1 | Aplicador  | Um       | applicator1@example.com |
      | student1    | Estudante  | Um       | student1@example.com   |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Curso 1  | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1      | editingteacher |
      | student1 | C1      | student        |
    And the following "activities" exist:
      | activity | name            | course | idnumber | timeopen              | timeclose             |
      | quiz     | Questionário 1  | C1     | quiz1    | ## 1 January 2030 08:00 ## | ## 2 January 2030 22:00 ## |

  Scenario: Teacher includes and revokes an application account
    Given I am on the "quiz1" "Activity editing" page logged in as "teacher1"
    When I set the field "Enable in-person release" to "Yes"
    And I press "Save and return to course"
    And I am on the "quiz1" "Activity" page
    Then I should see "Manage application team"
    And I follow "Manage application team"
    Then I should see "Eligible accounts"
    When I set the field "Eligible accounts" to "Aplicador Um"
    And I request inclusion of the selected application accounts with GET
    Then I should see "None"
    When I set the field "Eligible accounts" to "Aplicador Um"
    And I press "Include selected accounts"
    Then I should see "Application delegation included."
    And I should see "Aplicador Um" in the ".presencial-current-applicators" "css_element"
    And I should see "1" node occurrences of type "tr" in the ".presencial-current-applicators tbody" "css_element"
    And I should see "Direct inclusion"
    When I set the field "Eligible accounts" to "Aplicador Um"
    And I press "Include selected accounts"
    Then I should see "Application delegation included."
    And I should see "1" node occurrences of type "tr" in the ".presencial-current-applicators tbody" "css_element"
    And I should see "1" occurrences of "Aplicador Um" in the ".presencial-current-applicators" "css_element"
    When I request revocation of the current application delegation with GET
    Then I should see "Aplicador Um" in the ".presencial-current-applicators" "css_element"
    When I press "Revoke"
    Then I should see "Application delegation revoked."
    And I should see "None"

  Scenario: Student cannot find the application-team management entry
    Given I am on the "quiz1" "Activity" page logged in as "student1"
    Then I should not see "Manage application team"
    When I open the application-team management page for "quiz1"
    Then I should see "Sorry, but you do not currently have permissions to do that"
