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
 * Tests for the merged Feedback + Questionnaire evaluation collector.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_mastermind_assistant\local\evaluation_metrics
 */
final class evaluation_metrics_test extends \advanced_testcase {
    /**
     * Skip when mod_questionnaire isn't installed (it is optional).
     */
    private function require_questionnaire(): void {
        global $DB;
        $installed = \core_component::get_component_directory('mod_questionnaire') !== null
            && $DB->get_manager()->table_exists('questionnaire');
        if (!$installed) {
            $this->markTestSkipped('mod_questionnaire is not installed.');
        }
    }

    /**
     * Create a feedback with one numeric item and one answer per value (raw 1-5).
     *
     * @param \stdClass $course Course.
     * @param string $name Activity name.
     * @param int[] $values Answers.
     * @return \stdClass The feedback module.
     */
    private function create_feedback(\stdClass $course, string $name, array $values): \stdClass {
        global $DB;
        $gen = $this->getDataGenerator();
        $feedback = $gen->create_module('feedback', ['course' => $course->id, 'name' => $name]);
        $itemid = $DB->insert_record('feedback_item', (object) [
            'feedback' => $feedback->id, 'template' => 0, 'name' => 'Overall', 'label' => '',
            'presentation' => '', 'typ' => 'numeric', 'hasvalue' => 1, 'position' => 1,
            'required' => 0, 'dependitem' => 0, 'dependvalue' => '', 'options' => '',
        ]);
        foreach ($values as $value) {
            $user = $gen->create_user();
            $compid = $DB->insert_record('feedback_completed', (object) [
                'feedback' => $feedback->id, 'userid' => $user->id, 'timemodified' => time(),
                'random_response' => 0, 'anonymous_response' => 2, 'courseid' => 0,
            ]);
            $DB->insert_record('feedback_value', (object) [
                'course_id' => 0, 'item' => $itemid, 'completed' => $compid,
                'tmp_completed' => 0, 'value' => (string) $value,
            ]);
        }
        return $feedback;
    }

    /**
     * Create a questionnaire with one 1-5 rate row and one response per value.
     *
     * @param \stdClass $course Course.
     * @param string $name Activity name.
     * @param int[] $values Rate values (1-based).
     * @return int The course-module id.
     */
    private function create_questionnaire(\stdClass $course, string $name, array $values): int {
        global $DB;
        $gen = $this->getDataGenerator();
        $q = $gen->create_module('questionnaire', ['course' => $course->id, 'name' => $name]);
        $cm = get_coursemodule_from_instance('questionnaire', $q->id, $course->id, false, MUST_EXIST);
        $qgen = $gen->get_plugin_generator('mod_questionnaire');
        $question = $qgen->create_question(
            $q,
            ['surveyid' => $q->sid, 'type_id' => 8, 'name' => '', 'content' => '<p>Didaktik</p>', 'length' => 5],
            ['Didaktik']
        );
        $choiceid = (int) $DB->get_field('questionnaire_quest_choice', 'id', ['question_id' => $question->id]);
        foreach ($values as $value) {
            $rid = $DB->insert_record('questionnaire_response', (object) [
                'questionnaireid' => $q->id, 'submitted' => time(), 'complete' => 'y', 'grade' => 0, 'userid' => 0,
            ]);
            $DB->insert_record('questionnaire_response_rank', (object) [
                'response_id' => $rid, 'question_id' => $question->id, 'choice_id' => $choiceid, 'rankvalue' => $value,
            ]);
        }
        return (int) $cm->id;
    }

    /**
     * Feedback activities come first, then questionnaires, each tagged.
     */
    public function test_merged_activities_both_modules(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        // Questionnaire created first, to prove the order is by collector, not by creation.
        $qcmid = $this->create_questionnaire($course, 'Evaluation Q', [4, 5]);
        $feedback = $this->create_feedback($course, 'Course evaluation', [4]);

        $result = evaluation_metrics::collect($course->id);

        $this->assertCount(2, $result['activities']);
        $this->assertSame('feedback', $result['activities'][0]['module']);
        $this->assertSame((int) $feedback->cmid, $result['activities'][0]['cmid']);
        $this->assertSame('questionnaire', $result['activities'][1]['module']);
        $this->assertSame($qcmid, $result['activities'][1]['cmid']);
        $this->assertArrayNotHasKey('ratings', $result);
        $this->assertSame(['activities', 'satisfaction'], array_keys($result));
    }

    /**
     * Satisfaction is weighted by the number of ratings, not averaged per module.
     */
    public function test_satisfaction_weighted_across_modules(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->create_feedback($course, 'Course evaluation', [5]);
        $this->create_questionnaire($course, 'Evaluation Q', [1, 1]);

        $result = evaluation_metrics::collect($course->id);

        // (5 + 1 + 1) / 3 = 2.33, not (5 + 1) / 2 = 3.
        $this->assertSame(2.3, $result['satisfaction']);
    }

    /**
     * Without questionnaire activities the legacy satisfaction is unchanged.
     */
    public function test_feedback_only_matches_legacy_satisfaction(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->create_feedback($course, 'Course evaluation', [4, 5, 3]);

        $legacy = feedback_metrics::collect($course->id);
        $merged = evaluation_metrics::collect($course->id);

        $this->assertSame($legacy['satisfaction'], $merged['satisfaction']);
        $this->assertSame(4.0, $merged['satisfaction']);
        $this->assertSame($legacy['activities'], $merged['activities']);
        // The legacy collector keeps its exact shape; the rating totals live on the internal variant.
        $this->assertSame(['activities', 'satisfaction'], array_keys($legacy));
        $this->assertSame(['sum5' => 12.0, 'n' => 3], feedback_metrics::collect_with_ratings($course->id)['ratings']);
    }

    /**
     * No ratings at all: satisfaction is null.
     */
    public function test_no_ratings_gives_null(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $result = evaluation_metrics::collect($course->id);

        $this->assertSame([], $result['activities']);
        $this->assertNull($result['satisfaction']);
    }
}
