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
 * Utility code for LTI service handling.
 *
 * @package mod_glaaster
 * @copyright  Copyright (c) 2011 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Chris Scribner
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/glaaster/OAuthBody.php');
require_once($CFG->dirroot . '/mod/glaaster/locallib.php');

// TODO: Switch to core oauthlib once implemented - MDL-30149.
use moodle\mod\glaaster as lti;

define('LTI_ITEM_TYPE', 'mod');
define('LTI_ITEM_MODULE', 'glaaster');
define('LTI_SOURCE', 'mod/glaaster');

/**
 * Build an LTI POX response XML envelope.
 *
 * @param string $codemajor The major status code (success, failure, unsupported).
 * @param string $description Human-readable status description.
 * @param string $messageref Reference to the originating message identifier.
 * @param string $messagetype The response message type.
 * @return SimpleXMLElement The constructed XML response envelope.
 */
function lti_get_response_xml($codemajor, $description, $messageref, $messagetype) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><imsx_POXEnvelopeResponse />');
    $xml->addAttribute('xmlns', 'http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0');

    $headerinfo = $xml->addChild('imsx_POXHeader')->addChild('imsx_POXResponseHeaderInfo');

    $headerinfo->addChild('imsx_version', 'V1.0');
    $headerinfo->addChild('imsx_messageIdentifier', (string)mt_rand());

    $statusinfo = $headerinfo->addChild('imsx_statusInfo');
    $statusinfo->addchild('imsx_codeMajor', $codemajor);
    $statusinfo->addChild('imsx_severity', 'status');
    $statusinfo->addChild('imsx_description', $description);
    $statusinfo->addChild('imsx_messageRefIdentifier', $messageref);
    $incomingtype = str_replace('Response', 'Request', $messagetype);
    $statusinfo->addChild('imsx_operationRefIdentifier', $incomingtype);

    $xml->addChild('imsx_POXBody')->addChild($messagetype);

    return $xml;
}

/**
 * Parse the message identifier from an LTI POX XML request.
 *
 * @param SimpleXMLElement $xml The parsed XML request.
 * @return string The message identifier, or empty string if not present.
 */
function lti_parse_message_id($xml) {
    if (empty($xml->imsx_POXHeader)) {
        return '';
    }

    $node = $xml->imsx_POXHeader->imsx_POXRequestHeaderInfo->imsx_messageIdentifier;
    $messageid = (string)$node;

    return $messageid;
}

/**
 * Parse a grade replace (replaceResult) request from an LTI POX XML message.
 *
 * @param SimpleXMLElement $xml The parsed XML request.
 * @return stdClass Parsed result object with instanceid, userid, launchid, typeid, sourcedidhash, gradeval, messageid.
 * @throws Exception If sourcedId is invalid or score is out of range.
 */
function lti_parse_grade_replace_message($xml) {
    $node = $xml->imsx_POXBody->replaceResultRequest->resultRecord->sourcedGUID->sourcedId;
    $resultjson = json_decode((string)$node);
    if (is_null($resultjson)) {
        throw new Exception('Invalid sourcedId in result message');
    }
    $node = $xml->imsx_POXBody->replaceResultRequest->resultRecord->result->resultScore->textString;

    $score = (string)$node;
    if (!is_numeric($score)) {
        throw new Exception('Score must be numeric');
    }
    $grade = floatval($score);
    if ($grade < 0.0 || $grade > 1.0) {
        throw new Exception('Score not between 0.0 and 1.0');
    }

    $parsed = new stdClass();
    $parsed->gradeval = $grade;

    $parsed->instanceid = $resultjson->data->instanceid;
    $parsed->userid = $resultjson->data->userid;
    $parsed->launchid = $resultjson->data->launchid;
    $parsed->typeid = $resultjson->data->typeid;
    $parsed->sourcedidhash = $resultjson->hash;

    $parsed->messageid = lti_parse_message_id($xml);

    return $parsed;
}

/**
 * Parse a grade read (readResult) request from an LTI POX XML message.
 *
 * @param SimpleXMLElement $xml The parsed XML request.
 * @return stdClass Parsed result object with instanceid, userid, launchid, typeid, sourcedidhash, messageid.
 * @throws Exception If sourcedId is invalid.
 */
