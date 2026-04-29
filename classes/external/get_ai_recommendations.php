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
 * External function to get AI recommendations
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
 * External API for get ai recommendations.
 */
class get_ai_recommendations extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'coursedata' => new external_value(PARAM_RAW, 'Course data JSON'),
        ]);
    }

    /**
     * Get AI recommendations for course structure via Dashboard API.
     * @param int $courseid
     * @param string $coursedata
     * @return array
     */
    public static function execute($courseid, $coursedata) {
        // Allow long execution for AI processing.
        @set_time_limit(300);

        try {
            $params = self::validate_parameters(self::execute_parameters(), [
                'courseid' => $courseid,
                'coursedata' => $coursedata,
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

            // Parse course data.
            $data = json_decode($params['coursedata'], true);
            if (!$data) {
                throw new Exception('Invalid course data JSON');
            }

            // Call dashboard API instead of OpenAI directly.
            $client = new \block_mastermind_assistant\api_client();
            $airesponse = $client->analyze_course($data);

            // Normalize response — different dashboard packages may return
            // different shapes (e.g. {recommendations: ...}, {analysis: ...},
            // or a flat response).
            $recommendations = $airesponse;
            if (isset($airesponse['recommendations'])) {
                $recommendations = $airesponse['recommendations'];
            } else if (isset($airesponse['analysis'])) {
                $recommendations = $airesponse['analysis'];
            } else if (isset($airesponse['result'])) {
                $recommendations = $airesponse['result'];
            }

            // Ensure we always return a JSON string.
            $encoded = is_string($recommendations) ? $recommendations : json_encode($recommendations);

            return [
                'success' => true,
                'recommendations' => $encoded,
            ];
        } catch (Exception $e) {
            debugging('get_ai_recommendations error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'error' => 'AI Analysis Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'recommendations' => new external_value(PARAM_RAW, 'AI recommendations', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }
}
