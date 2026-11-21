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
 * Tests for the evaluation feedback metrics collector.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Tests for the evaluation feedback metrics collector.
 *
 * @covers \block_mastermind_assistant\local\feedback_metrics
 */
final class feedback_metrics_test extends \advanced_testcase {
    /**
     * Insert a feedback item row.
     *
     * @param int $feedbackid Feedback instance id.
     * @param string $typ Item type (numeric, textarea, label, ...).
     * @param string $name Item name (the question text).
     * @param int $position Item position.
     * @return int New item id.
     */
    private function add_item(int $feedbackid, string $typ, string $name, int $position = 1): int {
        global $DB;
        return $DB->insert_record('feedback_item', (object) [
            'feedback' => $feedbackid,
            'template' => 0,
            'name' => $name,
            'label' => '',
            'presentation' => $typ === 'textarea' ? '30|5' : '',
            'typ' => $typ,
            'hasvalue' => 1,
            'position' => $position,
            'required' => 0,
            'dependitem' => 0,
            'dependvalue' => '',
            'options' => '',
        ]);
    }

    /**
     * Insert one completed submission with values for the given items.
     *
     * @param int $feedbackid Feedback instance id.
     * @param int $userid Responding user id.
     * @param array $itemvalues Map of itemid => value string.
     * @param int $time Submission time (0 = now).
     * @return int The feedback_completed id.
     */
    private function add_response(int $feedbackid, int $userid, array $itemvalues, int $time = 0): int {
        global $DB;
        $compid = $DB->insert_record('feedback_completed', (object) [
            'feedback' => $feedbackid,
            'userid' => $userid,
            'timemodified' => $time ?: time(),
            'random_response' => 0,
            'anonymous_response' => 2,
            'courseid' => 0,
        ]);
        foreach ($itemvalues as $itemid => $value) {
            $DB->insert_record('feedback_value', (object) [
                'course_id' => 0,
                'item' => $itemid,
                'completed' => $compid,
                'tmp_completed' => 0,
                'value' => $value,
            ]);
        }
        return $compid;
    }

    /**
     * No feedback activity: empty shape.
     */
    public function test_empty_course_returns_empty_shape(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $result = feedback_metrics::collect($course->id);

        $this->assertSame([], $result['activities']);
        $this->assertNull($result['satisfaction']);
    }

    /**
     * Rated questions aggregate to averages; satisfaction spans all rated values.
     */
    public function test_rated_question_aggregates(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $feedback = $gen->create_module('feedback', ['course' => $course->id, 'name' => 'Course evaluation']);
        $item = $this->add_item($feedback->id, 'numeric', 'Overall satisfaction');
        $u1 = $gen->create_user();
        $u2 = $gen->create_user();
        $u3 = $gen->create_user();
        $this->add_response($feedback->id, $u1->id, [$item => '4']);
        $this->add_response($feedback->id, $u2->id, [$item => '5']);
        $this->add_response($feedback->id, $u3->id, [$item => '3']);

        $result = feedback_metrics::collect($course->id);

        $this->assertCount(1, $result['activities']);
        $activity = $result['activities'][0];
        $this->assertSame((int) $feedback->cmid, $activity['cmid']);
        $this->assertSame('Course evaluation', $activity['name']);
        $this->assertSame(3, $activity['responses']);
        $this->assertCount(1, $activity['questions']);
        $q = $activity['questions'][0];
        $this->assertSame('Overall satisfaction', $q['question']);
        $this->assertSame('numeric', $q['type']);
        $this->assertSame(3, $q['responses']);
        $this->assertEqualsWithDelta(4.0, $q['avg'], 0.05);
        $this->assertEqualsWithDelta(4.0, $result['satisfaction'], 0.05);
    }

