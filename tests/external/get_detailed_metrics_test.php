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
 * Tests for the detailed metrics external function.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\external;

/**
 * Tests for the detailed metrics external function.
 *
 * @covers \block_mastermind_assistant\external\get_detailed_metrics
 * @runTestsInSeparateProcesses
 */
final class get_detailed_metrics_test extends \advanced_testcase {
    /**
     * feedback_summary carries the per-question aggregation.
     */
    public function test_feedback_summary_populated(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $feedback = $gen->create_module('feedback', ['course' => $course->id, 'name' => 'Course evaluation']);
        $itemid = $DB->insert_record('feedback_item', (object) [
            'feedback' => $feedback->id, 'template' => 0, 'name' => 'Overall satisfaction',
            'label' => '', 'presentation' => '', 'typ' => 'numeric', 'hasvalue' => 1,
            'position' => 1, 'required' => 0, 'dependitem' => 0, 'dependvalue' => '', 'options' => '',
        ]);
        $user = $gen->create_user();
        $compid = $DB->insert_record('feedback_completed', (object) [
            'feedback' => $feedback->id, 'userid' => $user->id, 'timemodified' => 1720000000,
            'random_response' => 0, 'anonymous_response' => 2, 'courseid' => 0,
        ]);
        $DB->insert_record('feedback_value', (object) [
            'course_id' => 0, 'item' => $itemid, 'completed' => $compid,
            'tmp_completed' => 0, 'value' => '4',
        ]);

        $result = get_detailed_metrics::execute($course->id);
        $metrics = json_decode($result['metrics'], true);

        $this->assertSame('4.0/5', $metrics['satisfaction_score']);
        $this->assertCount(1, $metrics['feedback_summary']);
        $this->assertSame('Course evaluation', $metrics['feedback_summary'][0]['name']);
        $this->assertEqualsWithDelta(4.0, $metrics['feedback_summary'][0]['questions'][0]['avg'], 0.05);
    }

    /**
     * The dropout point and the most incomplete activity name the activity,
     * not its module type (both used to read just "page").
     */
    public function test_dropout_point_and_most_incomplete_name_the_activity(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $intro = $gen->create_module('page', ['course' => $course->id, 'name' => 'Welcome', 'completion' => 1]);
        $roundup = $gen->create_module('page', ['course' => $course->id, 'name' => 'Evidence roundup', 'completion' => 1]);

        // Two of three learners last touched the roundup; one completed only the welcome page.
        $now = time();
        foreach ([[$intro, $now - 300], [$roundup, $now - 200], [$roundup, $now - 100]] as $i => [$cm, $time]) {
            $user = $gen->create_user();
            $DB->insert_record('logstore_standard_log', (object) [
                'eventname' => '\\mod_page\\event\\course_module_viewed', 'component' => 'mod_page',
                'action' => 'viewed', 'target' => 'course_module', 'crud' => 'r', 'edulevel' => 2,
                'contextid' => \context_module::instance($cm->cmid)->id, 'contextlevel' => CONTEXT_MODULE,
                'contextinstanceid' => $cm->cmid, 'userid' => $user->id, 'courseid' => $course->id,
                'anonymous' => 0, 'timecreated' => $time,
            ]);
            if ($i === 0) {
                $DB->insert_record('course_modules_completion', (object) [
                    'coursemoduleid' => $intro->cmid, 'userid' => $user->id,
                    'completionstate' => COMPLETION_COMPLETE, 'timemodified' => $time,
                ]);
            }
        }

        $metrics = json_decode(get_detailed_metrics::execute($course->id)['metrics'], true);

        $this->assertSame('Evidence roundup', $metrics['dropout_point']);
        $this->assertSame('Evidence roundup', $metrics['most_incomplete_activity']);
    }

    /**
     * No feedback activity: defaults untouched.
     */
    public function test_no_feedback_defaults(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $result = get_detailed_metrics::execute($course->id);
        $metrics = json_decode($result['metrics'], true);

        $this->assertSame('No feedback activity', $metrics['satisfaction_score']);
        $this->assertSame([], $metrics['feedback_summary']);
    }
}
