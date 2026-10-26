@quizaccess @quizaccess_presencial @mod_quiz
Feature: Gate new quiz attempts with an in-person release request
  To start a new attempt in a supervised session
  As a student
  I need a pending request to be authorized before Moodle creates my attempt

  Background:
    Given the time is frozen at "2030-01-02 12:00:00"
    And the following "users" exist:
      | username    | firstname | lastname | email                   |
      | student1    | Student   | One      | student@example.com     |
      | applicator1 | Operator  | One      | applicator@example.com  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
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
  Scenario: Student waits, sees authorization, and only then gets an attempt
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    Then I should see "Waiting for in-person release"
    And I should see "Your release request is pending"
    And the waiting page should not contain a manual check button
    And the release request for "Quiz 1" for "student1" should be pending
    And there should be no attempt for "Quiz 1" as "student1"
    When I authorize the release request for "Quiz 1" for "student1"
    And I wait for the release request poll "authorized"
    Then I should see "Your request was authorized"
    And there should be no attempt for "Quiz 1" as "student1"
    When I press "Start attempt"
    Then I should see "Question 1"
    And the release request for attempt 1 of "Quiz 1" for "student1" should be consumed
    And there should be no release request for attempt 2 of "Quiz 1" for "student1"
    When I open the latest release request for "Quiz 1" for "student1"
    Then I should see "Question 1"
    When I am on the "Quiz 1" "mod_quiz > View" page
    And I press "Continue your attempt"
    Then I should see "Question 1"
    And there should be no release request for attempt 2 of "Quiz 1" for "student1"

  @javascript
  Scenario: Waiting page polls automatically and presents an expired request
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    Then I should see "Your release request is pending"
    And there should be no attempt for "Quiz 1" as "student1"
    When I make the release request for "Quiz 1" for "student1" due
    And I wait for the release request poll "expired"
    Then I should see "This request or its unused authorization has expired"
    And I should see "Return to the quiz"

  @javascript
  Scenario: Refreshing the waiting page reuses the pending request
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    Then I should see "Your release request is pending"
    When I reload the page
    Then I should see "Your release request is pending"
    And the release request for "Quiz 1" for "student1" should be pending
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: My applications shows the live pending count and removes due requests on refresh
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    Then the release request for "Quiz 1" for "student1" should be pending
    And there should be no attempt for "Quiz 1" as "student1"
    When I log out
    And I log in as "applicator1"
    And I follow "My applications" in the user menu
    Then I should see "1" in the ".presencial-applications tbody tr td:nth-child(6)" "css_element"
    When I make the release request for "Quiz 1" for "student1" due
    And I reload the page
    Then I should see "0" in the ".presencial-applications tbody tr td:nth-child(6)" "css_element"

  Scenario: Core preflight protects the no-JavaScript start path
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And there should be no release request for attempt 1 of "Quiz 1" for "student1"
    And there should be no attempt for "Quiz 1" as "student1"
    When I press "Start attempt"
    Then I should see "Waiting for in-person release"
    And I should see "Your release request is pending"
    And the release request for "Quiz 1" for "student1" should be pending
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: An expired authorization needs a new request and decision before confirmation
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I authorize the release request for "Quiz 1" for "student1"
    And I wait for the release request poll "authorized"
    Then I should see "Your request was authorized"
    And there should be no attempt for "Quiz 1" as "student1"
    When I make the release request for "Quiz 1" for "student1" due
    And I wait for the release request poll "expired"
    Then I should see "This request or its unused authorization has expired"
    When I follow "Return to the quiz"
    And I press "Attempt quiz"
    Then I should see "Your release request is pending"
    And there should be no attempt for "Quiz 1" as "student1"
    When I authorize the release request for "Quiz 1" for "student1"
    And I wait for the release request poll "authorized"
    And I press "Start attempt"
    Then I should see "Question 1"
    And the release request for attempt 1 of "Quiz 1" for "student1" should be consumed

  @javascript
  Scenario: Confirmation validates a quiz password again after a new login
    Given the following "activities" exist:
      | activity | name          | course | quizpassword | presencial_enabled | presencial_timeopen | presencial_timeclose |
      | quiz     | Password quiz | C1     | secret   | 1                  | ## 1 January 2020 ## | ## 1 January 2099 ## |
    And quiz "Password quiz" contains the following questions:
      | question | page |
      | Q1       | 1    |
    And I am on the "Password quiz" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I set the field "Quiz password" to "secret"
    And I press "Start attempt"
    Then I should see "Your release request is pending"
    When I authorize the release request for "Password quiz" for "student1"
    And I log out
    And I log in as "student1"
    And I open the latest release request for "Password quiz" for "student1"
    And I press "Start attempt"
    Then I should see "Quiz password"
    And there should be no attempt for "Password quiz" as "student1"
    When I set the field "Quiz password" to "secret"
    And I press "Start attempt"
    Then I should see "Question 1"
    And the release request for attempt 1 of "Password quiz" for "student1" should be consumed
