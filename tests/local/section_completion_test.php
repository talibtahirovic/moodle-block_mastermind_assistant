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
 * Tests for the shared drop-off section definition.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_mastermind_assistant\local\section_completion
 */
final class section_completion_test extends \advanced_testcase {
    /**
     * A course with a tracked page in sections 1-4 and an untracked page in section 5.
     *
     * @return array [course, cms keyed by section number]
     */
    private function course_with_pages(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1, 'numsections' => 5]);
        $cms = [];
        foreach ([1, 2, 3, 4] as $section) {
            $page = $gen->create_module('page', ['course' => $course->id, 'section' => $section],
                ['completion' => COMPLETION_TRACKING_MANUAL]);
            $cms[$section] = $page->cmid;
        }
        $gen->create_module('page', ['course' => $course->id, 'section' => 5], ['completion' => COMPLETION_TRACKING_NONE]);
        return [$course, $cms];
    }

    /**
     * Record completions the way Moodle mostly does: a row only once completed.
     *
     * @param int $cmid Course module id.
     * @param int $learners Number of distinct learners who completed it.
     */
    private function complete(int $cmid, int $learners): void {
        global $DB;
        for ($i = 0; $i < $learners; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $DB->insert_record('course_modules_completion', [
                'coursemoduleid' => $cmid,
                'userid' => $user->id,
                'completionstate' => COMPLETION_COMPLETE,
                'timemodified' => time(),
            ]);
        }
    }

    public function test_drop_off_is_the_section_fewest_learners_completed(): void {
        $this->resetAfterTest();
        [$course, $cms] = $this->course_with_pages();
        $this->complete($cms[1], 9);
        $this->complete($cms[2], 8);
        $this->complete($cms[3], 7);
        $this->complete($cms[4], 2);

        $least = section_completion::least_completed($course->id);

        $this->assertSame(4, (int) $least->section);
        $this->assertSame(2, (int) $least->completions);
        $this->assertSame([1, 2, 3, 4], array_map(fn($row) => (int) $row->section,
            section_completion::ranked($course->id)));
    }

    public function test_untracked_and_empty_sections_are_left_out(): void {
        $this->resetAfterTest();
        [$course, $cms] = $this->course_with_pages();
        foreach ($cms as $cmid) {
            $this->complete($cmid, 3);
        }

        $sections = array_map(fn($row) => (int) $row->section, section_completion::ranked($course->id));

        $this->assertNotContains(5, $sections);
        $this->assertNotContains(0, $sections);
    }

    public function test_a_tie_names_the_earlier_section(): void {
        $this->resetAfterTest();
        [$course, $cms] = $this->course_with_pages();
        $this->complete($cms[1], 5);
        $this->complete($cms[2], 5);

        // Sections 3 and 4 tie at zero: the drop-off begins at 3.
        $this->assertSame(3, (int) section_completion::least_completed($course->id)->section);
    }

    public function test_no_tracked_activity_means_no_drop_off(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $this->assertNull(section_completion::least_completed($course->id));
    }
}
