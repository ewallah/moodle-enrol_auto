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
 * Coursecompleted enrolment privacy tests.
 *
 * @package    enrol_auto
 * @copyright  Eugene Venter <eugene@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_auto\privacy;

use \core_privacy\tests\provider_testcase;

/**
 * Coursecompleted enrolment privacy tests.
 *
 * @package    enrol_auto
 * @copyright  Eugene Venter <eugene@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_test extends provider_testcase {

    /**
     * Test returning metadata.
     * @covers enrol_auto\privacy\provider
     */
    public function test_get_metadata() {
        $this->resetAfterTest(true);
        $collection = new \core_privacy\local\metadata\collection('enrol_auto');
        $reason = \enrol_auto\privacy\provider::get_reason($collection);
        $this->assertEquals($reason, 'privacy:metadata');
        $str = 'does not store';
        $this->assertStringContainsString($str, get_string($reason, 'enrol_auto'));
    }
}
