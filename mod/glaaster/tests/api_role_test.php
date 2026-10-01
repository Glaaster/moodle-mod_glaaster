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

namespace mod_glaaster;

use context_system;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/glaaster/db/install.php');

/**
 * PHPUnit tests for the "Glaaster API" role provisioning.
 *
 * @package    mod_glaaster
 * @copyright  2026 Glaaster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::mod_glaaster_create_api_role
 * @covers     ::mod_glaaster_assign_api_role_capabilities
 */
final class api_role_test extends \advanced_testcase {
    /**
     * The API role holds moodle/cohort:view, required by mod_glaaster_get_user_cohorts.
     */
    public function test_api_role_grants_cohort_view(): void {
        global $DB;
        $this->resetAfterTest();

        mod_glaaster_create_api_role();

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'glaasterapi'], MUST_EXIST);
        $this->assertTrue($DB->record_exists('role_capabilities', [
            'roleid' => $roleid,
            'capability' => 'moodle/cohort:view',
            'contextid' => context_system::instance()->id,
            'permission' => CAP_ALLOW,
        ]));
    }

    /**
     * Re-running provisioning on an existing role restores a capability removed by hand.
     */
    public function test_api_role_reprovisioning_restores_cohort_view(): void {
        global $DB;
        $this->resetAfterTest();

        mod_glaaster_create_api_role();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'glaasterapi'], MUST_EXIST);
        unassign_capability('moodle/cohort:view', $roleid, context_system::instance()->id);

        mod_glaaster_create_api_role();

        $this->assertEquals(1, $DB->count_records('role', ['shortname' => 'glaasterapi']));
        $this->assertTrue($DB->record_exists('role_capabilities', [
            'roleid' => $roleid,
            'capability' => 'moodle/cohort:view',
            'permission' => CAP_ALLOW,
        ]));
    }
}