    /**
     * Text answers become anonymized comment objects: newest first, tags
     * stripped, truncated to 400 chars, non-numeric never breaks rated averages.
     */
    public function test_comments_and_text_handling(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $rated = $this->add_item($feedback->id, 'numeric', 'Rating', 1);
        $text = $this->add_item($feedback->id, 'textarea', 'What could we improve?', 2);
        $u1 = $gen->create_user();
        $u2 = $gen->create_user();
        $long = str_repeat('a', 500);
        $this->add_response(
            $feedback->id,
            $u1->id,
            [$rated => '4', $text => '<p>First comment</p>']
        );
        $this->add_response(
            $feedback->id,
            $u2->id,
            [$rated => 'not-a-number', $text => $long]
        );

        $result = feedback_metrics::collect($course->id);

        $activity = $result['activities'][0];
        // Newest (u2) first; truncated to 400; tags stripped from the older one.
        // Each comment keeps its question so reflection items can be themed apart.
        $this->assertCount(2, $activity['comments']);
        $this->assertSame(400, \core_text::strlen($activity['comments'][0]['text']));
        $this->assertSame([
            'questionName' => 'What could we improve?',
            'questionType' => 'textarea',
            'text' => 'First comment',
        ], $activity['comments'][1]);
        // Rated question: only the numeric answer is a response; the other isn't a rating.
        $q = $activity['questions'][0];
        $this->assertSame(1, $q['responses']);
        $this->assertEqualsWithDelta(4.0, $q['avg'], 0.05);
        // The textarea question row exists with no average.
        $this->assertNull($activity['questions'][1]['avg']);
    }

    /**
     * Every comment reaches the payload, read in pages; presentation-only
     * items are skipped.
     */
    public function test_all_comments_emitted_across_pages_and_skipped_types(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $this->add_item($feedback->id, 'label', 'Section header', 1);
        $text = $this->add_item($feedback->id, 'textarea', 'Comments?', 2);
        for ($i = 0; $i < 55; $i++) {
            $user = $gen->create_user();
            $this->add_response($feedback->id, $user->id, [$text => 'Comment number ' . $i]);
        }

        // A 20-row page forces three pages for the 55 values.
        $result = feedback_metrics::collect($course->id, 20);

        $activity = $result['activities'][0];
        $this->assertCount(55, $activity['comments']);
        // Newest first across page boundaries: the last inserted submission leads.
        $texts = array_column($activity['comments'], 'text');
        $this->assertSame('Comment number 54', $texts[0]);
        $this->assertSame('Comment number 0', $texts[54]);
        $this->assertSame(55, count(array_unique($texts)));
        // The label item was skipped: only the textarea question remains.
        $this->assertCount(1, $activity['questions']);
        $this->assertSame('Comments?', $activity['questions'][0]['question']);
        $this->assertSame(55, $activity['questions'][0]['responses']);
    }

    /**
     * Multichoicerated values are option indexes mapped to presentation ratings.
     */
    public function test_multichoicerated_maps_indexes_to_ratings(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $itemid = $DB->insert_record('feedback_item', (object) [
            'feedback' => $feedback->id, 'template' => 0, 'name' => 'Rate the course',
            'label' => '', 'presentation' => 'r>>>>>10####Low|20####Medium|30####High<<<<<1',
            'typ' => 'multichoicerated', 'hasvalue' => 1, 'position' => 1,
            'required' => 0, 'dependitem' => 0, 'dependvalue' => '', 'options' => '',
        ]);
        $u1 = $gen->create_user();
        $u2 = $gen->create_user();
        // Stored values are option indexes: 1 -> rating 10, 3 -> rating 30.
        $this->add_response($feedback->id, $u1->id, [$itemid => '1']);
        $this->add_response($feedback->id, $u2->id, [$itemid => '3']);

        $result = feedback_metrics::collect($course->id);

        $q = $result['activities'][0]['questions'][0];
        $this->assertSame(2, $q['responses']);
        $this->assertEqualsWithDelta(20.0, $q['avg'], 0.05);
        // The item keeps its own 10-30 scale; the headline is on 1-5: 20 -> 3.
        $this->assertEqualsWithDelta(3.0, $result['satisfaction'], 0.05);
    }

