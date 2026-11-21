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
 * Tests for the outbound payload sanitizer.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_mastermind_assistant\local\outbound_sanitizer
 */
final class outbound_sanitizer_test extends \basic_testcase {
    /**
     * A legacy-shaped course payload (as an older browser build could still
     * post back) loses every per-learner array and identity key.
     */
    public function test_strips_identity_from_legacy_course_payload(): void {
        $payload = [
            'course' => ['id' => 3, 'fullname' => 'Patient Safety', 'shortname' => 'PS'],
            'sections' => [['id' => 1, 'section' => 1, 'name' => 'Intro', 'activities' => []]],
            'activities' => [['id' => 9, 'module' => 'page', 'name' => 'Welcome']],
            'users' => [['id' => 5, 'username' => 'zq', 'firstname' => 'Zelda', 'email' => 'z@example.com']],
            'grades' => [[
                'item' => ['id' => 1, 'itemname' => 'Quiz', 'grademax' => 10],
                'grades' => [['userid' => 5, 'finalgrade' => 8]],
            ]],
            'completions' => [['coursemoduleid' => 9, 'userid' => 5, 'completionstate' => 1]],
            'forum_posts' => [['id' => 1, 'userid' => 5, 'subject' => 'Hi', 'message' => 'Body']],
            'feedback' => ['activities' => [], 'satisfaction' => null],
        ];

        $clean = outbound_sanitizer::strip_identity($payload);

        $this->assertArrayNotHasKey('users', $clean);
        $this->assertArrayNotHasKey('completions', $clean);
        $this->assertArrayNotHasKey('forum_posts', $clean);
        $this->assertArrayNotHasKey('grades', $clean['grades'][0]);
        $this->assertSame(['id' => 1, 'itemname' => 'Quiz', 'grademax' => 10], $clean['grades'][0]['item']);
        $this->assertSame('Patient Safety', $clean['course']['fullname']);
        $this->assertSame($payload['sections'], $clean['sections']);
        $this->assertSame($payload['activities'], $clean['activities']);
        $this->assertDoesNotMatchRegularExpression(
            '/"(userid|user_id|username|email|firstname|lastname|idnumber)"/i',
            json_encode($clean)
        );
    }

    /**
     * Identity keys nested anywhere are removed, whatever the surrounding shape.
     */
    public function test_strips_nested_identity_keys(): void {
        $payload = ['a' => [['b' => ['userid' => 1, 'keep' => 'x', 'email' => 'e']], ['user_id' => 2]]];

        $clean = outbound_sanitizer::strip_identity($payload);

        $this->assertSame(['a' => [['b' => ['keep' => 'x']], []]], $clean);
    }

    /**
     * A payload that already carries no identity is returned unchanged.
     */
    public function test_clean_payload_is_unchanged(): void {
        $payload = [
            'course' => ['id' => 3, 'fullname' => 'Patient Safety'],
            'sections' => [],
            'activities' => [],
            'enrolment' => ['enrolled' => 4, 'active_30d' => 1, 'roles' => ['student' => 3]],
            'grades' => [['item' => ['itemname' => 'Quiz'], 'summary' => ['graded' => 3, 'avg_finalgrade' => 6.0]]],
        ];

        $this->assertSame($payload, outbound_sanitizer::strip_identity($payload));
    }

