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
//
// This file is part of BasicLTI4Moodle
//
// BasicLTI4Moodle is an IMS BasicLTI (Basic Learning Tools for Interoperability)
// consumer for Moodle 1.9 and Moodle 2.0. BasicLTI is a IMS Standard that allows web
// based learning tools to be easily integrated in LMS as native ones. The IMS BasicLTI
// specification is part of the IMS standard Common Cartridge 1.1 Sakai and other main LMS
// are already supporting or going to support BasicLTI. This project Implements the consumer
// for Moodle. Moodle is a Free Open source Learning Management System by Martin Dougiamas.
// BasicLTI4Moodle is a project iniciated and leaded by Ludo(Marc Alier) and Jordi Piguillem
// at the GESSI research group at UPC.
// SimpleLTI consumer for Moodle is an implementation of the early specification of LTI
// by Charles Severance (Dr Chuck) htp://dr-chuck.com , developed by Jordi Piguillem in a
// Google Summer of Code 2008 project co-mentored by Charles Severance and Marc Alier.
//
// BasicLTI4Moodle is copyright 2009 by Marc Alier Forment, Jordi Piguillem and Nikolas Galanis
// of the Universitat Politecnica de Catalunya http://www.upc.edu
// Contact info: Marc Alier Forment granludo @ gmail.com or marc.alier @ upc.edu.

/**
 * This file contains a library of functions and constants for the lti module
 *
 * @package mod_glaaster
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @author     Chris Scribner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\context\course;
use core_calendar\action_factory;
use core_calendar\local\event\entities\action_interface;
use core_completion\api;
use core_course\local\entity\content_item;
use core_course\local\entity\string_title;
use mod_glaaster\event\course_module_viewed;

/**
 * List of features supported in URL module
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function lti_glaaster_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_OTHER;

        default:
            return null;
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $lti An object from the form in mod.html
 * @param object|null $mform The form instance (unused, for API compatibility)
 * @return int The id of the newly inserted basiclti record
 **/
