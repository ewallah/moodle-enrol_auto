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
 * Auto enrolment plugin.
 *
 * @package     enrol_auto
 * @copyright   Eugene Venter <eugene@catalyst.net.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Auto enrolment plugin.
 *
 * @package     enrol_auto
 * @copyright   Eugene Venter <eugene@catalyst.net.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_auto_plugin extends enrol_plugin {

    /**
     * Returns optional enrolment information icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        return [new pix_icon('i/courseevent', get_string('pluginname', 'enrol_auto'))];
    }

    /**
     * Allow unenrol.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance):bool {
        return true;
    }

    /**
     * Is it possible to add enrol instance via standard UI?
     *
     * @param int $courseid id of the course to add the instance to
     * @return boolean
     */
    public function can_add_instance($courseid):bool {
        return has_capability('enrol/auto:manage', context_course::instance($courseid));
    }


    /**
     * Allow manage.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_manage(stdClass $instance):bool {
        return has_capability('enrol/auto:manage', context_course::instance($instance->courseid));
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance):bool {
        return has_capability('enrol/auto:config', context_course::instance($instance->courseid));
    }

     /**
      * Is it possible to delete enrol instance via standard UI?
      *
      * @param stdClass $instance
      * @return bool
      */
    public function can_delete_instance($instance):bool {
        return has_capability('enrol/auto:config', context_course::instance($instance->courseid));
    }

    /**
     * Add new instance of enrol plugin with default settings.
     * @param stdClass $course
     * @return int id of new instance
     */
    public function add_default_instance($course) {
        $fields = $this->get_instance_defaults();
        return $this->add_instance($course, $fields);
    }

    /**
     * Returns defaults for new instances.
     * @return array
     */
    public function get_instance_defaults() {
        $fields = [];
        $fields['status'] = $this->get_config('status');
        $fields['roleid'] = $this->get_config('roleid');
        return $fields;
    }

    /**
     * Get the instance of this plugin attached to a course if any
     * @param int $courseid id of course
     * @param bool $onlyenabled only return an enabled instance
     * @return object|bool $instance or false if not found
     */
    public function get_instance_for_course($courseid, $onlyenabled = true) {
        global $DB;
        $params = ['enrol' => 'auto', 'courseid' => $courseid];
        if (!empty($onlyenabled)) {
            $params['status'] = ENROL_INSTANCE_ENABLED;
        }

        return $DB->get_record('enrol', $params);
    }

    /**
     * Attempt to automatically enrol current user in course without any interaction,
     * calling code has to make sure the plugin and instance are active.
     *
     * @param stdClass $instance course enrol instance
     * @return bool|int false means not enrolled, otherwise a timestamp in the future
     */
    public function try_autoenrol(stdClass $instance) {
        global $USER;

        // Prevent guest user from being enrolled.
        if (isguestuser()) {
            return false;
        }
        $context = context_course::instance($instance->courseid);

        // Check if this user can self-enrol.
        if (!has_capability('enrol/auto:enrolself', $context)) {
            return false;
        }

        // Plugin is enabled?
        if (!enrol_is_enabled('auto')) {
            return false;
        }

        // Instance is enabled?
        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            return false;
        }

        // Instance ended.
        if ($instance->enrolenddate > 0 && time() > $instance->enrolenddate) {
            return false;
        }

        $this->enrol_user($instance, $USER->id, $instance->roleid);
        return time() + 10;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = [ENROL_INSTANCE_ENABLED  => get_string('yes'), ENROL_INSTANCE_DISABLED => get_string('no')];
        $mform->addElement('select', 'status', get_string('enabled', 'admin'), $options);
        $mform->setDefault('status', $this->get_config('status'));
        $mform->addHelpButton('status', 'status', 'enrol_auto');

        $role = ($instance->id) ? $instance->roleid : $this->get_config('roleid');
        $roles = get_default_enrol_roles($context, $role);
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_paypal'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_paypal'), ['optional' => true]);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_paypal');
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];
        return $errors;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = ['courseid' => $data->courseid, 'enrol' => 'auto', 'roleid' => $data->roleid];
        }
        if ($merge and $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $userid
     * @param int $oldinstancestatus
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $this->enrol_user($instance, $userid, null, 0, 0, $data->status);
        }
        mark_user_dirty($userid);
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        role_assign($roleid, $userid, $contextid, 'enrol_auto', $instance->id);
        mark_user_dirty($userid);
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return bool
     */
    public function use_standard_editing_ui() {
        return true;
    }
}
