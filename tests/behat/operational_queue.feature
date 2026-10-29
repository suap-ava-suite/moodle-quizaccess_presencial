@quizaccess @quizaccess_presencial @mod_quiz @operational_queue
Feature: Decide requests in the operational queue
  To start a new attempt in a supervised session
  As an applicator
  I need to authorize or reject requests and let students see the decision

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
  Scenario: An unenrolled applicator authorizes through the queue and the student starts explicitly
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    Then I should see "Your release request is pending"
    When I log out
    And I log in as "applicator1"
    And I follow "My applications" in the user menu
    And I follow "Open application"
    Then I should see "Student One" in the ".presencial-queue" "css_element"
    And the operational identity should not link to student profiles
    When I click on "Authorize" "button" in the "Student One" "table_row"
    Then I should see "Authorized" in the "Student One" "table_row"
    And there should be no attempt for "Quiz 1" as "student1"
    When I log out
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    Then I should see "Your request was authorized"
    When I press "Start attempt"
    Then I should see "Question 1"
    And the release request for attempt 1 of "Quiz 1" for "student1" should be consumed

  @javascript
  Scenario: Rejection requires the configured reason and the student can immediately request again
    Given the following config values are set as admin:
      | rejectionjustificationrequired | 1 | quizaccess_presencial |
    And application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I log out
    And I log in as "applicator1"
    And I follow "My applications" in the user menu
    And I follow "Open application"
    Then "Rejection reason" "field" should not be visible
    When I click on ".presencial-rejection > summary" "css_element" in the "Student One" "table_row"
    Then "Rejection reason" "field" should be visible
    And I should see "Pending" in the "Student One" "table_row"
    When I click on "Confirm rejection" "button" in the "Student One" "table_row"
    Then I should see "Enter a reason for rejecting this request"
    And "Rejection reason" "field" should be visible
    When I set the field "Rejection reason" to "Please bring your identification."
    And I click on "Confirm rejection" "button" in the "Student One" "table_row"
    Then I should see "Rejected" in the "Student One" "table_row"
    When I log out
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I open the latest release request for "Quiz 1" for "student1"
    Then I should see "Rejected"
    And I should see "Please bring your identification."
    When I follow "Return to the quiz"
    And I press "Attempt quiz"
    Then I should see "Your release request is pending"
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: The waiting page automatically presents rejection and its complete explanation
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And applicator "applicator1" rejects the request for "Quiz 1" for "student1" with "Please check your identification."
    And I wait for the release request poll "rejected"
    Then I should see "Please check your identification."
    When applicator "applicator1" authorizes the request for "Quiz 1" for "student1"
    And I wait for the release request poll "authorized"
    Then I should see "Your request was authorized"
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: Multiline explanations remain complete and do not repeatedly reload the waiting page
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And applicator "applicator1" rejects the request for "Quiz 1" for "student1" with a multiline explanation
    And I wait for the release request poll "rejected"
    Then I should see "First line."
    And I should see "Second line."
    And I should see "<script>alert('unsafe')</script>"
    And the waiting page should remain stable across the next status poll
    When applicator "applicator1" authorizes the request for "Quiz 1" for "student1"
    And I wait for the release request poll "authorized"
    Then I should see "Your request was authorized"

  @javascript
  Scenario: Rejection remains visible and revisable after the cancelled grant would expire
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And applicator "applicator1" authorizes the request for "Quiz 1" for "student1"
    And I wait for the release request poll "authorized"
    Given the time is frozen at "2030-01-02 12:04:59"
    When applicator "applicator1" rejects the request for "Quiz 1" for "student1" with "Bring an identity document."
    Given the time is frozen at "2030-01-02 12:05:04"
    When I wait for the release request poll "rejected"
    Then I should see "Bring an identity document."
    And there should be no attempt for "Quiz 1" as "student1"
    Given the time is frozen at "2030-01-02 12:15:01"
    When I reload the page
    Then I should see "Rejected"
    And I should see "Bring an identity document."
    When I log out
    And I log in as "applicator1"
    And I follow "My applications" in the user menu
    And I follow "Open application"
    Then I should see "Rejected" in the "Student One" "table_row"
    When I click on "Authorize" "button" in the "Student One" "table_row"
    Then I should see "Authorized" in the "Student One" "table_row"
