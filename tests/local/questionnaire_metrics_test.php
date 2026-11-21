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
 * Tests for the evaluation questionnaire (mod_questionnaire) collector.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Tests for the evaluation questionnaire collector.
 *
 * Questionnaires, surveys and questions come from the mod_questionnaire data
 * generator; responses are inserted directly so their submission time and
 * completion state can be set.
 *
 * @covers \block_mastermind_assistant\local\questionnaire_metrics
 */
final class questionnaire_metrics_test extends \advanced_testcase {
    /** @var int Question type: text box. */
    const TEXT = 2;
    /** @var int Question type: essay. */
    const ESSAY = 3;
    /** @var int Question type: yes/no. */
    const YESNO = 1;
    /** @var int Question type: radio buttons. */
    const RADIO = 4;
    /** @var int Question type: dropdown. */
    const DROP = 6;
    /** @var int Question type: rate (Likert grid). */
    const RATE = 8;
    /** @var int Question type: slider. */
    const SLIDER = 11;

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
     * Create a questionnaire activity (with its survey) in a course.
     *
     * @param \stdClass $course Course.
     * @param string $name Activity name.
     * @param array $record Extra module fields (e.g. visible).
     * @return \stdClass {id, sid, cmid, instance}
     */
    private function create_questionnaire(\stdClass $course, string $name, array $record = []): \stdClass {
        $questionnaire = $this->getDataGenerator()->create_module(
            'questionnaire',
            ['course' => $course->id, 'name' => $name] + $record
        );
        $cm = get_coursemodule_from_instance('questionnaire', $questionnaire->id, $course->id, false, MUST_EXIST);
        return (object) [
            'id' => (int) $questionnaire->id,
            'sid' => (int) $questionnaire->sid,
            'cmid' => (int) $cm->id,
            'instance' => $questionnaire,
        ];
    }

    /**
     * Add a question (and its choices) through the questionnaire generator.
     *
     * @param \stdClass $questionnaire From create_questionnaire().
     * @param int $typeid Question type id.
     * @param string $content Question content (HTML).
     * @param string $name Question short name (often empty in real data).
     * @param array $fields Extra question fields (length, precise, extradata).
     * @param string[] $choices Choice contents, in form order.
     * @return array [questionid, choiceids in form order]
     */
    private function add_question(
        \stdClass $questionnaire,
        int $typeid,
        string $content,
        string $name = '',
        array $fields = [],
        array $choices = []
    ): array {
        global $DB;
        $record = ['surveyid' => $questionnaire->sid, 'type_id' => $typeid, 'name' => $name, 'content' => $content]
            + $fields;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_questionnaire');
        $question = $generator->create_question($questionnaire->instance, $record, $choices ?: null);
        $choiceids = array_map(
            'intval',
            array_keys($DB->get_records('questionnaire_quest_choice', ['question_id' => $question->id], 'id ASC', 'id'))
        );
        return [(int) $question->id, $choiceids];
    }

    /**
     * Insert a questionnaire_response row.
     *
     * @param int $questionnaireid Questionnaire instance id.
     * @param int $submitted Submission time (0 = now).
     * @param string $complete 'y' or 'n'.
     * @param int $userid Respondent (0 = anonymous).
     * @return int Response id.
     */
    private function add_response(
        int $questionnaireid,
        int $submitted = 0,
        string $complete = 'y',
        int $userid = 0
    ): int {
        global $DB;
        return $DB->insert_record('questionnaire_response', (object) [
            'questionnaireid' => $questionnaireid,
            'submitted' => $submitted ?: time(),
            'complete' => $complete,
            'grade' => 0,
            'userid' => $userid,
        ]);
    }

    /**
     * Insert a questionnaire_response_rank row (rate answer; -1 = N/A).
     *
     * @param int $responseid Response id.
     * @param int $questionid Question id.
     * @param int $choiceid Rated row (choice) id.
     * @param int $value 1-based scale value, or -1.
     */
    private function add_rank(int $responseid, int $questionid, int $choiceid, int $value): void {
        global $DB;
        $DB->insert_record('questionnaire_response_rank', (object) [
            'response_id' => $responseid,
            'question_id' => $questionid,
            'choice_id' => $choiceid,
            'rankvalue' => $value,
        ]);
    }

    /**
     * Insert a questionnaire_response_text row (text, essay and slider answers).
     *
     * @param int $responseid Response id.
     * @param int $questionid Question id.
     * @param string $text Answer.
     */
    private function add_text(int $responseid, int $questionid, string $text): void {
        global $DB;
        $DB->insert_record('questionnaire_response_text', (object) [
            'response_id' => $responseid,
            'question_id' => $questionid,
            'response' => $text,
        ]);
    }

    /**
     * Insert a questionnaire_resp_single row (radio and dropdown answers).
     *
     * @param int $responseid Response id.
     * @param int $questionid Question id.
     * @param int $choiceid Chosen choice id.
     */
    private function add_single(int $responseid, int $questionid, int $choiceid): void {
        global $DB;
        $DB->insert_record('questionnaire_resp_single', (object) [
            'response_id' => $responseid,
            'question_id' => $questionid,
            'choice_id' => $choiceid,
        ]);
    }