    /**
     * Comments from multiple text questions merge newest-first per activity.
     */
    public function test_comments_merge_newest_first_across_questions(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $q1 = $this->add_item($feedback->id, 'textarea', 'What worked well?', 1);
        $q2 = $this->add_item($feedback->id, 'textarea', 'What could improve?', 2);
        $ua = $gen->create_user();
        $ub = $gen->create_user();
        $uc = $gen->create_user();
        // Oldest submission answers both questions, then B answers only Q1,
        // then the newest submission C answers only Q2.
        $this->add_response($feedback->id, $ua->id, [$q1 => 'A on Q1', $q2 => 'A on Q2']);
        $this->add_response($feedback->id, $ub->id, [$q1 => 'B on Q1']);
        $this->add_response($feedback->id, $uc->id, [$q2 => 'C on Q2']);

        $result = feedback_metrics::collect($course->id);

        $comments = $result['activities'][0]['comments'];
        $this->assertSame('C on Q2', $comments[0]['text']);
        $this->assertSame('What could improve?', $comments[0]['questionName']);
        $this->assertSame('B on Q1', $comments[1]['text']);
        $this->assertSame('What worked well?', $comments[1]['questionName']);
        $this->assertSame(['A on Q1', 'A on Q2'], array_column(array_slice($comments, 2), 'text'));
    }

    /**
     * Each activity carries its own course-module id, so a finding links to the right analysis page.
     */
    public function test_each_activity_carries_its_own_cmid(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $first = $gen->create_module('feedback', ['course' => $course->id, 'name' => 'Post-course evaluation']);
        $second = $gen->create_module('feedback', ['course' => $course->id, 'name' => 'Module 4 check-in']);
        $other = $gen->create_course();
        $gen->create_module('feedback', ['course' => $other->id, 'name' => 'Elsewhere']);

        $result = feedback_metrics::collect($course->id);

        $bycmid = array_column($result['activities'], 'name', 'cmid');
        $this->assertSame([
            (int) $first->cmid => 'Post-course evaluation',
            (int) $second->cmid => 'Module 4 check-in',
        ], $bycmid);
    }

