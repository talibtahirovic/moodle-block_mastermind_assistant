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
 * Tests for the Moodle-side redaction of free-text evaluation answers.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Redaction of contact details and signatures before an answer leaves Moodle.
 *
 * The fixture mirrors the dashboard's own redaction tests (src/__tests__/redact.test.ts)
 * so both layers are held to the same cases. Names are invented.
 *
 * @covers \block_mastermind_assistant\local\comment_redactor
 */
final class comment_redactor_test extends \basic_testcase {
    /**
     * Signed or contact-bearing answers: [raw, fragments that must go, fragment that must stay].
     *
     * @return array
     */
    public static function signed_answers(): array {
        return [
            'dash sign-off' => ["Great module on airway management. -- Jane Okonkwo", ['Jane', 'Okonkwo'], 'airway management'],
            'regards on its own line' => [
                "The pacing was too fast in week 3.\nRegards, Tomasz Wielgosz",
                ['Tomasz', 'Wielgosz'],
                'pacing was too fast',
            ],
            'thanks with title' => ["More case-based questions please. Thanks, Dr. Ayers", ['Ayers'], 'case-based questions'],
            'title and name mid-sentence' => [
                "Prof. Lindqvist explained the ventilation section brilliantly",
                ['Lindqvist'],
                'ventilation section',
            ],
            'email and phone' => [
                "Contact me at maria.santos@hospital.org or +44 7700 900123 for follow-up",
                ['maria.santos', 'hospital.org', '7700', '900123'],
                'follow-up',
            ],
            'link' => ["See my notes at https://example.org/~msantos/notes for details", ['example.org', 'msantos'], 'for details'],
            'best wishes with title' => [
                "Loved the scoring feedback. Best wishes, Mr. Kwame Boateng",
                ['Kwame', 'Boateng'],
                'scoring feedback',
            ],
            'bare trailing name' => ["Excellent content overall. Priya Raghunathan", ['Priya', 'Raghunathan'], 'Excellent content'],
            'self introduction' => [
                "My name is Olamide Adeyemi and I found the quizzes useful",
                ['Olamide', 'Adeyemi'],
                'quizzes useful',
            ],
            'signature only' => ["Sincerely,\nHannah O'Brien-Walsh", ['Hannah', "O'Brien"], ''],
        ];
    }

    /**
     * The identity fragments are gone and the content stays.
     *
     * @dataProvider signed_answers
     * @param string $raw The answer as written.
     * @param string[] $gone Identity fragments that must not survive.
     * @param string $kept A content fragment that must survive ('' for none).
     */
    public function test_strips_identity(string $raw, array $gone, string $kept): void {
        $out = comment_redactor::redact($raw);

        foreach ($gone as $fragment) {
            $this->assertStringNotContainsString($fragment, $out);
        }
        if ($kept !== '') {
            $this->assertStringContainsString($kept, $out);
        }
    }

    /**
     * Every removal leaves a placeholder.
     *
     * @dataProvider signed_answers
     * @param string $raw The answer as written.
     */
    public function test_leaves_a_placeholder_wherever_it_removed_something(string $raw): void {
        // The dashboard never uses an answer with a placeholder as a quoted excerpt,
        // so every removal must leave one.
        $this->assertMatchesRegularExpression(
            '/\[(name|email|number|link) removed\]/',
            comment_redactor::redact($raw)
        );
    }

    /**
     * Redacting twice changes nothing more.
     *
     * @dataProvider signed_answers
     * @param string $raw The answer as written.
     */
    public function test_is_idempotent(string $raw): void {
        $once = comment_redactor::redact($raw);
        $this->assertSame($once, comment_redactor::redact($once));
    }

    public function test_leaves_ordinary_sentences_with_capitalised_words_alone(): void {
        $raw = 'The Damage Control Resuscitation module was current. Airway Management could go deeper.';
        $this->assertSame($raw, comment_redactor::redact($raw));
    }

    public function test_keeps_small_numbers(): void {
        $raw = '3 hours for 12 cases felt about right';
        $this->assertSame($raw, comment_redactor::redact($raw));
    }

    public function test_keeps_umlauts_and_accents(): void {
        $raw = 'Die Übungen zur Prämienberechnung waren sehr hilfreich, merci beaucoup.';
        $this->assertSame($raw, comment_redactor::redact($raw));
    }

    public function test_sign_off_becomes_a_placeholder(): void {
        $this->assertSame(
            'Great module on airway management. [name removed]',
            comment_redactor::redact('Great module on airway management. -- Jane Okonkwo')
        );
    }

    public function test_empty_answer_stays_empty(): void {
        $this->assertSame('', comment_redactor::redact('   '));
    }
}
