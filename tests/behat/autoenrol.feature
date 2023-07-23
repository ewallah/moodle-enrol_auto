@enrol @enrol_auto
Feature: Auto enrol setup and use
  In order to participate in courses
  As a user
  I need to be enrolled automatically

  Background:
    Given the following "users" exist:
      | username | firstname    | lastname | email             |
      | student1 | Eugene1      | Student1 | eugene@venter.com |
      | teacher1 | Elmaret1     | Teacher1 | teacher1@asd.com  |
    And the following "courses" exist:
      | fullname  | shortname |
      | Course 1  | c1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | c1     | editingteacher |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Auto enrolment" "table_row"
    And I log out

  Scenario: Auto enrolment but no guest access
    Given I log in as "teacher1"
    And I add "Auto enrolment" enrolment method in "Course 1" with:
      | Custom instance name | Eugene auto enrolment |
    And I log out

    And I am on "Course 1" course homepage
    # trying to access the course should redirect you to the login page
    When I press "Access as a guest"
    Then I should not see "Topic 1"
    And I should see "Guests cannot access this course."

    When I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    Then I should not see "eugene@venter.com"
    But I should see "Eugene auto enrolment"

  Scenario: Auto enrolment upon course view
    Given I log in as "teacher1"
    And I add "Auto enrolment" enrolment method in "Course 1" with:
      | Custom instance name | Eugene auto enrolment |
    And I am on the "Course 1" "enrolled users" page
    Then I should not see "eugene@venter.com"
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Topic 1"
    And I log out

    When I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    Then I should see "eugene@venter.com"
    And I should see "Eugene auto enrolment"

  Scenario: Student can unenrol him/her self
    Given I log in as "teacher1"
    And I add "Auto enrolment" enrolment method in "Course 1" with:
      | Custom instance name | Eugene auto enrolment |
    And I log out

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I navigate to "Unenrol me from this course" in current page administration
    And I click on "Continue" "button" in the "Confirm" "dialogue"
    And I log out

    When I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    Then I should not see "eugene@venter.com"
