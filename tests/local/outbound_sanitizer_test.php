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
}
