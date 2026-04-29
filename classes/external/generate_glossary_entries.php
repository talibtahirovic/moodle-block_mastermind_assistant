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
 * External function to generate glossary entries
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
 * External API for generate glossary entries.
 */
class generate_glossary_entries extends external_api {
    /**
     * Describe the parameters accepted by execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'coursename' => new external_value(PARAM_TEXT, 'Course name', VALUE_DEFAULT, ''),
            'glossaryname' => new external_value(PARAM_TEXT, 'Glossary name', VALUE_DEFAULT, ''),
            'glossarydescription' => new external_value(PARAM_RAW, 'Glossary description', VALUE_DEFAULT, ''),
            'academiclevel' => new external_value(PARAM_TEXT, 'Academic level', VALUE_DEFAULT, ''),
            'entrycount' => new external_value(PARAM_INT, 'Number of entries to generate', VALUE_DEFAULT, 10),
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
        $glossaryname,
        $glossarydescription,
        $academiclevel = '',
        $entrycount = 10,
        $sectionname = '',
        $courseactivities = '[]'
    ) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'coursename' => $coursename,
            'glossaryname' => $glossaryname,
            'glossarydescription' => $glossarydescription,
            'academiclevel' => $academiclevel,
            'entrycount' => $entrycount,
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
            $response = $client->generate_glossary(
                $params['coursename'],
                $params['glossaryname'],
                $params['glossarydescription'],
                $params['academiclevel'],
                $params['entrycount'],
                $params['sectionname'],
                $activities
            );

            return [
                'success' => true,
                'description' => $response['description'] ?? '',
                'entries' => json_encode($response['entries'] ?? []),
                'message' => 'Glossary entries generated successfully',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'description' => '',
                'entries' => '[]',
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
            'description' => new external_value(PARAM_RAW, 'HTML description for the glossary'),
            'entries' => new external_value(PARAM_RAW, 'JSON array of glossary entries'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
