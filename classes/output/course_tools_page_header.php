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

namespace mod_glaaster\output;

use coding_exception;
use core\exception\moodle_exception;
use core\output\notification;
use moodle_url;
use renderer_base;
use stdClass;
use templatable;

/**
 * Course tools page header renderable, containing the data for the page zero state and 'add tool' button.
 *
 * @package    mod_glaaster
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_tools_page_header implements templatable {
    /**
     * The course ID.
     *
     * @var int
     */
    protected int $courseid;

    /**
     * The number of tools in the course.
     *
     * @var int
     */
    protected int $toolcount;

    /**
     * Whether the user can add tools.
     *
     * @var bool
     */
    protected bool $canadd;

    /**
     * Constructor.
     *
     * @param int $courseid the course id.
     * @param int $toolcount the number of tools available in the course.
     * @param bool $canadd whether the user can add tools to the course or not.
     */
    public function __construct(int $courseid, int $toolcount, bool $canadd) {
        $this->courseid = $courseid;
        $this->toolcount = $toolcount;
        $this->canadd = $canadd;
    }

    /**
     * Export the header's data for template use.
     *
     * @param renderer_base $output
     * @return object the data.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output): stdClass {
        $context = (object) [];

        if ($this->canadd) {
            $context->addlink =
                (new moodle_url('/mod/glaaster/coursetooledit.php', ['course' => $this->courseid]))->out();
        }

        if ($this->toolcount == 0) {
            $notification =
                new notification(
                    get_string('nocourseexternaltoolsnotice', 'mod_glaaster'),
                    notification::NOTIFY_INFO,
                    true
                );
            $context->notoolsnotice = $notification->export_for_template($output);
        }

        return $context;
    }
}
