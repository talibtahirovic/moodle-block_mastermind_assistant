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

namespace block_mastermind_assistant\local;

/**
 * Course evaluation data from every source: Feedback and Questionnaire.
 *
 * Merges the two collectors into one result of the shape the callers already
 * know: the activities (Feedback first, then Questionnaire, each tagged with
 * its module) and one satisfaction figure on a 1-5 scale, weighted by the
 * number of ratings across both modules. The internal per-collector rating
 * totals (feedback_metrics::collect_with_ratings() and
 * questionnaire_metrics::collect()) are used for that merge and are never
 * part of the result: the result has exactly the keys activities and
 * satisfaction, which the dashboard's strict schema requires.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation_metrics {
    /**
     * Collect the evaluation activities and the overall satisfaction of a course.
     *
     * @param int $courseid Course id.
     * @param int[] $include Course-module ids forced in.
     * @param int[] $exclude Course-module ids forced out (wins over include).
     * @return array ['activities' => [...], 'satisfaction' => float|null]
     */
    public static function collect(int $courseid, array $include = [], array $exclude = []): array {
        $feedback = feedback_metrics::collect_with_ratings($courseid, feedback_metrics::VALUE_PAGE, $include, $exclude);
        $questionnaire = questionnaire_metrics::collect($courseid, $include, $exclude);

        $sum5 = (float) ($feedback['ratings']['sum5'] ?? 0) + (float) ($questionnaire['ratings']['sum5'] ?? 0);
        $n = (int) ($feedback['ratings']['n'] ?? 0) + (int) ($questionnaire['ratings']['n'] ?? 0);

        return [
            'activities' => array_merge($feedback['activities'], $questionnaire['activities']),
            'satisfaction' => $n > 0 ? round($sum5 / $n, 1) : null,
        ];
    }

    /**
     * Whether a course tracks completion: enabled on the site and the course,
     * with at least one completion criterion. Without criteria nobody can
     * complete the course, so a completion rate would read 0% for the wrong reason.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function completion_tracked(int $courseid): bool {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $course = $DB->get_record('course', ['id' => $courseid], 'id, enablecompletion');
        if (!$course) {
            return false;
        }
        if (!(new \completion_info($course))->is_enabled()) {
            return false;
        }
        return $DB->record_exists('course_completion_criteria', ['course' => $courseid]);
    }
}