    /**
     * Create a feedback with one numeric item and return [course, feedback, itemid].
     *
     * @param array $modrecord Extra module settings.
     * @param string $presentation Numeric item presentation.
     * @return array
     */
    private function numeric_fixture(array $modrecord = [], string $presentation = ''): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['startdate' => 1730000000]);
        $feedback = $gen->create_module('feedback', ['course' => $course->id] + $modrecord);
        $itemid = $this->add_item($feedback->id, 'numeric', 'Rating');
        if ($presentation !== '') {
            $DB->set_field('feedback_item', 'presentation', $presentation, ['id' => $itemid]);
        }
        return [$course, $feedback, $itemid];
    }

    /**
     * Hidden activities are skipped, and the manual selection applies.
     */
    public function test_hidden_feedback_skipped(): void {
        $this->resetAfterTest();
        [$course, $feedback] = $this->numeric_fixture();
        $gen = $this->getDataGenerator();
        $gen->create_module('feedback', ['course' => $course->id, 'visible' => 0, 'name' => 'Hidden']);
        $names = array_column(feedback_metrics::collect($course->id)['activities'], 'cmid');
        $this->assertSame([(int) $feedback->cmid], $names);

        $none = feedback_metrics::collect($course->id, 500, [], [(int) $feedback->cmid]);
        $this->assertSame([], $none['activities']);
    }

    /**
     * Activities being deleted are skipped.
     */
    public function test_deletion_in_progress_skipped(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $feedback] = $this->numeric_fixture();
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $feedback->cmid]);
        \course_modinfo::clear_instance_cache($course->id);

        $this->assertSame([], feedback_metrics::collect($course->id)['activities']);
    }

    /**
     * Submissions before the course start do not count, and their values are not read.
     */
    public function test_answers_before_startdate_ignored(): void {
        $this->resetAfterTest();
        [$course, $feedback, $item] = $this->numeric_fixture();
        $gen = $this->getDataGenerator();
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '1'], 1720000000);
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '5'], 1730000500);
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '3'], 1730000000);

        $result = feedback_metrics::collect($course->id);

        $a = $result['activities'][0];
        $this->assertSame(2, $a['responses']);
        $this->assertSame(2, $a['questions'][0]['responses']);
        $this->assertEqualsWithDelta(4.0, $a['questions'][0]['avg'], 0.05);
        $this->assertEqualsWithDelta(4.0, $result['satisfaction'], 0.05);
    }

    /**
     * A course without a start date counts everything.
     */
    public function test_no_startdate_counts_all(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['startdate' => 0]);
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $item = $this->add_item($feedback->id, 'numeric', 'Rating');
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '2'], 1000000);

        $this->assertSame(1, feedback_metrics::collect($course->id)['activities'][0]['responses']);
    }

    /**
     * Blank answers are not counted in a question's responses.
     */
    public function test_blank_answers_not_counted(): void {
        $this->resetAfterTest();
        [$course, $feedback, $item] = $this->numeric_fixture();
        $gen = $this->getDataGenerator();
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '4']);
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '']);
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '  ']);

        $result = feedback_metrics::collect($course->id);

        $this->assertSame(3, $result['activities'][0]['responses']);
        $this->assertSame(1, $result['activities'][0]['questions'][0]['responses']);
    }

    /**
     * Multichoicerated scale comes from the min/max of the rating map.
     */
    public function test_scale_max_from_multichoicerated(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['startdate' => 1730000000]);
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $itemid = $DB->insert_record('feedback_item', (object) [
            'feedback' => $feedback->id, 'template' => 0, 'name' => 'Rate',
            'label' => '', 'presentation' => 'r>>>>>1####Bad|2####Ok|3####Good|4####Great|5####Best<<<<<1',
            'typ' => 'multichoicerated', 'hasvalue' => 1, 'position' => 1,
            'required' => 0, 'dependitem' => 0, 'dependvalue' => '', 'options' => '',
        ]);
        $this->add_response($feedback->id, $gen->create_user()->id, [$itemid => '5']);

        $result = feedback_metrics::collect($course->id);

        $a = $result['activities'][0];
        $this->assertSame('feedback', $a['module']);
        $q = $a['questions'][0];
        $this->assertSame('rated', $q['kind']);
        $this->assertSame(1.0, (float) $q['scale_min']);
        $this->assertSame(5.0, (float) $q['scale_max']);
        $this->assertEqualsWithDelta(5.0, $result['satisfaction'], 0.05);
    }

    /**
     * Numeric items take their scale from the "min|max" presentation, and
     * the headline converts them to 1-5.
     */
    public function test_numeric_scale_and_headline_conversion(): void {
        $this->resetAfterTest();
        [$course, $feedback, $item] = $this->numeric_fixture([], '1|10');
        $gen = $this->getDataGenerator();
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '10']);
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '10']);

        $result = feedback_metrics::collect($course->id);

        $q = $result['activities'][0]['questions'][0];
        $this->assertSame(1.0, (float) $q['scale_min']);
        $this->assertSame(10.0, (float) $q['scale_max']);
        $this->assertEqualsWithDelta(10.0, $q['avg'], 0.05);
        $this->assertEqualsWithDelta(5.0, $result['satisfaction'], 0.05);
    }

    /**
     * Items without a known scale contribute raw values, as before; 1-5 items stay identical.
     */
    public function test_satisfaction_unchanged_for_one_to_five_items(): void {
        $this->resetAfterTest();
        [$course, $feedback, $item] = $this->numeric_fixture([], '1|5');
        $unscaled = $this->add_item($feedback->id, 'numeric', 'Unscaled', 2);
        $gen = $this->getDataGenerator();
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '4', $unscaled => '2']);
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '5', $unscaled => '3']);
        $this->add_response($feedback->id, $gen->create_user()->id, [$item => '3']);

        $result = feedback_metrics::collect($course->id);

        // (4 + 5 + 3 + 2 + 3) / 5 = 3.4 with raw values for the unscaled item.
        $this->assertEqualsWithDelta(3.4, $result['satisfaction'], 0.05);
    }

    /**
     * Text questions are tagged with their kind.
     */
    public function test_text_kind(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['startdate' => 1730000000]);
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $text = $this->add_item($feedback->id, 'textarea', 'Comments?');
        $this->add_response($feedback->id, $gen->create_user()->id, [$text => 'Nice']);

        $q = feedback_metrics::collect($course->id)['activities'][0]['questions'][0];
        $this->assertSame('text', $q['kind']);
        $this->assertArrayNotHasKey('scale_min', $q);
    }

    /**
     * A manager's submission is excluded; anonymous ones (userid 0) stay.
     */
    public function test_feedback_counts_only_participants(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_user();
        $manager = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($manager->id, $course->id, 'manager');
        $feedback = $gen->create_module('feedback', ['course' => $course->id, 'name' => 'Evaluation']);
        $item = $this->add_item($feedback->id, 'numeric', 'Overall');
        $text = $this->add_item($feedback->id, 'textarea', 'Comment', 2);
        $this->add_response($feedback->id, $student->id, [$item => '4', $text => 'student']);
        $this->add_response($feedback->id, $manager->id, [$item => '1', $text => 'manager']);
        $this->add_response($feedback->id, 0, [$item => '2', $text => 'anon']);

        $result = feedback_metrics::collect_with_ratings($course->id);

        $activity = $result['activities'][0];
        $this->assertSame(2, $activity['responses']);
        $this->assertSame(2, $result['ratings']['n']);
        $this->assertEqualsWithDelta(3.0, $result['satisfaction'], 0.05);
        $this->assertEqualsCanonicalizing(['student', 'anon'], array_column($activity['comments'], 'text'));
    }

    /**
     * Without any participant nothing is filtered.
     */
    public function test_no_participants_counts_everything(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $manager = $gen->create_user();
        $gen->enrol_user($manager->id, $course->id, 'manager');
        $feedback = $gen->create_module('feedback', ['course' => $course->id, 'name' => 'Evaluation']);
        $item = $this->add_item($feedback->id, 'numeric', 'Overall');
        $this->add_response($feedback->id, $manager->id, [$item => '1']);
        $this->add_response($feedback->id, 0, [$item => '5']);

        $result = feedback_metrics::collect($course->id);

        $this->assertSame(2, $result['activities'][0]['responses']);
    }

    /**
     * The public result keeps the legacy shape: enterprise v1.3.x sends it
     * unchanged to the dashboard's strict schema, so no extra key may appear.
     */
    public function test_collect_public_shape_is_legacy(): void {
        $this->resetAfterTest();
        [$course, $feedback, $item] = $this->numeric_fixture([], '1|5');
        $this->add_response($feedback->id, $this->getDataGenerator()->create_user()->id, [$item => '4']);

        $result = feedback_metrics::collect($course->id);

        $this->assertSame(['activities', 'satisfaction'], array_keys($result));
        $this->assertSame(['activities', 'satisfaction'], array_keys(feedback_metrics::collect($course->id + 1000)));
        $this->assertSame(4.0, $result['satisfaction']);
        // The rating totals exist only on the internal variant.
        $internal = feedback_metrics::collect_with_ratings($course->id);
        $this->assertSame(['activities', 'satisfaction', 'ratings'], array_keys($internal));
        $this->assertSame(['sum5' => 4.0, 'n' => 1], $internal['ratings']);
        $this->assertSame($result['activities'], $internal['activities']);
    }

    /**
     * Multichoicerated stores "0" when nothing was chosen: that isn't an answer.
     */
    public function test_multichoicerated_zero_not_counted(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['startdate' => 1730000000]);
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $itemid = $DB->insert_record('feedback_item', (object) [
            'feedback' => $feedback->id, 'template' => 0, 'name' => 'Rate',
            'label' => '', 'presentation' => 'r>>>>>1####Bad|2####Ok|3####Good|4####Great|5####Best',
            'typ' => 'multichoicerated', 'hasvalue' => 1, 'position' => 1,
            'required' => 0, 'dependitem' => 0, 'dependvalue' => '', 'options' => '',
        ]);
        $this->add_response($feedback->id, $gen->create_user()->id, [$itemid => '4']);
        $this->add_response($feedback->id, $gen->create_user()->id, [$itemid => '0']);
        $this->add_response($feedback->id, $gen->create_user()->id, [$itemid => '9']);

        $internal = feedback_metrics::collect_with_ratings($course->id);

        $q = $internal['activities'][0]['questions'][0];
        $this->assertSame(1, $q['responses']);
        $this->assertEqualsWithDelta(4.0, $q['avg'], 0.05);
        $this->assertSame(1, $internal['ratings']['n']);
        $this->assertSame(3, $internal['activities'][0]['responses'], 'Submissions still count.');
    }

    /**
     * Multichoice items send their distribution: one count per option, in
     * form order; multiple answers are "|"-separated; "0" is not an answer.
     */
    public function test_multichoice_sends_choices(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['startdate' => 1730000000]);
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $itemid = $DB->insert_record('feedback_item', (object) [
            'feedback' => $feedback->id, 'template' => 0, 'name' => 'How did you hear about us?',
            'label' => '', 'presentation' => 'c>>>>>Colleague|<b>Website</b>|Newsletter &amp; mail<<<<<1',
            'typ' => 'multichoice', 'hasvalue' => 1, 'position' => 1,
            'required' => 0, 'dependitem' => 0, 'dependvalue' => '', 'options' => '',
        ]);
        $this->add_response($feedback->id, $gen->create_user()->id, [$itemid => '1']);
        $this->add_response($feedback->id, $gen->create_user()->id, [$itemid => '1|2']);
        $this->add_response($feedback->id, $gen->create_user()->id, [$itemid => '0']);

        $q = feedback_metrics::collect($course->id)['activities'][0]['questions'][0];

        $this->assertSame('choice', $q['kind']);
        $this->assertSame(2, $q['responses']);
        $this->assertNull($q['avg']);
        $this->assertSame([
            ['label' => 'Colleague', 'count' => 2],
            ['label' => 'Website', 'count' => 1],
            ['label' => 'Newsletter & mail', 'count' => 0],
        ], $q['choices']);
    }

    /**
     * A feedback instance without a course module (orphan) is not read.
     */
    public function test_orphan_feedback_skipped(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $feedback, $item] = $this->numeric_fixture();
        $this->add_response($feedback->id, $this->getDataGenerator()->create_user()->id, [$item => '5']);
        $DB->delete_records('course_modules', ['id' => $feedback->cmid]);
        rebuild_course_cache($course->id, true);

        $result = feedback_metrics::collect($course->id);

        $this->assertSame([], $result['activities']);
        $this->assertNull($result['satisfaction']);
    }

    /**
     * Contact details and signatures are removed in Moodle, before the answer leaves the site.
     */
    public function test_signed_comments_are_redacted_in_moodle(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $feedback = $gen->create_module('feedback', ['course' => $course->id]);
        $text = $this->add_item($feedback->id, 'textarea', 'What could we improve?', 1);
        $learner = $gen->create_user();
        $this->add_response(
            $feedback->id,
            $learner->id,
            [$text => '<p>The pacing was too fast. Mail me at jane.okonkwo@example.org. Regards, Jane Okonkwo</p>']
        );

        $result = feedback_metrics::collect($course->id);

        $comment = $result['activities'][0]['comments'][0]['text'];
        $this->assertStringContainsString('The pacing was too fast.', $comment);
        $this->assertStringNotContainsString('Okonkwo', $comment);
        $this->assertStringNotContainsString('jane.okonkwo@example.org', $comment);
        $this->assertStringContainsString('[name removed]', $comment);
    }
}
