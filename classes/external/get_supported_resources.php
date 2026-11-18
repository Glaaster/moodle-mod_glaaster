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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Filter a list of course module ids down to the file resources Glaaster supports.
 *
 * Used by the generic link detection of the contextual buttons: on custom pages (theme views,
 * dashboards...) the resource links carry no file type hint, so the type is resolved here from
 * the icon Moodle already computed for the resource (cm_info::$icon, e.g. "f/pdf").
 *
 * @package    mod_glaaster
 * @copyright  2026 Glaaster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_supported_resources extends external_api {
    /** Upper bound of course module ids checked per call. */
    const MAX_CMIDS = 200;

    /** Moodle file icons matching the file types Glaaster supports (prefix match, 4.1 adds "-24"). */
    const SUPPORTED_ICONS = ['f/pdf', 'f/image', 'f/document', 'f/powerpoint', 'f/writer', 'f/impress'];

    /**
     * Get parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module id of a mod_resource activity')
            ),
        ]);
    }

    /**
     * Return the course module ids that are visible file resources of a supported type.
     *
     * @param int[] $cmids
     * @return int[]
     */
    public static function execute(array $cmids): array {
        global $DB;

        ['cmids' => $cmids] = self::validate_parameters(self::execute_parameters(), ['cmids' => $cmids]);
        self::validate_context(\context_system::instance());

        $cmids = array_slice(array_unique(array_filter(array_map('intval', $cmids))), 0, self::MAX_CMIDS);
        if (empty($cmids)) {
            return [];
        }

        // Group by course so each course modinfo (cached) is loaded only once.
        $courses = $DB->get_records_list('course_modules', 'id', $cmids, '', 'id, course');
        $bycourse = [];
        foreach ($courses as $record) {
            $bycourse[$record->course][] = (int)$record->id;
        }

        $supported = [];
        foreach ($bycourse as $courseid => $ids) {
            try {
                $modinfo = get_fast_modinfo($courseid);
            } catch (\moodle_exception $e) {
                continue;
            }
            $cms = $modinfo->get_cms();
            foreach ($ids as $cmid) {
                // Missing from modinfo when deletion is in progress.
                $cm = $cms[$cmid] ?? null;
                if (!$cm || $cm->modname !== 'resource' || !$cm->uservisible || !self::is_supported_icon((string)$cm->icon)) {
                    continue;
                }
                $supported[] = $cmid;
            }
        }
        return $supported;
    }

    /**
     * Whether a Moodle file icon name maps to a supported file type.
     *
     * @param string $icon
     * @return bool
     */
    protected static function is_supported_icon(string $icon): bool {
        foreach (self::SUPPORTED_ICONS as $prefix) {
            if (strpos($icon, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get service returns definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_value(PARAM_INT, 'Course module id of a supported resource')
        );
    }
}
