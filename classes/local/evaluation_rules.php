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
 * Shared rules for deciding which activities are evaluations and for
 * normalising their answers. Used by the Feedback, Questionnaire and
 * merged evaluation collectors so they all agree.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation_rules {
    /** @var string Activity names that mark an evaluation ("Rückmeldung" is left out: sign-up forms use it). */
    const NAME_PATTERN = '/feedback|evaluation|evaluierung|beurteilung|bewertung/iu';

    /**
     * @var string Opening or closing block-level tags, which separate words.
     * Quoted attribute values may contain ">", so they are matched whole.
     */
    const BLOCK_TAG_PATTERN = '~</?(?:p|div|br|li|ul|ol|h[1-6]|tr|td|th|table|blockquote)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~i';

    /**
     * Strip tags, decode entities and collapse whitespace.
     *
     * Block-level tags become a space ("<p>a</p><p>b</p>" reads "a b"); all
     * other tags are removed without one ("gefallen</b>?" reads "gefallen?").
     * Tag attributes (e.g. a translate aria-label) never reach the result.
     *
     * @param string $html Raw HTML or text.
     * @return string Plain text.
     */
    public static function plain_text(string $html): string {
        $text = preg_replace(self::BLOCK_TAG_PATTERN, ' ', $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * Whether a questionnaire question's "deleted" value means "not deleted".
     *
     * Moodle 5.x stores a bigint timestamp or NULL; older versions 'y'/'n'.
     *
     * @param mixed $deleted Raw column value.
     * @return bool
     */
    public static function is_not_deleted($deleted): bool {
        if ($deleted === null || $deleted === '' || $deleted === 'n') {
            return true;
        }
        if (is_numeric($deleted)) {
            return (float) $deleted == 0;
        }
        return false;
    }

    /**
     * Whether an activity name marks it as an evaluation.
     *
     * @param string $name Activity name.
     * @return bool
     */
    public static function name_is_evaluation(string $name): bool {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    /**
     * Convert a value on [min, max] to the 1-5 scale.
     *
     * @param float $v Value.
     * @param float $min Scale minimum.
     * @param float $max Scale maximum.
     * @return float
     */
    public static function to_five_point(float $v, float $min, float $max): float {
        if ($max <= $min) {
            return $v;
        }
        return 1 + ($v - $min) * 4 / ($max - $min);
    }

    /**
     * Apply the product's manual selection to the automatic decision.
     *
     * @param int $cmid Course module id.
     * @param bool $auto The automatic evaluation decision.
     * @param int[] $include Forced-in cmids.
     * @param int[] $exclude Forced-out cmids (wins over include).
     * @return bool
     */
    public static function selection_allows(int $cmid, bool $auto, array $include, array $exclude): bool {
        if (in_array($cmid, array_map('intval', $exclude), true)) {
            return false;
        }
        if (in_array($cmid, array_map('intval', $include), true)) {
            return true;
        }
        return $auto;
    }

    /**
     * Ids of the course's participants: users enrolled in the course who hold
     * a role with the student archetype in the course context. Staff who
     * test an evaluation don't count.
     *
     * @param int $courseid Course id.
     * @return int[] User ids, distinct, ascending.
     */
    public static function participant_ids(int $courseid): array {
        global $DB;
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }
        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.contextid = :contextid AND r.archetype = :archetype
                AND EXISTS (SELECT 1
                              FROM {user_enrolments} ue
                              JOIN {enrol} e ON e.id = ue.enrolid
                             WHERE ue.userid = ra.userid AND e.courseid = :courseid)",
            ['contextid' => $context->id, 'archetype' => 'student', 'courseid' => $courseid]
        );
        $ids = array_map('intval', $ids);
        sort($ids);
        return $ids;
    }

    /**
     * SQL condition keeping only participants' answers, dropping staff ones.
     *
     * Feedback and Questionnaire store the real user id with every answer,
     * also in anonymous activities, so anonymous answers are filtered like any
     * other. Only rows without a user (0 or NULL, e.g. guest submissions) are
     * kept as they are. The ids stay inside the query; none is returned.
     *
     * @param int $courseid Course id.
     * @param string $column Qualified user id column, e.g. "r.userid".
     * @param string $prefix Parameter name prefix, unique within the query.
     * @return array [sql, params]; ['1 = 1', []] when the course has no participants.
     */
    public static function participant_condition(int $courseid, string $column, string $prefix = 'pu'): array {
        global $DB;
        $ids = self::participant_ids($courseid);
        if (!$ids) {
            return ['1 = 1', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, $prefix);
        return ["($column IS NULL OR $column = 0 OR $column $insql)", $params];
    }
}