function lti_parse_grade_read_message($xml) {
    $node = $xml->imsx_POXBody->readResultRequest->resultRecord->sourcedGUID->sourcedId;
    $resultjson = json_decode((string)$node);
    if (is_null($resultjson)) {
        throw new Exception('Invalid sourcedId in result message');
    }

    $parsed = new stdClass();
    $parsed->instanceid = $resultjson->data->instanceid;
    $parsed->userid = $resultjson->data->userid;
    $parsed->launchid = $resultjson->data->launchid;
    $parsed->typeid = $resultjson->data->typeid;
    $parsed->sourcedidhash = $resultjson->hash;

    $parsed->messageid = lti_parse_message_id($xml);

    return $parsed;
}

/**
 * Parse a grade delete (deleteResult) request from an LTI POX XML message.
 *
 * @param SimpleXMLElement $xml The parsed XML request.
 * @return stdClass Parsed result object with instanceid, userid, launchid, typeid, sourcedidhash, messageid.
 * @throws Exception If sourcedId is invalid.
 */
function lti_parse_grade_delete_message($xml) {
    $node = $xml->imsx_POXBody->deleteResultRequest->resultRecord->sourcedGUID->sourcedId;
    $resultjson = json_decode((string)$node);
    if (is_null($resultjson)) {
        throw new Exception('Invalid sourcedId in result message');
    }

    $parsed = new stdClass();
    $parsed->instanceid = $resultjson->data->instanceid;
    $parsed->userid = $resultjson->data->userid;
    $parsed->launchid = $resultjson->data->launchid;
    $parsed->typeid = $resultjson->data->typeid;
    $parsed->sourcedidhash = $resultjson->hash;

    $parsed->messageid = lti_parse_message_id($xml);

    return $parsed;
}

/**
 * Check whether the given LTI instance accepts grades from external tools.
 *
 * @param stdClass $ltiinstance The LTI activity instance record.
 * @return bool True if the instance accepts grades, false otherwise.
 */
function lti_accepts_grades($ltiinstance) {
    global $DB;

    $acceptsgrades = true;
    $ltitype = $DB->get_record('lti_types', ['id' => $ltiinstance->typeid]);

    if (empty($ltitype->toolproxyid)) {
        $typeconfig = lti_glaaster_get_config($ltiinstance);

        $typeacceptgrades = isset($typeconfig['acceptgrades']) ? $typeconfig['acceptgrades'] : LTI_GLAASTER_SETTING_DELEGATE;

        if (
            !($typeacceptgrades == LTI_GLAASTER_SETTING_ALWAYS ||
            ($typeacceptgrades == LTI_GLAASTER_SETTING_DELEGATE &&
                $ltiinstance->instructorchoiceacceptgrades == LTI_GLAASTER_SETTING_ALWAYS))
        ) {
            $acceptsgrades = false;
        }
    } else {
        $enabledcapabilities = explode("\n", $ltitype->enabledcapability);
        $acceptsgrades = in_array('Result.autocreate', $enabledcapabilities) ||
            in_array('BasicOutcome.url', $enabledcapabilities);
    }

    return $acceptsgrades;
}

/**
 * Set the passed user ID to the session user.
 *
 * @param int $userid
 */
function lti_set_session_user($userid) {
    global $DB;

    if ($user = $DB->get_record('user', ['id' => $userid])) {
        \core\session\manager::set_user($user);
    }
}

/**
 * Update a user's grade for an LTI activity instance.
 *
 * @param stdClass $ltiinstance The LTI activity instance record.
 * @param int $userid The user ID whose grade is being updated.
 * @param int $launchid The LTI launch identifier associated with the submission.
 * @param float $gradeval The grade value between 0.0 and 1.0.
 * @return bool True if the grade was updated successfully, false otherwise.
 */
