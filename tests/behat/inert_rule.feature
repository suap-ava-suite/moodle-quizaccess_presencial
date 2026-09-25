@quizaccess @quizaccess_presencial @mod_quiz
Feature: Manter o fluxo nativo de tentativas quando a Liberação Presencial não estiver configurada
  Para realizar um questionário comum
  Como estudante
  Preciso que a regra de acesso Liberação Presencial permaneça inativa enquanto não estiver configurada

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student1 | Estudante | Um       | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Curso 1  | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Professor | Um      | teacher@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name              |
      | Course       | C1        | Questões de teste |
    And the following "activities" exist:
      | activity | name            | intro                       | course | idnumber |
      | quiz     | Questionário 1  | Descrição do questionário 1 | C1     | quiz1    |
    And the following "questions" exist:
      | questioncategory  | qtype     | name | questiontext    |
      | Questões de teste | truefalse | VF1  | Primeira questão |
    And quiz "Questionário 1" contains the following questions:
      | question | page |
      | VF1      | 1    |

  Scenario: Iniciar uma tentativa normalmente
    Given I am on the "Questionário 1" "mod_quiz > View" page logged in as "student1"
    When I press "Attempt quiz"
    Then I should see "Primeira questão"

  Scenario: Professor habilita a Liberação Presencial com as datas do Questionário
    Given the following "activities" exist:
      | activity | name            | course | idnumber | timeopen              | timeclose             |
      | quiz     | Questionário 2  | C1     | quiz2    | ## 1 January 2030 ##  | ## 2 January 2030 ##  |
    And I am on the "quiz2" "Activity editing" page logged in as "teacher1"
    When I set the field "Enable in-person release" to "Yes"
    Then the following fields match these values:
      | Authorization period starts | ## 1 January 2030 ## |
      | Authorization period ends   | ## 2 January 2030 ## |
    And I press "Save and return to course"
    Then I should see "Questionário 2"

  Scenario: Professor cria o Questionário com a Liberação Presencial habilitada
    Given I am on the "Curso 1" course page logged in as "teacher1"
    When I add a "quiz" activity to course "Curso 1" section 1
    And I set the following fields to these values:
      | Name                       | Questionário 4            |
      | Open the quiz              | ## 1 January 2030 08:00 ## |
      | Close the quiz             | ## 2 January 2030 08:00 ## |
      | Enable in-person release   | Yes                       |
    And I press "Save and return to course"
    And I am on the "Questionário 4" "Activity editing" page
    Then the following fields match these values:
      | Authorization period starts | ## 1 January 2030 08:00 ## |
      | Authorization period ends   | ## 2 January 2030 08:00 ## |

  Scenario: Professor cria o Questionário preservando um período de autorização personalizado
    Given I am on the "Curso 1" course page logged in as "teacher1"
    When I add a "quiz" activity to course "Curso 1" section 1
    And I set the following fields to these values:
      | Name                       | Questionário 5             |
      | Open the quiz              | ## 1 January 2030 08:00 ## |
      | Close the quiz             | ## 2 January 2030 08:00 ## |
      | Enable in-person release   | Yes                        |
      | Authorization period starts | ## 1 January 2030 10:00 ## |
      | Authorization period ends   | ## 1 January 2030 20:00 ## |
    And I press "Save and return to course"
    And I am on the "Questionário 5" "Activity editing" page
    Then the following fields match these values:
      | Authorization period starts | ## 1 January 2030 10:00 ## |
      | Authorization period ends   | ## 1 January 2030 20:00 ## |

  Scenario: Professor salva uma edição não relacionada com período de autorização expirado
    Given the following "activities" exist:
      | activity | name                    | course | idnumber | timeopen             | timeclose            | presencial_enabled | presencial_timeopen       | presencial_timeclose      |
      | quiz     | Questionário expirado   | C1     | quizexpired | ## 1 January 2020 ## | ## 2 January 2020 ## | 1                  | ## 1 January 2020 08:00 ## | ## 1 January 2020 20:00 ## |
    And I am on the "quizexpired" "Activity editing" page logged in as "teacher1"
    When I set the field "Name" to "Questionário expirado renomeado"
    And I press "Save and return to course"
    Then I should see "Questionário expirado renomeado"

  Scenario: Professor precisa informar limites quando o Questionário não os possui
    Given the following "activities" exist:
      | activity | name            | course | idnumber |
      | quiz     | Questionário 3  | C1     | quiz3    |
    And I am on the "quiz3" "Activity editing" page logged in as "teacher1"
    When I set the field "Enable in-person release" to "Yes"
    And I press "Save and return to course"
    Then I should see "Set when the authorization period starts."
    And I should see "Set when the authorization period ends."
