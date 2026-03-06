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
 * External function to log generation feedback
 *
 * @package    block_mastermind_assistant
 * @copyright  2025 The Namers <info@mastermindassistant.ai>
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

/**
 * Log user feedback on AI-generated content (apply, regenerate, discard).
 * Sends the feedback to the dashboard for training data collection.
 */
class log_generation_feedback extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'action' => new external_value(PARAM_ALPHA, 'User action: apply, regenerate, or discard'),
            'moduletype' => new external_value(PARAM_ALPHA, 'Module type: page, quiz, assign, forum, lesson, glossary, book, url'),
            'activityname' => new external_value(PARAM_TEXT, 'Activity name', VALUE_DEFAULT, ''),
            'coursename' => new external_value(PARAM_TEXT, 'Course name', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute($courseid, $action, $moduletype, $activityname = '', $coursename = '') {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'moduletype' => $moduletype,
            'activityname' => $activityname,
            'coursename' => $coursename,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        try {
            $client = new \block_mastermind_assistant\api_client();
            $client->logGenerationFeedback(
                $params['action'],
                $params['moduletype'],
                $params['activityname'],
                $params['coursename']
            );

            return ['success' => true, 'message' => 'Feedback logged'];
        } catch (\Exception $e) {
            // Non-critical — don't fail the UI if logging fails.
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether feedback was logged'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
