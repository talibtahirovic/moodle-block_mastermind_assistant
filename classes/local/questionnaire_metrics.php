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
 * Aggregates course evaluations built with mod_questionnaire.
 *
 * Emits activities in the same shape as feedback_metrics, tagged
 * module => 'questionnaire', so the merged collector and the dashboard treat
 * both alike. Only evaluations are read: a visible questionnaire with a live
 * rate or slider question, or one whose name marks it as an evaluation (see
 * evaluation_rules), adjusted by the product's include/exclude selection.
 * A ranking (rate with "no duplicate choices") is not a rating question and
 * doesn't make a questionnaire an evaluation.
 * Answers of any other questionnaire (sign-up forms, contact forms) are never
 * queried. Only completed responses submitted on or after the course start
 * count, so answers from earlier runs of a reused course stay out.
 *
 * - rate (8): one rated item per row, on the scale 1..length (or the
 *   question's named degrees); N/A (-1) is counted apart and never averaged.
 *   Rankings (no duplicate choices) aren't ratings and aren't sent.
 * - slider (11): one rated item on the range stored in its extradata.
 * - radio (4) / dropdown (6): a distribution of counts per choice, no average.
 * - text box (2) / essay (3): a text item plus the comments (answer text only).
 *
 * Response user ids are read only to keep participants' answers and drop
 * staff ones (mod_questionnaire stores the real user id even for an
 * anonymous questionnaire); no user id, name or other identifying field
 * leaves this collector. mod_questionnaire is optional: without its tables
 * the empty shape is returned.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire_metrics {
    /** @var int Maximum length of a single comment (matches feedback_metrics and the dashboard). */
    const COMMENT_MAXLEN = 400;

    /** @var int Question type: text box. */
    const TYPE_TEXT = 2;

    /** @var int Question type: essay. */
    const TYPE_ESSAY = 3;

    /** @var int Question type: radio buttons. */
    const TYPE_RADIO = 4;

    /** @var int Question type: dropdown. */
    const TYPE_DROP = 6;

    /** @var int Question type: rate (Likert grid, one row per choice). */
    const TYPE_RATE = 8;

    /** @var int Question type: slider. */
    const TYPE_SLIDER = 11;

    /** @var int[] Question types that make a questionnaire an evaluation: rate (not a ranking) and slider. */
    const RATING_TYPES = [self::TYPE_RATE, self::TYPE_SLIDER];

    /** @var int Rate answer meaning "not applicable". */
    const RATE_NA = -1;

    /** @var int Rate question "precise" setting: the grid has an N/A column. */
    const PRECISE_NA_COLUMN = 1;

    /** @var int Rate question "precise" setting: no duplicate choices (a ranking, not a rating). */
    const PRECISE_RANKING = 2;

    /** @var int Rate question "precise" setting: Osgood scale, rows read "left|right". */
    const PRECISE_OSGOOD = 3;

    /** @var string[] Payload type name per question type read. */
    const TYPE_NAMES = [
        self::TYPE_TEXT => 'text',
        self::TYPE_ESSAY => 'essay',
        self::TYPE_RADIO => 'radio',
        self::TYPE_DROP => 'dropdown',
        self::TYPE_RATE => 'rate',
        self::TYPE_SLIDER => 'slider',
    ];

    /**
     * Collect the evaluation questionnaires of a course.
     *
     * @param int $courseid Course id.
     * @param int[] $include Course-module ids forced in.
     * @param int[] $exclude Course-module ids forced out (wins over include).
     * @return array ['activities' => [...], 'ratings' => ['sum5' => float, 'n' => int]], where
     *     sum5 is the sum of every rated answer converted to 1-5 and n their count.
     */
    public static function collect(int $courseid, array $include = [], array $exclude = []): array {
        global $DB;

        $result = ['activities' => [], 'ratings' => ['sum5' => 0.0, 'n' => 0]];
        if (!static::available()) {
            return $result;
        }
        $instances = $DB->get_records('questionnaire', ['course' => $courseid], 'id', 'id, name, sid');
        if (!$instances) {
            return $result;
        }
        $cms = get_fast_modinfo($courseid)->get_instances_of('questionnaire');
        $startdate = (int) $DB->get_field('course', 'startdate', ['id' => $courseid]);

        foreach ($instances as $instance) {
            // No course module (orphaned instance): nobody can see it, so it isn't read.
            $cm = $cms[$instance->id] ?? null;
            if ($cm === null || !$cm->visible || $cm->deletioninprogress) {
                continue;
            }
            // Excluded activities aren't touched at all, not even their question rows.
            if (!evaluation_rules::selection_allows((int) $cm->id, true, [], $exclude)) {
                continue;
            }
            $questions = self::live_questions((int) $instance->sid);
            $auto = self::has_rating_question($questions)
                || evaluation_rules::name_is_evaluation(evaluation_rules::plain_text((string) $instance->name));
            if (!evaluation_rules::selection_allows((int) $cm->id, $auto, $include, $exclude)) {
                continue;
            }
            $result['activities'][] = self::activity($instance, (int) $cm->id, $questions, $startdate, $courseid, $result['ratings']);
        }
        return $result;
    }

    /**
     * Questionnaire instances of a course that have a live rating question
     * (rate or slider; not a ranking), whatever their visibility or the
     * product's selection.
     *
     * Reads question types only, never answers, so callers can classify every
     * questionnaire (e.g. an activity inventory) without touching responses.
     * Same rule as collect(): the deleted flag is checked with
     * evaluation_rules::is_not_deleted().
     *
     * @param int $courseid Course id.
     * @return int[] Questionnaire instance ids, ascending; empty without mod_questionnaire.
     */
    public static function rated_questionnaire_ids(int $courseid): array {
        global $DB;
        if (!static::available()) {
            return [];
        }
        [$typesql, $params] = $DB->get_in_or_equal(self::RATING_TYPES, SQL_PARAMS_NAMED, 'rt');
        $rows = $DB->get_records_sql(
            "SELECT qq.id, q.id AS questionnaireid, qq.type_id, qq.precise, qq.deleted
               FROM {questionnaire} q
               JOIN {questionnaire_question} qq ON qq.surveyid = q.sid
              WHERE q.course = :courseid AND qq.type_id $typesql",
            ['courseid' => $courseid] + $params
        );
        $ids = [];
        foreach ($rows as $row) {
            if (self::is_rating_question($row) && evaluation_rules::is_not_deleted($row->deleted)) {
                $ids[(int) $row->questionnaireid] = true;
            }
        }
        $ids = array_keys($ids);
        sort($ids);
        return $ids;
    }

    /**
     * Whether mod_questionnaire's tables exist on this site.
     *
     * @return bool
     */
    protected static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('questionnaire');
    }

    /**
     * The survey's questions that aren't deleted, in form order.
     *
     * The deleted flag is checked in PHP: 5.x stores a bigint (NULL or 0 when
     * live), older versions 'y'/'n', and no single SQL comparison fits both.
     *
     * @param int $surveyid questionnaire_survey id.
     * @return \stdClass[]
     */
    private static function live_questions(int $surveyid): array {
        global $DB;
        $questions = $DB->get_records(
            'questionnaire_question',
            ['surveyid' => $surveyid],
            'position, id',
            'id, name, type_id, content, length, precise, deleted, extradata'
        );
        return array_values(array_filter(
            $questions,
            fn($q) => evaluation_rules::is_not_deleted($q->deleted)
        ));
    }

    /**
     * Whether any of the questions is a rating question.
     *
     * @param \stdClass[] $questions Live questions.
     * @return bool
     */
    private static function has_rating_question(array $questions): bool {
        foreach ($questions as $question) {
            if (self::is_rating_question($question)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a question is a rating question: a slider, or a rate question
     * that isn't a ranking ("no duplicate choices", precise 2).
     *
     * @param \stdClass $question Question row (type_id, precise).
     * @return bool
     */
    private static function is_rating_question(\stdClass $question): bool {
        $type = (int) $question->type_id;
        if (!in_array($type, self::RATING_TYPES, true)) {
            return false;
        }
        return !($type === self::TYPE_RATE && (int) $question->precise === self::PRECISE_RANKING);
    }

    /**
     * Build one activity: response count, question items and comments.
     *
     * @param \stdClass $instance questionnaire row (id, name, sid).
     * @param int $cmid Course-module id.
     * @param \stdClass[] $questions Live questions in form order.
     * @param int $startdate Course start (0 = none).
     * @param int $courseid Course id.
     * @param array $ratings Running ['sum5', 'n'] across the course, updated in place.
     * @return array
     */
    private static function activity(
        \stdClass $instance,
        int $cmid,
        array $questions,
        int $startdate,
        int $courseid,
        array &$ratings
    ): array {
        global $DB;

        // Responses that count: completed, submitted on or after the course start.
        $where = 'r.questionnaireid = :questionnaireid AND r.complete = :complete';
        $params = ['questionnaireid' => (int) $instance->id, 'complete' => 'y'];
        if ($startdate > 0) {
            $where .= ' AND r.submitted >= :start';
            $params['start'] = $startdate;
        }
        // Only participants' answers count; staff (test) answers are dropped. Anonymous
        // responses still carry the real user id and are filtered the same way.
        [$participantsql, $participantparams] = evaluation_rules::participant_condition($courseid, 'r.userid');
        $where .= ' AND ' . $participantsql;
        $params += $participantparams;
        $responsecount = (int) $DB->get_field_sql(
            'SELECT COUNT(1) FROM {questionnaire_response} r WHERE ' . $where,
            $params
        );

        $bytype = [];
        foreach ($questions as $question) {
            $bytype[(int) $question->type_id][] = (int) $question->id;
        }
        $rankcounts = self::rank_counts($bytype[self::TYPE_RATE] ?? [], $where, $params);
        $singlecounts = self::single_counts(
            array_merge($bytype[self::TYPE_RADIO] ?? [], $bytype[self::TYPE_DROP] ?? []),
            $where,
            $params
        );
        $choices = self::choices(array_merge(
            $bytype[self::TYPE_RATE] ?? [],
            $bytype[self::TYPE_RADIO] ?? [],
            $bytype[self::TYPE_DROP] ?? []
        ));
        $texts = self::text_answers(
            array_merge($bytype[self::TYPE_TEXT] ?? [], $bytype[self::TYPE_ESSAY] ?? [], $bytype[self::TYPE_SLIDER] ?? []),
            $where,
            $params
        );

        $items = [];
        $commentrows = [];
        foreach ($questions as $question) {
            $qid = (int) $question->id;
            $text = self::question_text($question);
            switch ((int) $question->type_id) {
                case self::TYPE_RATE:
                    $items = array_merge(
                        $items,
                        self::rate_items($question, $text, $choices[$qid] ?? [], $rankcounts[$qid] ?? [], $ratings)
                    );
                    break;
                case self::TYPE_SLIDER:
                    $item = self::slider_item($question, $text, $texts[$qid] ?? [], $ratings);
                    if ($item !== null) {
                        $items[] = $item;
                    }
                    break;
                case self::TYPE_RADIO:
                case self::TYPE_DROP:
                    $items[] = self::choice_item($question, $text, $choices[$qid] ?? [], $singlecounts[$qid] ?? []);
                    break;
                case self::TYPE_TEXT:
                case self::TYPE_ESSAY:
                    $type = self::TYPE_NAMES[(int) $question->type_id];
                    $count = 0;
                    foreach ($texts[$qid] ?? [] as $answer) {
                        $answertext = evaluation_rules::plain_text((string) $answer->response);
                        if ($answertext === '') {
                            continue;
                        }
                        $count++;
                        $commentrows[] = [
                            'submitted' => (int) $answer->submitted,
                            'responseid' => (int) $answer->responseid,
                            'answerid' => (int) $answer->id,
                            'comment' => [
                                'questionName' => $text,
                                'questionType' => $type,
                                'text' => \core_text::substr($answertext, 0, self::COMMENT_MAXLEN),
                            ],
                        ];
                    }
                    $items[] = ['question' => $text, 'type' => $type, 'kind' => 'text', 'responses' => $count, 'avg' => null];
                    break;
                default:
                    // Labels, page breaks, yes/no, check boxes, dates, numbers: not sent.
                    break;
            }
        }

        // Comments across every text question, newest response first.
        usort($commentrows, fn($a, $b) => [$b['submitted'], $b['responseid'], $a['answerid']]
            <=> [$a['submitted'], $a['responseid'], $b['answerid']]);

        return [
            'cmid' => $cmid,
            'name' => format_string($instance->name),
            'module' => 'questionnaire',
            'responses' => $responsecount,
            'questions' => $items,
            'comments' => array_column($commentrows, 'comment'),
        ];
    }

    /**
     * Choices (rate rows, radio and dropdown options) per question, in form order.
     *
     * @param int[] $questionids Question ids.
     * @return array questionid => choice rows (id, content)
     */
    private static function choices(array $questionids): array {
        global $DB;
        if (!$questionids) {
            return [];
        }
        $choices = [];
        $rows = $DB->get_records_list('questionnaire_quest_choice', 'question_id', $questionids, 'id', 'id, question_id, content');
        foreach ($rows as $row) {
            $choices[(int) $row->question_id][] = $row;
        }
        return $choices;
    }

    /**
     * Rate answers counted per question, row and value.
     *
     * @param int[] $questionids Rate question ids.
     * @param string $where Response filter on alias r.
     * @param array $params Its parameters.
     * @return array questionid => choiceid => rankvalue => count
     */
    private static function rank_counts(array $questionids, string $where, array $params): array {
        global $DB;
        if (!$questionids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'rq');
        $rs = $DB->get_recordset_sql(
            "SELECT a.question_id, a.choice_id, a.rankvalue, COUNT(1) AS answers
               FROM {questionnaire_response_rank} a
               JOIN {questionnaire_response} r ON r.id = a.response_id
              WHERE a.question_id $insql AND $where
           GROUP BY a.question_id, a.choice_id, a.rankvalue",
            $inparams + $params
        );
        $counts = [];
        foreach ($rs as $row) {
            $counts[(int) $row->question_id][(int) $row->choice_id][(int) $row->rankvalue] = (int) $row->answers;
        }
        $rs->close();
        return $counts;
    }

    /**
     * Radio and dropdown answers counted per question and choice.
     *
     * @param int[] $questionids Radio/dropdown question ids.
     * @param string $where Response filter on alias r.
     * @param array $params Its parameters.
     * @return array questionid => choiceid => count
     */
    private static function single_counts(array $questionids, string $where, array $params): array {
        global $DB;
        if (!$questionids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'sq');
        $rs = $DB->get_recordset_sql(
            "SELECT a.question_id, a.choice_id, COUNT(1) AS answers
               FROM {questionnaire_resp_single} a
               JOIN {questionnaire_response} r ON r.id = a.response_id
              WHERE a.question_id $insql AND $where
           GROUP BY a.question_id, a.choice_id",
            $inparams + $params
        );
        $counts = [];
        foreach ($rs as $row) {
            $counts[(int) $row->question_id][(int) $row->choice_id] = (int) $row->answers;
        }
        $rs->close();
        return $counts;
    }

    /**
     * Text-table answers (text box, essay, slider) per question.
     *
     * @param int[] $questionids Question ids.
     * @param string $where Response filter on alias r.
     * @param array $params Its parameters.
     * @return array questionid => answers (id, response, responseid, submitted)
     */
    private static function text_answers(array $questionids, string $where, array $params): array {
        global $DB;
        if (!$questionids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'tq');
        $rs = $DB->get_recordset_sql(
            "SELECT a.id, a.question_id, a.response, r.id AS responseid, r.submitted
               FROM {questionnaire_response_text} a
               JOIN {questionnaire_response} r ON r.id = a.response_id
              WHERE a.question_id $insql AND $where",
            $inparams + $params
        );
        $answers = [];
        foreach ($rs as $row) {
            $answers[(int) $row->question_id][] = $row;
        }
        $rs->close();
        return $answers;
    }

    /**
     * One rated item per row of a rate question.
     *
     * @param \stdClass $question Question row.
     * @param string $group Question text.
     * @param \stdClass[] $choices The question's rows in form order.
     * @param array $counts choiceid => rankvalue => count.
     * @param array $ratings Running ['sum5', 'n'], updated in place.
     * @return array[]
     */
    private static function rate_items(
        \stdClass $question,
        string $group,
        array $choices,
        array $counts,
        array &$ratings
    ): array {
        if ((int) $question->precise === self::PRECISE_RANKING) {
            // A ranking: its average is an average rank, not a rating.
            return [];
        }
        $scale = self::rate_scale($question);
        if ($scale === null) {
            return [];
        }
        [$valid, $min, $max] = $scale;
        $hasna = (int) $question->precise === self::PRECISE_NA_COLUMN;
        $items = [];
        foreach ($choices as $choice) {
            $content = (string) $choice->content;
            // Legacy "named degree" rows ("1=poor") label the scale and "other" rows
            // take free text; neither is a rated line.
            if (preg_match('/^[0-9]{1,3}=/', $content) || strpos($content, '!other') === 0) {
                continue;
            }
            $n = 0;
            $na = 0;
            $sum = 0.0;
            foreach ($counts[(int) $choice->id] ?? [] as $value => $answers) {
                if ($value === self::RATE_NA) {
                    $na += $answers;
                    continue;
                }
                if (!isset($valid[$value])) {
                    // Not a point on the scale (e.g. -999, "not yet answered").
                    continue;
                }
                $n += $answers;
                $sum += $value * $answers;
                $ratings['sum5'] += evaluation_rules::to_five_point($value, $min, $max) * $answers;
                $ratings['n'] += $answers;
            }

            $item = ['question' => $group, 'type' => self::TYPE_NAMES[self::TYPE_RATE], 'kind' => 'rated'];
            $row = self::row_label($content, (int) $question->precise === self::PRECISE_OSGOOD);
            // Label-based (ruling R22), whatever the number of rows: a row with its own
            // label (not empty, not the question text) gets group and row; otherwise
            // neither, and the item is the question itself. Never one without the other.
            if ($group !== '' && $row !== '' && \core_text::strtolower($row) !== \core_text::strtolower($group)) {
                $item['question'] = $group . ': ' . $row;
                $item['group'] = $group;
                $item['row'] = $row;
            } else if ($group === '') {
                $item['question'] = $row;
            }
            $item += [
                'responses' => $n,
                'avg' => $n > 0 ? round($sum / $n, 1) : null,
                'scale_min' => $min,
                'scale_max' => $max,
            ];
            if ($hasna) {
                $item['na'] = $na;
            }
            $items[] = $item;
        }
        return $items;
    }

    /**
     * The valid answer values of a rate question and its scale.
     *
     * Named degrees (extradata: a JSON map of value => label) define the
     * values: the first `length` keys. Otherwise the values are 1..length.
     *
     * @param \stdClass $question Question row.
     * @return array|null [value => true, min, max], or null when there's no usable scale.
     */
    private static function rate_scale(\stdClass $question): ?array {
        $length = (int) $question->length;
        $degrees = json_decode((string) $question->extradata, true);
        $keys = is_array($degrees) ? array_keys($degrees) : [];
        if ($keys && count(array_filter($keys, 'is_numeric')) === count($keys)) {
            $values = array_map('intval', array_slice($keys, 0, max($length, 0)));
        } else {
            $values = $length > 0 ? range(1, $length) : [];
        }
        if (count(array_unique($values)) < 2) {
            return null;
        }
        return [array_fill_keys($values, true), min($values), max($values)];
    }

    /**
     * Plain-text label of a rate row; Osgood rows ("left|right") read "left – right".
     *
     * @param string $content Choice content.
     * @param bool $osgood Whether the question is an Osgood scale.
     * @return string
     */
    private static function row_label(string $content, bool $osgood): string {
        if (!$osgood) {
            return evaluation_rules::plain_text($content);
        }
        $parts = preg_split('/[|]/', $content);
        $ends = array_filter(
            [evaluation_rules::plain_text($parts[0]), evaluation_rules::plain_text($parts[1] ?? '')],
            fn($end) => $end !== ''
        );
        return implode(' – ', $ends);
    }

    /**
     * A slider as one rated item, or null when its range can't be read.
     *
     * @param \stdClass $question Question row.
     * @param string $text Question text.
     * @param \stdClass[] $answers Text-table answers.
     * @param array $ratings Running ['sum5', 'n'], updated in place.
     * @return array|null
     */
    private static function slider_item(\stdClass $question, string $text, array $answers, array &$ratings): ?array {
        $range = json_decode((string) $question->extradata);
        $readable = is_object($range) && isset($range->minrange, $range->maxrange)
            && is_numeric($range->minrange) && is_numeric($range->maxrange);
        if (!$readable) {
            return null;
        }
        $min = (float) $range->minrange;
        $max = (float) $range->maxrange;
        if ($max <= $min) {
            return null;
        }
        $values = [];
        foreach ($answers as $answer) {
            $raw = trim((string) $answer->response);
            if (is_numeric($raw) && (float) $raw >= $min && (float) $raw <= $max) {
                $values[] = (float) $raw;
            }
        }
        foreach ($values as $value) {
            $ratings['sum5'] += evaluation_rules::to_five_point($value, $min, $max);
        }
        $ratings['n'] += count($values);
        return [
            'question' => $text,
            'type' => self::TYPE_NAMES[self::TYPE_SLIDER],
            'kind' => 'rated',
            'responses' => count($values),
            'avg' => $values ? round(array_sum($values) / count($values), 1) : null,
            'scale_min' => self::number($min),
            'scale_max' => self::number($max),
        ];
    }

    /**
     * A radio or dropdown question as a distribution in form order.
     *
     * @param \stdClass $question Question row.
     * @param string $text Question text.
     * @param \stdClass[] $choices The question's choices in form order.
     * @param array $counts choiceid => count.
     * @return array
     */
    private static function choice_item(\stdClass $question, string $text, array $choices, array $counts): array {
        $distribution = [];
        $total = 0;
        foreach ($choices as $choice) {
            // Answers to choices that no longer exist are left out of both n and the distribution.
            $count = $counts[(int) $choice->id] ?? 0;
            $total += $count;
            $distribution[] = ['label' => self::choice_label((string) $choice->content), 'count' => $count];
        }
        return [
            'question' => $text,
            'type' => self::TYPE_NAMES[(int) $question->type_id],
            'kind' => 'choice',
            'responses' => $total,
            'avg' => null,
            'choices' => $distribution,
        ];
    }

    /**
     * The question's text: its content as plain text, else its short name.
     *
     * @param \stdClass $question Question row.
     * @return string
     */
    private static function question_text(\stdClass $question): string {
        $text = evaluation_rules::plain_text((string) $question->content);
        return $text !== '' ? $text : evaluation_rules::plain_text((string) $question->name);
    }

    /**
     * Plain-text label of a radio or dropdown choice, as the questionnaire displays it.
     *
     * "!other=Label" is an "other" choice; choices may carry a score prefix
     * ("2=gut") or a value prefix ("v::gut").
     *
     * @param string $content Choice content.
     * @return string
     */
    private static function choice_label(string $content): string {
        if (strpos($content, '!other') === 0) {
            $label = preg_replace('/^!other=?/', '', $content);
            return $label !== '' ? evaluation_rules::plain_text($label) : get_string('other', 'questionnaire');
        }
        $content = preg_replace('/^\d{1,2}=/', '', $content) ?? $content;
        $pos = strpos($content, '::');
        if ($pos) {
            $content = substr($content, $pos + 2);
        }
        return evaluation_rules::plain_text($content);
    }

    /**
     * A scale bound as int when whole, else float.
     *
     * @param float $value Value.
     * @return int|float
     */
    private static function number(float $value) {
        return floor($value) == $value ? (int) $value : $value;
    }
}
