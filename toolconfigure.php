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
 * This page allows the configuration of external tools that meet the LTI specification.
 *
 * @package    mod_glaaster
 * @copyright  2015 Ryan Wyllie <ryan@moodle.com>
 * @author     Ryan Wyllie
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_glaaster\output\tool_configure_page;

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/glaaster/lib.php');
require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

$cartridgeurl = optional_param('cartridgeurl', '', PARAM_URL);

// No guest autologin.
require_login(0, false);
admin_externalpage_setup('glaastertoolconfigure');

if ($cartridgeurl) {
    $type = new stdClass();
    $data = new stdClass();
    $type->state = LTI_GLAASTER_TOOL_STATE_CONFIGURED;
    $data->lti_coursevisible = 1;
    lti_glaaster_load_type_from_cartridge($cartridgeurl, $data);
    lti_glaaster_add_type($type, $data);
}

$pageurl = new moodle_url('/mod/glaaster/toolconfigure.php');
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('toolregistration', 'mod_glaaster'));
$PAGE->requires->string_for_js('success', 'moodle');
$PAGE->requires->string_for_js('error', 'moodle');
$PAGE->requires->string_for_js('successfullycreatedtooltype', 'mod_glaaster');
$PAGE->requires->string_for_js('failedtocreatetooltype', 'mod_glaaster');
$output = $PAGE->get_renderer('mod_glaaster');

