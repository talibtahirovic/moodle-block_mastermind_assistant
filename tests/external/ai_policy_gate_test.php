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
 * Tests that the AI usage policy is enforced on the server.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\external;

/**
 * The AI usage policy gate on every external function that sends data to the dashboard.
 *
 * The browser asks for acceptance before any AI request, but a direct
 * web-service call used to skip that modal. Each function must refuse before
 * it builds a payload or contacts the dashboard, unless the user accepted the
 * policy. No API key is configured in these tests, so a function that passes
 * the gate fails later with a different message.
 *
 * @covers \block_mastermind_assistant\local\ai_policy
 * @runTestsInSeparateProcesses
 */
final class ai_policy_gate_test extends \advanced_testcase {
    /**
     * Every external function that sends course or learner data to the dashboard.
     *
     * @return array
     */
    public static function gated_functions(): array {
        return [
            'get_full_analysis' => ['get_full_analysis'],
            'get_ai_recommendations' => ['get_ai_recommendations'],
            'get_updated_structure' => ['get_updated_structure'],
            'refine_analysis' => ['refine_analysis'],
            'refine_course' => ['refine_course'],
            'create_course_with_ai' => ['create_course_with_ai'],
            'create_course_from_document' => ['create_course_from_document'],
            'generate_page_content' => ['generate_page_content'],
            'generate_assignment_instructions' => ['generate_assignment_instructions'],
            'generate_forum_content' => ['generate_forum_content'],
            'generate_lesson_content' => ['generate_lesson_content'],
            'generate_glossary_entries' => ['generate_glossary_entries'],
            'generate_book_content' => ['generate_book_content'],
            'generate_url_resource' => ['generate_url_resource'],
            'generate_quiz_questions' => ['generate_quiz_questions'],
            'grading_assist analyze' => ['grading_assist'],
            'generate_report' => ['generate_report'],
        ];
    }

    /**
     * A course with a quiz and an assignment, and the admin logged in.
     *
     * @return array [course, quiz record, assign record]
     */
    private function fixture(): array {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Policy course']);
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $assign = $generator->create_module('assign', ['course' => $course->id]);
        return [$course, $quiz, $assign];
    }

    /**
     * Call one external function with minimal arguments.
     *
     * @param string $function Function name from gated_functions().
     * @param \stdClass $course Course record.
     * @param \stdClass $quiz Quiz record (with cmid).
     * @param \stdClass $assign Assign record (with cmid).
     * @return mixed The function's return value.
     */
    private function call(string $function, \stdClass $course, \stdClass $quiz, \stdClass $assign) {
        $refinement = json_encode([
            'course' => ['id' => $course->id, 'fullname' => 'Policy course'],
            'sections' => [],
            'activities' => [],
            'previous_result' => ['recommendations' => 'Add a quiz', 'structure' => '{"sections":[]}'],
            'conversation' => [],
            'feedback' => 'Make it more practical',
        ]);
        switch ($function) {
            case 'get_full_analysis':
                return get_full_analysis::execute($course->id, '{"course":{"fullname":"Policy course"}}');
            case 'get_ai_recommendations':
                return get_ai_recommendations::execute($course->id, '{"course":{"fullname":"Policy course"}}');
            case 'get_updated_structure':
                return get_updated_structure::execute($course->id, '{"course":{"fullname":"Policy course"}}', '{}');
            case 'refine_analysis':
                return refine_analysis::execute($course->id, $refinement);
            case 'refine_course':
                return refine_course::execute(json_encode([
                    'course_name' => 'Policy course',
                    'previous_result' => ['course_name' => 'Policy course', 'sections' => []],
                    'conversation' => [],
                    'feedback' => 'Add a section',
                ]));
            case 'create_course_with_ai':
                return create_course_with_ai::execute('Solar Energy 101', 1, true, '');
            case 'create_course_from_document':
                return create_course_from_document::execute(
                    base64_encode(str_repeat('Course notes about solar energy. ', 20)),
                    'text/plain',
                    'notes.txt',
                    1,
                    true,
                    ''
                );
            case 'generate_quiz_questions':
                return generate_quiz_questions::execute($course->id, $quiz->cmid, 'Quiz', '');
            case 'grading_assist':
                return grading_assist::execute($assign->cmid, 'assign', 'analyze', '999999');
            case 'generate_report':
                return generate_report::execute($course->id, 'list enrolled students');
            default:
                // The activity generators: course, course name, activity name, description.
                $class = __NAMESPACE__ . '\\' . $function;
                return $class::execute($course->id, 'Policy course', 'Week 1 activity', 'About the topic');
        }
    }

    /**
     * Everything the call reported, thrown or returned, as one string.
     *
     * @param callable $call The external function call.
     * @return string
     */
    private function output_of(callable $call): string {
        // Some functions raise the PHP time limit themselves; restore it so the
        // test framework's environment check stays clean.
        $timelimit = (int) ini_get('max_execution_time');
        try {
            $output = json_encode($call());
        } catch (\Throwable $e) {
            $output = $e->getMessage();
        }
        set_time_limit($timelimit);
        $this->resetDebugging();
        return $output;
    }

    /**
     * The message a user gets when the policy has not been accepted.
     *
     * @return string
     */
    private function refusal(): string {
        return get_string('ai_policy_declined_msg', 'block_mastermind_assistant');
    }

    /**
     * Without acceptance the function refuses before contacting the dashboard.
     *
     * @dataProvider gated_functions
     * @param string $function External function name.
     */
    public function test_refuses_without_accepted_policy(string $function): void {
        $this->resetAfterTest();
        [$course, $quiz, $assign] = $this->fixture();

        $output = $this->output_of(fn() => $this->call($function, $course, $quiz, $assign));

        $this->assertStringContainsString($this->refusal(), $output);
    }

    /**
     * With acceptance recorded the function gets past the gate.
     *
     * @dataProvider gated_functions
     * @param string $function External function name.
     */
    public function test_passes_the_gate_once_policy_is_accepted(string $function): void {
        $this->resetAfterTest();
        [$course, $quiz, $assign] = $this->fixture();
        set_user_preference(\block_mastermind_assistant\local\ai_policy::PREFERENCE, 1);

        $output = $this->output_of(fn() => $this->call($function, $course, $quiz, $assign));

        $this->assertStringNotContainsString($this->refusal(), $output);
    }

    public function test_listing_grading_items_needs_no_policy(): void {
        $this->resetAfterTest();
        [, , $assign] = $this->fixture();

        $result = grading_assist::execute($assign->cmid, 'assign', 'list');
        $this->resetDebugging();

        $this->assertTrue($result['success']);
    }

    public function test_every_function_that_creates_the_dashboard_client_checks_the_policy(): void {
        global $CFG;
        // Calls that send no course content to the AI service.
        $exempt = ['test_connection.php', 'generate_connect_nonce.php'];
        $dir = $CFG->dirroot . '/blocks/mastermind_assistant/classes/external';

        $missing = [];
        foreach (glob($dir . '/*.php') as $file) {
            $source = file_get_contents($file);
            if (in_array(basename($file), $exempt, true) || strpos($source, 'api_client()') === false) {
                continue;
            }
            if (strpos($source, 'ai_policy::require_accepted()') === false) {
                $missing[] = basename($file);
            }
        }

        $this->assertSame([], $missing, 'These external functions contact the dashboard without the policy check.');
    }
}
