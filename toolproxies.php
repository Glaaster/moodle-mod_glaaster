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
 * This file contains all necessary code to launch a Tool Proxy registration
 *
 * @package mod_glaaster
 * @copyright  2014 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

// No guest autologin.
require_login(0, false);
admin_externalpage_setup('glaastertoolproxies');

$configuredtoolproxieshtml = '';
$pendingtoolproxieshtml = '';
$acceptedtoolproxieshtml = '';
$rejectedtoolproxieshtml = '';

$configured = get_string('configured', 'glaaster');
$pending = get_string('pending', 'glaaster');
$accepted = get_string('accepted', 'glaaster');
$rejected = get_string('rejected', 'glaaster');

$toolproxies = $DB->get_records('glaaster_tool_proxies');

$configuredtoolproxies = glaaster_filter_tool_proxy_types($toolproxies, MOD_GLAASTER_TOOL_PROXY_STATE_CONFIGURED);
$configuredtoolproxieshtml = glaaster_get_tool_proxy_table($configuredtoolproxies, 'tp_configured');

$pendingtoolproxies = glaaster_filter_tool_proxy_types($toolproxies, MOD_GLAASTER_TOOL_PROXY_STATE_PENDING);
$pendingtoolproxieshtml = glaaster_get_tool_proxy_table($pendingtoolproxies, 'tp_pending');

$acceptedtoolproxies = glaaster_filter_tool_proxy_types($toolproxies, MOD_GLAASTER_TOOL_PROXY_STATE_ACCEPTED);
$acceptedtoolproxieshtml = glaaster_get_tool_proxy_table($acceptedtoolproxies, 'tp_accepted');

$rejectedtoolproxies = glaaster_filter_tool_proxy_types($toolproxies, MOD_GLAASTER_TOOL_PROXY_STATE_REJECTED);
$rejectedtoolproxieshtml = glaaster_get_tool_proxy_table($rejectedtoolproxies, 'tp_rejected');

$tab = optional_param('tab', '', PARAM_ALPHAEXT);

$registertypeurl = new moodle_url(
    '/mod/glaaster/registersettings.php',
    ['action' => 'add', 'sesskey' => sesskey(), 'tab' => 'tool_proxy']
);

$tabs = [
    [
        'id' => 'tp_configured',
        'label' => $configured,
        'selected' => ($tab === '' || $tab === 'tp_configured'),
        'content' => html_writer::link(
            $registertypeurl,
            get_string('registertype', 'glaaster'),
            ['class' => 'd-block mb-2']
        ) . $configuredtoolproxieshtml,
    ],
    [
        'id' => 'tp_pending',
        'label' => $pending,
        'selected' => ($tab === 'tp_pending'),
        'content' => $pendingtoolproxieshtml,
    ],
    [
        'id' => 'tp_accepted',
        'label' => $accepted,
        'selected' => ($tab === 'tp_accepted'),
        'content' => $acceptedtoolproxieshtml,
    ],
    [
        'id' => 'tp_rejected',
        'label' => $rejected,
        'selected' => ($tab === 'tp_rejected'),
        'content' => $rejectedtoolproxieshtml,
    ],
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_tool_proxies', 'glaaster'), 2);
echo $OUTPUT->heading(new lang_string('toolproxy', 'glaaster') .
    $OUTPUT->help_icon('toolproxy', 'glaaster'), 3);

echo $OUTPUT->box_start('generalbox');

echo $OUTPUT->render_from_template('mod_glaaster/tool_proxies_tabs', ['tabs' => $tabs]);

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
