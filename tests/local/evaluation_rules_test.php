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
 * Tests for the shared evaluation rules.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_mastermind_assistant\local\evaluation_rules
 */
final class evaluation_rules_test extends \advanced_testcase {
    /**
     * Attributes never leak into the text.
     */
    public function test_plain_text_strips_attributes(): void {
        $this->assertSame(
            'How could roles help?',
            evaluation_rules::plain_text('<p aria-label="allergies">How could roles help?</p>')
        );
        $this->assertSame('A & B c', evaluation_rules::plain_text("<div>A &amp; B</div>\n  <b>c</b>"));
    }

    /**
     * Block-level tags separate words; inline tags vanish without a space.
     */
    public function test_plain_text_block_tags_become_spaces(): void {
        $this->assertSame('a b', evaluation_rules::plain_text('<p>a</p><p>b</p>'));
        $this->assertSame('a b', evaluation_rules::plain_text('a<br>b'));
        $this->assertSame('a b', evaluation_rules::plain_text('a<br />b'));
        $this->assertSame('Was hat mir gefallen?', evaluation_rules::plain_text('<b>Was hat mir gefallen</b>?'));
        $this->assertSame('gefallen?', evaluation_rules::plain_text('gefallen</b>?'));
        $this->assertSame('x y z', evaluation_rules::plain_text('<ul><li>x</li><li>y</li></ul><ol><li>z</li></ol>'));
        $this->assertSame('T body', evaluation_rules::plain_text('<h2>T</h2>body'));
        $this->assertSame('a b c', evaluation_rules::plain_text('<table><tr><th>a</th><td>b</td></tr></table>c'));
        $this->assertSame('q r', evaluation_rules::plain_text('<blockquote>q</blockquote>r'));
        $this->assertSame('a b', evaluation_rules::plain_text('<DIV class="x">a</DIV>b'));
        $this->assertSame(
            'Was nehme ich mit?',
            evaluation_rules::plain_text('<p><span aria-label="Allergien bitte angeben">Was nehme ich mit?</span></p>')
        );
        // A ">" inside a quoted attribute doesn't end the tag early.
        $this->assertSame('Text', evaluation_rules::plain_text('<p aria-label="a > Allergien">Text</p>'));
        $this->assertSame('Text', evaluation_rules::plain_text("<p title='x > Allergien'>Text</p>"));
        $this->assertSame('Text', evaluation_rules::plain_text('<span title="x > Allergien">Text</span>'));
        // Inline tags, and tags that only start like a block tag (<b>, <pre>), add no space.
        $this->assertSame('ab', evaluation_rules::plain_text('a<span>b</span>'));
        $this->assertSame('ab', evaluation_rules::plain_text('a<b>b</b>'));
        $this->assertSame('ab', evaluation_rules::plain_text('a<pre>b</pre>'));
    }

    /**
     * NULL, 0, '0' and 'n' mean not deleted; 'y' and timestamps mean deleted.
     */
    public function test_is_not_deleted_variants(): void {
        foreach ([null, 0, '0', 'n', ''] as $v) {
            $this->assertTrue(evaluation_rules::is_not_deleted($v), var_export($v, true));
        }
        foreach (['y', 1, '1', 1720000000, '1720000000'] as $v) {
            $this->assertFalse(evaluation_rules::is_not_deleted($v), var_export($v, true));
        }
    }

    /**
     * Name pattern.
     */
    public function test_name_is_evaluation(): void {
        $this->assertTrue(evaluation_rules::name_is_evaluation('Feedback + Learnings'));
        $this->assertTrue(evaluation_rules::name_is_evaluation('Kursbewertung'));
        // Not an evaluation marker (controller ruling R24): sign-up forms say "Rückmeldung" too.
        $this->assertFalse(evaluation_rules::name_is_evaluation('Rückmeldung zum Modul'));
        $this->assertFalse(evaluation_rules::name_is_evaluation('Rückmeldung zur Diplomfeier'));
        $this->assertFalse(evaluation_rules::name_is_evaluation('Anmeldung zur Diplomfeier'));
        $this->assertFalse(evaluation_rules::name_is_evaluation('Erwartungen'));
    }

    /**
     * Exclude beats include beats auto.
     */
    public function test_selection_exclude_wins(): void {
        $this->assertTrue(evaluation_rules::selection_allows(5, true, [], []));
        $this->assertFalse(evaluation_rules::selection_allows(5, false, [], []));
        $this->assertTrue(evaluation_rules::selection_allows(5, false, [5], []));
        $this->assertFalse(evaluation_rules::selection_allows(5, true, [], [5]));
        $this->assertFalse(evaluation_rules::selection_allows(5, false, [5], [5]));
        $this->assertTrue(evaluation_rules::selection_allows(6, false, [6], [5]));
    }

    /**
     * Five-point conversion.
     */
    public function test_to_five_point(): void {
        $this->assertEqualsWithDelta(1.0, evaluation_rules::to_five_point(1, 1, 10), 1e-9);
        $this->assertEqualsWithDelta(5.0, evaluation_rules::to_five_point(10, 1, 10), 1e-9);
        $this->assertEqualsWithDelta(4.0, evaluation_rules::to_five_point(4, 1, 5), 1e-9);
        $this->assertEqualsWithDelta(3.0, evaluation_rules::to_five_point(20, 10, 30), 1e-9);
    }
}
