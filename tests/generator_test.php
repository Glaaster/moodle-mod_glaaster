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

use advanced_testcase;
use context_module;

/**
 * PHPUnit data generator testcase
 *
 * @package    mod_glaaster
 * @category   external
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generator_test extends advanced_testcase {
    public function test_generator(): void {
        global $DB;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('glaaster'));

        $course = $this->getDataGenerator()->create_course();

        /** @var mod_glaaster_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_glaaster');
        $this->assertInstanceOf('mod_glaaster_generator', $generator);
        $this->assertEquals('glaaster', $generator->get_modulename());

        $generator->create_instance(['course' => $course->id]);
        $generator->create_instance(['course' => $course->id]);
        $lti = $generator->create_instance(['course' => $course->id]);
        $this->assertEquals(3, $DB->count_records('glaaster'));

        $cm = get_coursemodule_from_instance('glaaster', $lti->id);
        $this->assertEquals($lti->id, $cm->instance);
        $this->assertEquals('glaaster', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($lti->cmid, $context->instanceid);

        // Test gradebook integration using low level DB access - DO NOT USE IN PLUGIN CODE!
        $lti = $generator->create_instance(['course' => $course->id, 'assessed' => 1, 'scale' => 100]);
        $gitem = $DB->get_record('grade_items', ['courseid' => $course->id, 'itemtype' => 'mod',
            'itemmodule' => 'lti', 'iteminstance' => $lti->id]);
        $this->assertNotEmpty($gitem);
        $this->assertEquals(100, $gitem->grademax);
        $this->assertEquals(0, $gitem->grademin);
        $this->assertEquals(GRADE_TYPE_VALUE, $gitem->gradetype);
    }
}
