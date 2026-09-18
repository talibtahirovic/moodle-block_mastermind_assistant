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

defined('MOODLE_INTERNAL') || die();

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
