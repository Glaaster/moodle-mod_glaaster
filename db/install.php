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
 * Post-installation and migration code.
 *
 * @package    mod_glaaster
 * @copyright  2019 Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Stub for database installation.
 */
function xmldb_glaaster_install() {
    global $CFG, $OUTPUT;

    // Create the private key.
    require_once($CFG->dirroot . '/mod/glaaster/upgradelib.php');

    $warning = mod_glaaster_verify_private_key();
    if (!empty($warning)) {
        echo $OUTPUT->notification($warning, 'notifyproblem');
    }

    mod_glaaster_create_api_role();

    set_config('needs_setup', 1, 'mod_glaaster');
    set_config('tooldomain', 'lti.glaaster.com', 'mod_glaaster');
}

/**
 * Create the "Glaaster API" role with the required capabilities.
 *
 * Idempotent: does nothing if the role already exists (shortname = glaasterapi).
 */
function mod_glaaster_create_api_role() {
    global $DB;
    // Only create if not already present.
    if ($role = $DB->get_record('role', ['shortname' => 'glaasterapi'])) {
        mod_glaaster_assign_api_role_capabilities($role->id);
        return;
    }

    $roleid = create_role('Glaaster API', 'glaasterapi', '', '');
    if (!$roleid) {
        return;
    }

    // Allow assignment at system context only.
    set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

    mod_glaaster_assign_api_role_capabilities($roleid);
}

/**
 * Assign the required capabilities to the Glaaster API role.
 *
 * @param int $roleid The role ID.
 */
function mod_glaaster_assign_api_role_capabilities($roleid) {
    $context = context_system::instance();

    // Required capabilities for Glaaster Document Service access.
    $capabilities = [
        'moodle/course:view', // View courses without enrolment.
        'webservice/rest:use', // Use REST protocol.
        'mod/resource:view', // View resource activities.
        'moodle/course:update', // Required by core_course_get_contents webservice.
    ];

    foreach ($capabilities as $capability) {
        if (get_capability_info($capability)) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
    }
}
