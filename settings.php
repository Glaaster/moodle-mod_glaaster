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
 * This file defines the global lti administration form
 *
 * @package mod_glaaster
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @author     Chris Scribner
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_glaaster\plugininfo\ltiglaastersource;

defined('MOODLE_INTERNAL') || die;

// Hide the default $settings page injected by Moodle core - we manage our own structure.
$settings->hidden = true;

$proxieslink = new admin_externalpage(
    'glaastertoolproxies',
    get_string('manage_tool_proxies', 'glaaster'),
    new moodle_url('/mod/glaaster/toolproxies.php')
);
$proxieslink->hidden = true;
$ADMIN->add('modsettings', $proxieslink);

// Single entry: "Glaaster" → toolconfigure.php (tool domain + LTI tool management).
$ADMIN->add('modsettings', new admin_externalpage(
    'glaastertoolconfigure',
    new lang_string('pluginname', 'mod_glaaster'),
    new moodle_url('/mod/glaaster/toolconfigure.php'),
    'moodle/site:config',
    $module->is_enabled() === false
));

foreach (core_plugin_manager::instance()->get_plugins_of_type('ltiglaastersource') as $plugin) {
    $plugin->load_settings($ADMIN, 'modsettings', $hassiteconfig);
}

// Tell core we already added the settings structure.
$settings = null;