    /**
     * One response per value on one rated row.
     *
     * @param \stdClass $questionnaire From create_questionnaire().
     * @param int $questionid Rate question id.
     * @param int[] $choiceids Rated rows.
     * @param int[][] $values Per response: one value per row, in row order.
     */
    private function add_rate_responses(\stdClass $questionnaire, int $questionid, array $choiceids, array $values): void {
        foreach ($values as $row) {
            $rid = $this->add_response($questionnaire->id);
            foreach ($row as $i => $value) {
                $this->add_rank($rid, $questionid, $choiceids[$i], $value);
            }
        }
    }

    /**
     * Find the question entry with the given text.
     *
     * @param array $activity Collected activity.
     * @param string $question Question text.
     * @return array
     */
    private function question(array $activity, string $question): array {
        foreach ($activity['questions'] as $q) {
            if ($q['question'] === $question) {
                return $q;
            }
        }
        $this->fail("Question '$question' not collected; got: "
            . implode(' | ', array_column($activity['questions'], 'question')));
    }

    /**
     * A course without questionnaires: empty shape.
     */
    public function test_course_without_questionnaire_returns_empty(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $result = questionnaire_metrics::collect($course->id);

        $this->assertSame(['activities' => [], 'ratings' => ['sum5' => 0.0, 'n' => 0]], $result);
    }

    /**
     * Each rated row becomes its own item, grouped under the question text.
     */
    public function test_rate_rows_become_items(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$qid, $rows] = $this->add_question(
            $q,
            self::RATE,
            '<p>Beurteilung der Gastreferent:innen</p>',
            '',
            ['length' => 5, 'precise' => 0],
            ['Lecturer A', 'Lecturer B']
        );
        $this->add_rate_responses($q, $qid, $rows, [[4, 2], [4, 3], [5, 3], [5, 4]]);

        $result = questionnaire_metrics::collect($course->id);

        $this->assertCount(1, $result['activities']);
        $activity = $result['activities'][0];
        $this->assertSame($q->cmid, $activity['cmid']);
        $this->assertSame('Feedback', $activity['name']);
        $this->assertSame('questionnaire', $activity['module']);
        $this->assertSame(4, $activity['responses']);
        $this->assertSame([], $activity['comments']);
        $this->assertCount(2, $activity['questions']);

        $a = $activity['questions'][0];
        $this->assertSame('Beurteilung der Gastreferent:innen: Lecturer A', $a['question']);
        $this->assertSame('Beurteilung der Gastreferent:innen', $a['group']);
        $this->assertSame('Lecturer A', $a['row']);
        $this->assertSame('rate', $a['type']);
        $this->assertSame('rated', $a['kind']);
        $this->assertSame(4, $a['responses']);
        $this->assertEqualsWithDelta(4.5, $a['avg'], 0.001);
        $this->assertEquals(1, $a['scale_min']);
        $this->assertEquals(5, $a['scale_max']);
        $this->assertArrayNotHasKey('na', $a, 'No N/A column (precise 0): no na key.');

        $b = $activity['questions'][1];
        $this->assertSame('Beurteilung der Gastreferent:innen: Lecturer B', $b['question']);
        $this->assertSame('Lecturer B', $b['row']);
        $this->assertEqualsWithDelta(3.0, $b['avg'], 0.001);

