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
 * Tests for removing a learner's own identity from their submitted text.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Removal of the submitting learner's names, email, username and id number.
 *
 * Moodle knows who submitted, so these are exact matches against the user
 * record, not guesses. Names are invented.
 *
 * @covers \block_mastermind_assistant\local\learner_identity_redactor
 */
final class learner_identity_redactor_test extends \basic_testcase {
    /**
     * A user record with the identity fields the redactor reads.
     *
     * @param array $fields Overrides.
     * @return \stdClass
     */
    private function user(array $fields = []): \stdClass {
        return (object) array_merge([
            'firstname' => 'Amara',
            'lastname' => 'Okafor',
            'middlename' => '',
            'alternatename' => '',
            'firstnamephonetic' => '',
            'lastnamephonetic' => '',
            'email' => 'amara.okafor@example.edu',
            'username' => 'aokafor',
            'idnumber' => 'S1029384',
        ], $fields);
    }

    public function test_removes_full_name_email_username_and_id_number(): void {
        $text = "Essay by Amara Okafor (amara.okafor@example.edu), login aokafor, student no. S1029384.\n"
            . 'Climate policy needs carbon pricing.';

        $out = learner_identity_redactor::redact($text, [$this->user()]);

        foreach (['Amara', 'Okafor', 'amara.okafor@example.edu', 'aokafor', 'S1029384'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $out);
        }
        $this->assertStringContainsString('Climate policy needs carbon pricing.', $out);
        $this->assertStringContainsString('[name removed]', $out);
        $this->assertStringContainsString('[email removed]', $out);
        $this->assertStringContainsString('[id removed]', $out);
    }

    public function test_removes_surname_first_forms_and_upper_case(): void {
        $out = learner_identity_redactor::redact('OKAFOR, AMARA. Submitted by Okafor Amara.', [$this->user()]);

        $this->assertStringNotContainsStringIgnoringCase('okafor', $out);
        $this->assertStringNotContainsStringIgnoringCase('amara', $out);
    }

    public function test_single_names_match_only_as_capitalised_words(): void {
        // A first name that is also an everyday word must not wipe that word.
        $user = $this->user(['firstname' => 'Will', 'lastname' => 'Mayhew', 'email' => '', 'username' => '', 'idnumber' => '']);

        $out = learner_identity_redactor::redact('I will argue that taxes work. Will wrote this; Mayhew agrees.', [$user]);

        $this->assertStringContainsString('I will argue', $out);
        $this->assertStringNotContainsString('Will wrote', $out);
        $this->assertStringNotContainsString('Mayhew', $out);
    }

    public function test_handles_accents_and_compound_names(): void {
        $user = $this->user([
            'firstname' => 'Zoë', 'lastname' => 'Müller-Lüdenscheidt', 'email' => '', 'username' => '', 'idnumber' => '',
        ]);

        $out = learner_identity_redactor::redact('Zoë Müller-Lüdenscheidt schreibt über Risiko.', [$user]);

        $this->assertStringNotContainsString('Zoë', $out);
        $this->assertStringNotContainsString('Müller', $out);
        $this->assertStringContainsString('schreibt über Risiko.', $out);
    }

    public function test_removes_every_member_of_a_group_submission(): void {
        $second = $this->user(['firstname' => 'Lukas', 'lastname' => 'Brenner', 'email' => 'l.brenner@example.edu',
            'username' => 'lbrenner', 'idnumber' => '']);

        $out = learner_identity_redactor::redact('Team: Amara Okafor and Lukas Brenner.', [$this->user(), $second]);

        foreach (['Amara', 'Okafor', 'Lukas', 'Brenner'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $out);
        }
    }

    public function test_ignores_empty_and_one_letter_fields(): void {
        $user = $this->user(['firstname' => 'A', 'lastname' => '', 'email' => '', 'username' => '', 'idnumber' => '']);
        $text = 'A short answer about inflation.';

        $this->assertSame($text, learner_identity_redactor::redact($text, [$user]));
    }

    public function test_text_without_identity_is_unchanged(): void {
        $text = 'Carbon pricing reduces emissions at the lowest cost.';
        $this->assertSame($text, learner_identity_redactor::redact($text, [$this->user()]));
    }
}
