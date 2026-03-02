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
 * This file contains all the backup steps that will be used
 * by the backup_lti_activity_task
 *
 * @package mod_glaaster
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete assignment structure for backup, with file and id annotations
 */
class backup_lti_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines structure of activity backup
     *
     * @return backup_nested_element
     * @throws backup_step_exception
     * @throws base_element_struct_exception
     * @throws base_step_exception
     * @throws dml_exception
     */
    protected function define_structure() {
        global $DB;
        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');
        // Define each element separated.
        $lti = new backup_nested_element(
            'glaaster',
            ['id'],
            ['name', 'intro', 'introformat', 'timecreated', 'timemodified', 'typeid', 'toolurl', 'securetoolurl',
                'preferheight', 'launchcontainer', 'instructorchoicesendname', 'instructorchoicesendemailaddr',
                'instructorchoiceallowroster', 'instructorchoiceallowsetting',
                'instructorcustomparameters', 'debuglaunch', 'showtitlelaunch', 'showdescriptionlaunch', 'icon',
                'secureicon', new encrypted_final_element('resourcekey'), new encrypted_final_element('password'),
            ]
        );
        $ltitype = new backup_nested_element(
            'ltitype',
            ['id'],
            ['name', 'baseurl', 'tooldomain', 'state', 'course', 'coursevisible', 'ltiversion', 'clientid',
                'toolproxyid', 'enabledcapability', 'parameter', 'icon', 'secureicon', 'createdby', 'timecreated',
            'timemodified',
            'description']
        );
        $ltitypesconfigs = new backup_nested_element('ltitypesconfigs');
        $ltitypesconfig = new backup_nested_element('ltitypesconfig', ['id'], ['name', 'value']);
        $lticonfigenc =
            new backup_nested_element('ltitypesconfigencrypted', ['id'], ['name', new encrypted_final_element('value')]);
        $ltitoolproxy = new backup_nested_element('ltitoolproxy', ['id']);
        $ltitoolsettings = new backup_nested_element('ltitoolsettings');
        $ltitoolsetting =
            new backup_nested_element('ltitoolsetting', ['id'], ['settings', 'timecreated', 'timemodified']);
        $lticoursevisible =
            new backup_nested_element('lticoursevisible', ['id'], ['typeid', 'courseid', 'coursevisible']);
        // Build the tree.
        $lti->add_child($ltitype);
        $ltitype->add_child($ltitypesconfigs);
        $ltitypesconfigs->add_child($ltitypesconfig);
        $ltitypesconfigs->add_child($lticonfigenc);
        $ltitype->add_child($ltitoolproxy);
        $ltitoolproxy->add_child($ltitoolsettings);
        $ltitoolsettings->add_child($ltitoolsetting);
        $lti->add_child($lticoursevisible);

        // Define sources.
        $ltirecord = $DB->get_record('glaaster', ['id' => $this->task->get_activityid()]);
        $lti->set_source_array([$ltirecord]);
        $ltitypedata = $this->retrieve_lti_type($ltirecord);
        $ltitype->set_source_array($ltitypedata ? [$ltitypedata] : []);
        if (isset($ltitypedata->baseurl)) {
            // Add type config values only if the type was backed up. Encrypt password and resourcekey.
            $params = [backup_helper::is_sqlparam($ltitypedata->id),
                backup_helper::is_sqlparam('password'),
                backup_helper::is_sqlparam('resourcekey')];
            $ltitypesconfig->set_source_sql("SELECT id, name, value FROM {glaaster_types_config}
                WHERE typeid = ? AND name <> ? AND name <> ?", $params);
            $lticonfigenc->set_source_sql("SELECT id, name, value FROM {glaaster_types_config}
                WHERE typeid = ? AND (name = ? OR name = ?)", $params);
        }
        if (!empty($ltitypedata->toolproxyid)) {
            // If this is LTI 2 tool add settings for the current activity.
            $ltitoolproxy->set_source_array([['id' => $ltitypedata->toolproxyid]]);
            $ltitoolsetting->set_source_sql(
                "SELECT * FROM {glaaster_tool_settings}
                WHERE toolproxyid = ? AND course = ? AND coursemoduleid = ?",
                [backup_helper::is_sqlparam($ltitypedata->toolproxyid), backup::VAR_COURSEID, backup::VAR_MODID]
            );
        } else {
            $ltitoolproxy->set_source_array([]);
        }
        $lticoursevisibledata = $this->retrieve_lti_coursevisible($ltirecord);
        $lticoursevisible->set_source_array($lticoursevisibledata ? [$lticoursevisibledata] : []);

        // Define id annotations.
        $ltitype->annotate_ids('user', 'createdby');
        $ltitype->annotate_ids('course', 'course');

        // Define file annotations.
        $lti->annotate_files('mod_glaaster', 'intro', null); // This file areas haven't itemid.

        // Add support for subplugin structures.
        $this->add_subplugin_structure('ltiglaastersource', $lti, true);
        $this->add_subplugin_structure('ltiglaasterservice', $lti, true);

        // Return the root element (lti), wrapped into standard activity structure.
        return $this->prepare_activity_structure($lti);
    }

    /**
     * Retrieves a record from the {lti_type} table associated with the current activity
     *
     * Information about site tools is not returned because it is insecure to back it up,
     * only the fields necessary for same-site tool matching are left in the record
     *
     * @param stdClass $ltirecord record from {glaaster} table
     * @return stdClass|null
     * @throws dml_exception
     */
    protected function retrieve_lti_type($ltirecord) {
        global $DB;
        if (!$ltirecord->typeid) {
            return null;
        }

        $record = $DB->get_record('glaaster_types', ['id' => $ltirecord->typeid]);
        if ($record && $record->course == SITEID) {
            // Site LTI types or registrations are not backed up except for their name (which is visible).
            // Predefined course types can be backed up.
            $allowedkeys = ['id', 'course', 'name', 'toolproxyid'];
            foreach (array_keys($record) as $key) {
                if (!in_array($key, $allowedkeys)) {
                    $record->$key = null;
                }
            }
        }

        return $record;
    }

    /**
     * Retrieves a record from {lti_coursevisible} table associated with the current type
     *
     * @param stdClass $ltirecord record from {glaaster} table
     * @return mixed
     */
    protected function retrieve_lti_coursevisible(stdClass $ltirecord): mixed {
        global $DB;
        if (!$ltirecord->typeid) {
            return null;
        }
        return $DB->get_record(
            'glaaster_coursevisible',
            ['typeid' => $ltirecord->typeid, 'courseid' => $ltirecord->course]
        );
    }
}
