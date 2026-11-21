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
 * Tests that the apply functions write only into an activity of the given course.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\external;

/**
 * Activity ownership and the activity-level capability check of the apply functions.
 *
 * A teacher of one course must not be able to write into an activity of a course
 * they do not teach by passing their own course id with a foreign course-module id,
 * and a prohibition set on the activity itself must hold.
 *
 * @covers \block_mastermind_assistant\external\apply_book_chapters
 * @covers \block_mastermind_assistant\external\apply_lesson_pages
 * @covers \block_mastermind_assistant\external\generate_quiz_questions
 * @covers \block_mastermind_assistant\local\activity_access
 * @runTestsInSeparateProcesses
 */
final class activity_ownership_test extends \advanced_testcase {
    /**
     * A logged-in editing teacher of one course, and an activity in a course they do not teach.
     *
     * @param string $modname Module name, e.g. 'book'.
     * @return array [the teacher's course, the foreign activity record (with cmid)]
     */
    private function teacher_and_foreign_activity(string $modname): array {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $own = $generator->create_course();
        $foreign = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $own->id, 'editingteacher');
        $activity = $generator->create_module($modname, ['course' => $foreign->id]);
        $this->setUser($teacher);
        return [$own, $activity];
    }

    /**
     * Run a call and return what it threw, or null when it returned normally.
     *
     * @param callable $call The external function call.
     * @return \Throwable|null
     */
    private function thrown_by(callable $call): ?\Throwable {
        try {
            $call();
        } catch (\Throwable $e) {
            return $e;
        }
        return null;
    }

    public function test_book_chapters_refused_for_a_book_in_another_course(): void {
        global $DB;
        $this->resetAfterTest();
        [$own, $book] = $this->teacher_and_foreign_activity('book');

        $thrown = $this->thrown_by(fn() => apply_book_chapters::execute(
            $own->id,
            $book->cmid,
            json_encode([['title' => 'Injected chapter', 'content' => 'Not yours']])
        ));

        $this->assertInstanceOf(\dml_missing_record_exception::class, $thrown, 'A book in another course must be refused.');
        $this->assertSame(0, $DB->count_records('book_chapters', ['bookid' => $book->id]));
    }

    public function test_lesson_pages_refused_for_a_lesson_in_another_course(): void {
        global $DB;
        $this->resetAfterTest();
        [$own, $lesson] = $this->teacher_and_foreign_activity('lesson');

        $thrown = $this->thrown_by(fn() => apply_lesson_pages::execute(
            $own->id,
            $lesson->cmid,
            json_encode([['title' => 'Injected page', 'content' => 'Not yours', 'page_type' => 'content']])
        ));

        $this->assertInstanceOf(\dml_missing_record_exception::class, $thrown, 'A lesson in another course must be refused.');
        $this->assertSame(0, $DB->count_records('lesson_pages', ['lessonid' => $lesson->id]));
    }

    public function test_quiz_insert_refused_for_a_quiz_in_another_course(): void {
        global $DB;
        $this->resetAfterTest();
        [$own, $quiz] = $this->teacher_and_foreign_activity('quiz');

        $question = [
            'name' => 'Injected question',
            'questiontext' => 'Is this your quiz?',
            'qtype' => 'truefalse',
            'correctanswer' => false,
            'defaultmark' => 1,
            'generalfeedback' => '',
        ];
        // The quiz function raises the PHP time limit itself; put it back afterwards
        // so the test framework's environment check stays clean.
        $timelimit = (int) ini_get('max_execution_time');
        $thrown = $this->thrown_by(fn() => generate_quiz_questions::execute(
            $own->id,
            $quiz->cmid,
            'Quiz',
            '',
            '',
            1,
            '',
            '',
            '[]',
            false,
            json_encode([$question])
        ));
        set_time_limit($timelimit);

        $this->assertNotNull($thrown, 'A quiz in another course must be refused.');
        $this->assertStringContainsString('Quiz not found', $thrown->getMessage());
        $this->assertSame(0, $DB->count_records('quiz_slots', ['quizid' => $quiz->id]));
    }

    public function test_book_chapters_respect_a_prohibition_on_the_activity(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $book = $generator->create_module('book', ['course' => $course->id]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability(
            'moodle/course:manageactivities',
            CAP_PROHIBIT,
            $roleid,
            \context_module::instance($book->cmid)->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $thrown = $this->thrown_by(fn() => apply_book_chapters::execute(
            $course->id,
            $book->cmid,
            json_encode([['title' => 'Chapter', 'content' => 'Text']])
        ));

        $this->assertInstanceOf(\required_capability_exception::class, $thrown);
        $this->assertSame(0, $DB->count_records('book_chapters', ['bookid' => $book->id]));
    }

    public function test_book_chapters_still_apply_to_a_book_in_the_teachers_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $book = $generator->create_module('book', ['course' => $course->id]);
        $this->setUser($teacher);

        $result = apply_book_chapters::execute(
            $course->id,
            $book->cmid,
            json_encode([['title' => 'Chapter one', 'content' => 'Text']])
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $DB->count_records('book_chapters', ['bookid' => $book->id]));
    }
}