        // 5-point values pass through to_five_point unchanged: 18 + 12 over 8 answers.
        $this->assertEqualsWithDelta(30.0, $result['ratings']['sum5'], 0.001);
        $this->assertSame(8, $result['ratings']['n']);
    }

    /**
     * A row everyone marked N/A (a lecturer nobody saw): n 0, no average,
     * N/A counted, and nothing added to the ratings.
     */
    public function test_rate_row_all_na(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$qid, $rows] = $this->add_question(
            $q,
            self::RATE,
            '<p>Beurteilung der Gastreferent:innen</p>',
            '',
            ['length' => 5, 'precise' => 1],
            ['Lecturer A', 'Lecturer B']
        );
        $this->add_rate_responses($q, $qid, $rows, [[-1, 4], [-1, 4], [-1, 5], [-1, 5]]);

        $result = questionnaire_metrics::collect($course->id);

        $activity = $result['activities'][0];
        $a = $this->question($activity, 'Beurteilung der Gastreferent:innen: Lecturer A');
        $this->assertSame(0, $a['responses']);
        $this->assertNull($a['avg']);
        $this->assertSame(4, $a['na']);
        $b = $this->question($activity, 'Beurteilung der Gastreferent:innen: Lecturer B');
        $this->assertSame(4, $b['responses']);
        $this->assertEqualsWithDelta(4.5, $b['avg'], 0.001);
        $this->assertSame(0, $b['na']);
        $this->assertSame(4, $result['ratings']['n']);
        $this->assertEqualsWithDelta(18.0, $result['ratings']['sum5'], 0.001);
    }

    /**
     * N/A answers mixed with ratings: only the ratings are averaged and counted.
     */
    public function test_rate_row_mixed_na(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$qid, $rows] = $this->add_question(
            $q,
            self::RATE,
            '<p>Beurteilung der Gastreferent:innen</p>',
            '',
            ['length' => 5, 'precise' => 1],
            ['Lecturer A', 'Lecturer B']
        );
        $this->add_rate_responses($q, $qid, [$rows[0]], [[4], [-1], [5], [-1]]);

        $result = questionnaire_metrics::collect($course->id);

        $item = $this->question($result['activities'][0], 'Beurteilung der Gastreferent:innen: Lecturer A');
        $this->assertSame(2, $item['responses']);
        $this->assertEqualsWithDelta(4.5, $item['avg'], 0.001);
        $this->assertSame(2, $item['na']);
        $this->assertSame(2, $result['ratings']['n']);
        $this->assertEqualsWithDelta(9.0, $result['ratings']['sum5'], 0.001);
    }

    /**
     * A ranking (rate with "no duplicate choices", precise 2) isn't a rating:
     * it doesn't make the questionnaire an evaluation, and when the activity is
     * read anyway (forced in) it gives no items and nothing in the ratings.
     */
    public function test_rate_ranking_not_rated(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Kursumfrage');
        [$qid, $rows] = $this->add_question(
            $q,
            self::RATE,
            '<p>Ordnen Sie die Themen</p>',
            '',
            ['length' => 3, 'precise' => 2],
            ['Thema 1', 'Thema 2', 'Thema 3']
        );
        $this->add_rate_responses($q, $qid, $rows, [[1, 2, 3], [2, 1, 3]]);

        $this->assertSame([], questionnaire_metrics::collect($course->id)['activities'], 'A ranking is no evaluation.');
        $this->assertSame([], questionnaire_metrics::rated_questionnaire_ids($course->id));

        $result = questionnaire_metrics::collect($course->id, [$q->cmid]);

        $this->assertCount(1, $result['activities']);
        $this->assertSame([], $result['activities'][0]['questions']);
        $this->assertSame(['sum5' => 0.0, 'n' => 0], $result['ratings']);
    }

    /**
     * Osgood rows ("left|right") read "left – right"; "other" rows aren't rated lines.
     */
    public function test_rate_row_labels(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$osgood, $orows] = $this->add_question(
            $q,
            self::RATE,
            '<p>Der Kurs war</p>',
            '',
            ['length' => 5, 'precise' => 3],
            ['langweilig|spannend']
        );
        [$lecturers, $lrows] = $this->add_question(
            $q,
            self::RATE,
            '<p>Gastreferent:innen</p>',
            '',
            ['length' => 5, 'precise' => 0],
            ['Lecturer A', '!other=Weitere:']
        );
        $rid = $this->add_response($q->id);
        $this->add_rank($rid, $osgood, $orows[0], 4);
        $this->add_rank($rid, $lecturers, $lrows[0], 5);
        $this->add_rank($rid, $lecturers, $lrows[1], 1);

        $result = questionnaire_metrics::collect($course->id);

        $questions = $result['activities'][0]['questions'];
        $this->assertSame(
            ['Der Kurs war: langweilig – spannend', 'Gastreferent:innen: Lecturer A'],
            array_column($questions, 'question')
        );
        $this->assertSame('langweilig – spannend', $questions[0]['row']);
        $this->assertSame(2, $result['ratings']['n'], 'The "other" row adds nothing.');
    }

    /**
     * Named degrees with keys 0..4: the scale is 0-4 and converts from there.
     */
    public function test_named_degree_scale_zero_based(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$qid, $rows] = $this->add_question($q, self::RATE, '<p>Didaktik</p>', '', [
            'length' => 5,
            'extradata' => '["schlecht","eher schlecht","mittel","eher gut","sehr gut"]',
        ], ['Didaktik']);
        // 5 isn't one of the degrees: ignored.
        $this->add_rate_responses($q, $qid, $rows, [[0], [4], [2], [5]]);

        $result = questionnaire_metrics::collect($course->id);

        $item = $result['activities'][0]['questions'][0];
        $this->assertSame(3, $item['responses']);
        $this->assertEqualsWithDelta(2.0, $item['avg'], 0.001);
        $this->assertEquals(0, $item['scale_min']);
        $this->assertEquals(4, $item['scale_max']);
        // Converted to 1-5 on 0-4: 0 is 1, 4 is 5, 2 is 3.
        $this->assertEqualsWithDelta(9.0, $result['ratings']['sum5'], 0.001);
        $this->assertSame(3, $result['ratings']['n']);
    }

    /**
     * Named degrees with keys 10/20/30: the scale is 10-30.
     */
    public function test_named_degree_scale_tens(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$qid, $rows] = $this->add_question($q, self::RATE, '<p>Didaktik</p>', '', [
            'length' => 3,
            'extradata' => '{"10":"niedrig","20":"mittel","30":"hoch"}',
        ], ['Didaktik']);
        // 2 isn't one of the degrees: ignored.
        $this->add_rate_responses($q, $qid, $rows, [[10], [30], [20], [30], [2]]);

        $result = questionnaire_metrics::collect($course->id);

        $item = $result['activities'][0]['questions'][0];
        $this->assertSame(4, $item['responses']);
        $this->assertEqualsWithDelta(22.5, $item['avg'], 0.001);
        $this->assertEquals(10, $item['scale_min']);
        $this->assertEquals(30, $item['scale_max']);
        // Converted to 1-5 on 10-30: 10 is 1, 20 is 3, 30 is 5.
        $this->assertEqualsWithDelta(14.0, $result['ratings']['sum5'], 0.001);
        $this->assertSame(4, $result['ratings']['n']);
    }

    /**
     * A 1-10 overall rating keeps its own scale; the ratings sum is on 1-5.
     * A single row without its own text is sent as a plain item (no group/row).
     */
    public function test_ten_point_rating_converts_to_five_point(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback + Learnings');
        [$qid, $rows] = $this->add_question(
            $q,
            self::RATE,
            '<p>Gesamtbeurteilung</p>',
            'gesamt',
            ['length' => 10, 'precise' => 0],
            ['Gesamtbeurteilung']
        );
        $this->add_rate_responses($q, $qid, $rows, [[10], [1]]);

        $result = questionnaire_metrics::collect($course->id);

        $item = $result['activities'][0]['questions'][0];
        $this->assertSame('Gesamtbeurteilung', $item['question']);
        $this->assertArrayNotHasKey('row', $item);
        $this->assertArrayNotHasKey('group', $item);
        $this->assertEqualsWithDelta(5.5, $item['avg'], 0.001);
        $this->assertEquals(10, $item['scale_max']);
        $this->assertSame(2, $item['responses']);
        // Converted to 1-5: 10 of 10 is 5, 1 of 10 is 1.
        $this->assertEqualsWithDelta(6.0, $result['ratings']['sum5'], 0.001);
        $this->assertSame(2, $result['ratings']['n']);
    }

    /**
     * A slider is one rated item on the range from its extradata; a slider
     * whose range can't be read is skipped.
     */
    public function test_slider_uses_extradata_range(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Kursumfrage');
        [$slider] = $this->add_question($q, self::SLIDER, '<p>Wie praxisnah war der Kurs?</p>', '', [
            'extradata' => json_encode(['minrange' => '0', 'maxrange' => '100', 'startingvalue' => '50',
                'stepvalue' => '1', 'leftlabel' => 'gar nicht', 'rightlabel' => 'sehr']),
        ]);
        [$broken] = $this->add_question($q, self::SLIDER, '<p>Kaputt</p>', '', ['extradata' => 'not json']);
        foreach (['50', '100'] as $value) {
            $rid = $this->add_response($q->id);
            $this->add_text($rid, $slider, $value);
            $this->add_text($rid, $broken, '3');
        }

        $result = questionnaire_metrics::collect($course->id);

        $this->assertCount(1, $result['activities'], 'A slider question makes it an evaluation.');
        $questions = $result['activities'][0]['questions'];
        $this->assertCount(1, $questions, 'The unreadable slider is skipped.');
        $item = $questions[0];
        $this->assertSame('Wie praxisnah war der Kurs?', $item['question']);
        $this->assertSame('slider', $item['type']);
        $this->assertSame('rated', $item['kind']);
        $this->assertSame(2, $item['responses']);
        $this->assertEqualsWithDelta(75.0, $item['avg'], 0.001);
        $this->assertEquals(0, $item['scale_min']);
        $this->assertEquals(100, $item['scale_max']);
        // Converted to 1-5: 50 of 0-100 is 3, 100 is 5.
        $this->assertEqualsWithDelta(8.0, $result['ratings']['sum5'], 0.001);
        $this->assertSame(2, $result['ratings']['n']);
    }

    /**
     * Text questions take their text from the content (HTML stripped,
     * attributes ignored), falling back to the short name.
     */
    public function test_text_answers_use_content(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['startdate' => time() - DAYSECS]);
        $q = $this->create_questionnaire($course, 'Feedback');
        [$liked] = $this->add_question($q, self::ESSAY, '<p>Was hat mir gefallen?</p>', '');
        [$takeaway] = $this->add_question(
            $q,
            self::TEXT,
            '<p><span aria-label="Allergien bitte angeben">Was nehme ich mit?</span></p>',
            'mitnehmen'
        );
        [$improve] = $this->add_question($q, self::ESSAY, '', 'Verbesserungen');
        $now = time();
        $r1 = $this->add_response($q->id, $now - 100);
        $this->add_text($r1, $liked, '<p>Die Praxisbeispiele</p>');
        $this->add_text($r1, $takeaway, '   ');
        $this->add_text($r1, $improve, 'Mehr Pausen');
        $r2 = $this->add_response($q->id, $now - 10);
        $this->add_text($r2, $liked, 'Struktur &amp; Tempo');

        $result = questionnaire_metrics::collect($course->id);

        $activity = $result['activities'][0];
        $this->assertSame([
            ['question' => 'Was hat mir gefallen?', 'type' => 'essay', 'kind' => 'text', 'responses' => 2, 'avg' => null],
            ['question' => 'Was nehme ich mit?', 'type' => 'text', 'kind' => 'text', 'responses' => 0, 'avg' => null],
            ['question' => 'Verbesserungen', 'type' => 'essay', 'kind' => 'text', 'responses' => 1, 'avg' => null],
        ], $activity['questions']);
        $this->assertSame([
            ['questionName' => 'Was hat mir gefallen?', 'questionType' => 'essay', 'text' => 'Struktur & Tempo'],
            ['questionName' => 'Was hat mir gefallen?', 'questionType' => 'essay', 'text' => 'Die Praxisbeispiele'],
            ['questionName' => 'Verbesserungen', 'questionType' => 'essay', 'text' => 'Mehr Pausen'],
        ], $activity['comments']);
    }

    /**
     * Comments are clamped to 400 characters and listed newest first.
     */
    public function test_comments_clamped_newest_first(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['startdate' => time() - DAYSECS]);
        $q = $this->create_questionnaire($course, 'Feedback');
        [$essay] = $this->add_question($q, self::ESSAY, '<p>Kommentar</p>');
        $now = time();
        $this->add_text($this->add_response($q->id, $now - 300), $essay, 'oldest');
        $this->add_text($this->add_response($q->id, $now - 10), $essay, str_repeat('ä', 500));
        $this->add_text($this->add_response($q->id, $now - 200), $essay, 'middle');

        $comments = questionnaire_metrics::collect($course->id)['activities'][0]['comments'];

        $this->assertCount(3, $comments);
        $this->assertSame(400, \core_text::strlen($comments[0]['text']));
        $this->assertSame('middle', $comments[1]['text']);
        $this->assertSame('oldest', $comments[2]['text']);
    }

    /**
     * Radio and dropdown questions are distributions in form order, with
     * zero counts kept and no average.
     */
    public function test_radio_distribution(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$radio, $levels] = $this->add_question(
            $q,
            self::RADIO,
            '<p>Wie sind Sie mit der Veranstaltung insgesamt zufrieden?</p>',
            '',
            [],
            ['sehr zufrieden', 'zufrieden', 'kaum zufrieden', 'unzufrieden']
        );
        [$drop, $sources] = $this->add_question(
            $q,
            self::DROP,
            '<p>Wie haben Sie vom Kurs erfahren?</p>',
            '',
            [],
            ['<b>Website</b>', 'Kollegen']
        );
        $answers = array_merge(array_fill(0, 6, 0), array_fill(0, 3, 1), [2]);
        foreach ($answers as $i => $level) {
            $rid = $this->add_response($q->id);
            $this->add_single($rid, $radio, $levels[$level]);
            if ($i < 3) {
                $this->add_single($rid, $drop, $sources[$i === 0 ? 0 : 1]);
            }
        }

        $result = questionnaire_metrics::collect($course->id);

        $activity = $result['activities'][0];
        $this->assertSame(10, $activity['responses']);
        $this->assertSame([
            'question' => 'Wie sind Sie mit der Veranstaltung insgesamt zufrieden?',
            'type' => 'radio',
            'kind' => 'choice',
            'responses' => 10,
            'avg' => null,
            'choices' => [
                ['label' => 'sehr zufrieden', 'count' => 6],
                ['label' => 'zufrieden', 'count' => 3],
                ['label' => 'kaum zufrieden', 'count' => 1],
                ['label' => 'unzufrieden', 'count' => 0],
            ],
        ], $activity['questions'][0]);
        $this->assertSame([
            'question' => 'Wie haben Sie vom Kurs erfahren?',
            'type' => 'dropdown',
            'kind' => 'choice',
            'responses' => 3,
            'avg' => null,
            'choices' => [
                ['label' => 'Website', 'count' => 1],
                ['label' => 'Kollegen', 'count' => 2],
            ],
        ], $activity['questions'][1]);
        $this->assertSame(0, $result['ratings']['n'], 'Choice questions never feed the ratings.');
    }

    /**
     * Sign-up forms (no rating question, name not an evaluation) are never
     * read: nothing of theirs reaches the result, next to a real evaluation.
     */
    public function test_non_evaluation_not_read(): void {
        global $DB;
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $evaluation = $this->create_questionnaire($course, 'Feedback');
        [$rate, $rows] = $this->add_question($evaluation, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
        $this->add_rank($this->add_response($evaluation->id), $rate, $rows[0], 4);
        foreach (['Anmeldung zur Diplomfeier', 'Rückmeldung zur Diplomfeier'] as $name) {
            $q = $this->create_questionnaire($course, $name);
            [$yesno] = $this->add_question($q, self::YESNO, '<p>Nehmen Sie teil?</p>');
            [$essay] = $this->add_question($q, self::ESSAY, '<p>Allergien</p>');
            [$radio, $options] = $this->add_question(
                $q,
                self::RADIO,
                '<p>Shuttle</p>',
                '',
                [],
                ['Shuttlebus ja', 'Shuttlebus nein']
            );
            $rid = $this->add_response($q->id);
            $DB->insert_record('questionnaire_response_bool', (object) [
                'response_id' => $rid, 'question_id' => $yesno, 'choice_id' => 'y',
            ]);
            $this->add_text($rid, $essay, 'Nüsse');
            $this->add_single($rid, $radio, $options[0]);
        }

        $result = questionnaire_metrics::collect($course->id);

        $this->assertCount(1, $result['activities']);
        $this->assertSame($evaluation->cmid, $result['activities'][0]['cmid']);
        $this->assertSame(1, $result['ratings']['n']);
        $json = json_encode($result, JSON_UNESCAPED_UNICODE);
        foreach (['Diplomfeier', 'Nehmen Sie teil', 'Allergien', 'Nüsse', 'Shuttle'] as $signup) {
            $this->assertStringNotContainsString($signup, $json);
        }
    }

    /**
     * An evaluation with only radio questions is read through its name.
     */
    public function test_name_rule_reads_radio_evaluation(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$radio, $choices] = $this->add_question(
            $q,
            self::RADIO,
            '<p>Beurteilung des Seminars</p>',
            '',
            [],
            ['sehr gut', 'gut', 'genügend', 'ungenügend']
        );
        $this->add_single($this->add_response($q->id), $radio, $choices[1]);

        $result = questionnaire_metrics::collect($course->id);

        $this->assertCount(1, $result['activities']);
        $item = $result['activities'][0]['questions'][0];
        $this->assertSame('choice', $item['kind']);
        $this->assertSame(1, $item['responses']);
        $this->assertSame(1, $item['choices'][1]['count']);
    }

    /**
     * Answers submitted before the course start (an earlier run) don't count.
     */
    public function test_old_answers_ignored(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $start = time() - 30 * DAYSECS;
        $course = $this->getDataGenerator()->create_course(['startdate' => $start]);
        $q = $this->create_questionnaire($course, 'Feedback');
        [$rate, $rows] = $this->add_question($q, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
        [$essay] = $this->add_question($q, self::ESSAY, '<p>Was hat mir gefallen?</p>');
        $old = $this->add_response($q->id, $start - 365 * DAYSECS);
        $this->add_rank($old, $rate, $rows[0], 1);
        $this->add_text($old, $essay, 'from 2025');
        $new = $this->add_response($q->id, $start + DAYSECS);
        $this->add_rank($new, $rate, $rows[0], 5);
        $this->add_text($new, $essay, 'from this run');

        $activity = questionnaire_metrics::collect($course->id)['activities'][0];

        $this->assertSame(1, $activity['responses']);
        $item = $this->question($activity, 'Didaktik');
        $this->assertSame(1, $item['responses']);
        $this->assertEqualsWithDelta(5.0, $item['avg'], 0.001);
        $this->assertSame(['from this run'], array_column($activity['comments'], 'text'));
    }

    /**
     * Unfinished (saved, not submitted) responses don't count.
     */
    public function test_incomplete_ignored(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$rate, $rows] = $this->add_question($q, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
        $this->add_rank($this->add_response($q->id, 0, 'n'), $rate, $rows[0], 1);
        $this->add_rank($this->add_response($q->id), $rate, $rows[0], 4);

        $result = questionnaire_metrics::collect($course->id);

        $activity = $result['activities'][0];
        $this->assertSame(1, $activity['responses']);
        $this->assertEqualsWithDelta(4.0, $activity['questions'][0]['avg'], 0.001);
        $this->assertSame(1, $result['ratings']['n']);
    }

    /**
     * A live question stored the 5.x way (deleted NULL, or 0 where the column
     * can't hold NULL) counts as a rating question; a deleted one is ignored
     * and doesn't make its questionnaire an evaluation.
     */
    public function test_deleted_question_null_is_live(): void {
        global $DB;
        $this->require_questionnaire();
        $this->resetAfterTest();
        $column = $DB->get_columns('questionnaire_question')['deleted'];
        $numeric = in_array($column->meta_type, ['I', 'N'], true);
        $live = $column->not_null ? ($numeric ? 0 : '0') : null;
        $gone = $numeric ? time() : 'y';

        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Kursumfrage');
        [$liverate, $rows] = $this->add_question($q, self::RATE, '<p>Struktur</p>', '', ['length' => 5], ['Struktur']);
        [$oldrate] = $this->add_question($q, self::RATE, '<p>Alte Frage</p>', '', ['length' => 5], ['Alt']);
        $DB->set_field('questionnaire_question', 'deleted', $live, ['id' => $liverate]);
        $DB->set_field('questionnaire_question', 'deleted', $gone, ['id' => $oldrate]);
        $this->add_rank($this->add_response($q->id), $liverate, $rows[0], 4);

        $other = $this->create_questionnaire($course, 'Kursumfrage 2');
        [$onlydeleted] = $this->add_question($other, self::RATE, '<p>Weg</p>', '', ['length' => 5], ['Weg']);
        $DB->set_field('questionnaire_question', 'deleted', $gone, ['id' => $onlydeleted]);

        $result = questionnaire_metrics::collect($course->id);

        $this->assertCount(1, $result['activities']);
        $activity = $result['activities'][0];
        $this->assertSame($q->cmid, $activity['cmid']);
        $this->assertSame(['Struktur'], array_column($activity['questions'], 'question'));
        $this->assertEqualsWithDelta(4.0, $activity['questions'][0]['avg'], 0.001);
    }

    /**
     * Hidden activities are skipped, even when included.
     */
    public function test_hidden_questionnaire_skipped(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback', ['visible' => 0]);
        [$rate, $rows] = $this->add_question($q, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
        $this->add_rank($this->add_response($q->id), $rate, $rows[0], 4);

        $this->assertSame([], questionnaire_metrics::collect($course->id)['activities']);
        $this->assertSame([], questionnaire_metrics::collect($course->id, [$q->cmid])['activities']);
    }

    /**
     * An excluded evaluation is skipped; exclude wins over include.
     */
    public function test_exclude_cmid_skips(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$rate, $rows] = $this->add_question($q, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
        $this->add_rank($this->add_response($q->id), $rate, $rows[0], 4);

        $this->assertCount(1, questionnaire_metrics::collect($course->id)['activities']);
        $excluded = questionnaire_metrics::collect($course->id, [], [$q->cmid]);
        $this->assertSame([], $excluded['activities']);
        $this->assertSame(0, $excluded['ratings']['n']);
        $this->assertSame([], questionnaire_metrics::collect($course->id, [$q->cmid], [$q->cmid])['activities']);
    }

    /**
     * An excluded activity isn't touched at all: it costs no more database
     * reads than a hidden one (its question rows aren't read either).
     */
    public function test_excluded_activity_not_read(): void {
        global $DB;
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Feedback');
        [$rate, $rows] = $this->add_question($q, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
        $this->add_rank($this->add_response($q->id), $rate, $rows[0], 4);

        questionnaire_metrics::collect($course->id, [], [$q->cmid]); // Warm the course caches.
        $before = $DB->perf_get_reads();
        $excluded = questionnaire_metrics::collect($course->id, [], [$q->cmid]);
        $excludedreads = $DB->perf_get_reads() - $before;

        set_coursemodule_visible($q->cmid, 0);
        questionnaire_metrics::collect($course->id); // Warm the rebuilt caches.
        $before = $DB->perf_get_reads();
        $hidden = questionnaire_metrics::collect($course->id);
        $hiddenreads = $DB->perf_get_reads() - $before;

        $this->assertSame([], $excluded['activities']);
        $this->assertSame([], $hidden['activities']);
        $this->assertSame($hiddenreads, $excludedreads);
    }

    /**
     * Include forces a non-evaluation questionnaire in.
     */
    public function test_include_cmid_forces_other_questionnaire(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $q = $this->create_questionnaire($course, 'Erwartungen');
        [$essay] = $this->add_question($q, self::ESSAY, '<p>Was möchten Sie mitnehmen?</p>');
        $this->add_text($this->add_response($q->id), $essay, 'Werkzeuge für den Alltag');

        $this->assertSame([], questionnaire_metrics::collect($course->id)['activities']);
        $result = questionnaire_metrics::collect($course->id, [$q->cmid]);
        $this->assertCount(1, $result['activities']);
        $this->assertSame($q->cmid, $result['activities'][0]['cmid']);
        $this->assertSame(['Werkzeuge für den Alltag'], array_column($result['activities'][0]['comments'], 'text'));
    }

    /**
     * Questionnaires with a live rate or slider question, whatever their
     * visibility or selection; other courses and deleted questions don't count.
     */
    public function test_rated_questionnaire_ids(): void {
        global $DB;
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();

        $rate = $this->create_questionnaire($course, 'Seminar');
        $this->add_question($rate, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
        $slider = $this->create_questionnaire($course, 'Stimmung', ['visible' => 0]);
        $this->add_question($slider, self::SLIDER, '<p>Wie war es?</p>', '', ['extradata' => '{"minrange":1,"maxrange":10}']);
        $text = $this->create_questionnaire($course, 'Erwartungen');
        $this->add_question($text, self::TEXT, '<p>Was erwarten Sie?</p>');
        $deleted = $this->create_questionnaire($course, 'Anmeldung');
        [$gone] = $this->add_question($deleted, self::RATE, '<p>Vorwissen</p>', '', ['length' => 5], ['Thema']);
        $column = $DB->get_columns('questionnaire_question')['deleted'];
        $deletedvalue = in_array($column->meta_type, ['I', 'N'], true) ? time() : 'y';
        $DB->set_field('questionnaire_question', 'deleted', $deletedvalue, ['id' => $gone]);
        $elsewhere = $this->create_questionnaire($other, 'Seminar');
        $this->add_question($elsewhere, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
        $ranking = $this->create_questionnaire($course, 'Themenwahl');
        $this->add_question($ranking, self::RATE, '<p>Ordnen Sie</p>', '', ['length' => 2, 'precise' => 2], ['A', 'B']);

        $expected = [$rate->id, $slider->id];
        sort($expected);
        $this->assertSame($expected, questionnaire_metrics::rated_questionnaire_ids($course->id));
    }

    /**
     * Without the questionnaire tables (plugin not installed): empty shape.
     */
    public function test_no_table_returns_empty(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $empty = ['activities' => [], 'ratings' => ['sum5' => 0.0, 'n' => 0]];

        if (!$DB->get_manager()->table_exists('questionnaire')) {
            $this->assertSame($empty, questionnaire_metrics::collect($course->id));
        } else {
            // A readable evaluation, so the empty result below comes from the table check.
            $q = $this->create_questionnaire($course, 'Feedback');
            [$rate, $rows] = $this->add_question($q, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
            $this->add_rank($this->add_response($q->id), $rate, $rows[0], 4);
            $this->assertCount(1, questionnaire_metrics::collect($course->id)['activities']);
        }

        // Simulate a site without mod_questionnaire.
        $collector = new class extends questionnaire_metrics {
            /**
             * Pretend the questionnaire table is missing.
             *
             * @return bool
             */
            protected static function available(): bool {
                return false;
            }
        };
        $this->assertSame($empty, $collector::collect($course->id));
    }

    /**
     * Build a course with a rate and an essay question and answers from a
     * student, a manager and an anonymous respondent.
     *
     * @param bool $withstudent Whether the student is enrolled (else nobody is a participant).
     * @return array [course, activity result]
     */
    private function participants_fixture(bool $withstudent): array {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['startdate' => time() - DAYSECS]);
        $student = $gen->create_user();
        $manager = $gen->create_user();
        if ($withstudent) {
            $gen->enrol_user($student->id, $course->id, 'student');
        }
        $gen->enrol_user($manager->id, $course->id, 'manager');
        $q = $this->create_questionnaire($course, 'Evaluation');
        [$rate, $rows] = $this->add_question($q, self::RATE, '<p>Didaktik</p>', '', ['length' => 5], ['Didaktik']);
        [$essay] = $this->add_question($q, self::ESSAY, '<p>Kommentar?</p>');
        foreach ([[$student->id, 4, 'student'], [$manager->id, 1, 'manager'], [0, 2, 'anon']] as [$uid, $value, $text]) {
            $response = $this->add_response($q->id, 0, 'y', (int) $uid);
            $this->add_rank($response, $rate, $rows[0], $value);
            $this->add_text($response, $essay, $text);
        }
        return [$course, questionnaire_metrics::collect($course->id)];
    }

    /**
     * A manager's response is excluded everywhere; anonymous ones stay.
     */
    public function test_questionnaire_counts_only_participants(): void {
        [, $result] = $this->participants_fixture(true);
        $activity = $result['activities'][0];
        $this->assertSame(2, $activity['responses']);
        $item = $this->question($activity, 'Didaktik');
        $this->assertSame(2, $item['responses']);
        $this->assertEqualsWithDelta(3.0, $item['avg'], 0.001);
        $this->assertSame(2, $result['ratings']['n']);
        $this->assertEqualsCanonicalizing(['student', 'anon'], array_column($activity['comments'], 'text'));
    }

    /**
     * Without any participant nothing is filtered.
     */
    public function test_no_participants_counts_everything(): void {
        [, $result] = $this->participants_fixture(false);
        $activity = $result['activities'][0];
        $this->assertSame(3, $activity['responses']);
        $this->assertCount(3, $activity['comments']);
        $this->assertSame(3, $result['ratings']['n']);
    }

    /**
     * Contact details and signatures are removed in Moodle, before the answer leaves the site.
     */
    public function test_signed_text_answers_are_redacted_in_moodle(): void {
        $this->require_questionnaire();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['startdate' => time() - DAYSECS]);
        $q = $this->create_questionnaire($course, 'Feedback');
        [$improve] = $this->add_question($q, self::ESSAY, '', 'Verbesserungen');
        $response = $this->add_response($q->id, time() - 10);
        $this->add_text($response, $improve, 'Mehr Pausen bitte. Rufen Sie mich an: +41 79 123 45 67. Viele Grüsse -- Anna Muster');

        $result = questionnaire_metrics::collect($course->id);

        $comment = $result['activities'][0]['comments'][0]['text'];
        $this->assertStringContainsString('Mehr Pausen bitte.', $comment);
        $this->assertStringNotContainsString('Muster', $comment);
        $this->assertStringNotContainsString('123 45 67', $comment);
        $this->assertStringContainsString('[number removed]', $comment);
    }
}
