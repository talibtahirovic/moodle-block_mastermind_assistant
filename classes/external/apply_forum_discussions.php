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
 * External function to apply forum discussions
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/forum/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_course;
use Exception;

/**
 * Apply AI-generated forum discussions by creating them via Moodle's forum API.
 */
class apply_forum_discussions extends external_api {
    /**
     * Describe the parameters accepted by execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'discussions' => new external_value(PARAM_RAW, 'JSON array of discussions [{subject, message}]'),
        ]);
    }

    /**
     * Execute the web service call.
     *
     * @return array
     */
    public static function execute($courseid, $cmid, $discussions) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'discussions' => $discussions,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $items = json_decode($params['discussions'], true);
        if (empty($items) || !is_array($items)) {
            return ['success' => false, 'created' => 0, 'message' => 'No discussions to create.'];
        }

        // Get forum instance from course module.
        $cm = get_coursemodule_from_id('forum', $params['cmid'], 0, false, MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);

        $created = 0;
        $errors = [];

        foreach ($items as $disc) {
            $subject = trim($disc['subject'] ?? $disc['title'] ?? '');
            $message = trim($disc['message'] ?? $disc['content'] ?? '');
            if (empty($subject)) {
                continue;
            }
            if (empty($message)) {
                $message = '<p>' . $subject . '</p>';
            }

            try {
                \mod_forum_external::add_discussion(
                    $forum->id,
                    $subject,
                    $message,
                    -1
                );
                $created++;
            } catch (Exception $e) {
                $errors[] = $subject . ': ' . $e->getMessage();
            }
        }

        $msg = $created . ' discussion(s) created successfully.';
        if (!empty($errors)) {
            $msg .= ' Errors: ' . implode('; ', $errors);
        }

        return [
            'success' => $created > 0,
            'created' => $created,
            'message' => $msg,
        ];
    }

    /**
     * Describe the return value of execute().
     *
     * @return \external_description
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether any discussions were created'),
            'created' => new external_value(PARAM_INT, 'Number of discussions created'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
        ]);
    }
}
