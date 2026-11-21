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

    /**
     * The fields declared for the dashboard, keyed by name.
     *
     * @return string[]
     */
    private function dashboard_fields(): array {
        $collection = provider::get_metadata(new collection('block_mastermind_assistant'));
        foreach ($collection->get_collection() as $item) {
            if ($item->get_name() === 'mastermind_dashboard') {
                return $item->get_privacy_fields();
            }
        }
        return [];
    }

    /**
     * Everything the plugin sends to the dashboard is declared.
     */
    public function test_declares_everything_the_plugin_sends(): void {
        $fields = $this->dashboard_fields();

        foreach (
            ['coursename', 'coursedata', 'activityname', 'submissioncontent', 'evaluationcomments',
                'teachercontent', 'siteconnection', 'actor'] as $field
        ) {
            $this->assertArrayHasKey($field, $fields, $field);
        }
        // Generation events are no longer sent, so they are no longer declared.
        $this->assertArrayNotHasKey('usageevents', $fields);
    }

    /**
     * The declarations say what is reduced in Moodle before anything is sent.
     */
    public function test_declarations_describe_what_moodle_removes_before_sending(): void {
        $fields = $this->dashboard_fields();
        $text = fn(string $field) => get_string($fields[$field], 'block_mastermind_assistant');

        $this->assertStringContainsString('removed in Moodle', $text('evaluationcomments'));
        $this->assertStringContainsString('five learners', $text('coursedata'));
        $this->assertStringContainsString('removed in Moodle', $text('submissioncontent'));
        $this->assertStringContainsString('PDF', $text('submissioncontent'));
    }

    /**
     * The summary promises no more than Moodle removes: learners' free text can still carry a name.
     *
     * Until v4.4.0 it said that identity a learner typed "is removed in Moodle before anything is
     * sent", which the heuristic redaction cannot guarantee (names in running text, other people's
     * names, anything inside PDF or Word files).
     */
    public function test_summary_states_the_limits_of_text_redaction(): void {
        $summary = get_string('privacy:metadata:mastermind_dashboard', 'block_mastermind_assistant');
        $this->assertStringNotContainsString('removed in Moodle before anything is sent', $summary);
        $this->assertStringContainsString('not completely', $summary);
        $this->assertStringContainsString('PDF', $summary);

        $fields = $this->dashboard_fields();
        $evaluation = get_string($fields['evaluationcomments'], 'block_mastermind_assistant');
        $this->assertStringContainsString('average ratings', $evaluation);
    }

    /**
     * The pseudonym goes with every request a logged-in user makes (build_headers() adds it to all of
     * them), and the dashboard keeps it only with AI requests: the declaration says both.
     */
    public function test_actor_declaration_says_when_it_is_sent_and_kept(): void {
        $fields = $this->dashboard_fields();
        $actor = get_string($fields['actor'], 'block_mastermind_assistant');
        $this->assertStringContainsString('every request', $actor);
        $this->assertStringContainsString('only with requests to AI features', $actor);
    }
}
