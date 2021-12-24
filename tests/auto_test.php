<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Auto enrolment plugin tests.
 *
 * @package     enrol_auto
 * @copyright   Eugene Venter <eugene@catalyst.net.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_auto;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/enrol/auto/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Auto enrolment plugin tests.
 *
 * @package     enrol_auto
 * @copyright   Eugene Venter <eugene@catalyst.net.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \enrol_auto
 */
class auto_test extends \advanced_testcase {

    /** @var stdClass Instance. */
    private $instance;

    /** @var stdClass Student. */
    private $student;

    /** @var stdClass Course. */
    private $course;

    /** @var stdClass Plugin. */
    private $plugin;

    /**
     * Tests initial setup.
     */
    protected function setUp():void {
        global $DB;
        $this->resetAfterTest(true);
        $this->assertFalse(enrol_is_enabled('auto'));
        $enabled = enrol_get_plugins(true);
        unset($enabled['guest']);
        unset($enabled['self']);
        $enabled['auto'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $this->setAdminUser();
        $this->plugin = enrol_get_plugin('auto');
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->student = $generator->create_user();
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $id = $this->plugin->add_instance($this->course, ['roleid' => $studentrole]);
        $this->instance = $DB->get_record('enrol', ['id' => $id]);
    }

    /**
     * Tests basics.
     */
    public function test_basics() {
        $this->assertTrue(enrol_is_enabled('auto'));

        // Correct enrol instance.
        $this->assertInstanceOf('enrol_auto_plugin', $this->plugin);

        // Default config checks.
        $this->assertEquals('1', get_config('enrol_auto', 'defaultenrol'));
        $this->assertEquals('1', get_config('enrol_auto', 'status'));
    }

    /**
     * Test library.
     */
    public function test_library() {
        global $DB;
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $generator = $this->getDataGenerator();
        $context = \context_course::instance($this->course->id);
        $course = $generator->create_course();
        $user = $generator->create_user();
        $this->assertEquals(false, $this->plugin->get_instance_for_course($course->id));
        $this->plugin->add_default_instance($this->course);
        $this->assertTrue(has_capability('moodle/course:enrolconfig', $context));
        $this->assertTrue(has_capability('enrol/auto:config', $context));
        $this->assertEquals(null, $this->plugin->get_newinstance_link($this->course->id));

        $this->assertEquals($this->instance, $this->plugin->get_instance_for_course($this->course->id));
        $this->assertEquals($this->plugin->try_autoenrol($this->instance), time() + 10);
        $this->assertEquals($this->plugin->get_name(), 'auto');
        $this->assertEquals($this->plugin->get_config('enabled'), null);
        $this->assertTrue($this->plugin->roles_protected());
        $this->assertTrue($this->plugin->can_add_instance($this->course->id));
        $this->assertTrue($this->plugin->allow_unenrol($this->instance));
        $this->assertTrue($this->plugin->allow_manage($this->instance));
        $this->assertTrue($this->plugin->can_hide_show_instance($this->instance));
        $this->assertTrue($this->plugin->can_delete_instance($this->instance));
        $this->assertFalse($this->plugin->show_enrolme_link($this->instance));
        $this->assertCount(1, $this->plugin->get_info_icons([$this->instance]));
        $this->assertCount(1, $this->plugin->get_action_icons($this->instance));
        $this->assertEquals('Auto enrolment', $this->plugin->get_instance_name($this->instance));
        $this->assertEquals(null, $this->plugin->get_description_text($this->instance));
        $this->assertEquals(null, $this->plugin->enrol_page_hook($this->instance));
        $tmp = $this->plugin->edit_instance_validation(['status' => 0], null, $this->instance, null);
        $this->assertEquals([], $tmp);
        $this->setUser(1);
        $this->assertEquals(null, $this->plugin->enrol_page_hook($this->instance));
        $this->assertCount(1, $this->plugin->get_info_icons([$this->instance]));
        $this->setUser($user);
        $this->assertCount(1, $this->plugin->get_info_icons([$this->instance]));
        $page = new \moodle_page();
        $page->set_context(\context_course::instance($this->course->id));
        $page->set_course($this->course);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->set_url('/enrol/index.php?id=' . $this->course->id);
        $this->assertfalse($this->plugin->can_add_instance($this->course->id));
        $this->asserttrue($this->plugin->allow_unenrol($this->instance));
        $this->assertfalse($this->plugin->allow_manage($this->instance));
        $this->assertfalse($this->plugin->can_hide_show_instance($this->instance));
        $this->assertfalse($this->plugin->can_delete_instance($this->instance));
        $this->plugin->enrol_user($this->instance, $user->id, $studentrole);
        mark_user_dirty($user->id);
        $this->setUser($user);
        $this->assertEquals($this->plugin->try_autoenrol($this->instance), time() + 10);
        $this->assertCount(1, $this->plugin->get_info_icons([$this->instance]));
        set_config('enrol_plugins_enabled', '');
        $this->assertFalse($this->plugin->try_autoenrol($this->instance));
    }

    /**
     * Test ue.
     */
    public function test_ue() {
        global $PAGE;
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $url = new \moodle_url('/user/index.php', ['id' => $this->course->id]);
        $PAGE->set_url($url);
        $manager = new \course_enrolment_manager($PAGE, $this->course);
        $enrolments = $manager->get_user_enrolments($user->id);
        $this->assertCount(0, $enrolments);
        $this->plugin->enrol_user($this->instance, $user->id, 5);
        $enrolments = $manager->get_user_enrolments($user->id);
        $this->assertCount(1, $enrolments);
        foreach ($enrolments as $enrolment) {
            if ($enrolment->enrolmentinstance->enrol == 'auto') {
                $actions = $this->plugin->get_user_enrolment_actions($manager, $enrolment);
                $this->assertCount(2, $actions);
                $this->assertEquals('Edit enrolment', $actions[0]->get_title());
                $this->assertEquals('Unenrol', $actions[1]->get_title());
            }
        }
    }

    /**
     * Test other files.
     */
    public function test_files() {
        global $CFG;
        include($CFG->dirroot . '/enrol/auto/db/access.php');
    }

    /**
     * Test backup.
     */
    public function test_backup() {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, 5);
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $this->course->id, \backup::FORMAT_MOODLE, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, 2);
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/test-restore-course-event';
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();
        $rc = new \restore_controller('test-restore-course-event', $this->course->id, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, 2, \backup::TARGET_NEW_COURSE);
        $rc->execute_precheck();
        $rc->execute_plan();
        $newid = $rc->get_courseid();
        $rc->destroy();
        $this->assertEquals(4, $DB->count_records('enrol', ['enrol' => 'auto']));
        $this->assertTrue(is_enrolled(\context_course::instance($newid), $user->id));
        $url = new \moodle_url('/user/index.php', ['id' => $newid]);
        $PAGE->set_url($url);
        $course2 = get_course($newid);
        $manager = new \course_enrolment_manager($PAGE, $course2);
        $enrolments = $manager->get_user_enrolments($user->id);
        $this->assertCount(2, $enrolments);
        $this->assertCount(5, $manager->get_enrolment_instance_names());
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $this->course->id, \backup::FORMAT_MOODLE, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, 2);
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/test-restore-course-event';
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();
        $rc = new \restore_controller('test-restore-course-event', $newid, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, 2, \backup::TARGET_EXISTING_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        $this->assertEquals(4, $DB->count_records('enrol', ['enrol' => 'auto']));
        $this->assertTrue(is_enrolled(\context_course::instance($newid), $user->id));
    }

    /**
     * Test form.
     */
    public function test_form() {
        $page = new \moodle_page();
        $context = \context_course::instance($this->course->id);
        $page->set_context($context);
        $page->set_course($this->course);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->set_url('/enrol/auto/manage.php?enrolid=' . $this->instance->id);
        $form = new temp_auto_form();
        $mform = $form->getform();
        $this->plugin->edit_instance_form($this->instance, $mform, $context);
        $this->assertStringContainsString('Required field', $mform->getReqHTML());
        ob_start();
        $mform->display();
        $html = ob_get_clean();
        $this->assertStringContainsString('Custom instance name', $html);
    }
}

/**
 * Form object to be used in test case.
 *
 * @package   enrol_auto
 * @copyright Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class temp_auto_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        // No definition required.
    }
    /**
     * Returns form reference
     * @return MoodleQuickForm
     */
    public function getform() {
        $mform = $this->_form;
        // Set submitted flag, to simulate submission.
        $mform->_flagSubmitted = true;
        return $mform;
    }
}
