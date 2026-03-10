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
 * External tool module external API
 *
 * @package    mod_glaaster
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

use core_course\external\helper_for_get_mods_by_courses;
use core_external\external_api;
use core_external\external_description;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_external\util;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/glaaster/lib.php');
require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

/**
 * External tool module external functions
 *
 * @package    mod_glaaster
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 *
 * @phpcs:disable PHPMD.TooManyPublicMethods, PHPMD.CyclomaticComplexity
 */
class mod_glaaster_external extends external_api {
    /**
     * Returns the tool types.
     *
     * @param bool $orphanedonly Retrieve only tool proxies that do not have a corresponding tool type
     * @return array of tool types
     * @throws moodle_exception
     * @since Moodle 3.1
     */
    public static function get_tool_proxies($orphanedonly) {
        $params = self::validate_parameters(
            self::get_tool_proxies_parameters(),
            [
                'orphanedonly' => $orphanedonly,
            ]
        );
        $orphanedonly = $params['orphanedonly'];

        $context = context_system::instance();

        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        return lti_glaaster_get_tool_proxies($orphanedonly);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_tool_proxies_parameters() {
        return new external_function_parameters(
            [
                'orphanedonly' => new external_value(PARAM_BOOL, 'Orphaned tool types only', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function get_tool_proxies_returns() {
        return new external_multiple_structure(
            self::tool_proxy_return_structure()
        );
    }

    /**
     * Returns description of a tool proxy
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    private static function tool_proxy_return_structure() {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'Tool proxy id'),
                'name' => new external_value(PARAM_TEXT, 'Tool proxy name'),
                'regurl' => new external_value(PARAM_URL, 'Tool proxy registration URL'),
                'state' => new external_value(PARAM_INT, 'Tool proxy state'),
                'guid' => new external_value(PARAM_TEXT, 'Tool proxy globally unique identifier'),
                'secret' => new external_value(PARAM_TEXT, 'Tool proxy shared secret'),
                'vendorcode' => new external_value(PARAM_TEXT, 'Tool proxy consumer code'),
                'capabilityoffered' => new external_value(PARAM_TEXT, 'Tool proxy capabilities offered'),
                'serviceoffered' => new external_value(PARAM_TEXT, 'Tool proxy services offered'),
                'toolproxy' => new external_value(PARAM_TEXT, 'Tool proxy'),
                'timecreated' => new external_value(PARAM_INT, 'Tool proxy time created'),
                'timemodified' => new external_value(PARAM_INT, 'Tool proxy modified'),
            ]
        );
    }

    /**
     * Return the launch data for a given external tool.
     *
     * @param int $toolid the external tool instance id
     * @return array of warnings and launch data
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function get_tool_launch_data($toolid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/glaaster/lib.php');

        $params = self::validate_parameters(
            self::get_tool_launch_data_parameters(),
            [
                'toolid' => $toolid,
            ]
        );
        $warnings = [];

        // Request and permission validation.
        $lti = $DB->get_record('glaaster', ['id' => $params['toolid']], '*', MUST_EXIST);
        [$cm] = get_course_and_cm_from_instance($lti, 'glaaster');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/glaaster:view', $context);

        $lti->cmid = $cm->id;
        [$endpoint, $parms] = lti_glaaster_get_launch_data($lti);

        $parameters = [];
        foreach ($parms as $name => $value) {
            $parameters[] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        $result = [];
        $result['endpoint'] = $endpoint;
        $result['parameters'] = $parameters;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_tool_launch_data_parameters() {
        return new external_function_parameters(
            [
                'toolid' => new external_value(PARAM_INT, 'external tool instance id'),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function get_tool_launch_data_returns() {
        return new external_single_structure(
            [
                'endpoint' => new external_value(PARAM_RAW, 'Endpoint URL'),
                // Using PARAM_RAW as is defined in the module.
                'parameters' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'name' => new external_value(PARAM_NOTAGS, 'Parameter name'),
                            'value' => new external_value(PARAM_RAW, 'Parameter value'),
                        ]
                    )
                ),
                'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Returns a list of external tools in a provided list of courses,
     * if no list is provided all external tools that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array the lti details
     * @since Moodle 3.0
     */
    public static function get_ltis_by_courses($courseids = []) {
        $returnedltis = [];
        $warnings = [];

        $params = self::validate_parameters(self::get_ltis_by_courses_parameters(), ['courseids' => $courseids]);

        $mycourses = [];
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {
            [$courses, $warnings] = util::validate_courses($params['courseids'], $mycourses);

            // Get the ltis in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $ltis = get_all_instances_in_courses("lti", $courses);

            foreach ($ltis as $lti) {
                $context = context_module::instance($lti->coursemodule);

                // Entry to return.
                $module = helper_for_get_mods_by_courses::standard_coursemodule_element_values(
                    $lti,
                    'mod_glaaster',
                    'moodle/course:manageactivities',
                    'mod/glaaster:view'
                );

                $viewablefields = [];
                if (has_capability('mod/glaaster:view', $context)) {
                    $viewablefields =
                        ['launchcontainer', 'showtitlelaunch', 'showdescriptionlaunch', 'icon', 'secureicon'];
                }

                // Check additional permissions for returning optional private settings.
                if (has_capability('moodle/course:manageactivities', $context)) {
                    $additionalfields = ['timecreated', 'timemodified', 'typeid', 'toolurl', 'securetoolurl',
                        'instructorchoicesendname', 'instructorchoicesendemailaddr', 'instructorchoiceallowroster',
                        'instructorchoiceallowsetting', 'instructorcustomparameters',
                        'resourcekey', 'password', 'debuglaunch', 'servicesalt'];
                    $viewablefields = array_merge($viewablefields, $additionalfields);
                }

                foreach ($viewablefields as $field) {
                    $module[$field] = $lti->{$field};
                }

                $returnedltis[] = $module;
            }
        }

        $result = [];
        $result['ltis'] = $returnedltis;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the parameters for get_ltis_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_ltis_by_courses_parameters() {
        return new external_function_parameters(
            [
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'),
                    'Array of course ids',
                    VALUE_DEFAULT,
                    []
                ),
            ]
        );
    }

    /**
     * Describes the get_ltis_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_ltis_by_courses_returns() {
        return new external_single_structure(
            [
                'ltis' => new external_multiple_structure(
                    new external_single_structure(array_merge(
                        helper_for_get_mods_by_courses::standard_coursemodule_elements_returns(true),
                        [
                            'timecreated' => new external_value(PARAM_INT, 'Time of creation', VALUE_OPTIONAL),
                            'timemodified' => new external_value(
                                PARAM_INT,
                                'Time of last modification',
                                VALUE_OPTIONAL
                            ),
                            'typeid' => new external_value(PARAM_INT, 'Type id', VALUE_OPTIONAL),
                            'toolurl' => new external_value(PARAM_URL, 'Tool url', VALUE_OPTIONAL),
                            'securetoolurl' => new external_value(PARAM_RAW, 'Secure tool url', VALUE_OPTIONAL),
                            'instructorchoicesendname' => new external_value(
                                PARAM_TEXT,
                                'Instructor choice send name',
                                VALUE_OPTIONAL
                            ),
                            'instructorchoicesendemailaddr' => new external_value(
                                PARAM_INT,
                                'instructor choice send mail address',
                                VALUE_OPTIONAL
                            ),
                            'instructorchoiceallowroster' => new external_value(
                                PARAM_INT,
                                'Instructor choice allow roster',
                                VALUE_OPTIONAL
                            ),
                            'instructorchoiceallowsetting' => new external_value(
                                PARAM_INT,
                                'Instructor choice allow setting',
                                VALUE_OPTIONAL
                            ),
                            'instructorcustomparameters' => new external_value(
                                PARAM_RAW,
                                'instructor custom parameters',
                                VALUE_OPTIONAL
                            ),
                            'launchcontainer' => new external_value(PARAM_INT, 'Launch container mode', VALUE_OPTIONAL),
                            'resourcekey' => new external_value(PARAM_RAW, 'Resource key', VALUE_OPTIONAL),
                            'password' => new external_value(PARAM_RAW, 'Shared secret', VALUE_OPTIONAL),
                            'debuglaunch' => new external_value(PARAM_INT, 'Debug launch', VALUE_OPTIONAL),
                            'showtitlelaunch' => new external_value(PARAM_INT, 'Show title launch', VALUE_OPTIONAL),
                            'showdescriptionlaunch' => new external_value(
                                PARAM_INT,
                                'Show description launch',
                                VALUE_OPTIONAL
                            ),
                            'servicesalt' => new external_value(PARAM_RAW, 'Service salt', VALUE_OPTIONAL),
                            'icon' => new external_value(PARAM_URL, 'Alternative icon URL', VALUE_OPTIONAL),
                            'secureicon' => new external_value(PARAM_URL, 'Secure icon URL', VALUE_OPTIONAL),
                        ]
                    ), 'Tool')
                ),
                'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $ltiid the lti instance id
     * @return array of warnings and status result
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function view_lti($ltiid) {
        global $DB;

        $params = self::validate_parameters(
            self::view_lti_parameters(),
            [
                'ltiid' => $ltiid,
            ]
        );
        $warnings = [];

        // Request and permission validation.
        $lti = $DB->get_record('glaaster', ['id' => $params['ltiid']], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($lti, 'glaaster');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/glaaster:view', $context);

        // Trigger course_module_viewed event and completion.
        glaaster_view($lti, $course, $cm, $context);

        $result = [];
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_lti_parameters() {
        return new external_function_parameters(
            [
                'ltiid' => new external_value(PARAM_INT, 'lti instance id'),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_lti_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Creates a new tool proxy
     *
     * @param string $name Tool proxy name
     * @param string $registrationurl Registration url
     * @param string[] $capabilityoffered List of capabilities this tool proxy should be offered
     * @param string[] $serviceoffered List of services this tool proxy should be offered
     * @return object The new tool proxy
     * @throws moodle_exception
     * @since Moodle 3.1
     */
    public static function create_tool_proxy($name, $registrationurl, $capabilityoffered, $serviceoffered) {
        $params = self::validate_parameters(
            self::create_tool_proxy_parameters(),
            [
                'name' => $name,
                'regurl' => $registrationurl,
                'capabilityoffered' => $capabilityoffered,
                'serviceoffered' => $serviceoffered,
            ]
        );
        $name = $params['name'];
        $capabilityoffered = $params['capabilityoffered'];
        $serviceoffered = $params['serviceoffered'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // Can't create duplicate proxies with the same URL.
        $duplicates = lti_glaaster_get_tool_proxies_from_registration_url($registrationurl);
        if (!empty($duplicates)) {
            throw new moodle_exception('duplicateregurl', 'mod_glaaster');
        }

        $config = new stdClass();
        $config->lti_registrationurl = $registrationurl;

        if (!empty($name)) {
            $config->lti_registrationname = $name;
        }

        if (!empty($capabilityoffered)) {
            $config->lti_capabilities = $capabilityoffered;
        }

        if (!empty($serviceoffered)) {
            $config->lti_services = $serviceoffered;
        }

        $id = lti_glaaster_add_tool_proxy($config);
        $toolproxy = lti_glaaster_get_tool_proxy($id);

        // Pending makes more sense than configured as the first state, since
        // the next step is to register, which requires the state be pending.
        $toolproxy->state = LTI_GLAASTER_TOOL_PROXY_STATE_PENDING;
        lti_glaaster_update_tool_proxy($toolproxy);

        return $toolproxy;
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function create_tool_proxy_parameters() {
        return new external_function_parameters(
            [
                'name' => new external_value(PARAM_TEXT, 'Tool proxy name', VALUE_DEFAULT, ''),
                'regurl' => new external_value(PARAM_URL, 'Tool proxy registration URL'),
                'capabilityoffered' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Tool proxy capabilities offered'),
                    'Array of capabilities',
                    VALUE_DEFAULT,
                    []
                ),
                'serviceoffered' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Tool proxy services offered'),
                    'Array of services',
                    VALUE_DEFAULT,
                    []
                ),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function create_tool_proxy_returns() {
        return self::tool_proxy_return_structure();
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $id the lti instance id
     * @return object The tool proxy
     * @throws moodle_exception
     * @since Moodle 3.1
     */
    public static function delete_tool_proxy($id) {
        $params = self::validate_parameters(
            self::delete_tool_proxy_parameters(),
            [
                'id' => $id,
            ]
        );
        $id = $params['id'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $toolproxy = lti_glaaster_get_tool_proxy($id);

        lti_glaaster_delete_tool_proxy($id);

        return $toolproxy;
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function delete_tool_proxy_parameters() {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'Tool proxy id'),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function delete_tool_proxy_returns() {
        return self::tool_proxy_return_structure();
    }

    /**
     * Returns the registration request for a tool proxy.
     *
     * @param int $id the lti instance id
     * @return array of registration parameters
     * @throws moodle_exception
     * @since Moodle 3.1
     */
    public static function get_tool_proxy_registration_request($id) {
        $params = self::validate_parameters(
            self::get_tool_proxy_registration_request_parameters(),
            [
                'id' => $id,
            ]
        );
        $id = $params['id'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $toolproxy = lti_glaaster_get_tool_proxy($id);
        return lti_glaaster_build_registration_request($toolproxy);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_tool_proxy_registration_request_parameters() {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'Tool proxy id'),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function get_tool_proxy_registration_request_returns() {
        return new external_function_parameters(
            [
                'lti_message_type' => new external_value(PARAM_ALPHANUMEXT, 'LTI message type'),
                'lti_version' => new external_value(PARAM_ALPHANUMEXT, 'LTI version'),
                'reg_key' => new external_value(PARAM_TEXT, 'Tool proxy registration key'),
                'reg_password' => new external_value(PARAM_TEXT, 'Tool proxy registration password'),
                'reg_url' => new external_value(PARAM_TEXT, 'Tool proxy registration url'),
                'tc_profile_url' => new external_value(PARAM_URL, 'Tool consumers profile URL'),
                'launch_presentation_return_url' => new external_value(
                    PARAM_URL,
                    'URL to redirect on registration completion'
                ),
            ]
        );
    }

    /**
     * Returns the tool types.
     *
     * @param int $toolproxyid The tool proxy id
     * @return array of tool types
     * @throws moodle_exception
     * @since Moodle 3.1
     */
    public static function get_tool_types($toolproxyid) {
        $params = self::validate_parameters(
            self::get_tool_types_parameters(),
            [
                'toolproxyid' => $toolproxyid,
            ]
        );
        $toolproxyid = $params['toolproxyid'];

        $types = [];
        $context = context_system::instance();

        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        if (!empty($toolproxyid)) {
            $types = lti_glaaster_get_lti_types_from_proxy_id($toolproxyid);
        } else {
            $types = lti_glaaster_get_lti_types();
        }

        return array_map("glaaster_serialise_tool_type", array_values($types));
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_tool_types_parameters() {
        return new external_function_parameters(
            [
                'toolproxyid' => new external_value(PARAM_INT, 'Tool proxy id', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function get_tool_types_returns() {
        return new external_multiple_structure(
            self::tool_type_return_structure()
        );
    }

    /**
     * Returns structure be used for returning a tool type from a web service.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    private static function tool_type_return_structure() {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'Tool type id'),
                'name' => new external_value(PARAM_NOTAGS, 'Tool type name'),
                'description' => new external_value(PARAM_NOTAGS, 'Tool type description'),
                'platformid' => new external_value(PARAM_TEXT, 'Platform ID'),
                'clientid' => new external_value(PARAM_TEXT, 'Client ID'),
                'deploymentid' => new external_value(PARAM_INT, 'Deployment ID'),
                'statusurl' => new external_value(PARAM_URL, 'Status URL for the tool', VALUE_OPTIONAL),
                'urls' => new external_single_structure(
                    [
                        'icon' => new external_value(PARAM_URL, 'Tool type icon URL'),
                        'edit' => new external_value(PARAM_URL, 'Tool type edit URL'),
                        'course' => new external_value(PARAM_URL, 'Tool type edit URL', VALUE_OPTIONAL),
                        'publickeyset' => new external_value(PARAM_URL, 'Public Keyset URL'),
                        'accesstoken' => new external_value(PARAM_URL, 'Access Token URL'),
                        'authrequest' => new external_value(PARAM_URL, 'Authorisation Request URL'),
                    ]
                ),
                'state' => new external_single_structure(
                    [
                        'text' => new external_value(PARAM_TEXT, 'Tool type state name string'),
                        'pending' => new external_value(PARAM_BOOL, 'Is the state pending'),
                        'configured' => new external_value(PARAM_BOOL, 'Is the state configured'),
                        'rejected' => new external_value(PARAM_BOOL, 'Is the state rejected'),
                        'unknown' => new external_value(PARAM_BOOL, 'Is the state unknown'),
                    ]
                ),
                'hascapabilitygroups' => new external_value(PARAM_BOOL, 'Indicate if capabilitygroups is populated'),
                'capabilitygroups' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Tool type capability groups enabled'),
                    'Array of capability groups',
                    VALUE_DEFAULT,
                    []
                ),
                'courseid' => new external_value(PARAM_INT, 'Tool type course', VALUE_DEFAULT, 0),
                'instanceids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'LTI instance ID'),
                    'IDs for the LTI instances using this type',
                    VALUE_DEFAULT,
                    []
                ),
                'instancecount' => new external_value(PARAM_INT, 'The number of times this tool is being used'),
            ],
            'Tool'
        );
    }

    /**
     * Creates a tool type.
     *
     * @param string $cartridgeurl Url of the xml cartridge representing the LTI tool
     * @param string $key The consumer key to identify this consumer
     * @param string $secret The secret
     * @return array created tool type
     * @throws moodle_exception If the tool type could not be created
     * @since Moodle 3.1
     */
    public static function create_tool_type($cartridgeurl, $key, $secret) {
        $params = self::validate_parameters(
            self::create_tool_type_parameters(),
            [
                'cartridgeurl' => $cartridgeurl,
                'key' => $key,
                'secret' => $secret,
            ]
        );
        $cartridgeurl = $params['cartridgeurl'];
        $key = $params['key'];
        $secret = $params['secret'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $id = null;

        if (!empty($cartridgeurl)) {
            $type = new stdClass();
            $data = new stdClass();
            $type->state = LTI_GLAASTER_TOOL_STATE_CONFIGURED;
            $data->lti_coursevisible = 2;
            $data->lti_sendname = LTI_GLAASTER_SETTING_DELEGATE;
            $data->lti_sendemailaddr = LTI_GLAASTER_SETTING_DELEGATE;
            $data->lti_acceptgrades = LTI_GLAASTER_SETTING_DELEGATE;
            $data->lti_forcessl = 0;

            if (!empty($key)) {
                $data->lti_resourcekey = $key;
            }

            if (!empty($secret)) {
                $data->lti_password = $secret;
            }

            lti_glaaster_load_type_from_cartridge($cartridgeurl, $data);
            if (empty($data->lti_toolurl)) {
                throw new moodle_exception('unabletocreatetooltype', 'mod_glaaster');
            } else {
                $id = lti_glaaster_add_type($type, $data);
            }
        }

        if (!empty($id)) {
            $type = lti_glaaster_get_type($id);
            return glaaster_serialise_tool_type($type);
        } else {
            throw new moodle_exception('unabletocreatetooltype', 'mod_glaaster');
        }
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function create_tool_type_parameters() {
        return new external_function_parameters(
            [
                'cartridgeurl' => new external_value(
                    PARAM_URL,
                    'URL to cardridge to load tool information',
                    VALUE_DEFAULT,
                    ''
                ),
                'key' => new external_value(PARAM_TEXT, 'Consumer key', VALUE_DEFAULT, ''),
                'secret' => new external_value(PARAM_TEXT, 'Shared secret', VALUE_DEFAULT, ''),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function create_tool_type_returns() {
        return self::tool_type_return_structure();
    }

    /**
     * Update a tool type.
     *
     * @param int $id The id of the tool type to update
     * @param string $name The name of the tool type
     * @param string $description The name of the tool type
     * @param int $state The state of the tool type
     * @return array updated tool type
     * @throws moodle_exception
     * @since Moodle 3.1
     */
    public static function update_tool_type($id, $name, $description, $state) {
        $params = self::validate_parameters(
            self::update_tool_type_parameters(),
            [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'state' => $state,
            ]
        );
        $id = $params['id'];
        $name = $params['name'];
        $description = $params['description'];
        $state = $params['state'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $type = lti_glaaster_get_type($id);

        if (empty($type)) {
            throw new moodle_exception('unabletofindtooltype', 'mod_glaaster', '', ['id' => $id]);
        }

        if (!empty($name)) {
            $type->name = $name;
        }

        if (!empty($description)) {
            $type->description = $description;
        }

        if (!empty($state)) {
            // Valid state range.
            if (in_array($state, [1, 2, 3])) {
                $type->state = $state;
            } else {
                throw new moodle_exception("Invalid state: $state - must be 1, 2, or 3");
            }
        }

        lti_glaaster_update_type($type, new stdClass());

        return glaaster_serialise_tool_type($type);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function update_tool_type_parameters() {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'Tool type id'),
                'name' => new external_value(PARAM_RAW, 'Tool type name', VALUE_DEFAULT, null),
                'description' => new external_value(PARAM_RAW, 'Tool type description', VALUE_DEFAULT, null),
                'state' => new external_value(PARAM_INT, 'Tool type state', VALUE_DEFAULT, null),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function update_tool_type_returns() {
        return self::tool_type_return_structure();
    }

    /**
     * Delete a tool type.
     *
     * @param int $id The id of the tool type to be deleted
     * @return array deleted tool type
     * @throws moodle_exception
     * @since Moodle 3.1
     */
    public static function delete_tool_type($id) {
        $params = self::validate_parameters(
            self::delete_tool_type_parameters(),
            [
                'id' => $id,
            ]
        );
        $id = $params['id'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $type = lti_glaaster_get_type($id);

        if (!empty($type)) {
            lti_glaaster_delete_type($id);

            // If this is the last type for this proxy then remove the proxy
            // as well so that it isn't orphaned.
            $types = lti_glaaster_get_lti_types_from_proxy_id($type->toolproxyid);
            if (empty($types)) {
                lti_glaaster_delete_tool_proxy($type->toolproxyid);
            }
        }

        return ['id' => $id];
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function delete_tool_type_parameters() {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'Tool type id'),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function delete_tool_type_returns() {
        return new external_function_parameters(
            [
                'id' => new external_value(PARAM_INT, 'Tool type id'),
            ]
        );
    }

    /**
     * Determine if the url to a tool is for a cartridge.
     *
     * @param string $url Url that may or may not be an xml cartridge
     * @return bool True if the url is for a cartridge.
     * @throws moodle_exception
     * @since Moodle 3.1
     */
    public static function is_cartridge($url) {
        $params = self::validate_parameters(
            self::is_cartridge_parameters(),
            [
                'url' => $url,
            ]
        );
        $url = $params['url'];

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $iscartridge = lti_glaaster_is_cartridge($url);

        return ['iscartridge' => $iscartridge];
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function is_cartridge_parameters() {
        return new external_function_parameters(
            [
                'url' => new external_value(PARAM_URL, 'Tool url'),
            ]
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function is_cartridge_returns() {
        return new external_function_parameters(
            [
                'iscartridge' => new external_value(PARAM_BOOL, 'True if the URL is a cartridge'),
            ]
        );
    }

    /**
     * AJAX endpoint to validate if a Glaaster instance is still valid.
     *
     * Used by JavaScript to check instance validity in real-time, particularly
     * after detecting potential deletions via MutationObserver.
     *
     * Performs same validation as glaaster_retrieve_instance_from_tooldomain():
     * - Instance exists in database
     * - Course exists and is not deleted
     * - Course module entry exists with deletioninprogress = 0
     *
     * @param int $instanceid The Glaaster instance ID to validate
     * @return array Array with 'isvalid' boolean key
     * @since Moodle 4.0
     */
    public static function validate_instance($instanceid) {
        global $DB;

        $params = self::validate_parameters(
            self::validate_instance_parameters(),
            ['instanceid' => $instanceid]
        );

        $context = context_system::instance();
        self::validate_context($context);

        $isvalid = false;

        if (!empty($params['instanceid'])) {
            $instance = $DB->get_record('glaaster', ['id' => $params['instanceid']]);

            if ($instance) {
                // Check course exists.
                if ($DB->record_exists('course', ['id' => $instance->course])) {
                    $moduleid = $DB->get_field('modules', 'id', ['name' => 'glaaster']);

                    if ($moduleid) {
                        // Check course module exists and deletioninprogress = 0.
                        $isvalid = $DB->record_exists_sql(
                            "SELECT cm.id FROM {course_modules} cm" .
                            " WHERE cm.course = ? AND cm.module = ? AND cm.instance = ?" .
                            " AND cm.deletioninprogress = 0",
                            [$instance->course, $moduleid, $instance->id]
                        );
                    }
                }
            }
        }

        return ['isvalid' => $isvalid];
    }

    /**
     * Returns description of method parameters for validate_instance
     *
     * @return external_function_parameters
     * @since Moodle 4.0
     */
    public static function validate_instance_parameters() {
        return new external_function_parameters(
            [
                'instanceid' => new external_value(PARAM_INT, 'Glaaster instance ID'),
            ]
        );
    }

    /**
     * Returns description of method result value for validate_instance
     *
     * @return external_description
     * @since Moodle 4.0
     */
    public static function validate_instance_returns() {
        return new external_single_structure(
            [
                'isvalid' => new external_value(PARAM_BOOL, 'True if the instance is valid and not deleted'),
            ]
        );
    }

    /**
     * Returns description of method parameters for search_users
     *
     * @return external_function_parameters
     */
    public static function search_users_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search query'),
        ]);
    }

    /**
     * Search Moodle users for Glaaster API setup autocomplete.
     *
     * @param string $query Search query string
     * @return array List of matching users
     */
    public static function search_users($query) {
        global $DB;
        $params = self::validate_parameters(self::search_users_parameters(), ['query' => $query]);
        self::validate_context(context_system::instance());
        require_capability('moodle/site:config', context_system::instance());

        $search = '%' . $DB->sql_like_escape($params['query']) . '%';
        $sql = "SELECT id, username, firstname, lastname, email FROM {user}" .
               " WHERE deleted = 0 AND suspended = 0" .
               " AND (" . $DB->sql_like('username', '?', false) .
               " OR " . $DB->sql_like('firstname', '?', false) .
               " OR " . $DB->sql_like('lastname', '?', false) . ")" .
               " ORDER BY lastname, firstname";
        $users = $DB->get_records_sql($sql, [$search, $search, $search], 0, 10);

        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'id'       => $user->id,
                'username' => $user->username,
                'fullname' => fullname($user),
            ];
        }
        return $result;
    }

    /**
     * Returns description of method result value for search_users
     *
     * @return external_multiple_structure
     */
    public static function search_users_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id'       => new external_value(PARAM_INT, 'User ID'),
                'username' => new external_value(PARAM_USERNAME, 'Username'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
            ])
        );
    }

    /**
     * Returns description of method parameters for check_tool_status
     *
     * @return external_function_parameters
     */
    public static function check_tool_status_parameters() {
        return new external_function_parameters([
            'statusurl' => new external_value(PARAM_URL, 'Status URL of the LTI tool'),
            'iss'       => new external_value(PARAM_URL, 'Platform issuer (iss) URL'),
            'client_id' => new external_value(PARAM_TEXT, 'Client ID'),
        ]);
    }

    /**
     * Server-side proxy for the LTI tool /status endpoint.
     *
     * Avoids CORS by performing the HTTP request from PHP instead of the browser.
     *
     * @param string $statusurl Base status URL of the LTI tool
     * @param string $iss       Platform issuer (iss)
     * @param string $clientid  Client ID registered for the tool
     * @return array With 'active' bool key
     */
    public static function check_tool_status($statusurl, $iss, $clientid) {
        $params = self::validate_parameters(
            self::check_tool_status_parameters(),
            ['statusurl' => $statusurl, 'iss' => $iss, 'client_id' => $clientid]
        );

        $context = context_system::instance();
        self::validate_context($context);

        $separator = strpos($params['statusurl'], '?') !== false ? '&' : '?';
        $fullurl = $params['statusurl'] . $separator
            . 'iss=' . urlencode($params['iss'])
            . '&client_id=' . urlencode($params['client_id']);

        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 5, 'CURLOPT_CONNECTTIMEOUT' => 5]);
        $response = $curl->get($fullurl, [], ['CURLOPT_RETURNTRANSFER' => true]);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        $active = false;
        $status = '';
        if ($httpcode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                $active = isset($data['active']) && $data['active'] === true;
                $status = isset($data['status']) ? clean_param($data['status'], PARAM_TEXT) : '';
            }
        }

        return ['active' => $active, 'status' => $status];
    }

    /**
     * Returns description of method result value for check_tool_status
     *
     * @return external_single_structure
     */
    public static function check_tool_status_returns() {
        return new external_single_structure([
            'active' => new external_value(PARAM_BOOL, 'True if the tool is active and reachable'),
            'status' => new external_value(PARAM_TEXT, 'Status string from the tool API', VALUE_DEFAULT, ''),
        ]);
    }
}
