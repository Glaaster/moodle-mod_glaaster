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
 * Class containing data for tool_configure page
 *
 * @package    mod_glaaster
 * @copyright  2015 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_glaaster\output;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Class containing data for tool_configure page
 *
 * @copyright  2015 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_configure_page implements renderable, templatable {
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB;

        $data = new stdClass();

        $tooldomain = get_config('mod_glaaster', 'tooldomain') ?: 'lti.glaaster.com';
        $data->registerurl = 'https://' . $tooldomain . '/register';

        $apiuserid = (int) get_config('mod_glaaster', 'apiuserid');
        $service = $DB->get_record('external_services', ['shortname' => 'glaaster_api']);
        $apitoken = null;
        if ($apiuserid && $service) {
            $apitoken = $DB->get_record_select(
                'external_tokens',
                'userid = ? AND externalserviceid = ? AND (validuntil = 0 OR validuntil > ?)',
                [$apiuserid, $service->id, time()]
            );
        }

        $data->hasapiuser = (bool) $apiuserid;
        $data->hasapitoken = (bool) $apitoken;
        $data->apitoken = $apitoken ? $apitoken->token : '';
        $data->connectenabled = $data->hasapiuser && $data->hasapitoken;

        return $data;
    }
}
