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
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function for retrieving cohorts a user belongs to.
 *
 * @package    mod_glaaster
 * @copyright  2026 Glaaster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_cohorts extends external_api {
    /**
     * Return the cohorts a user belongs to.
     *
     * @param int $userid The user ID to query
     * @return array List of cohort objects
     */
    public static function execute(int $userid): array {
        global $CFG;

        $params = self::validate_parameters(self::execute_parameters(), ['userid' => $userid]);

        $context = context_system::instance();
        self::validate_context($context);

        $user = \core_user::get_user($params['userid'], '*', MUST_EXIST);
        \core_user::require_active_user($user);

        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohorts = cohort_get_user_cohorts($params['userid']);

        $result = [];
        foreach ($cohorts as $cohort) {
            $result[] = [
                'id'          => (int) $cohort->id,
                'name'        => clean_param($cohort->name, PARAM_TEXT),
                'idnumber'    => clean_param($cohort->idnumber, PARAM_RAW),
                'description' => clean_param($cohort->description, PARAM_RAW),
                'visible'     => (bool) $cohort->visible,
            ];
        }

        return $result;
    }

    /**
     * Parameter definition for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'ID of the user whose cohorts to retrieve'),
        ]);
    }

    /**
     * Return value definition for execute().
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'          => new external_value(PARAM_INT, 'Cohort ID'),
                'name'        => new external_value(PARAM_TEXT, 'Cohort name'),
                'idnumber'    => new external_value(PARAM_RAW, 'Cohort ID number', VALUE_OPTIONAL),
                'description' => new external_value(PARAM_RAW, 'Cohort description', VALUE_OPTIONAL),
                'visible'     => new external_value(PARAM_BOOL, 'Whether the cohort is visible'),
            ])
        );
    }
}
