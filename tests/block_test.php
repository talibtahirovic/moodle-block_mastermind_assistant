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

namespace block_mastermind_assistant;

/**
 * Tests for the block's course tiles.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_mastermind_assistant
 */
final class block_test extends \advanced_testcase {
    /**
     * Run the block's protected tile calculation.
     *
     * @param int $courseid Course id.
     * @return array
     */
    private function tiles(int $courseid): array {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/mastermind_assistant/block_mastermind_assistant.php');
        $method = new \ReflectionMethod(\block_mastermind_assistant::class, 'calculate_metrics');
        return $method->invoke(null, $courseid);
    }

    /**
     * No completion criteria: the tile says "not tracked" instead of 0%.
     */
    public function test_completion_tile_not_tracked_without_criteria(): void {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $gen->enrol_user($gen->create_user()->id, $course->id, 'student');

        $metrics = $this->tiles($course->id);

        $this->assertNull($metrics['completionrate']);
        $this->assertSame(get_string('completionnottracked', 'block_mastermind_assistant'), $metrics['completiondisplay']);
        $this->assertSame('Not tracked', $metrics['completiondisplay']);
    }

    /**
     * Completion disabled on the course also reads "not tracked", even with criteria rows.
     */
    public function test_completion_tile_not_tracked_when_disabled(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $DB->insert_record('course_completion_criteria', (object) [
            'course' => $course->id, 'criteriatype' => COMPLETION_CRITERIA_TYPE_SELF,
        ]);

        $this->assertNull($this->tiles($course->id)['completionrate']);
    }

    /**
     * With criteria the tile keeps its percentage.
     */
    public function test_completion_tile_percentage_with_criteria(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');
        $DB->insert_record('course_completion_criteria', (object) [
            'course' => $course->id, 'criteriatype' => COMPLETION_CRITERIA_TYPE_SELF,
        ]);
        $DB->insert_record('course_completions', (object) [
            'userid' => $user->id, 'course' => $course->id, 'timeenrolled' => 1, 'timestarted' => 1,
            'timecompleted' => time(),
        ]);

        $metrics = $this->tiles($course->id);

        $this->assertEquals(100, $metrics['completionrate']);
        $this->assertSame('100%', $metrics['completiondisplay']);
    }
}
