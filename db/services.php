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
 * External tool external functions and service definitions.
 *
 * @package    mod_glaaster
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

$functions = [

    'mod_glaaster_get_tool_launch_data' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'get_tool_launch_data',
        'description' => 'Return the launch data for a given external tool.',
        'type' => 'read',
        'capabilities' => 'mod/glaaster:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_glaaster_get_ltis_by_courses' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'get_ltis_by_courses',
        'description' => 'Returns a list of external tool instances in a provided set of courses, if no courses are provided ' .
            'then all the external tool instances the user has access to will be returned.',
        'type' => 'read',
        'capabilities' => 'mod/glaaster:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_glaaster_view_lti' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'view_lti',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'read',
        'capabilities' => 'mod/glaaster:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_glaaster_get_tool_proxies' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'get_tool_proxies',
        'description' => 'Get a list of the tool proxies',
        'type' => 'read',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_create_tool_proxy' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'create_tool_proxy',
        'description' => 'Create a tool proxy',
        'type' => 'write',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_delete_tool_proxy' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'delete_tool_proxy',
        'description' => 'Delete a tool proxy',
        'type' => 'write',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_get_tool_proxy_registration_request' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'glaaster_get_tool_proxy_registration_request',
        'description' => 'Get a registration request for a tool proxy',
        'type' => 'read',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_get_tool_types' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'get_tool_types',
        'description' => 'Get a list of the tool types',
        'type' => 'read',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_get_tool_types_and_proxies' => [
        'classname' => 'mod_glaaster\external\get_tool_types_and_proxies',
        'methodname' => 'execute',
        'description' => 'Get a list of the tool types and tool proxies',
        'type' => 'read',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_get_tool_types_and_proxies_count' => [
        'classname' => 'mod_glaaster\external\get_tool_types_and_proxies_count',
        'methodname' => 'execute',
        'description' => 'Get total number of the tool types and tool proxies',
        'type' => 'read',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_create_tool_type' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'create_tool_type',
        'description' => 'Create a tool type',
        'type' => 'write',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_update_tool_type' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'update_tool_type',
        'description' => 'Update a tool type',
        'type' => 'write',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_delete_tool_type' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'delete_tool_type',
        'description' => 'Delete a tool type',
        'type' => 'write',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_delete_course_tool_type' => [
        'classname' => 'mod_glaaster\external\delete_course_tool_type',
        'description' => 'Delete a course tool type',
        'type' => 'write',
        'capabilities' => 'mod/glaaster:addcoursetool',
        'ajax' => true,
    ],

    'mod_glaaster_toggle_showinactivitychooser' => [
        'classname' => 'mod_glaaster\external\toggle_showinactivitychooser',
        'description' => 'Toggle showinactivitychooser for a tool type in a course',
        'type' => 'write',
        'capabilities' => 'mod/glaaster:addcoursetool',
        'ajax' => true,
    ],

    'mod_glaaster_is_cartridge' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'is_cartridge',
        'description' => 'Determine if the given url is for a cartridge',
        'type' => 'read',
        'capabilities' => 'moodle/site:config',
        'ajax' => true,
    ],

    'mod_glaaster_search_users' => [
        'classname'    => 'mod_glaaster_external',
        'methodname'   => 'search_users',
        'description'  => 'Search Moodle users for Glaaster API setup',
        'type'         => 'read',
        'capabilities' => 'moodle/site:config',
        'ajax'         => true,
    ],

    // Server-side proxy for the LTI tool /status endpoint.
    // Avoids CORS by performing the HTTP request from PHP (no browser cross-origin restriction).
    // loginrequired = false: the tool card page is admin-only but the status badge is rendered
    // before the session check completes in some Moodle page flows; keeping it consistent with
    // validate_instance. The endpoint reveals only {active: true/false} and makes no data changes.
    'mod_glaaster_check_tool_status' => [
        'classname'    => 'mod_glaaster_external',
        'methodname'   => 'check_tool_status',
        'description'  => 'Proxy HTTP GET to the LTI tool /status endpoint to avoid browser CORS',
        'type'         => 'read',
        'ajax'         => true,
        'loginrequired' => false,
    ],

    // Instance validation endpoint for real-time deletion detection.
    // Used by JavaScript MutationObserver to check if a Glaaster activity is still valid.
    //
    // Security note: loginrequired = false is intentional and safe.
    // Course module IDs (cmid) are not secret — they appear in Moodle URLs visible to all
    // authenticated users. This endpoint only returns {isvalid: true/false} and reveals no
    // user data, course content, or private information. Authentication overhead would add
    // ~200ms latency to a real-time UI operation triggered by DOM mutations.
    // Reviewed against OWASP A01 (Broken Access Control): the endpoint cannot enumerate users,
    // access course content, or modify any data.
    'mod_glaaster_validate_instance' => [
        'classname' => 'mod_glaaster_external',
        'methodname' => 'validate_instance',
        'description' => 'Validate if a Glaaster instance is still valid and not deleted',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false,
    ],
];

// Pre-built service for Glaaster API integration.
// Automatically created on plugin install/upgrade so administrators do not need
// to manually create it via Site administration > Server > Web services > External services.
//
// Settings mirror the manual setup instructions:
// - Enabled on creation.
// - restrictedusers = 1 (only authorised users can use the token).
// - downloadfiles = 1 (required for core_files_get_files).
//
// NOTE: Moodle does not allow admins to add/remove functions from pre-built services.
// The three functions below are the ones required by the Glaaster Document Service.
$services = [
    'Glaaster API' => [
        'functions' => [
            'mod_resource_get_resources_by_courses',
            'core_files_get_files',
            'core_course_get_contents',
        ],
        'enabled' => 1,
        'restrictedusers' => 1,
        'downloadfiles' => 1,
        'shortname' => 'glaaster_api',
    ],
];
