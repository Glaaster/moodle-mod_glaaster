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

namespace mod_glaaster\external;

use context_system;
use core_external\external_api;
use mod_glaaster_testcase;
use required_capability_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/glaaster/tests/mod_glaaster_testcase.php');
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * PHPUnit tests for get_user_cohorts external function.
 *
 * @package    mod_glaaster
 * @copyright  2026 Glaaster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_glaaster\external\get_user_cohorts
 */
final class get_user_cohorts_test extends mod_glaaster_testcase {
    /**
     * A user can always read their own cohorts.
     * @covers ::execute
     */
    public function test_own_cohorts(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user->id);
        $this->setUser($user);

        $result = get_user_cohorts::execute($user->id);
        $result = external_api::clean_returnvalue(get_user_cohorts::execute_returns(), $result);
        $this->assertCount(1, $result);
        $this->assertEquals($cohort->id, $result[0]['id']);
    }

    /**
     * A user without moodle/cohort:view cannot read another user's cohorts.
     * @covers ::execute
     */
    public function test_other_user_cohorts_denied(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $other->id);
        $this->setUser($user);

        $this->expectException(required_capability_exception::class);
        get_user_cohorts::execute($other->id);
    }

    /**
     * A user with moodle/cohort:view can read another user's cohorts.
     * @covers ::execute
     */
    public function test_other_user_cohorts_with_capability(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $other->id);

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, context_system::instance()->id);
        role_assign($roleid, $user->id, context_system::instance()->id);
        $this->setUser($user);

        $result = get_user_cohorts::execute($other->id);
        $result = external_api::clean_returnvalue(get_user_cohorts::execute_returns(), $result);
        $this->assertCount(1, $result);
        $this->assertEquals($cohort->id, $result[0]['id']);
    }
}
