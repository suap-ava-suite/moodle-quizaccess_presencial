@quizaccess @quizaccess_presencial @mod_quiz
Feature: Manage the in-person release invitation
  In order to share the invitation securely
  As a quiz manager
  I need to generate a link that is displayed only once

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Teacher   | One      | teacher@example.com |
      | student1 | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name   | course | idnumber | presencial_enabled | presencial_timeopen       | presencial_timeclose      |
      | quiz     | Quiz 1 | C1     | quiz1    | 1                  | ## 1 January 2020 08:00 ## | ## 1 January 2030 20:00 ## |

  Scenario: Generate a link through quiz navigation and hide it after returning
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    When I navigate to "Invitation" in current page administration
    Then I should see "No invitation has been generated."
    When I press "Generate invitation"
    Then I should see "Invitation generated"
    And the "value" attribute of "#presencial-invitation-link" "css_element" should contain "/mod/quiz/accessrule/presencial/invite.php?cmid="
    And the "value" attribute of "#presencial-invitation-link" "css_element" should contain "token="
    And "Generate invitation" "button" should not exist
    When I follow "Return to invitation management"
    Then I should see "The invitation is active."
    And "Invitation link" "field" should not exist
    And "Regenerate invitation" "button" should exist

  @javascript
  Scenario: Copy the generated link with Moodle feedback
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    And I navigate to "Invitation" in current page administration
    When I press "Generate invitation"
    And I press "Copy invitation link"
    Then I should see "Invitation link copied."
    And the "readonly" attribute of "#presencial-invitation-link" "css_element" should be set

  @javascript
  Scenario: Disable an invitation and generate a replacement
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    And I navigate to "Invitation" in current page administration
    And I press "Generate invitation"
    And I remember the generated invitation link
    And I follow "Return to invitation management"
    When I press "Disable invitation"
    Then I should see "The invitation is disabled."
    And "Invitation link" "field" should not exist
    When I press "Regenerate invitation"
    Then the generated invitation link should differ from the previous link
    When I follow "Return to invitation management"
    Then I should see "The invitation is active."
    And "Invitation link" "field" should not exist

  Scenario: A student cannot access invitation management
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    Then "Invitation" "link" should not exist in current page administration
    And accessing invitation management for "Quiz 1" should be denied

  Scenario: An expired invitation stays secret and requires a replacement
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    And I navigate to "Invitation" in current page administration
    And I press "Generate invitation"
    And I remember the generated invitation link
    And the generated invitation for "Quiz 1" has expired
    When I follow "Return to invitation management"
    Then I should see "The invitation has expired."
    And "Invitation link" "field" should not exist
    And "Disable invitation" "button" should not exist
    When I press "Regenerate invitation"
    Then the generated invitation link should differ from the previous link
    When I follow "Return to invitation management"
    Then I should see "The invitation is active."
    And "Invitation link" "field" should not exist

  Scenario: An expired authorization period does not allow issuing invitations
    Given the following "activities" exist:
      | activity | name         | course | idnumber    | presencial_enabled | presencial_timeopen       | presencial_timeclose      |
      | quiz     | Expired quiz | C1     | expiredquiz | 1                  | ## 1 January 2020 08:00 ## | ## 2 January 2020 20:00 ## |
    And I am on the "Expired quiz" "mod_quiz > View" page logged in as "teacher1"
    When I navigate to "Invitation" in current page administration
    Then I should see "Enable in-person release and set a valid authorization period before generating an invitation."
    And "Generate invitation" "button" should not exist
