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

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_top_of_body_html_generation;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

/**
 * This file contains hook callbacks for the Glaaster module.
 *
 * @package    mod_glaaster
 * @copyright  2025 Glaaster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Runs before the HTTP footer is generated.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        // Load the appropriate JS file based on Moodle version.
        mod_glaaster_load_js();
    }

    /**
     * Callback for before_standard_top_of_body_html_generation.
     *
     * Plugins implementing this callback can add HTML content to the top of the body.
     *
     * @param before_standard_top_of_body_html_generation $hook
     * @return void
     */
    public static function before_standard_top_of_body_html_generation(before_standard_top_of_body_html_generation $hook): void {
        global $DB;
        $dbman = $DB->get_manager();

        // Check if the required table exists to avoid errors during installation.
        if (!$dbman->table_exists('glaaster_types')) {
            // Table does not exist: add an empty variable.
            $hook->add_html("<script>const glaasterInstanceId = '';</script>");
            return;
        }

        // Retrieve the instance ID using your custom function.
        $instanceid = glaaster_retrieve_instance_from_tooldomain();
        if ($instanceid === false) {
            $instanceid = '';
        }

        // Check if webservices are enabled for AJAX functionality.
        $webservicesenabled = glaaster_check_webservices_enabled() ? 'true' : 'false';

        // Check if Glaaster webservice is properly configured (user, token, external functions).
        $webserviceconfigured = glaaster_check_webservice_configured() ? 'true' : 'false';

        // Check if debug level is set to show warnings (NORMAL, ALL, or DEVELOPER).
        // DEBUG_NORMAL = 15, so anything >= 15 will show console warnings.
        global $CFG;
        $debugenabled = (!empty($CFG->debug) && $CFG->debug >= 15) ? 'true' : 'false';

        // Add the scripts to the top of the body.
        $js = "<script>const glaasterInstanceId = '{$instanceid}'; " .
            "const glaasterWebservicesEnabled = {$webservicesenabled}; " .
            "const glaasterWebserviceConfigured = {$webserviceconfigured}; " .
            "const glaasterDebugEnabled = {$debugenabled};</script>";
        $hook->add_html($js);
    }
}
