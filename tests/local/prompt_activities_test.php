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
 * Tests for the course activity list sent with generation requests.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * The generators send the course's other activities as context for the prompt.
 *
 * @covers \block_mastermind_assistant\local\prompt_activities
 */
final class prompt_activities_test extends \advanced_testcase {
    public function test_uses_the_list_the_browser_sent(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $sent = json_encode(['Week 1 quiz (quiz)', 'Readings (page)']);
        $this->assertSame(['Week 1 quiz (quiz)', 'Readings (page)'], prompt_activities::names((int) $course->id, $sent));
    }

    public function test_falls_back_to_the_visible_activities_of_the_course(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_module('page', ['course' => $course->id, 'name' => 'Welcome']);
        $gen->create_module('forum', ['course' => $course->id, 'name' => 'Hidden forum', 'visible' => 0]);

        $names = prompt_activities::names((int) $course->id, '');

        $this->assertContains('Welcome (page)', $names);
        $this->assertNotContains('Hidden forum (forum)', $names);
    }

    public function test_an_empty_or_broken_list_falls_back_too(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_module('page', ['course' => $course->id, 'name' => 'Welcome']);

        $this->assertSame(['Welcome (page)'], prompt_activities::names((int) $course->id, '[]'));
        $this->assertSame(['Welcome (page)'], prompt_activities::names((int) $course->id, '{not json'));
    }
}