// Glaaster API user setup.
$apiuserid = (int) get_config('mod_glaaster', 'apiuserid');
if (optional_param('saveapiuser', 0, PARAM_BOOL)) {
    require_sesskey();
    $newuserid = required_param('apiuserid', PARAM_INT);
    $user = \core_user::get_user($newuserid);
    if (!$user || $user->deleted || $user->suspended) {
        redirect(
            $PAGE->url,
            get_string('apiuser_notfound', 'mod_glaaster'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    // Assign glaasterapi role at system context.
    $apirole = $DB->get_record('role', ['shortname' => 'glaasterapi']);
    $roleid = $apirole ? $apirole->id : null;
    if ($roleid) {
        role_assign($roleid, $user->id, context_system::instance()->id);
    }
    // Add user to glaaster_api service.
    $service = $DB->get_record('external_services', ['shortname' => 'glaaster_api']);
    if ($service) {
        if (
            !$DB->record_exists('external_services_users', [
                'externalserviceid' => $service->id,
                'userid' => $user->id,
            ])
        ) {
            $DB->insert_record('external_services_users', [
                'externalserviceid' => $service->id,
                'userid' => $user->id,
                'timecreated' => time(),
            ]);
        }
    }
    set_config('apiuserid', $user->id, 'mod_glaaster');
    $apiuserid = $user->id;
    redirect(
        $PAGE->url,
        get_string('apiuser_saved', 'mod_glaaster'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
if (optional_param('generateapitoken', 0, PARAM_BOOL)) {
    require_sesskey();
    $apiuserid = (int) get_config('mod_glaaster', 'apiuserid');
    if (!$apiuserid) {
        redirect(
            $PAGE->url,
            get_string('apitoken_nouser', 'mod_glaaster'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $service = $DB->get_record('external_services', ['shortname' => 'glaaster_api']);
    if ($service) {
        $existing = $DB->get_record_select(
            'external_tokens',
            'userid = ? AND externalserviceid = ? AND (validuntil = 0 OR validuntil > ?)',
            [$apiuserid, $service->id, time()]
        );
        if (!$existing) {
            $tokenobj = new stdClass();
            $tokenobj->token = md5(uniqid(rand(), 1));
            $tokenobj->userid = $apiuserid;
            $tokenobj->tokentype = EXTERNAL_TOKEN_PERMANENT;
            $tokenobj->contextid = context_system::instance()->id;
            $tokenobj->creatorid = $USER->id;
            $tokenobj->timecreated = time();
            $tokenobj->externalserviceid = $service->id;
            $tokenobj->validuntil = 0;
            $tokenobj->iprestriction = '';
            $DB->insert_record('external_tokens', $tokenobj);
        }
    }
    redirect(
        $PAGE->url,
        get_string('apitoken_created', 'mod_glaaster'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
if (optional_param('savetooldomain', 0, PARAM_BOOL)) {
    require_sesskey();
    $newdomain = required_param('tooldomain', PARAM_HOST);
    set_config('tooldomain', $newdomain, 'mod_glaaster');
    $tooldomain = $newdomain;
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Setup wizard: detect first-install flag and check whether config is now complete.
$needssetup = (bool) get_config('mod_glaaster', 'needs_setup');
$tooldomain = get_config('mod_glaaster', 'tooldomain');
if (!$tooldomain) {
    $tooldomain = 'lti.glaaster.com';
    set_config('tooldomain', $tooldomain, 'mod_glaaster');
}
$apiuserid = (int) get_config('mod_glaaster', 'apiuserid');
$apiuser = $apiuserid ? \core_user::get_user($apiuserid) : null;
$service = $DB->get_record('external_services', ['shortname' => 'glaaster_api']);
$apitoken = null;
if ($apiuserid && $service) {
    $apitoken = $DB->get_record_select(
        'external_tokens',
        'userid = ? AND externalserviceid = ? AND (validuntil = 0 OR validuntil > ?)',
        [$apiuserid, $service->id, time()]
    );
}
if ($needssetup && $tooldomain && $apiuser && $apitoken) {
    unset_config('needs_setup', 'mod_glaaster');
    $needssetup = false;
}

echo $output->header();

if ($needssetup) {
    echo $OUTPUT->notification(
        get_string('setup_welcome', 'mod_glaaster'),
        \core\output\notification::NOTIFY_INFO
    );
}

$domainform = html_writer::start_div('card border-0 shadow-sm mb-4');
$domainform .= html_writer::start_div('card-header d-flex align-items-center gap-2 bg-white border-bottom');
$domainform .= html_writer::tag('span', '', [
    'class' => 'rounded-circle bg-primary d-inline-block',
    'style' => 'width:10px;height:10px;flex-shrink:0',
]);
$domainform .= html_writer::tag(
    'h5',
    get_string('tooldomain', 'mod_glaaster'),
    ['class' => 'mb-0 fw-semibold text-dark']
);
$domainform .= html_writer::end_div();
$domainform .= html_writer::start_div('card-body');
$domainform .= html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
$domainform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$domainform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savetooldomain', 'value' => '1']);
$domainform .= html_writer::start_div('mb-3');
$domainform .= html_writer::tag(
    'label',
    get_string('tooldomain', 'mod_glaaster'),
    ['for' => 'tooldomain', 'class' => 'form-label fw-medium text-secondary small text-uppercase letter-spacing-1']
);
$domainform .= html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'tooldomain',
    'id' => 'tooldomain',
    'value' => $tooldomain,
    'class' => 'form-control form-control-lg',
    'placeholder' => 'example.glaaster.com',
]);
$domainform .= html_writer::tag(
    'div',
    get_string('tooldomain_desc', 'mod_glaaster'),
    ['class' => 'form-text text-muted mt-1']
);
$domainform .= html_writer::end_div();
$domainform .= html_writer::tag('button', get_string('savechanges'), [
    'type' => 'submit',
    'class' => 'btn btn-primary px-4',
]);
$domainform .= html_writer::end_tag('form');
$domainform .= html_writer::end_div();
$domainform .= html_writer::end_div();

echo $domainform;

// Glaaster API Setup card.
$apirole = $DB->get_record('role', ['shortname' => 'glaasterapi']);
$roleid = $apirole ? $apirole->id : null;
$service = $DB->get_record('external_services', ['shortname' => 'glaaster_api']);
$apiuser = $apiuserid ? \core_user::get_user($apiuserid) : null;

$apitoken = null;
if ($apiuserid && $service) {
    $apitoken = $DB->get_record_select(
        'external_tokens',
        'userid = ? AND externalserviceid = ? AND (validuntil = 0 OR validuntil > ?)',
        [$apiuserid, $service->id, time()]
    );
}

$statusok = html_writer::tag(
    'span',
    get_string('apistatus_ok', 'mod_glaaster'),
    ['class' => 'badge bg-success']
);
$statusmissing = html_writer::tag(
    'span',
    get_string('apistatus_missing', 'mod_glaaster'),
    ['class' => 'badge bg-warning text-dark']
);

$setupform = html_writer::start_div('card border-0 shadow-sm mb-4');
$setupform .= html_writer::start_div('card-header d-flex align-items-center gap-2 bg-white border-bottom');
$setupform .= html_writer::tag('span', '', [
    'class' => 'rounded-circle bg-primary d-inline-block',
    'style' => 'width:10px;height:10px;flex-shrink:0',
]);
$setupform .= html_writer::tag(
    'h5',
    get_string('apisetup', 'mod_glaaster'),
    ['class' => 'mb-0 fw-semibold text-dark']
);
$setupform .= html_writer::end_div();
$setupform .= html_writer::start_div('card-body');

// Status rows.
$setupform .= html_writer::tag(
    'p',
    get_string('apistatus', 'mod_glaaster'),
    ['class' => 'fw-medium text-secondary small text-uppercase mb-2']
);
$setupform .= html_writer::start_tag('ul', ['class' => 'list-unstyled mb-3']);
$setupform .= html_writer::tag(
    'li',
    get_string('apistatus_role', 'mod_glaaster') . ' — ' . ($roleid ? $statusok : $statusmissing),
    ['class' => 'mb-1']
);
$setupform .= html_writer::tag(
    'li',
    get_string('apistatus_service', 'mod_glaaster') . ' — ' . ($service ? $statusok : $statusmissing),
    ['class' => 'mb-1']
);
$setupform .= html_writer::tag(
    'li',
    get_string('apistatus_user', 'mod_glaaster') . ' — ' . ($apiuser ? $statusok : $statusmissing),
    ['class' => 'mb-1']
);
$setupform .= html_writer::tag(
    'li',
    get_string('apistatus_token', 'mod_glaaster') . ' — ' . ($apitoken ? $statusok : $statusmissing),
    ['class' => 'mb-1']
);
$setupform .= html_writer::end_tag('ul');

if ($apitoken) {
    $setupform .= html_writer::start_div('mb-3');
    $setupform .= html_writer::tag(
        'label',
        get_string('apitoken_label', 'mod_glaaster'),
        ['class' => 'form-label fw-medium text-secondary small text-uppercase']
    );
    $setupform .= html_writer::start_div('input-group');
    $setupform .= html_writer::empty_tag('input', [
        'type' => 'password',
        'id' => 'apitoken-display',
        'value' => $apitoken->token,
        'class' => 'form-control font-monospace',
        'readonly' => 'readonly',
    ]);
    $setupform .= html_writer::tag(
        'button',
        get_string('apitoken_reveal', 'mod_glaaster'),
        ['type' => 'button', 'class' => 'btn btn-outline-secondary', 'id' => 'apitoken-reveal']
    );
    $setupform .= html_writer::tag(
        'button',
        get_string('apitoken_copy', 'mod_glaaster'),
        ['type' => 'button', 'class' => 'btn btn-outline-secondary', 'id' => 'apitoken-copy']
    );
    $setupform .= html_writer::end_div();
    $setupform .= html_writer::end_div();
} else if ($apiuserid) {
    $setupform .= html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
    $setupform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $setupform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'generateapitoken', 'value' => '1']);
    $setupform .= html_writer::tag(
        'button',
        get_string('apitoken_generate', 'mod_glaaster'),
        ['type' => 'submit', 'class' => 'btn btn-outline-primary mt-2']
    );
    $setupform .= html_writer::end_tag('form');
}

// User autocomplete form.
$setupform .= html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
$setupform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$setupform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'saveapiuser', 'value' => '1']);
$setupform .= html_writer::start_div('mb-3 position-relative');
$setupform .= html_writer::tag(
    'label',
    get_string('apiuser', 'mod_glaaster'),
    [
        'for' => 'apiuser-search',
        'class' => 'form-label fw-medium text-secondary small text-uppercase letter-spacing-1',
    ]
);
// Hidden field holds the resolved user ID.
$setupform .= html_writer::empty_tag(
    'input',
    [
        'type' => 'hidden',
        'name' => 'apiuserid',
        'id' => 'apiuserid-value',
        'value' => $apiuserid ?: '',
    ]
);
// Visible search input (populated by JS autocomplete).
$currentname = $apiuser ? fullname($apiuser) . ' (' . $apiuser->username . ')' : '';
$setupform .= html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'apiuser-search',
    'value' => $currentname,
    'class' => 'form-control form-control-lg',
    'placeholder' => get_string('apiuser_desc', 'mod_glaaster'),
    'autocomplete' => 'off',
]);
$setupform .= html_writer::tag('div', '', [
    'id' => 'apiuser-suggestions',
    'class' => 'list-group position-absolute w-100 d-none',
    'style' => 'z-index:1000',
]);
$setupform .= html_writer::end_div();
$setupform .= html_writer::tag('button', get_string('savechanges'), [
    'type' => 'submit',
    'class' => 'btn btn-primary px-4',
]);
$setupform .= html_writer::end_tag('form');
$setupform .= html_writer::end_div();
$setupform .= html_writer::end_div();

$PAGE->requires->js_call_amd('mod_glaaster/api_setup', 'init');
echo $setupform;

$page = new tool_configure_page();
echo $output->render($page);

echo $output->footer();
