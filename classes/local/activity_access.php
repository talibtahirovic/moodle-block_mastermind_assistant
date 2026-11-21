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
 * Access check for the activity an apply function writes into.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Resolves the activity an apply function writes into.
 *
 * The callers check moodle/course:manageactivities in the course passed in;
 * that alone let a teacher of one course write into another course's activity
 * by pairing their own course id with a foreign course-module id. This helper
 * loads the course module only within the given course and checks the
 * capability on the activity itself, so activity-level overrides hold too.
 */
class activity_access {
    /**
     * The course module of the given type and id in the given course, after
     * checking the current user may manage it.
     *
     * @param string $modname Module name, e.g. 'book'.
     * @param int $cmid Course module id.
     * @param int $courseid Course the activity must belong to.
     * @param int $strictness MUST_EXIST (throws when absent) or IGNORE_MISSING (returns null).
     * @return \stdClass|null The course module record, or null with IGNORE_MISSING.
     * @throws \dml_missing_record_exception When the activity is not in the course (MUST_EXIST).
     * @throws \required_capability_exception When the user may not manage the activity.
     */
    public static function require_manageable(
        string $modname,
        int $cmid,
        int $courseid,
        int $strictness = MUST_EXIST
    ): ?\stdClass {
        $cm = get_coursemodule_from_id($modname, $cmid, $courseid, false, $strictness);
        if (!$cm) {
            return null;
        }
        require_capability('moodle/course:manageactivities', \context_module::instance($cm->id));
        return $cm;
    }
}
