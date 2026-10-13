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
      | fullname | shortname | category | summary               |
      | Course 1 | C1        | 0        | Secret course summary |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name   | intro                    | course | idnumber | presencial_enabled | presencial_timeopen       | presencial_timeclose      |
      | quiz     | Quiz 1 | Secret quiz introduction | C1     | quiz1    | 1                  | ## 1 January 2020 08:00 ## | ## 1 January 2030 20:00 ## |

  Scenario: Generate a link through quiz navigation and hide it after returning
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    When I navigate to "Manage application team" in current page administration
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
    And I navigate to "Manage application team" in current page administration
    When I press "Generate invitation"
    And I press "Copy invitation link"
    Then I should see "Invitation link copied."
    And the "readonly" attribute of "#presencial-invitation-link" "css_element" should be set

  @javascript
  Scenario: Disable an invitation and generate a replacement
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    And I navigate to "Manage application team" in current page administration
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
    Then "Manage application team" "link" should not exist in current page administration
    And accessing invitation management for "Quiz 1" should be denied

  Scenario: An expired invitation stays secret and requires a replacement
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    And I navigate to "Manage application team" in current page administration
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
    When I navigate to "Manage application team" in current page administration
    Then I should see "Enable in-person release and set a valid authorization period before generating an invitation."
    And "Generate invitation" "button" should not exist

  @javascript
  Scenario Outline: An unenrolled eligible account returns to the restricted invitation after login
    Given the following "users" exist:
      | username    | firstname  | lastname | email                  |
      | applicator1 | Applicator | One      | applicator@example.com |
    And the following config values are set as admin:
      | theme            | boost |
      | guestloginbutton | 1     |
    And the following "question categories" exist:
      | contextlevel | reference | name             |
      | Course       | C1        | Secret questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name             | questiontext         |
      | Secret questions | truefalse | Private question | Secret question text |
    And quiz "Quiz 1" contains the following questions:
      | question         | page |
      | Private question | 1    |
    And the following "activities" exist:
      | activity | name            | intro                  | course | idnumber |
      | page     | Private material | Secret course content | C1     | private1 |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    And I navigate to "Manage application team" in current page administration
    And I press "Generate invitation"
    And I remember the generated invitation link
    And I log out
    And I start the invitation journey with "<session>"
    When I open the remembered invitation link
    Then "Username" "field" should exist
    And "Password" "field" should exist
    When I set the following fields to these values:
      | Username | applicator1 |
      | Password | applicator1 |
    And I press "Log in"
    Then I should be on the remembered invitation page
    And I should see "Course 1"
    And I should see "Quiz 1"
    And I should see "Authorization period"
    And I should see "Teacher One"
    And "Accept invitation" "button" should exist
    And I should not see "You are now part of the application team."
    And the invitation acceptance form should require POST and a session key
    And the invitation page should not expose restricted academic information
    When I request invitation acceptance with GET
    Then "Accept invitation" "button" should exist
    And I should not see "You are now part of the application team."
    When I press "Accept invitation"
    Then I should see "You are now part of the application team."
    And "Accept invitation" "button" should not exist
    And the invitation page should not expose restricted academic information
    When I open the remembered invitation link
    And I press "Accept invitation"
    Then I should see "You already have a current application delegation."
    And the invitation page should not expose restricted academic information

    Examples:
      | session    |
      | no session |
      | guest      |

  @javascript
  Scenario: The invitation stays restricted even for an enrolled teacher
    Given the following config values are set as admin:
      | theme | boost |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "teacher1"
    And I navigate to "Manage application team" in current page administration
    And I press "Generate invitation"
    And I remember the generated invitation link
    When I open the remembered invitation link
    Then I should see "Teacher One"
    And the invitation page should not expose restricted academic information
    When I press "Accept invitation"
    Then I should see "You are now part of the application team."
    And the invitation page should not expose restricted academic information
