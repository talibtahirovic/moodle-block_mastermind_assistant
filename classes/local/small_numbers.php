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
 * Holding back grade and score figures that rest on too few learners.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Grade averages, SCORM scores and rates leave Moodle only when at least
 * MIN_LEARNERS learners are behind them; below that a figure is sent as null.
 *
 * A figure over one or two learners is in effect their own result, so it is not
 * sent to the dashboard. Counts are always sent. Evaluation ratings are out of
 * scope: the reports show them with their n and never draw a conclusion from
 * a small sample (decided for the real-data reports, November 2026).
 *
 * This applies only to what is sent. The block's own displays inside Moodle
 * keep every figure. The enterprise companion plugin uses the same threshold
 * for its collection.
 */
class small_numbers {
    /** @var int Fewest learners a grade or score figure may rest on. Same as the dashboard's cohort minimum. */
    const MIN_LEARNERS = 5;

    /**
     * A figure, or null when it rests on fewer than MIN_LEARNERS learners.
     *
     * @param float|int|null $value The figure.
     * @param int $learners How many learners it rests on.
     * @return float|int|null
     */
    public static function figure($value, int $learners) {
        return $learners >= self::MIN_LEARNERS ? $value : null;
    }

    /**
     * Grade items as get_course_data builds them: minimum and maximum dropped
     * (they are always one learner's grade, and nothing reads them), the average
     * held back below MIN_LEARNERS graded learners.
     *
     * @param array $grades [{item, summary: {graded, avg_finalgrade, min_finalgrade, max_finalgrade, passed}}].
     * @return array
     */
    public static function grade_items(array $grades): array {
        foreach ($grades as $i => $grade) {
            if (!is_array($grade) || !isset($grade['summary']) || !is_array($grade['summary'])) {
                continue;
            }
            $summary = $grade['summary'];
            unset($summary['min_finalgrade'], $summary['max_finalgrade']);
            if (array_key_exists('avg_finalgrade', $summary)) {
                $summary['avg_finalgrade'] = self::figure($summary['avg_finalgrade'], (int) ($summary['graded'] ?? 0));
            }
            $grades[$i]['summary'] = $summary;
        }
        return $grades;
    }

    /**
     * SCORM packages as scorm_metrics::collect() lists them, with each package's
     * and each unit's score and rate held back below MIN_LEARNERS learners.
     *
     * @param array $packages [{name, attempts, avg_score, completion_rate, scoes: [{title, attempts, avg_score, pass_rate}]}].
     * @return array
     */
    public static function scorm_packages(array $packages): array {
        foreach ($packages as $p => $package) {
            $learners = (int) ($package['attempts'] ?? 0);
            $packages[$p]['avg_score'] = self::figure($package['avg_score'] ?? null, $learners);
            $packages[$p]['completion_rate'] = self::figure($package['completion_rate'] ?? null, $learners);
            foreach ($package['scoes'] ?? [] as $s => $sco) {
                $unitlearners = (int) ($sco['attempts'] ?? 0);
                $packages[$p]['scoes'][$s]['avg_score'] = self::figure($sco['avg_score'] ?? null, $unitlearners);
                $packages[$p]['scoes'][$s]['pass_rate'] = self::figure($sco['pass_rate'] ?? null, $unitlearners);
            }
        }
        return $packages;
    }

    /**
     * The weakest unit that may be named: lowest average among units whose
     * average was not held back. Same wording as scorm_metrics::collect().
     *
     * @param array $packages Packages after scorm_packages().
     * @return string|null "Title (avg 64%)", or null when no unit qualifies.
     */
    public static function weakest_sco(array $packages): ?string {
        $weakest = null;
        foreach ($packages as $package) {
            foreach ($package['scoes'] ?? [] as $sco) {
                $avg = $sco['avg_score'] ?? null;
                if ($avg !== null && ($weakest === null || $avg < $weakest['avg'])) {
                    $weakest = ['title' => $sco['title'], 'avg' => $avg];
                }
            }
        }
        return $weakest ? $weakest['title'] . ' (avg ' . round($weakest['avg'], 1) . '%)' : null;
    }

    /**
     * A whole scorm_metrics::collect() result, ready to send.
     *
     * @param array $scorm {packages, avg_scorm_score, scorm_completion_rate, weakest_scorm_sco}.
     * @return array The same shape with small figures held back.
     */
    public static function scorm(array $scorm): array {
        $packages = self::scorm_packages($scorm['packages'] ?? []);
        $learners = array_sum(array_map(fn($p) => (int) ($p['attempts'] ?? 0), $packages));
        $scorm['packages'] = $packages;
        $scorm['avg_scorm_score'] = self::figure($scorm['avg_scorm_score'] ?? null, $learners);
        $scorm['scorm_completion_rate'] = self::figure($scorm['scorm_completion_rate'] ?? null, $learners);
        $scorm['weakest_scorm_sco'] = self::weakest_sco($packages);
        return $scorm;
    }
}
