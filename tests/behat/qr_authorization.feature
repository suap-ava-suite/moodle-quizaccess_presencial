@quizaccess @quizaccess_presencial @mod_quiz @presencial_qr
Feature: Authorize a release request with a QR code
  As an applicator I can authorize a student in person by reading the student's QR code.

  Background:
    Given the following "users" exist:
      | username    | firstname | lastname | email                  |
      | student1    | Student   | One      | student@example.com    |
      | applicator1 | Operator  | One      | applicator@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name      |
      | Course       | C1        | Test bank |
    And the following "activities" exist:
      | activity | name   | course | idnumber | timeopen | timeclose | presencial_enabled | presencial_timeopen | presencial_timeclose |
      | quiz     | Quiz 1 | C1     | quiz1    | 0        | 0         | 1                  | ## 1 January 2020 ## | ## 1 January 2099 ## |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext |
      | Test bank        | truefalse | Q1   | Question 1   |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | Q1       | 1    |
    And application account "applicator1" is delegated to the quizzes in course "C1"

  @javascript
  Scenario: A delegated applicator reads the student's code
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I remember the authorization QR URL
    And I log out
    And I log in as "applicator1"
    And I open the remembered authorization QR URL
    Then I should see "The request was authorized"
    And there should be no attempt for "Quiz 1" as "student1"
    When I log out
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    Then I should see "Your request was authorized"
    When I press "Start attempt"
    Then I should see "Question 1"

  @javascript
  Scenario: A delegated applicator reads the code before logging in
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I remember the authorization QR URL
    And I log out
    And I open the remembered authorization QR URL
    And I log in as "applicator1"
    Then I should see "The request was authorized"

  @javascript
  Scenario: A link in Moodle content cannot authorize a request
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I remember the authorization QR URL
    And I log out
    And I log in as "applicator1"
    And I follow the remembered QR URL from Moodle content
    Then the release request for "Quiz 1" for "student1" should be pending
