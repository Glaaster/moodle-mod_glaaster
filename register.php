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

$id = required_param('id', PARAM_INT);
$tab = optional_param('tab', '', PARAM_ALPHAEXT);

require_login(0, false);

$redirect = new moodle_url('/mod/glaaster/toolproxies.php', ['tab' => $tab]);
$redirect = $redirect->out();

require_sesskey();

$toolproxies = $DB->get_records('glaaster_tool_proxies');

$duplicate = false;
foreach ($toolproxies as $key => $toolproxy) {
    if (
        ($toolproxy->state == MOD_GLAASTER_TOOL_PROXY_STATE_PENDING) ||
        ($toolproxy->state == MOD_GLAASTER_TOOL_PROXY_STATE_ACCEPTED)
    ) {
        if ($toolproxy->regurl == $toolproxies[$id]->regurl) {
            $duplicate = true;
            break;
        }
    }
}

$redirect = new moodle_url('/mod/glaaster/toolproxies.php');
if ($duplicate) {
    redirect($redirect, get_string('duplicateregurl', 'glaaster'));
}

$profileservice = glaaster_get_service_by_name('profile');
if (empty($profileservice)) {
    redirect($redirect, get_string('noprofileservice', 'glaaster'));
}

$PAGE->set_heading(get_string('toolproxyregistration', 'glaaster'));
$PAGE->set_title(get_string('toolproxyregistration', 'glaaster'));

admin_externalpage_setup('glaastertoolproxies');

// Print the page header.
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('toolproxyregistration', 'glaaster'));

echo $OUTPUT->box_start('generalbox');

$registration = new moodle_url(
    '/mod/glaaster/registration.php',
    ['id' => $id, 'sesskey' => sesskey()]
);

echo $OUTPUT->render_from_template('mod_glaaster/register_frame', [
    'src' => $registration->out(false),
    'warningtext' => get_string('register_warning', 'glaaster'),
]);

$PAGE->requires->js_call_amd('mod_glaaster/register', 'init');

// Finish the page.
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
