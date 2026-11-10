@quizaccess @quizaccess_presencial @mod_quiz @configuration_lifecycle
Feature: Aplicar alterações da configuração aos estados vigentes
  Para manter a Liberação Presencial consistente após alterações do Professor
  Como estudante ou Aplicador
  Preciso perder acessos encerrados e continuar tentativas já criadas

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

  Scenario: Desabilitar retira o acesso do Aplicador e reabilitar recupera a mesma Delegação
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I log in as "applicator1"
    And I follow "My applications" in the user menu
    And I follow "Open application"
    When the in-person release rule for "Quiz 1" becomes "disabled"
    Then accessing the application panel for "Quiz 1" should be denied
    And I should not see "My applications"
    When the in-person release rule for "Quiz 1" becomes "enabled"
    And I reload the page
    And I follow "My applications" in the user menu
    And I follow "Open application"
    Then I should see "There are no actionable requests."

  Scenario: Um novo Período de Autorização futuro exige novas Delegações
    Given application account "applicator1" is delegated to the quizzes in course "C1"
    And I log in as "applicator1"
    When the in-person release rule for "Quiz 1" becomes "future"
    Then accessing the application panel for "Quiz 1" should be denied
    And I should not see "My applications"

  @javascript
  Scenario: Desabilitar encerra a Solicitação pendente definitivamente
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    Then I should see "Your release request is pending"
    When the in-person release rule for "Quiz 1" becomes "disabled"
    And I wait for the release request poll "expired"
    Then I should see "This request or its unused authorization has expired"
    When the in-person release rule for "Quiz 1" becomes "enabled"
    And I reload the page
    Then I should see "This request or its unused authorization has expired"
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: Desabilitar encerra a Autorização não consumida definitivamente
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I authorize the release request for "Quiz 1" for "student1"
    And I wait for the release request poll "authorized"
    When the in-person release rule for "Quiz 1" becomes "disabled"
    And I wait for the release request poll "expired"
    When the in-person release rule for "Quiz 1" becomes "enabled"
    And I reload the page
    Then I should see "This request or its unused authorization has expired"
    And there should be no attempt for "Quiz 1" as "student1"

  @javascript
  Scenario: Uma tentativa criada pode ser retomada após desabilitação e mudanças de período
    Given I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    And I authorize the release request for "Quiz 1" for "student1"
    And I wait for the release request poll "authorized"
    And I press "Start attempt"
    Then I should see "Question 1"
    When the in-person release rule for "Quiz 1" becomes "disabled"
    And I am on the "Quiz 1" "mod_quiz > View" page
    And I press "Continue your attempt"
    Then I should see "Question 1"
    When the in-person release rule for "Quiz 1" becomes "future"
    And I am on the "Quiz 1" "mod_quiz > View" page
    And I press "Continue your attempt"
    Then I should see "Question 1"
    When the in-person release rule for "Quiz 1" becomes "ended"
    And I am on the "Quiz 1" "mod_quiz > View" page
    And I press "Continue your attempt"
    Then I should see "Question 1"
    And there should be no release request for attempt 2 of "Quiz 1" for "student1"
