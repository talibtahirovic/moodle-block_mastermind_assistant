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
 * Aggregates course evaluation (mod_feedback) responses for a course.
 *
 * Produces per-activity, per-question statistics plus the free-text
 * comments. Submission user ids are read only to keep participants' answers
 * and drop staff ones (mod_feedback stores the real user id even when the
 * activity is anonymous); no user id, name or other identifying field ever
 * leaves this collector, so the result is safe to ship to the AI dashboard
 * as-is. Every free-text answer is emitted (values
 * are read in pages to bound memory) as {questionName, questionType, text},
 * so the dashboard can theme reflection questions apart from general
 * comments and count against the true number of answers. Each activity
 * carries its course-module id (cmid) so reports can link a finding to the
 * activity's own analysis page (/mod/feedback/analysis.php?id=cmid). Courses
 * without feedback activities get the empty shape, so callers never need a
 * guard.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_metrics {
    /** @var int Rows of feedback_value read per query (memory bound, not a cap). */
    const VALUE_PAGE = 500;

    /** @var int Maximum length of a single comment (matches the dashboard's clamp). */
    const COMMENT_MAXLEN = 400;

    /** @var string[] Item types whose values are numeric ratings. */
    const RATED_TYPES = ['numeric', 'multichoicerated'];

    /** @var string[] Item types whose values are free text. */
    const TEXT_TYPES = ['textarea', 'textfield'];

    /** @var string[] Presentation-only item types that carry no answers. */
    const SKIP_TYPES = ['label', 'pagebreak', 'captcha', 'info'];

    /**
     * Collect per-activity and per-question evaluation aggregates for a course.
     *
     * The public shape is exactly ['activities', 'satisfaction'], as in every
     * earlier version: enterprise v1.3.x sends this result unchanged to the
     * dashboard's strict schema, where any extra key fails the whole batch.
     *
     * @param int $courseid Course id.
     * @param int $pagesize Rows of feedback_value read per query.
     * @param int[] $include Course-module ids forced in (Feedback is always in already).
     * @param int[] $exclude Course-module ids forced out (wins over include).
     * @return array ['activities' => [...], 'satisfaction' => float|null]
     */
    public static function collect(
        int $courseid,
        int $pagesize = self::VALUE_PAGE,
        array $include = [],
        array $exclude = []
    ): array {
        $result = self::collect_with_ratings($courseid, $pagesize, $include, $exclude);
        unset($result['ratings']);
        return $result;
    }

    /**
     * collect() plus the rating totals, for merging with other collectors.
     *
     * Hidden, being-deleted and orphaned (no course module) activities are
     * skipped, as are those the manual selection excludes. Only submissions
     * made on or after the course start date count, and values are read only
     * for those submissions. Internal: the ratings key must never reach a
     * payload (evaluation_metrics merges and drops it).
     *
     * @param int $courseid Course id.
     * @param int $pagesize Rows of feedback_value read per query.
     * @param int[] $include Course-module ids forced in (Feedback is always in already).
     * @param int[] $exclude Course-module ids forced out (wins over include).
     * @return array ['activities' => [...], 'satisfaction' => float|null,
     *     'ratings' => ['sum5' => float, 'n' => int]]. The ratings are the sum of the converted
     *     (1-5) rated values and their count; satisfaction is computed from them.
     */
    public static function collect_with_ratings(
        int $courseid,
        int $pagesize = self::VALUE_PAGE,
        array $include = [],
        array $exclude = []
    ): array {
        global $DB;

        $result = ['activities' => [], 'satisfaction' => null, 'ratings' => ['sum5' => 0.0, 'n' => 0]];
        $feedbacks = $DB->get_records('feedback', ['course' => $courseid], 'id');
        if (!$feedbacks) {
            return $result;
        }
        // Course modules by feedback instance id, for the per-activity cmid.
        $cms = get_fast_modinfo($courseid)->get_instances_of('feedback');
        $startdate = (int) $DB->get_field('course', 'startdate', ['id' => $courseid]);

        $ratingsum = 0.0;
        $ratingcount = 0;

        foreach ($feedbacks as $feedback) {
            // No course module (orphaned instance): nobody can see it, so it isn't read.
            $cm = $cms[$feedback->id] ?? null;
            if ($cm === null || !$cm->visible || $cm->deletioninprogress) {
                continue;
            }
            if (!evaluation_rules::selection_allows((int) $cm->id, true, $include, $exclude)) {
                continue;
            }

            // Completions on or after the course start; values are read only for these.
            $completedsql = 'c.feedback = :feedback';
            $completedparams = ['feedback' => $feedback->id];
            if ($startdate > 0) {
                $completedsql .= ' AND c.timemodified >= :start';
                $completedparams['start'] = $startdate;
            }
            // Only participants' answers count; staff answers are dropped. Anonymous
            // submissions still carry the real user id and are filtered the same way;
            // only rows without a user (userid 0) are kept as they are.
            [$participantsql, $participantparams] = evaluation_rules::participant_condition($courseid, 'c.userid');
            $completedsql .= ' AND ' . $participantsql;
            $completedparams += $participantparams;
            $responsecount = (int) $DB->get_field_sql(
                'SELECT COUNT(1) FROM {feedback_completed} c WHERE ' . $completedsql,
                $completedparams
            );

            $items = $DB->get_records('feedback_item', ['feedback' => $feedback->id], 'position, id');
            $questions = [];
            $commentrows = [];

            foreach ($items as $item) {
                if (in_array($item->typ, self::SKIP_TYPES, true)) {
                    continue;
                }
                $questionname = format_string($item->name);
                $israted = in_array($item->typ, self::RATED_TYPES, true);
                $istext = in_array($item->typ, self::TEXT_TYPES, true);
                $ratingmap = $item->typ === 'multichoicerated'
                    ? self::rating_map($item->presentation) : null;
                $scale = $israted ? self::scale($item->typ, (string) $item->presentation, $ratingmap) : null;
                $choicelabels = $item->typ === 'multichoice' ? self::choice_labels((string) $item->presentation) : null;
                $choicecounts = $choicelabels !== null ? array_fill(0, count($choicelabels), 0) : [];
                $numbers = [];
                $responses = 0;

                // Newest first: feedback_completed ids increase per submission.
                // Values are read in pages so a large survey never loads at once.
                $sql = 'SELECT v.id, v.value, v.completed
                          FROM {feedback_value} v
                          JOIN {feedback_completed} c ON c.id = v.completed
                         WHERE v.item = :item AND ' . $completedsql
                    . ' ORDER BY v.completed DESC, v.id DESC';
                $params = ['item' => $item->id] + $completedparams;
                $page = 0;
                do {
                    $values = $DB->get_records_sql($sql, $params, $page * $pagesize, $pagesize);
                    $page++;
                    foreach ($values as $value) {
                        $raw = trim((string) $value->value);
                        if ($raw === '') {
                            continue;
                        }
                        if ($israted) {
                            // A rated answer counts only when it is a rating: multichoicerated
                            // stores "0" when nothing was chosen, which maps to no option.
                            if ($ratingmap !== null) {
                                if (!isset($ratingmap[(int) $raw])) {
                                    continue;
                                }
                                $numbers[] = $ratingmap[(int) $raw];
                            } else if (is_numeric($raw)) {
                                $numbers[] = (float) $raw;
                            } else {
                                continue;
                            }
                            $responses++;
                            continue;
                        }
                        if ($choicelabels !== null) {
                            // 1-based option indexes, "|"-separated for multiple answers;
                            // "0" or an unknown index is not an answer.
                            $picked = false;
                            foreach (array_unique(array_map('trim', explode('|', $raw))) as $index) {
                                if (ctype_digit($index) && isset($choicecounts[(int) $index - 1])) {
                                    $choicecounts[(int) $index - 1]++;
                                    $picked = true;
                                }
                            }
                            if ($picked) {
                                $responses++;
                            }
                            continue;
                        }
                        $responses++;
                        if ($istext) {
                            $text = trim(strip_tags($raw));
                            if ($text === '') {
                                continue;
                            }
                            $commentrows[] = [
                                'completed' => (int) $value->completed,
                                'comment' => [
                                    'questionName' => $questionname,
                                    'questionType' => $item->typ,
                                    'text' => \core_text::substr($text, 0, self::COMMENT_MAXLEN),
                                ],
                            ];
                        }
                    }
                } while (count($values) === $pagesize);

                $avg = null;
                if ($numbers) {
                    $avg = round(array_sum($numbers) / count($numbers), 1);
                    if ($scale !== null) {
                        foreach ($numbers as $number) {
                            $ratingsum += evaluation_rules::to_five_point($number, $scale[0], $scale[1]);
                        }
                    } else {
                        $ratingsum += array_sum($numbers);
                    }
                    $ratingcount += count($numbers);
                }
                $question = [
                    'question' => $questionname,
                    'type' => $item->typ,
                    'responses' => $responses,
                    'avg' => $avg,
                ];
                if ($israted) {
                    $question['kind'] = 'rated';
                } else if ($istext) {
                    $question['kind'] = 'text';
                } else if ($choicelabels !== null) {
                    $question['kind'] = 'choice';
                    $question['choices'] = array_map(
                        fn($label, $count) => ['label' => $label, 'count' => $count],
                        $choicelabels,
                        $choicecounts
                    );
                }
                if ($scale !== null) {
                    $question['scale_min'] = $scale[0];
                    $question['scale_max'] = $scale[1];
                }
                $questions[] = $question;
            }

            // Newest first across every text question in this activity.
            usort($commentrows, fn($a, $b) => $b['completed'] <=> $a['completed']);
            $comments = array_column($commentrows, 'comment');

            $result['activities'][] = [
                'cmid' => (int) $cm->id,
                'name' => format_string($feedback->name),
                'module' => 'feedback',
                'responses' => $responsecount,
                'questions' => $questions,
                'comments' => $comments,
            ];
        }

        if ($ratingcount > 0) {
            $result['satisfaction'] = round($ratingsum / $ratingcount, 1);
        }
        $result['ratings'] = ['sum5' => (float) $ratingsum, 'n' => $ratingcount];
        return $result;
    }

    /**
     * The scale of a rated item, when it can be determined.
     *
     * Numeric items carry "min|max" in their presentation; multichoicerated
     * items use the lowest and highest rating of their option map.
     *
     * @param string $typ Item type.
     * @param string $presentation Item presentation string.
     * @param float[]|null $ratingmap Rating map for multichoicerated items.
     * @return float[]|null [min, max], or null when unknown.
     */
    private static function scale(string $typ, string $presentation, ?array $ratingmap): ?array {
        if ($typ === 'multichoicerated') {
            if (!$ratingmap) {
                return null;
            }
            $min = (float) min($ratingmap);
            $max = (float) max($ratingmap);
        } else {
            $parts = explode('|', $presentation);
            if (count($parts) < 2 || !is_numeric(trim($parts[0])) || !is_numeric(trim($parts[1]))) {
                return null;
            }
            $min = (float) trim($parts[0]);
            $max = (float) trim($parts[1]);
        }
        return $max > $min ? [$min, $max] : null;
    }

    /**
     * The option labels of a multichoice item, in form order (plain text).
     *
     * The presentation reads "r>>>>>Yes|No|Maybe" (subtype r, c or d before
     * ">>>>>"), optionally ending with an "<<<<<1" layout suffix.
     *
     * @param string $presentation Item presentation string.
     * @return string[] Labels; option index i (1-based) is element i - 1.
     */
    private static function choice_labels(string $presentation): array {
        $pos = strpos($presentation, '>>>>>');
        if ($pos !== false) {
            $presentation = substr($presentation, $pos + 5);
        }
        $pos = strpos($presentation, '<<<<<');
        if ($pos !== false) {
            $presentation = substr($presentation, 0, $pos);
        }
        if (trim($presentation) === '') {
            return [];
        }
        return array_map(
            fn($line) => evaluation_rules::plain_text(format_string(trim($line))),
            explode('|', $presentation)
        );
    }

    /**
     * Map multichoicerated option indexes to their numeric ratings.
     *
     * feedback_value.value stores the 1-based index of the chosen option;
     * the rating is the numeric prefix of that option's presentation line
     * ("r>>>>>10####Low|20####Medium|30####High", optionally ending with an
     * "<<<<<1" adjustment suffix).
     *
     * @param string $presentation Item presentation string.
     * @return float[] Option index => rating.
     */
    private static function rating_map(string $presentation): array {
        $pos = strpos($presentation, '>>>>>');
        if ($pos !== false) {
            $presentation = substr($presentation, $pos + 5);
        }
        $pos = strpos($presentation, '<<<<<');
        if ($pos !== false) {
            $presentation = substr($presentation, 0, $pos);
        }
        $map = [];
        foreach (explode('|', $presentation) as $i => $line) {
            $parts = explode('####', $line);
            if (count($parts) === 2 && is_numeric(trim($parts[0]))) {
                $map[$i + 1] = (float) trim($parts[0]);
            }
        }
        return $map;
    }
}
