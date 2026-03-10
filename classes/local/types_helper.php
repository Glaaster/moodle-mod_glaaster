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

namespace mod_glaaster\local;

use core\context\course;
use moodle_exception;
use stdClass;

/**
 * Helper class specifically dealing with LTI types (preconfigured tools).
 *
 * @package    mod_glaaster
 * @copyright  2023 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class types_helper {
    /**
     * Returns all LTI tool types (preconfigured tools) visible in the given course and for the given user.
     *
     * This list will contain both site level tools and course-level tools.
     *
     * @param int $courseid the id of the course.
     * @param int $userid the id of the user.
     * @param array $coursevisible options for 'coursevisible' field, which will default to
     *        [LTI_GLAASTER_COURSEVISIBLE_PRECONFIGURED, LTI_GLAASTER_COURSEVISIBLE_ACTIVITYCHOOSER] if omitted.
     * @return stdClass[] the array of tool type objects.
     */
    public static function get_lti_types_by_course(int $courseid, int $userid, array $coursevisible = []): array {
        global $DB, $SITE;

        if (!has_capability('mod/glaaster:addpreconfiguredinstance', course::instance($courseid), $userid)) {
            return [];
        }

        if (empty($coursevisible)) {
            $coursevisible = [LTI_GLAASTER_COURSEVISIBLE_PRECONFIGURED, LTI_GLAASTER_COURSEVISIBLE_ACTIVITYCHOOSER];
        }
        [$coursevisiblesql, $coursevisparams] = $DB->get_in_or_equal($coursevisible, SQL_PARAMS_NAMED, 'coursevisible');
        [$coursevisiblesql1, $coursevisparams1] = $DB->get_in_or_equal($coursevisible, SQL_PARAMS_NAMED, 'coursevisible');
        [$coursevisibleoverriddensql, $coursevisoverriddenparams] = $DB->get_in_or_equal(
            $coursevisible,
            SQL_PARAMS_NAMED,
            'coursevisibleoverridden'
        );

        $coursecond = implode(" OR ", ["t.course = :courseid", "t.course = :siteid"]);
        $coursecategory = $DB->get_field('course', 'category', ['id' => $courseid]);
        $query = "SELECT *
                    FROM (SELECT t.*, c.coursevisible as coursevisibleoverridden
                            FROM {glaaster_types} t
                       LEFT JOIN {glaaster_types_categories} tc ON t.id = tc.typeid
                       LEFT JOIN {glaaster_coursevisible} c ON c.typeid = t.id AND c.courseid = $courseid
                           WHERE (t.coursevisible $coursevisiblesql
                                 OR (c.coursevisible $coursevisiblesql1 AND t.coursevisible NOT IN (:lticoursevisibleno)))
                             AND ($coursecond)
                             AND t.state = :active
                             AND (tc.id IS NULL OR tc.categoryid = :categoryid)) tt
                   WHERE tt.coursevisibleoverridden IS NULL
                      OR tt.coursevisibleoverridden $coursevisibleoverriddensql";

        return $DB->get_records_sql(
            $query,
            [
                'siteid' => $SITE->id,
                'courseid' => $courseid,
                'active' => LTI_GLAASTER_TOOL_STATE_CONFIGURED,
                'categoryid' => $coursecategory,
                'coursevisible' => LTI_GLAASTER_COURSEVISIBLE_ACTIVITYCHOOSER,
                'lticoursevisibleno' => LTI_GLAASTER_COURSEVISIBLE_NO,
            ] + $coursevisparams + $coursevisparams1 + $coursevisoverriddenparams
        );
    }

    /**
     * Override coursevisible for a given tool on course level.
     *
     * @param int $tooltypeid Type ID
     * @param int $courseid Course ID
     * @param course $context Course context
     * @param bool $showinactivitychooser Show or not show in activity chooser
     * @return bool True if the coursevisible was changed, false otherwise.
     */
    public static function override_type_showinactivitychooser(
        int $tooltypeid,
        int $courseid,
        course $context,
        bool $showinactivitychooser
    ): bool {
        global $DB;

        require_capability('mod/glaaster:addcoursetool', $context);

        $ltitype = lti_glaaster_get_type(typeid: $tooltypeid);
        if ($ltitype && ($ltitype->coursevisible != LTI_GLAASTER_COURSEVISIBLE_NO)) {
            $coursevisible =
                $showinactivitychooser ? LTI_GLAASTER_COURSEVISIBLE_ACTIVITYCHOOSER : LTI_GLAASTER_COURSEVISIBLE_PRECONFIGURED;
            $ltitype->coursevisible = $coursevisible;

            $config = new stdClass();
            $config->lti_coursevisible = $coursevisible;

            if (intval($ltitype->course) != intval(get_site()->id)) {
                // It is course tool - just update it.
                lti_glaaster_update_type($ltitype, $config);
            } else {
                $coursecategory = $DB->get_field('course', 'category', ['id' => $courseid]);
                $sql = "SELECT COUNT(*) AS count
                      FROM {glaaster_types_categories} tc
                     WHERE tc.typeid = :typeid";
                $restrictedtool = $DB->count_records_sql($sql, ['typeid' => $tooltypeid]);
                if ($restrictedtool) {
                    $record =
                        $DB->get_record('glaaster_types_categories', ['typeid' => $tooltypeid, 'categoryid' => $coursecategory]);
                    if (!$record) {
                        throw new moodle_exception('You are not allowed to change this setting for this tool.');
                    }
                }

                // This is site tool, but we would like to have course level setting for it.
                $lticoursevisible = $DB->get_record('glaaster_coursevisible', ['typeid' => $tooltypeid, 'courseid' => $courseid]);
                if (!$lticoursevisible) {
                    $lticoursevisible = new stdClass();
                    $lticoursevisible->typeid = $tooltypeid;
                    $lticoursevisible->courseid = $courseid;
                    $lticoursevisible->coursevisible = $coursevisible;
                    $DB->insert_record('glaaster_coursevisible', $lticoursevisible);
                } else {
                    $lticoursevisible->coursevisible = $coursevisible;
                    $DB->update_record('glaaster_coursevisible', $lticoursevisible);
                }
            }
            return true;
        }
        return false;
    }
}
