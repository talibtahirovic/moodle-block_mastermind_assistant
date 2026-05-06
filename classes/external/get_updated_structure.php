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
 * External function to get updated course structure
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_course;
use Exception;

/**
 * External API for get updated structure.
 */
class get_updated_structure extends external_api {
    /**
     * Describe the parameters accepted by execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'coursedata' => new external_value(PARAM_RAW, 'JSON string of comprehensive course data'),
            'recommendations' => new external_value(PARAM_RAW, 'Analysis and recommendations from first request'),
        ]);
    }

    /**
     * Execute the web service call.
     *
     * @param int $courseid Course ID.
     * @param string $coursedata JSON string of comprehensive course data.
     * @param string $recommendations Analysis and recommendations from first request.
     * @return array
     */
    public static function execute($courseid, $coursedata, $recommendations) {
        @set_time_limit(300);

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'coursedata' => $coursedata,
            'recommendations' => $recommendations,
        ]);

        // Fix potential section ID vs course ID confusion.
        global $DB;
        $courseexists = $DB->record_exists('course', ['id' => $params['courseid']]);
        if (!$courseexists) {
            $sectionrecord = $DB->get_record('course_sections', ['id' => $params['courseid']], 'id, course, section');
            if ($sectionrecord) {
                $params['courseid'] = $sectionrecord->course;
            }
        }

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/mastermind_assistant:view', $context);

        try {
            // Parse course data.
            $data = json_decode($params['coursedata'], true);
            if (!$data) {
                throw new Exception('Invalid course data JSON');
            }

            // Call dashboard API instead of OpenAI directly.
            $client = new \block_mastermind_assistant\api_client();
            $airesponse = $client->generate_structure($data, $params['recommendations']);

            // Normalize response — different dashboard packages may return
            // different shapes (e.g. {sections: ...}, {structure: {sections: ...}},
            // {result: ...}, or a flat response).
            $structure = $airesponse;
            if (isset($airesponse['structure']) && is_array($airesponse['structure'])) {
                $structure = $airesponse['structure'];
            } else if (isset($airesponse['result']) && is_array($airesponse['result'])) {
                $structure = $airesponse['result'];
            }

            // Accept if we have a sections array at any level.
            if (isset($structure['sections']) && is_array($structure['sections'])) {
                return [
                    'success' => true,
                    'structure' => json_encode($structure),
                ];
            }

            // If the response is a string (e.g. markdown), return it as-is.
            if (is_string($airesponse)) {
                return [
                    'success' => true,
                    'structure' => $airesponse,
                ];
            }

            // Last resort — return the full response as JSON.
            return [
                'success' => true,
                'structure' => is_string($structure) ? $structure : json_encode($structure),
            ];
        } catch (Exception $e) {
            debugging("get_updated_structure error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error generating updated structure: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Describe the return value of execute().
     *
     * @return \external_description
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'structure' => new external_value(PARAM_RAW, 'AI-generated updated course structure', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_RAW, 'Error message if failed', VALUE_OPTIONAL),
        ]);
    }
}
