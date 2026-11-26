@quizaccess @quizaccess_presencial @mod_quiz @waiting_accessibility
Feature: Keep keyboard navigation predictable when a release decision changes
  As a student waiting for in-person release
  I need to learn about decision changes and continue using the keyboard

  Background:
    Given the time is frozen at "2030-01-02 12:00:00"
    And the following "users" exist:
      | username    | firstname | lastname | email                   |
      | student1    | Student   | One      | student@example.com     |
      | teacher1    | Teacher   | One      | teacher@example.com     |
      | applicator1 | Operator  | One      | applicator@example.com  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name       |
      | Course       | C1        | Test bank  |
    And the following "activities" exist:
      | activity | name      | course | idnumber | timeopen | timeclose | presencial_enabled | presencial_timeopen | presencial_timeclose |
      | quiz     | Quiz 1    | C1     | quiz1    | 0        | 0         | 1                  | ## 1 January 2020 ## | ## 1 January 2099 ## |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext |
      | Test bank        | truefalse | Q1   | Question 1   |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | Q1       | 1    |

  @javascript
  Scenario: A revised authorization replaces the focused return link with a contextual focus target
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And applicator "applicator1" rejects "Quiz 1" with "Bring your identification." in another session
    And I wait for the release request poll "rejected"
    And I reach "Return to the quiz" "link" using only the keyboard
    Then the focused element is "Return to the quiz" "link"
    When applicator "applicator1" authorizes "Quiz 1" in another session
    And I wait for the release request poll "authorized"
    Then the focused element is "[data-region='request-status']" "css_element"
    And I should see "Your request was authorized"
    And the release announcement should be ""
    And there should be no attempt for "Quiz 1" as "student1"
    When I press the tab key
    Then the focused element is "Start attempt" "button"
    When I press the enter key
    Then I should see "Question 1"

  @javascript
  Scenario: Rejection and explanation revisions preserve the available return link
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I reach "Return to the quiz" "link" using only the keyboard
    And applicator "applicator1" rejects "Quiz 1" with "Bring your identification." in another session
    And I wait for the release request poll "rejected"
    Then the focused element is "Return to the quiz" "link"
    When applicator "applicator1" rejects "Quiz 1" with "Bring the original document." in another session
    And I wait for the release request explanation "Bring the original document."
    Then the focused element is "Return to the quiz" "link"
    And the release announcement should be "Rejected. You can return to the quiz and request release again. Bring the original document."
    And the release announcement should remain unchanged across the next poll
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: A decision change preserves focus outside the request controls
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I reach "#user-menu-toggle" "css_element" using only the keyboard
    And applicator "applicator1" authorizes "Quiz 1" in another session
    And I wait for the release request poll "authorized"
    Then the focused element is "#user-menu-toggle" "css_element"
    And the release announcement should be "Your request was authorized. Continue to start your attempt."
    And the release announcement should remain unchanged across the next poll
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: Revoking authorization contextualizes focus before the next return action
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And applicator "applicator1" authorizes "Quiz 1" in another session
    And I wait for the release request poll "authorized"
    And I reach "Start attempt" "button" using only the keyboard
    And applicator "applicator1" rejects "Quiz 1" with "Please check your identification." in another session
    And I wait for the release request poll "rejected"
    Then the focused element is "[data-region='request-status']" "css_element"
    And I should see "Please check your identification."
    When I press the tab key
    Then the focused element is "Return to the quiz" "link"
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: Expiring an authorization contextualizes focus and removes the start action
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And applicator "applicator1" authorizes "Quiz 1" in another session
    And I wait for the release request poll "authorized"
    And I reach "Start attempt" "button" using only the keyboard
    And I make the release request for "Quiz 1" for "student1" due
    And I wait for the release request poll "expired"
    Then the focused element is "[data-region='request-status']" "css_element"
    And I should see "This request or its unused authorization has expired"
    And "Start attempt" "button" should not be visible
    When I press the tab key
    Then the focused element is "Return to the quiz" "link"
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: Recovery presents the new decision without losing the keyboard position
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I reach "Return to the quiz" "link" using only the keyboard
    And the student loses connection while "applicator1" authorizes "Quiz 1" in another browser
    Then the focused element is "[data-region='request-status']" "css_element"
    And I should see "Your request was authorized"
    And I should not see "Unable to update your release request"
    And "[data-region='request-polling']" "css_element" should not be visible
    And there should be no attempt for "Quiz 1" as "student1"
    When I press the tab key
    Then the focused element is "Start attempt" "button"