function lti_update_grade($ltiinstance, $userid, $launchid, $gradeval) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [];
    $params['itemname'] = $ltiinstance->name;

    $gradeval = $gradeval * floatval($ltiinstance->grade);

    $grade = new stdClass();
    $grade->userid   = $userid;
    $grade->rawgrade = $gradeval;

    $status = grade_update(LTI_SOURCE, $ltiinstance->course, LTI_ITEM_TYPE, LTI_ITEM_MODULE, $ltiinstance->id, 0, $grade, $params);

    $record = $DB->get_record('lti_submission', ['ltiid' => $ltiinstance->id, 'userid' => $userid,
        'launchid' => $launchid], 'id');
    if ($record) {
        $id = $record->id;
    } else {
        $id = null;
    }

    if (!empty($id)) {
        $DB->update_record('lti_submission', [
            'id' => $id,
            'dateupdated' => time(),
            'gradepercent' => $gradeval,
            'state' => 2,
        ]);
    } else {
        $DB->insert_record('lti_submission', [
            'ltiid' => $ltiinstance->id,
            'userid' => $userid,
            'datesubmitted' => time(),
            'dateupdated' => time(),
            'gradepercent' => $gradeval,
            'originalgrade' => $gradeval,
            'launchid' => $launchid,
            'state' => 1,
        ]);
    }

    return $status == GRADE_UPDATE_OK;
}

/**
 * Read the current grade for a user in an LTI activity instance.
 *
 * @param stdClass $ltiinstance The LTI activity instance record.
 * @param int $userid The user ID whose grade is being read.
 * @return float|null The grade as a fraction of the maximum (0.0–1.0), or null if not set.
 */
function lti_read_grade($ltiinstance, $userid) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $grades = grade_get_grades($ltiinstance->course, LTI_ITEM_TYPE, LTI_ITEM_MODULE, $ltiinstance->id, $userid);

    $ltigrade = floatval($ltiinstance->grade);

    if (!empty($ltigrade) && isset($grades) && isset($grades->items[0]) && is_array($grades->items[0]->grades)) {
        foreach ($grades->items[0]->grades as $agrade) {
            $grade = $agrade->grade;
            if (isset($grade)) {
                return $grade / $ltigrade;
            }
        }
    }
}

/**
 * Delete a user's grade for an LTI activity instance.
 *
 * @param stdClass $ltiinstance The LTI activity instance record.
 * @param int $userid The user ID whose grade is being deleted.
 * @return bool True if the grade was deleted successfully, false otherwise.
 */
function lti_delete_grade($ltiinstance, $userid) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $grade = new stdClass();
    $grade->userid   = $userid;
    $grade->rawgrade = null;

    $status = grade_update(LTI_SOURCE, $ltiinstance->course, LTI_ITEM_TYPE, LTI_ITEM_MODULE, $ltiinstance->id, 0, $grade);

    return $status == GRADE_UPDATE_OK;
}

/**
 * Verify an LTI message signature against a list of shared secrets.
 *
 * @param string $key The OAuth consumer key.
 * @param array $sharedsecrets Array of shared secrets to attempt verification against.
 * @param string $body The raw request body.
 * @param array|null $headers Optional HTTP headers for the request.
 * @return string|false The matching secret if verification succeeds, false otherwise.
 */
function lti_verify_message($key, $sharedsecrets, $body, $headers = null) {
    foreach ($sharedsecrets as $secret) {
        $signaturefailed = false;

        try {
            // TODO: Switch to core oauthlib once implemented - MDL-30149.
            lti\handle_oauth_body_post($key, $secret, $body, $headers);
        } catch (Exception $e) {
            debugging('LTI message verification failed: ' . $e->getMessage());
            $signaturefailed = true;
        }

        if (!$signaturefailed) {
            return $secret; // Return the secret used to sign the message.
        }
    }

    return false;
}

/**
 * Validate source ID from external request
 *
 * @param object $ltiinstance
 * @param object $parsed
 * @throws Exception
 */
function lti_verify_sourcedid($ltiinstance, $parsed) {
    $sourceid = lti_glaaster_build_sourcedid(
        $parsed->instanceid,
        $parsed->userid,
        $ltiinstance->servicesalt,
        $parsed->typeid,
        $parsed->launchid
    );

    if ($sourceid->hash != $parsed->sourcedidhash) {
        throw new Exception('SourcedId hash not valid');
    }
}

/**
 * Extend the LTI services through the ltisource plugins
 *
 * @param stdClass $data LTI request data
 * @return bool
 * @throws coding_exception
 */
function lti_extend_lti_services($data) {
    $plugins = get_plugin_list_with_function('ltisource', $data->messagetype);
    if (!empty($plugins)) {
        // There can only be one.
        if (count($plugins) > 1) {
            throw new coding_exception('More than one ltisource plugin handler found');
        }
        $data->xml = new SimpleXMLElement($data->body);
        $callback = current($plugins);
        call_user_func($callback, $data);

        return true;
    }
    return false;
}
