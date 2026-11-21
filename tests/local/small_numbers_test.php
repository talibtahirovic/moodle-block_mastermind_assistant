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
 * Tests for holding back grade and score figures behind fewer than five learners.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Grade averages, SCORM scores and rates are sent only when at least five learners are behind them.
 *
 * Counts are always sent. Evaluation ratings are not touched here: they are
 * shown with their n and never drive conclusions on their own.
 *
 * @covers \block_mastermind_assistant\local\small_numbers
 */
final class small_numbers_test extends \basic_testcase {
    public function test_figure_is_held_back_below_five_learners(): void {
        $this->assertNull(small_numbers::figure(72.5, 4));
        $this->assertSame(72.5, small_numbers::figure(72.5, 5));
        $this->assertNull(small_numbers::figure(null, 30));
    }

    public function test_grade_items_drop_min_and_max_and_hold_back_small_averages(): void {
        $grades = [
            ['item' => ['itemname' => 'Quiz 1', 'itemmodule' => 'quiz'],
                'summary' => ['graded' => 3, 'avg_finalgrade' => 7.5, 'min_finalgrade' => 4.0, 'max_finalgrade' => 10.0,
                    'passed' => 2]],
            ['item' => ['itemname' => 'Quiz 2', 'itemmodule' => 'quiz'],
                'summary' => ['graded' => 12, 'avg_finalgrade' => 6.25, 'min_finalgrade' => 1.0, 'max_finalgrade' => 9.0,
                    'passed' => 8]],
        ];

        $out = small_numbers::grade_items($grades);

        $this->assertSame(['graded' => 3, 'avg_finalgrade' => null, 'passed' => 2], $out[0]['summary']);
        $this->assertSame(['graded' => 12, 'avg_finalgrade' => 6.25, 'passed' => 8], $out[1]['summary']);
        $this->assertSame('Quiz 1', $out[0]['item']['itemname']);
    }

    /**
     * Two packages: one with three learners, one with eight (one of its units has only two).
     *
     * @return array
     */
    private function scorm_packages(): array {
        return [
            ['name' => 'Small package', 'attempts' => 3, 'avg_score' => 40.0, 'completion_rate' => 66.7, 'scoes' => [
                ['title' => 'Unit A', 'attempts' => 3, 'avg_score' => 40.0, 'pass_rate' => 66.7],
            ]],
            ['name' => 'Large package', 'attempts' => 8, 'avg_score' => 71.3, 'completion_rate' => 75.0, 'scoes' => [
                ['title' => 'Unit B', 'attempts' => 8, 'avg_score' => 64.0, 'pass_rate' => 62.5],
                ['title' => 'Unit C', 'attempts' => 2, 'avg_score' => 12.0, 'pass_rate' => 0.0],
            ]],
        ];
    }

    public function test_scorm_packages_hold_back_small_packages_and_units(): void {
        $out = small_numbers::scorm_packages($this->scorm_packages());

        $this->assertSame(
            ['name' => 'Small package', 'attempts' => 3, 'avg_score' => null, 'completion_rate' => null, 'scoes' => [
                ['title' => 'Unit A', 'attempts' => 3, 'avg_score' => null, 'pass_rate' => null],
            ]],
            $out[0]
        );
        $this->assertSame(71.3, $out[1]['avg_score']);
        $this->assertSame(75.0, $out[1]['completion_rate']);
        $this->assertSame(['title' => 'Unit B', 'attempts' => 8, 'avg_score' => 64.0, 'pass_rate' => 62.5], $out[1]['scoes'][0]);
        $this->assertSame(['title' => 'Unit C', 'attempts' => 2, 'avg_score' => null, 'pass_rate' => null], $out[1]['scoes'][1]);
    }

    public function test_weakest_unit_is_chosen_only_among_units_with_five_learners(): void {
        // Unit C (12%) has two learners, so Unit B is the weakest that may be named.
        $packages = small_numbers::scorm_packages($this->scorm_packages());

        $this->assertSame('Unit B (avg 64%)', small_numbers::weakest_sco($packages));
        $this->assertNull(small_numbers::weakest_sco([$packages[0]]));
    }

    public function test_scorm_collection_holds_back_course_figures_below_five_learners(): void {
        $collected = [
            'packages' => [$this->scorm_packages()[0]],
            'avg_scorm_score' => 40.0,
            'scorm_completion_rate' => 66.7,
            'weakest_scorm_sco' => 'Unit A (avg 40%)',
        ];

        $out = small_numbers::scorm($collected);

        $this->assertNull($out['avg_scorm_score']);
        $this->assertNull($out['scorm_completion_rate']);
        $this->assertNull($out['weakest_scorm_sco']);
        $this->assertNull($out['packages'][0]['avg_score']);
    }

    public function test_scorm_collection_keeps_course_figures_with_enough_learners(): void {
        $collected = [
            'packages' => $this->scorm_packages(),
            'avg_scorm_score' => 58.1,
            'scorm_completion_rate' => 72.7,
            'weakest_scorm_sco' => 'Unit C (avg 12%)',
        ];

        $out = small_numbers::scorm($collected);

        $this->assertSame(58.1, $out['avg_scorm_score']);
        $this->assertSame(72.7, $out['scorm_completion_rate']);
        $this->assertSame('Unit B (avg 64%)', $out['weakest_scorm_sco']);
    }
}
