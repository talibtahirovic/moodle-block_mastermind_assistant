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
 * Tests for the privacy provider metadata.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\privacy;

use core_privacy\local\metadata\collection;

/**
 * Tests for the privacy provider metadata.
 *
 * @covers \block_mastermind_assistant\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * The evaluation data declared to the dashboard covers both evaluation
     * sources: Feedback and Questionnaire answers.
     */
    public function test_evaluation_metadata_names_both_sources(): void {
        $collection = provider::get_metadata(new collection('block_mastermind_assistant'));

        $fields = [];
        foreach ($collection->get_collection() as $item) {
            if ($item->get_name() === 'mastermind_dashboard') {
                $fields = $item->get_privacy_fields();
            }
        }
        $this->assertArrayHasKey('evaluationcomments', $fields);
        $text = get_string($fields['evaluationcomments'], 'block_mastermind_assistant');
        $this->assertStringContainsString('Feedback', $text);
        $this->assertStringContainsString('Questionnaire', $text);
    }
}