function glaaster_add_instance($lti, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

    if (!isset($lti->toolurl)) {
        $lti->toolurl = '';
    }

    lti_glaaster_load_tool_if_cartridge($lti);

    $lti->timecreated = time();
    $lti->timemodified = $lti->timecreated;
    $lti->servicesalt = uniqid('', true);
    if (!isset($lti->typeid)) {
        $lti->typeid = null;
    }

    lti_glaaster_force_type_config_settings($lti, lti_glaaster_get_type_config_by_instance($lti));

    if (empty($lti->typeid) && isset($lti->urlmatchedtypeid)) {
        $lti->typeid = $lti->urlmatchedtypeid;
    }

    $lti->id = $DB->insert_record('glaaster', $lti);

    $services = lti_glaaster_get_services();
    foreach ($services as $service) {
        $service->instance_added($lti);
    }

    $completiontimeexpected = !empty($lti->completionexpected) ? $lti->completionexpected : null;
    api::update_completion_date_event($lti->coursemodule, 'glaaster', $lti->id, $completiontimeexpected);

    return $lti->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $lti An object from the form in mod.html
 * @param object|null $mform The form instance (unused, for API compatibility)
 * @return boolean Success/Fail
 **/
function glaaster_update_instance($lti, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

    lti_glaaster_load_tool_if_cartridge($lti);

    $lti->timemodified = time();
    $lti->id = $lti->instance;

    if (!isset($lti->showtitlelaunch)) {
        $lti->showtitlelaunch = 0;
    }

    if (!isset($lti->showdescriptionlaunch)) {
        $lti->showdescriptionlaunch = 0;
    }

    lti_glaaster_force_type_config_settings($lti, lti_glaaster_get_type_config_by_instance($lti));

    if ($lti->typeid == 0 && isset($lti->urlmatchedtypeid)) {
        $lti->typeid = $lti->urlmatchedtypeid;
    }

    $services = lti_glaaster_get_services();
    foreach ($services as $service) {
        $service->instance_updated($lti);
    }

    $completiontimeexpected = !empty($lti->completionexpected) ? $lti->completionexpected : null;
    api::update_completion_date_event($lti->coursemodule, 'glaaster', $lti->id, $completiontimeexpected);

    return $DB->update_record('glaaster', $lti);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function glaaster_delete_instance($id) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

    if (!$basiclti = $DB->get_record("glaaster", ["id" => $id])) {
        return false;
    }

    $result = true;

    $ltitype = $DB->get_record('glaaster_types', ['id' => $basiclti->typeid]);
    if ($ltitype) {
        $DB->delete_records(
            'glaaster_tool_settings',
            ['toolproxyid' => $ltitype->toolproxyid, 'course' => $basiclti->course, 'coursemoduleid' => $id]
        );
    }

    $cm = get_coursemodule_from_instance('glaaster', $id);
    api::update_completion_date_event($cm->id, 'glaaster', $id, null);

    // We must delete the module record after we delete the grade item.
    if ($DB->delete_records("glaaster", ["id" => $basiclti->id])) {
        $services = lti_glaaster_get_services();
        foreach ($services as $service) {
            $service->instance_deleted($id);
        }
        return true;
    }
    return false;
}

/**
 * Return the preconfigured tools which are configured for inclusion in the activity picker.
 *
 * @param content_item $defaultmodulecontentitem reference to the content item for the LTI module.
 * @param stdClass $user the user object, to use for cap checks if desired.
 * @param stdClass $course the course to scope items to.
 * @return array the array of content items.
 */
function glaaster_get_course_content_items(
    content_item $defaultmodulecontentitem,
    stdClass $user,
    stdClass $course
) {
    global $CFG, $OUTPUT;
    require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

    $types = [];

    // Use of a tool type, whether site or course level, is controlled by the following cap.
    if (!has_capability('mod/glaaster:addpreconfiguredinstance', course::instance($course->id), $user)) {
        return $types;
    }
    $preconfiguredtools =
        glaaster_get_configured_types($course->id, $defaultmodulecontentitem->get_link()->param('sr'));

    foreach ($preconfiguredtools as $preconfiguredtool) {
        // Append the help link to the help text.
        if (isset($preconfiguredtool->help)) {
            if (isset($preconfiguredtool->helplink)) {
                $linktext = get_string('morehelp');
                $preconfiguredtool->help .= html_writer::tag(
                    'div',
                    $OUTPUT->doc_link($preconfiguredtool->helplink, $linktext, true),
                    ['class' => 'helpdoclink']
                );
            }
        } else {
            $preconfiguredtool->help = '';
        }

        // Preconfigured tools take their own id + 1. This logic exists because, previously, the entry permitting manual instance
        // creation (the $defaultmodulecontentitem, or 'External tool' item) was included and had the id 1. This logic prevented id
        // collisions.
        $types[] = new content_item(
            $preconfiguredtool->id + 1,
            $preconfiguredtool->name,
            new string_title($preconfiguredtool->title),
            $preconfiguredtool->link,
            $preconfiguredtool->icon,
            $preconfiguredtool->help,
            $defaultmodulecontentitem->get_archetype(),
            $defaultmodulecontentitem->get_component_name(),
            $defaultmodulecontentitem->get_purpose()
        );
    }
    return $types;
}

/**
 * Return all content items which can be added to any course.
 *
 * @param content_item $defaultmodulecontentitem
 * @return array the array of content items.
 */
function glaaster_get_all_content_items(content_item $defaultmodulecontentitem): array {
    global $OUTPUT, $CFG;
    require_once($CFG->dirroot . '/mod/glaaster/locallib.php'); // For access to constants.

    $types = [];

    foreach (lti_glaaster_get_lti_types() as $ltitype) {
        if ($ltitype->coursevisible != LTI_GLAASTER_COURSEVISIBLE_ACTIVITYCHOOSER) {
            continue;
        }
        $type = new stdClass();
        $type->id = $ltitype->id;
        $type->modclass = MOD_CLASS_ACTIVITY;
        $type->name = 'lti_type_' . $ltitype->id;
        // Clean the name. We don't want tags here.
        $type->title = clean_param($ltitype->name, PARAM_NOTAGS);
        $trimmeddescription = trim($ltitype->description ?? '');
        $type->help = '';
        if ($trimmeddescription != '') {
            // Clean the description. We don't want tags here.
            $type->help = clean_param($trimmeddescription, PARAM_NOTAGS);
            $type->helplink = get_string('modulename_shortcut_link', 'glaaster');
        }
        if (empty($ltitype->icon)) {
            $type->icon = $OUTPUT->pix_icon('monologo', '', 'glaaster', ['class' => 'icon']);
        } else {
            $type->icon =
                html_writer::empty_tag('img', ['src' => $ltitype->icon, 'alt' => $ltitype->name, 'class' => 'icon']);
        }
        $type->link =
            new moodle_url('/course/modedit.php', ['add' => 'glaaster', 'return' => 0, 'typeid' => $ltitype->id]);

        // Preconfigured tools take their own id + 1. This logic exists because, previously, the entry permitting manual instance
        // creation (the $defaultmodulecontentitem, or 'External tool' item) was included and had the id 1. This logic prevented id
        // collisions.
        $types[] = new content_item(
            $type->id + 1,
            $type->name,
            new string_title($type->title),
            $type->link,
            $type->icon,
            $type->help,
            $defaultmodulecontentitem->get_archetype(),
            $defaultmodulecontentitem->get_component_name(),
            $defaultmodulecontentitem->get_purpose()
        );
    }

    return $types;
}

/**
 * Given a coursemodule object, this function returns the extra
 * information needed to print this activity in various places.
 * For this module we just need to support external urls as
 * activity icons
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info info
 */
function glaaster_get_coursemodule_info($coursemodule) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

    if (
        !$lti = $DB->get_record(
            'glaaster',
            ['id' => $coursemodule->instance],
            'icon, secureicon, intro, introformat, name, typeid, toolurl, launchcontainer'
        )
    ) {
        return null;
    }

    $info = new cached_cm_info();

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('glaaster', $lti, $coursemodule->id, false);
    }

    if (!empty($lti->typeid)) {
        $toolconfig = lti_glaaster_get_type_config($lti->typeid);
    } else if ($tool = lti_glaaster_get_tool_by_url_match($lti->toolurl)) {
        $toolconfig = lti_glaaster_get_type_config($tool->id);
    } else {
        $toolconfig = [];
    }

    // We want to use the right icon based on whether the
    // current page is being requested over http or https.
    if (
        lti_glaaster_request_is_using_ssl() &&
        (!empty($lti->secureicon) || (isset($toolconfig['secureicon']) && !empty($toolconfig['secureicon'])))
    ) {
        if (!empty($lti->secureicon)) {
            $info->iconurl = new moodle_url($lti->secureicon);
        } else {
            $info->iconurl = new moodle_url($toolconfig['secureicon']);
        }
    } else if (!empty($lti->icon)) {
        $info->iconurl = new moodle_url($lti->icon);
    } else if (isset($toolconfig['icon']) && !empty($toolconfig['icon'])) {
        $info->iconurl = new moodle_url($toolconfig['icon']);
    }

    // Does the link open in a new window?
    $launchcontainer = lti_glaaster_get_launch_container($lti, $toolconfig);
    if ($launchcontainer == LTI_GLAASTER_LAUNCH_CONTAINER_WINDOW) {
        $launchurl = new moodle_url('/mod/glaaster/launch.php', ['id' => $coursemodule->id]);
        $info->onclick =
            "window.open('" . $launchurl->out(false) . "', 'lti-" . $coursemodule->id . "'); return false;";
    }

    $info->name = $lti->name;

    return $info;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param object $course The course object.
 * @param object $user The user object.
 * @param object $mod The course module object.
 * @param object $basiclti The basiclti instance object.
 * @return null
 **/
