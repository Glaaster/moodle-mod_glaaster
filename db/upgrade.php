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
 * This file keeps track of upgrades to the lti module
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
 * xmldb_glaaster_upgrade is the function that upgrades
 * the lti module database when is needed
 *
 * This function is automaticly called when version number in
 * version.php changes.
 *
 * @param int $oldversion New old version number.
 *
 * @return boolean
 */
function xmldb_glaaster_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2023070501) {
        // Define table glaaster_types_categories to be created.
        $table = new xmldb_table('glaaster_types_categories');

        // Adding fields to table glaaster_types_categories.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('typeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table glaaster_types_categories.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('typeid', XMLDB_KEY_FOREIGN, ['typeid'], 'glaaster_types', ['id']);
        $table->add_key('categoryid', XMLDB_KEY_FOREIGN, ['categoryid'], 'course_categories', ['id']);

        // Conditionally launch create table for glaaster_types_categories.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Lti savepoint reached.
        upgrade_mod_savepoint(true, 2023070501, 'glaaster');
    }

    if ($oldversion < 2023081101) {
        // Define table to override coursevisible for a tool on course level.
        $table = new xmldb_table('glaaster_coursevisible');

        // Adding fields to table glaaster_coursevisible.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('typeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'typeid');
        $table->add_field('coursevisible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'courseid');

        // Add key.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Define index courseid (not unique) to be added to glaaster_coursevisible.
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        // Define index typeid (not unique) to be added to glaaster_coursevisible.
        $table->add_index('typeid', XMLDB_INDEX_NOTUNIQUE, ['typeid']);

        // Conditionally launch create table for overriding coursevisible.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Lti savepoint reached.
        upgrade_mod_savepoint(true, 2023081101, 'glaaster');
    }

    // Automatically generated Moodle v4.3.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.4.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.5.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2026022500) {
        // Drop glaaster_submission table (grade data no longer used).
        $table = new xmldb_table('glaaster_submission');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Drop glaaster.instructorchoiceacceptgrades column.
        $table = new xmldb_table('glaaster');
        $field = new xmldb_field('instructorchoiceacceptgrades');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Drop glaaster.grade column.
        $field = new xmldb_field('grade');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026022500, 'glaaster');
    }

    if ($oldversion < 2026022502) {
        // Remove DB records for deleted LTI subplugins (basicoutcomes and gradebookservices).
        // These subplugins were removed in the grade removal refactor.
        $subplugins = ['ltiglaasterservice_basicoutcomes', 'ltiglaasterservice_gradebookservices'];
        foreach ($subplugins as $pluginname) {
            // Drop the gradebookservices table if it still exists.
            if ($pluginname === 'ltiglaasterservice_gradebookservices') {
                $table = new xmldb_table('ltiglaasterservice_gradebookservices');
                if ($dbman->table_exists($table)) {
                    $dbman->drop_table($table);
                }
            }
            // Remove plugin version and config records so Moodle no longer reports it as missing.
            unset_config('version', $pluginname);
            $DB->delete_records('config_plugins', ['plugin' => $pluginname]);
        }

        upgrade_mod_savepoint(true, 2026022502, 'glaaster');
    }

    if ($oldversion < 2026022508) {
        // Create the "Glaaster API" role with required capabilities.
        // Mirrors the manual setup step: Administration > Users > Permissions > Define roles.
        require_once($CFG->dirroot . '/mod/glaaster/db/install.php');
        mod_glaaster_create_api_role();

        upgrade_mod_savepoint(true, 2026022508, 'glaaster');
    }

    if ($oldversion < 2026022523) {
        // Adopt any manually-created "Glaaster API" external service into plugin management.
        // Existing tokens and authorised users are preserved (record is updated in-place).
        // On fresh installs, Moodle creates the service automatically via $services in services.php.
        $existing = $DB->get_record('external_services', ['name' => 'Glaaster API']);
        if ($existing && $existing->component !== 'mod_glaaster') {
            $DB->update_record('external_services', (object)[
                'id'              => $existing->id,
                'shortname'       => 'glaaster_api',
                'component'       => 'mod_glaaster',
                'enabled'         => 1,
                'restrictedusers' => 1,
                'downloadfiles'   => 1,
            ]);
        }

        upgrade_mod_savepoint(true, 2026022523, 'glaaster');
    }

    return true;
}
