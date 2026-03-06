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
 * External function to search courses
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
use external_multiple_structure;
use external_value;
use context_system;
use Exception;

class search_courses extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search query'),
            'categoryid' => new external_value(PARAM_INT, 'Filter by category ID (0 = all)', VALUE_DEFAULT, 0),
            'year' => new external_value(PARAM_TEXT, 'Filter by year in shortname', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Search for courses by name with optional filters
     * @param string $query
     * @param int $categoryid
     * @param string $year
     * @return array
     */
    public static function execute($query, $categoryid = 0, $year = '') {
        global $DB;

        try {
            // Validate parameters
            $params = self::validate_parameters(self::execute_parameters(), [
                'query' => $query,
                'categoryid' => $categoryid,
                'year' => $year
            ]);

            // Check capability
            $context = context_system::instance();
            self::validate_context($context);
            require_capability('moodle/course:create', $context);

            $query = trim($params['query']);
            if (empty($query)) {
                return [
                    'found' => false,
                    'courses' => []
                ];
            }

            // Build query with optional filters.
            $searchparam = '%' . $DB->sql_like_escape($query) . '%';
            $sqlparams = [
                'search1' => $searchparam,
                'search2' => $searchparam,
            ];

            $conditions = "(" . $DB->sql_like('c.fullname', ':search1', false) .
                          " OR " . $DB->sql_like('c.shortname', ':search2', false) . ")" .
                          " AND c.id != 1";

            // Category filter.
            if (!empty($params['categoryid'])) {
                $conditions .= " AND c.category = :catid";
                $sqlparams['catid'] = $params['categoryid'];
            }

            // Year filter — match year string anywhere in shortname.
            if (!empty($params['year']) && preg_match('/^\d{4}$/', $params['year'])) {
                $conditions .= " AND " . $DB->sql_like('c.shortname', ':yearfilter', false);
                $sqlparams['yearfilter'] = '%' . $DB->sql_like_escape($params['year']) . '%';
            }

            $sql = "SELECT c.id, c.fullname, c.shortname, c.idnumber, c.summary,
                           c.category, c.timemodified, cc.name AS categoryname
                      FROM {course} c
                      JOIN {course_categories} cc ON cc.id = c.category
                     WHERE {$conditions}
                  ORDER BY c.fullname ASC";

            $courses = $DB->get_records_sql($sql, $sqlparams, 0, 20);

            $results = [];
            foreach ($courses as $course) {
                // Count visible activities.
                $activitycount = (int) $DB->count_records_select(
                    'course_modules',
                    'course = ? AND visible = 1 AND deletioninprogress = 0',
                    [$course->id]
                );

                // Count enrolled users.
                $enrolledcount = (int) $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT ue.userid)
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE e.courseid = ?",
                    [$course->id]
                );

                $results[] = [
                    'id' => $course->id,
                    'fullname' => $course->fullname,
                    'shortname' => $course->shortname,
                    'summary' => strip_tags($course->summary),
                    'category' => $course->category,
                    'categoryname' => $course->categoryname,
                    'activitycount' => $activitycount,
                    'enrolledcount' => $enrolledcount,
                    'timemodified' => $course->timemodified,
                ];
            }

            return [
                'found' => !empty($results),
                'courses' => $results
            ];

        } catch (Exception $e) {
            return [
                'found' => false,
                'courses' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'found' => new external_value(PARAM_BOOL, 'Whether courses were found'),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Course ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                    'shortname' => new external_value(PARAM_TEXT, 'Short name'),
                    'summary' => new external_value(PARAM_RAW, 'Summary'),
                    'category' => new external_value(PARAM_INT, 'Category ID'),
                    'categoryname' => new external_value(PARAM_TEXT, 'Category name'),
                    'activitycount' => new external_value(PARAM_INT, 'Number of visible activities'),
                    'enrolledcount' => new external_value(PARAM_INT, 'Number of enrolled users'),
                    'timemodified' => new external_value(PARAM_INT, 'Last modified timestamp'),
                ]),
                'List of courses',
                VALUE_OPTIONAL
            ),
            'error' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }
}
