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
 * Auto enrolment plugin settings and presets.
 *
 * @package     enrol_auto
 * @author      Eugene Venter <eugene@catalyst.net.nz>
 * @copyright   2013 onwards Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot . '/enrol/auto/lib.php');

    $settings->add(new admin_setting_heading(
        'enrol_auto_defaults',
        get_string('enrolinstancedefaults', 'admin'),
        get_string('enrolinstancedefaults_desc', 'admin')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_auto/defaultenrol',
        get_string('defaultenrol', 'enrol'),
        get_string('defaultenrol_desc', 'enrol'),
        1
    ));

    $options = [ENROL_INSTANCE_ENABLED => get_string('yes'), ENROL_INSTANCE_DISABLED => get_string('no')];
    $settings->add(new admin_setting_configselect(
        'enrol_auto/status',
        get_string('status', 'enrol_auto'),
        get_string('status_desc', 'enrol_auto'),
        ENROL_INSTANCE_DISABLED,
        $options
    ));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_auto/roleid',
            get_string('defaultrole', 'enrol_auto'),
            get_string('defaultrole_desc', 'enrol_auto'),
            $student->id,
            $options
        ));
    }
}
