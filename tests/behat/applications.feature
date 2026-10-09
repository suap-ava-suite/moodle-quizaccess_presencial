@quizaccess @quizaccess_presencial @mod_quiz
Feature: Find and open standalone applications
  To operate only the quizzes delegated to me
  As an applicator without course enrolment
  I need My applications in my user menu and a protected standalone panel

  Background:
    Given the following "users" exist:
      | username    | firstname | lastname | email                   |
      | teacher1    | Teacher   | One      | teacher1@example.com    |
      | applicator1 | Operator  | One      | applicator1@example.com |
      | outsider1   | Outsider  | One      | outsider1@example.com   |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name   | course | idnumber | timeopen        | timeclose      |
      | quiz     | Quiz 1 | C1     | quiz1    | ## yesterday ## | ## tomorrow ## |
    And I am on the "quiz1" "Activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I set the field "Enable in-person release" to "Yes"
    And I press "Save and return to course"
    And I am on the "Quiz 1" "quizaccess_presencial > Invitation" page
    And I set the field "Eligible accounts" to "Operator One"
    And I press "Include selected accounts"
    And I log out

  Scenario: An unenrolled applicator finds and opens the delegated quiz
    Given I log in as "applicator1"
    When I follow "My applications" in the user menu
    Then I should see "Course 1" in the ".presencial-applications" "css_element"
    And I should see "Quiz 1" in the ".presencial-applications" "css_element"
    And I should see "Current" in the ".presencial-applications" "css_element"
    And I should see "Pending requests"
    And I should see "Unavailable" in the ".presencial-applications" "css_element"
    When I follow "Open application"
    Then I should see "Application panel" in the "h2" "css_element"
    And I should see "Quiz 1"
    And I should see "Release requests are not available in this version."
    And academic access to "Quiz 1" should still require enrolment

  Scenario: Revocation denies a known application URL in the existing session
    Given I log in as "applicator1"
    And I follow "My applications" in the user menu
    And I follow "Open application"
    When the application delegation of "applicator1" for "Quiz 1" is revoked
    Then accessing the application panel for "Quiz 1" should be denied
    And I should not see "My applications"

  Scenario: An unrelated user cannot discover or open the application
    Given I log in as "outsider1"
    Then I should not see "My applications"
    And accessing the application panel for "Quiz 1" should be denied

  Scenario: Account suspension denies the application in the existing session
    Given I log in as "applicator1"
    And I follow "My applications" in the user menu
    And I follow "Open application"
    When the application account "applicator1" is suspended
    Then accessing the application panel for "Quiz 1" should be denied
    And I should not see "My applications"

  Scenario: A professor opens the same panel without receiving a delegation
    Given I am on the "quiz1" "Activity" page logged in as "teacher1"
    When I follow "Application panel"
    Then I should see "Application panel" in the "h2" "css_element"
    And I should see "Quiz 1"
    And I should see "Release requests are not available in this version."

  @javascript @presencial_keyboard
  Scenario: An applicator opens and refreshes the panel using only the keyboard
    Given I log in as "applicator1"
    When I reach "#user-menu-toggle" "css_element" using only the keyboard
    Then the focused element is "#user-menu-toggle" "css_element"
    When I press the enter key
    Then I should see "My applications" in the "#user-action-menu" "css_element"
    And I reach "My applications" "link" using only the keyboard
    Then the focused element is "My applications" "link"
    When I press the enter key
    Then I should see "My applications" in the "h2" "css_element"
    When I reach "Open application" "link" using only the keyboard
    Then the focused element is "Open application" "link"
    When I press the enter key
    Then I should see "Application panel" in the "h2" "css_element"
    And I should see "Quiz 1"
    When I reach "Refresh" "link" using only the keyboard
    Then the focused element is "Refresh" "link"
    When I press the enter key
    Then I should see "Application panel" in the "h2" "css_element"
    When I reach "Refresh" "link" using only the keyboard
    And I press the tab key
    Then the focused element is "My applications" "link" in the "#region-main" "css_element"
    When I press the enter key
    Then I should see "My applications" in the "h2" "css_element"

  @javascript @presencial_keyboard
  Scenario: An applicator changes pages and opens an application using only the keyboard
    Given the following "activities" exist:
      | activity | name             | course | presencial_enabled | presencial_timeopen | presencial_timeclose |
      | quiz     | Keyboard quiz 01 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 02 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 03 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 04 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 05 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 06 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 07 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 08 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 09 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 10 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 11 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 12 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 13 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 14 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 15 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 16 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 17 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 18 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 19 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
      | quiz     | Keyboard quiz 20 | C1     | 1                  | ## today ##         | ## tomorrow ##       |
    And application account "applicator1" is delegated to the quizzes in course "C1"
    And I log in as "applicator1"
    When I reach "#user-menu-toggle" "css_element" using only the keyboard
    And I press the enter key
    And I reach "My applications" "link" using only the keyboard
    And I press the enter key
    Then I should see "Quiz 1" in the ".presencial-applications" "css_element"
    And I should not see "Keyboard quiz 20" in the ".presencial-applications" "css_element"
    When I reach "Page 2" "link" using only the keyboard
    Then the focused element is "Page 2" "link"
    When I press the enter key
    Then I should see "Keyboard quiz 20" in the ".presencial-applications" "css_element"
    And I should not see "Quiz 1" in the ".presencial-applications" "css_element"
    When I reach "Page 1" "link" using only the keyboard
    And I press the enter key
    Then I should see "Quiz 1" in the ".presencial-applications" "css_element"
    When I reach "Page 2" "link" using only the keyboard
    And I press the enter key
    And I reach "Open application" "link" using only the keyboard
    Then the focused element is "Open application" "link"
    When I press the enter key
    Then I should see "Application panel" in the "h2" "css_element"
    And I should see "Keyboard quiz 20"
