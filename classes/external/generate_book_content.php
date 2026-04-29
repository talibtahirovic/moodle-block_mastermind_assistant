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
 * External function to generate book content
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
 * External API for generate book content.
 */
class generate_book_content extends external_api {
    /**
     * Describe the parameters accepted by execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'coursename' => new external_value(PARAM_TEXT, 'Course name', VALUE_DEFAULT, ''),
            'bookname' => new external_value(PARAM_TEXT, 'Book name', VALUE_DEFAULT, ''),
            'bookdescription' => new external_value(PARAM_RAW, 'Book description', VALUE_DEFAULT, ''),
            'academiclevel' => new external_value(PARAM_TEXT, 'Academic level', VALUE_DEFAULT, ''),
            'chaptercount' => new external_value(PARAM_INT, 'Number of chapters to generate', VALUE_DEFAULT, 5),
            'targetlength' => new external_value(PARAM_TEXT, 'Target length per chapter', VALUE_DEFAULT, ''),
            'sectionname' => new external_value(PARAM_TEXT, 'Section name for context', VALUE_DEFAULT, ''),
            'courseactivities' => new external_value(PARAM_RAW, 'JSON array of course activity names', VALUE_DEFAULT, '[]'),
        ]);
    }

    /**
     * Execute the web service call.
     *
     * @return array
     */
    public static function execute(
        $courseid,
        $coursename,
        $bookname,
        $bookdescription,
        $academiclevel = '',
        $chaptercount = 5,
        $targetlength = '',
        $sectionname = '',
        $courseactivities = '[]'
    ) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'coursename' => $coursename,
            'bookname' => $bookname,
            'bookdescription' => $bookdescription,
            'academiclevel' => $academiclevel,
            'chaptercount' => $chaptercount,
            'targetlength' => $targetlength,
            'sectionname' => $sectionname,
            'courseactivities' => $courseactivities,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // AI generation can take time for complex content.
        \core_php_time_limit::raise(300);

        try {
            $activities = json_decode($params['courseactivities'], true) ?: [];
            if (empty($activities)) {
                $modinfo = get_fast_modinfo($params['courseid']);
                foreach ($modinfo->get_cms() as $cminfo) {
                    if ($cminfo->visible && $cminfo->has_view()) {
                        $activities[] = $cminfo->name . ' (' . $cminfo->modname . ')';
                    }
                }
            }

            $client = new \block_mastermind_assistant\api_client();
            $response = $client->generate_book(
                $params['coursename'],
                $params['bookname'],
                $params['bookdescription'],
                $params['academiclevel'],
                $params['chaptercount'],
                $params['targetlength'],
                $params['sectionname'],
                $activities
            );

            return [
                'success' => true,
                'chapters' => json_encode($response['chapters'] ?? []),
                'learning_objectives' => json_encode($response['learning_objectives'] ?? []),
                'content_summary' => $response['content_summary'] ?? '',
                'message' => 'Book content generated successfully',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'chapters' => '[]',
                'learning_objectives' => '[]',
                'content_summary' => '',
                'message' => 'Error: ' . $e->getMessage(),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'chapters' => new external_value(PARAM_RAW, 'JSON array of book chapters'),
            'learning_objectives' => new external_value(PARAM_RAW, 'JSON array of learning objectives'),
            'content_summary' => new external_value(PARAM_TEXT, 'Brief content summary'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
