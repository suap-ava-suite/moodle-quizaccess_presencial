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
      | accent1     | João       | Conceição | accent1@example.com    |
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
    Then I should see "Current application team"
    And I should see "Add applicators"
    And I should see "Invite applicators"
    And I should see "Eligible accounts"
    And I should see "João Conceição" in the "#applicators" "css_element"
    And "//h3[contains(., 'Current application team')]" "xpath_element" should appear before "//h3[contains(., 'Add applicators')]" "xpath_element"
    And "//h3[contains(., 'Add applicators')]" "xpath_element" should appear before "//h3[contains(., 'Invite applicators')]" "xpath_element"
    And "#applicators_searchtext" "css_element" should appear before "Include selected accounts" "button"
    And "Include selected accounts" "button" should appear before "Eligible accounts" "field"
    When I press "Include selected accounts"
    Then I should see "No account selected."
    And I should see "None"
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
    When I try to open the application-team management page for "quiz1" without permission

  @javascript
  Scenario: Teacher searches for an eligible account with the keyboard
    Given I am on the "quiz1" "Activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I set the field "Enable in-person release" to "Yes"
    And I press "Save and return to course"
    And I am on the "quiz1" "Activity" page
    When I navigate to "Manage application team" in current page administration
    And I reach "#applicators_searchtext" "css_element" using only the keyboard
    And I set the field "applicators_searchtext" to "Aplicador"
    Then the next focused element should be "#applicators_clearbutton" "css_element"
    And the next focused element should be "#userselector_options_caption a" "css_element"
    And the next focused element should be "Include selected accounts" "button"
    And the next focused element should be "Eligible accounts" "field"
    When I reach "#userselector_options_caption a" "css_element" using only the keyboard
    And I press the enter key
    Then the next focused element should be "#userselector_preserveselectedid" "css_element"
    When I reach "#userselector_options_caption a" "css_element" using only the keyboard
    And I press the enter key
    Then "#applicators optgroup[label*='Aplicador']" "css_element" should exist
    And I should see "Aplicador Um" in the "#applicators" "css_element"
    And I should not see "Estudante Um" in the "#applicators" "css_element"
    When I press "Clear"
    Then I should see "Estudante Um" in the "#applicators" "css_element"
    And I reach "Include selected accounts" "button" using only the keyboard
    Then the next focused element should be "Eligible accounts" "field"

  @javascript
  Scenario: Teacher uses section help and searches with Enter
    Given I am on the "quiz1" "Activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I set the field "Enable in-person release" to "Yes"
    And I press "Save and return to course"
    And I am on the "quiz1" "Activity" page
    When I navigate to "Manage application team" in current page administration
    Then "//h3[normalize-space(.)='Add applicators']" "xpath_element" should exist
    And "//h3[normalize-space(.)='Invite applicators']" "xpath_element" should exist
    When I reach "//h3[normalize-space(.)='Add applicators']/following-sibling::a[@aria-label='Help']" "xpath_element" using only the keyboard
    Then I should see "Select eligible accounts to grant an application delegation immediately"
    When I press the tab key
    Then I should not see "Select eligible accounts to grant an application delegation immediately"
    When I reach "//h3[normalize-space(.)='Invite applicators']/following-sibling::a[@aria-label='Help']" "xpath_element" using only the keyboard
    Then I should see "Generate a link for an eligible account to sign in and accept the invitation"
    When I press the tab key
    Then I should not see "Generate a link for an eligible account to sign in and accept the invitation"
    When I reach "#applicators_searchtext" "css_element" using only the keyboard
    And I set the field "applicators_searchtext" to "João"
    And I press the enter key
    Then I should not see "Application delegation included."
    And I should see "None"
