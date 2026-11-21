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
 * Last line of defence before a course payload leaves the plugin.
 *
 * get_course_data no longer emits per-learner rows, but the analysis
 * external functions forward a payload the browser posted back to them.
 * Whatever shape arrives (including one from an older cached browser
 * build), everything identity-shaped is removed here before the
 * dashboard call, so the guarantee "learner names, user IDs and email
 * addresses are never sent" does not depend on the client.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outbound_sanitizer {
    /** @var string[] Top-level arrays that only ever held per-learner rows. */
    const PER_LEARNER_ARRAYS = ['users', 'forum_posts', 'completions'];

    /** @var string Keys that identify a learner, removed wherever they occur. */
    const IDENTITY_KEY_RE = '/^(userid|user_id|username|email|firstname|lastname|idnumber|phone1|phone2)$/i';

    /**
     * Remove per-learner arrays and identity keys from a course payload.
     *
     * @param array $payload Decoded course payload.
     * @return array The payload without learner identity.
     */
    public static function strip_identity(array $payload): array {
        foreach (self::PER_LEARNER_ARRAYS as $key) {
            unset($payload[$key]);
        }
        // Legacy grade items carried one row per learner under 'grades'.
        if (isset($payload['grades']) && is_array($payload['grades'])) {
            foreach ($payload['grades'] as $i => $item) {
                if (is_array($item)) {
                    unset($payload['grades'][$i]['grades']);
                }
            }
        }
        return self::strip_identity_keys($payload);
    }

    /** @var string[] Top-level fields of the course payload that no dashboard prompt reads. */
    const UNUSED_TOP_LEVEL = ['forums', 'forum_activity', 'audit_flags'];

    /**
     * A course payload as it may leave Moodle: no learner identity, grade and
     * SCORM figures held back below five learners (small_numbers), and none of
     * the fields the dashboard never reads.
     *
     * The browser keeps the full get_course_data result for the block's own
     * screens (the post-copy guide reads audit_flags); only the copy that is
     * sent is reduced.
     *
     * @param array $payload Decoded course payload.
     * @return array
     */
    public static function for_dashboard(array $payload): array {
        $payload = self::strip_identity($payload);

        if (isset($payload['grades']) && is_array($payload['grades'])) {
            $payload['grades'] = small_numbers::grade_items($payload['grades']);
        }
        if (isset($payload['scorm_performance']) && is_array($payload['scorm_performance'])) {
            $payload['scorm_performance'] = small_numbers::scorm_packages($payload['scorm_performance']);
            $learners = array_sum(array_map(fn($p) => (int) ($p['attempts'] ?? 0), $payload['scorm_performance']));
            $weakest = small_numbers::weakest_sco($payload['scorm_performance']);
            if ($weakest === null) {
                // As get_course_data does when no unit has an average.
                unset($payload['scorm_summary']);
            } else if (isset($payload['scorm_summary']) && is_array($payload['scorm_summary'])) {
                $payload['scorm_summary'] = [
                    'avg_score' => small_numbers::figure($payload['scorm_summary']['avg_score'] ?? null, $learners),
                    'completion_rate' => small_numbers::figure($payload['scorm_summary']['completion_rate'] ?? null, $learners),
                    'weakest_sco' => $weakest,
                ];
            }
        }

        foreach (self::UNUSED_TOP_LEVEL as $key) {
            unset($payload[$key]);
        }
        // Activity intros and quiz layout: teacher text no prompt reads.
        if (isset($payload['activities']) && is_array($payload['activities'])) {
            $payload['activities'] = self::without_module_data($payload['activities']);
        }
        if (isset($payload['sections']) && is_array($payload['sections'])) {
            foreach ($payload['sections'] as $i => $section) {
                if (is_array($section) && isset($section['activities']) && is_array($section['activities'])) {
                    $payload['sections'][$i]['activities'] = self::without_module_data($section['activities']);
                }
            }
        }
        return $payload;
    }

    /**
     * Activities without their module_data.
     *
     * @param array $activities Activity entries.
     * @return array
     */
    private static function without_module_data(array $activities): array {
        foreach ($activities as $i => $activity) {
            if (is_array($activity)) {
                unset($activities[$i]['module_data']);
            }
        }
        return $activities;
    }

    /**
     * Recursively drop identity-shaped keys.
     *
     * @param array $data Subtree.
     * @return array Subtree without identity keys.
     */
    private static function strip_identity_keys(array $data): array {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::IDENTITY_KEY_RE, $key)) {
                unset($data[$key]);
                continue;
            }
            if (is_array($value)) {
                $data[$key] = self::strip_identity_keys($value);
            }
        }
        return $data;
    }
}