function glaaster_user_outline($course, $user, $mod, $basiclti) {
    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param object $course The course object.
 * @param object $user The user object.
 * @param object $mod The course module object.
 * @param object $basiclti The basiclti instance object.
 * @return boolean
 **/
function glaaster_user_complete($course, $user, $mod, $basiclti) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in basiclti activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @param object $course The course object.
 * @param bool $isteacher Whether the current user is a teacher.
 * @param int $timestart Timestamp to check activity from.
 * @return boolean
 * @uses $CFG
 */
function glaaster_print_recent_activity($course, $isteacher, $timestart) {
    return false;  // True if anything was printed, otherwise false.
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @uses $CFG
 */
function glaaster_cron() {
    return true;
}

/**
 * Execute post-install custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function glaaster_install() {
    return true;
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function glaaster_uninstall() {
    return true;
}

/**
 * Returns available Basic LTI types
 *
 * @return array of basicLTI types
 */
function lti_glaaster_get_lti_types() {
    global $DB;

    return $DB->get_records('glaaster_types', null, 'state DESC, timemodified DESC');
}

/**
 * Returns available Basic LTI types that match the given
 * tool proxy id
 *
 * @param int $toolproxyid Tool proxy id
 * @return array of basicLTI types
 */
function lti_glaaster_get_lti_types_from_proxy_id($toolproxyid) {
    global $DB;

    return $DB->get_records('glaaster_types', ['toolproxyid' => $toolproxyid], 'state DESC, timemodified DESC');
}

/**
 * Log post actions
 *
 * @return array
 */
function glaaster_get_post_actions() {
    return [];
}

/**
 * Log view actions
 *
 * @return array
 */
function glaaster_get_view_actions() {
    return ['view all', 'view'];
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param stdClass $lti lti object
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @since Moodle 3.0
 */
function glaaster_view($lti, $course, $cm, $context) {
    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $lti->id,
    ];

    $event = course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('glaaster', $lti);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param cm_info $cm course module data
 * @param int $from the time to check updates from
 * @param array $filter if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function glaaster_check_updates_since(cm_info $cm, $from, $filter = []) {
    global $DB;

    $updates = course_check_module_updates_since($cm, $from, [], $filter);

    return $updates;
}

/**
 * Get icon mapping for font-awesome.
 */
function glaaster_get_fontawesome_icon_map() {
    return [
        'mod_glaaster:warning' => 'fa-exclamation text-warning',
    ];
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return action_interface|null
 */
function mod_glaaster_core_calendar_provide_event_action(
    calendar_event $event,
    action_factory $factory,
    int $userid = 0
) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['glaaster'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $completion = new completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new moodle_url('/mod/glaaster/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Extend the course navigation with an "LTI External tools" link which redirects to a list of all tools available for
 * course use.
 *
 * @param settings_navigation $navigation The settings navigation object
 * @param stdClass $course The course
 * @param stdclass $context Course context
 * @return void
 */
function glaaster_extend_navigation_course($navigation, $course, $context): void {
    if (has_capability('mod/glaaster:addpreconfiguredinstance', $context)) {
        $url = new moodle_url('/mod/glaaster/coursetools.php', ['id' => $course->id]);
        $settingsnode = navigation_node::create(
            get_string('courseexternaltools', 'mod_glaaster'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'coursetools_glaaster',
            new pix_icon('i/settings', '')
        );
        $navigation->add_node($settingsnode);
    }
}

/**
 *
 *
 * @return string
 */
function mod_glaaster_before_standard_top_of_body_html() {
    global $CFG;
    require_once($CFG->dirroot . '/mod/glaaster/locallib.php');
    // Inject the instance ID into the page.
    return mod_glaaster_inject_instance_js();
}

/**
 * Legacy callback for Moodle versions before 4.4.
 *
 * This function is used for compatibility with older Moodle versions.
 *
 * @return void
 */
function mod_glaaster_before_footer() {
    global $CFG;
    require_once($CFG->dirroot . '/mod/glaaster/locallib.php');
    // Load the appropriate JS file based on Moodle version.
    mod_glaaster_load_js();
}
