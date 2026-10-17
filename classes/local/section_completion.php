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
 * Ranks a course's sections by how many learners completed something in them.
 *
 * One definition shared by the block's "Drop-off section" tile and the
 * detailed metrics, and matched by the enterprise companion's collection, so
 * every view names the same section. Moodle mostly writes a completion row
 * only once an activity is completed, so a ratio of completed rows to all
 * rows reads close to 100% everywhere; counting distinct learners does not.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_completion {
    /**
     * Sections ranked by the number of distinct learners who completed at
     * least one activity in them, most first.
     *
     * Only visible activities that track completion count, so a section that
     * is empty or holds only untracked activities never reads as the drop-off
     * point. On a tie the earlier section sorts last, so the least completed
     * section is where the drop-off begins.
     *
     * @param int $courseid Course id.
     * @return \stdClass[] Rows with id, section, name and completions.
     */
    public static function ranked(int $courseid): array {
        global $DB;
        $sql = "SELECT cs.id, cs.section, cs.name, COUNT(DISTINCT cmc.userid) AS completions
                  FROM {course_sections} cs
                  JOIN {course_modules} cm ON cm.section = cs.id AND cm.visible = 1
                       AND cm.completion > 0 AND cm.deletioninprogress = 0
             LEFT JOIN {course_modules_completion} cmc
                       ON cmc.coursemoduleid = cm.id AND cmc.completionstate > 0
                 WHERE cs.course = ?
              GROUP BY cs.id, cs.section, cs.name
              ORDER BY completions DESC, cs.section DESC";
        return array_values($DB->get_records_sql($sql, [$courseid]));
    }

    /**
     * The section the fewest learners completed anything in.
     *
     * @param int $courseid Course id.
     * @return \stdClass|null Row with id, section, name and completions, or null.
     */
    public static function least_completed(int $courseid): ?\stdClass {
        $ranked = self::ranked($courseid);
        return $ranked ? end($ranked) : null;
    }
}
