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
 * Removal of the submitting learner's own identity from their text.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Removes the names, email, username and id number of the learners who submitted.
 *
 * Grading assist sends a submission's text to the dashboard. Learners often put
 * their name on their work; Moodle knows exactly who submitted, so those exact
 * values are replaced with placeholders before anything leaves the site.
 *
 * - Email, username, id number and full-name forms ("Amara Okafor",
 *   "Okafor Amara", "Okafor, Amara") match case-insensitively.
 * - A single name matches only as written in the profile or in capitals, so a
 *   first name that is also an everyday word ("Will", "May") keeps its lower-case use.
 * - Values shorter than the minimum length are ignored.
 */
class learner_identity_redactor {
    /** @var string[] Name fields of a user record. */
    const NAME_FIELDS = ['firstname', 'lastname', 'middlename', 'alternatename', 'firstnamephonetic', 'lastnamephonetic'];

    /** @var int Shortest name that is matched. */
    const MIN_NAME_LENGTH = 2;

    /** @var int Shortest email, username or id number that is matched. */
    const MIN_ID_LENGTH = 3;

    /**
     * Redact the given learners' identity from a text.
     *
     * @param string $text Submitted text (plain text).
     * @param \stdClass[] $users User records of the submitting learners.
     * @return string The text with placeholders; '' if it cannot be processed.
     */
    public static function redact(string $text, array $users): string {
        $needles = [];
        foreach ($users as $user) {
            foreach (self::needles_for($user) as $needle) {
                $needles[] = $needle;
            }
        }
        // Longest first, so a full name or email is replaced before its parts.
        usort($needles, fn(array $a, array $b) => \core_text::strlen($b[0]) <=> \core_text::strlen($a[0]));

        foreach ($needles as [$value, $placeholder, $caseless]) {
            // Any run of whitespace between the parts of a name; any spacing after a comma.
            $pattern = preg_replace('/\s+/u', '\s+', preg_quote($value, '/'));
            $pattern = str_replace(',\s+', ',\s*', $pattern);
            $regex = '/(?<![\p{L}\p{N}])' . $pattern . '(?![\p{L}\p{N}])/u' . ($caseless ? 'i' : '');
            $text = preg_replace($regex, $placeholder, $text);
            if ($text === null) {
                // Invalid UTF-8 or a regex failure: send nothing rather than unredacted text.
                return '';
            }
        }
        return $text;
    }

    /**
     * The values to remove for one user: [value, placeholder, case-insensitive].
     *
     * @param \stdClass $user User record.
     * @return array[]
     */
    private static function needles_for(\stdClass $user): array {
        $needles = [];
        $field = fn(string $name) => trim((string) ($user->$name ?? ''));

        $identifiers = ['email' => '[email removed]', 'username' => '[id removed]', 'idnumber' => '[id removed]'];
        foreach ($identifiers as $name => $placeholder) {
            if (\core_text::strlen($field($name)) >= self::MIN_ID_LENGTH) {
                $needles[] = [$field($name), $placeholder, true];
            }
        }

        $first = $field('firstname');
        $last = $field('lastname');
        if (\core_text::strlen($first) >= self::MIN_NAME_LENGTH && \core_text::strlen($last) >= self::MIN_NAME_LENGTH) {
            foreach (["$first $last", "$last $first", "$last, $first"] as $fullname) {
                $needles[] = [$fullname, '[name removed]', true];
            }
        }

        foreach (self::NAME_FIELDS as $name) {
            $value = $field($name);
            if (\core_text::strlen($value) < self::MIN_NAME_LENGTH) {
                continue;
            }
            $needles[] = [$value, '[name removed]', false];
            $upper = \core_text::strtoupper($value);
            if ($upper !== $value) {
                $needles[] = [$upper, '[name removed]', false];
            }
        }
        return $needles;
    }
}
