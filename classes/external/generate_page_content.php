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
 * External function to generate page content
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

class generate_page_content extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'coursename' => new external_value(PARAM_TEXT, 'Course name', VALUE_DEFAULT, ''),
            'pagename' => new external_value(PARAM_TEXT, 'Page name', VALUE_DEFAULT, ''),
            'pagedescription' => new external_value(PARAM_RAW, 'Page description', VALUE_DEFAULT, ''),
            'contenttype' => new external_value(PARAM_TEXT, 'Content type', VALUE_DEFAULT, ''),
            'academiclevel' => new external_value(PARAM_TEXT, 'Academic level', VALUE_DEFAULT, ''),
            'targetlength' => new external_value(PARAM_TEXT, 'Target length', VALUE_DEFAULT, ''),
            'sectionname' => new external_value(PARAM_TEXT, 'Section name for context', VALUE_DEFAULT, ''),
            'courseactivities' => new external_value(PARAM_RAW, 'JSON array of course activity names', VALUE_DEFAULT, '[]'),
        ]);
    }

    public static function execute(
        $courseid,
        $coursename,
        $pagename,
        $pagedescription,
        $contenttype = '',
        $academiclevel = '',
        $targetlength = '',
        $sectionname = '',
        $courseactivities = '[]'
    ) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'coursename' => $coursename,
            'pagename' => $pagename,
            'pagedescription' => $pagedescription,
            'contenttype' => $contenttype,
            'academiclevel' => $academiclevel,
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
            // Gather course activities for context if not provided.
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
            $response = $client->generate_page_content(
                $params['coursename'],
                $params['pagename'],
                $params['pagedescription'],
                $params['contenttype'],
                $params['academiclevel'],
                $params['targetlength'],
                $params['sectionname'],
                $activities
            );

            return [
                'success' => true,
                'content' => $response['content'] ?? '',
                'title' => $response['title'] ?? '',
                'learning_objectives' => json_encode($response['learning_objectives'] ?? []),
                'key_concepts' => json_encode($response['key_concepts'] ?? []),
                'estimated_reading_time' => $response['estimated_reading_time'] ?? '',
                'content_summary' => $response['content_summary'] ?? '',
                'message' => 'Content generated successfully',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'content' => '',
                'title' => '',
                'learning_objectives' => '[]',
                'key_concepts' => '[]',
                'estimated_reading_time' => '',
                'content_summary' => '',
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'content' => new external_value(PARAM_RAW, 'Generated HTML content'),
            'title' => new external_value(PARAM_TEXT, 'Suggested title'),
            'learning_objectives' => new external_value(PARAM_RAW, 'JSON array of learning objectives'),
            'key_concepts' => new external_value(PARAM_RAW, 'JSON array of key concepts'),
            'estimated_reading_time' => new external_value(PARAM_TEXT, 'Estimated reading time'),
            'content_summary' => new external_value(PARAM_TEXT, 'Brief content summary'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