    /**
     * A current course payload, as get_course_data builds it.
     *
     * @return array
     */
    private function course_payload(): array {
        return [
            'course' => ['id' => 3, 'fullname' => 'Patient Safety', 'summary' => 'About safety'],
            'sections' => [['id' => 1, 'section' => 1, 'name' => 'Intro', 'summary' => '', 'activities' => [
                ['id' => 9, 'module' => 'quiz', 'name' => 'Check-in', 'section' => 1,
                    'module_data' => ['intro' => 'Teacher notes', 'questions' => '1,2,0']],
            ]]],
            'activities' => [['id' => 9, 'module' => 'quiz', 'name' => 'Check-in', 'section' => 1,
                'module_data' => ['intro' => 'Teacher notes', 'questions' => '1,2,0']]],
            'enrolment' => ['enrolled' => 14, 'active_30d' => 9, 'roles' => ['student' => 12]],
            'grades' => [['item' => ['itemname' => 'Check-in', 'itemmodule' => 'quiz', 'grademax' => 10],
                'summary' => ['graded' => 2, 'avg_finalgrade' => 8.5, 'min_finalgrade' => 7.0, 'max_finalgrade' => 10.0,
                    'passed' => 2]]],
            'forums' => [['name' => 'News', 'discussions' => 1, 'posts' => 1, 'replies' => 0]],
            'forum_activity' => ['posts_total' => 1, 'discussions_total' => 1, 'posts_by_month' => ['2026-10' => 1]],
            'audit_flags' => ['past_due_dates' => [], 'old_year_references' => [], 'empty_sections' => [],
                'missing_enrollments' => false],
            'feedback' => ['activities' => [['cmid' => 4, 'name' => 'Evaluation', 'responses' => 3, 'questions' => [
                ['question' => 'Overall', 'responses' => 3, 'avg' => 4.3],
            ], 'comments' => []]], 'satisfaction' => 4.3],
            'scorm_performance' => [['name' => 'Module', 'attempts' => 4, 'avg_score' => 55.0, 'completion_rate' => 50.0,
                'scoes' => [['title' => 'Unit', 'attempts' => 4, 'avg_score' => 55.0, 'pass_rate' => 50.0]]]],
            'scorm_summary' => ['avg_score' => 55.0, 'completion_rate' => 50.0, 'weakest_sco' => 'Unit (avg 55%)'],
        ];
    }

    public function test_for_dashboard_holds_back_small_grade_and_scorm_figures(): void {
        $clean = outbound_sanitizer::for_dashboard($this->course_payload());

        $this->assertSame(['graded' => 2, 'avg_finalgrade' => null, 'passed' => 2], $clean['grades'][0]['summary']);
        $this->assertNull($clean['scorm_performance'][0]['avg_score']);
        $this->assertNull($clean['scorm_performance'][0]['scoes'][0]['pass_rate']);
        // No unit has five learners, so there is no course summary to send.
        $this->assertArrayNotHasKey('scorm_summary', $clean);
        // Evaluation ratings keep their n and their average.
        $this->assertSame(4.3, $clean['feedback']['activities'][0]['questions'][0]['avg']);
        $this->assertSame(14, $clean['enrolment']['enrolled']);
    }

    public function test_for_dashboard_drops_fields_no_prompt_uses(): void {
        $clean = outbound_sanitizer::for_dashboard($this->course_payload());

        $this->assertArrayNotHasKey('forums', $clean);
        $this->assertArrayNotHasKey('forum_activity', $clean);
        $this->assertArrayNotHasKey('audit_flags', $clean);
        $this->assertArrayNotHasKey('module_data', $clean['activities'][0]);
        $this->assertArrayNotHasKey('module_data', $clean['sections'][0]['activities'][0]);
        $this->assertSame('Check-in', $clean['activities'][0]['name']);
        $this->assertSame('quiz', $clean['sections'][0]['activities'][0]['module']);
    }

    public function test_for_dashboard_strips_identity_as_well(): void {
        $payload = $this->course_payload();
        $payload['users'] = [['id' => 5, 'email' => 'z@example.com']];

        $clean = outbound_sanitizer::for_dashboard($payload);

        $this->assertArrayNotHasKey('users', $clean);
    }

    public function test_every_course_payload_sender_uses_for_dashboard(): void {
        global $CFG;
        $dir = $CFG->dirroot . '/blocks/mastermind_assistant/classes/external/';
        foreach (['get_full_analysis', 'get_ai_recommendations', 'get_updated_structure', 'refine_analysis'] as $name) {
            $source = file_get_contents($dir . $name . '.php');
            $this->assertStringContainsString('outbound_sanitizer::for_dashboard(', $source, $name);
            $this->assertStringNotContainsString('outbound_sanitizer::strip_identity(', $source, $name);
        }
    }
}
