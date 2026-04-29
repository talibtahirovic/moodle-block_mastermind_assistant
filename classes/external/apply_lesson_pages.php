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
 * External function to apply lesson pages
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/lesson/locallib.php');
require_once($CFG->dirroot . '/mod/lesson/pagetypes/branchtable.php');
require_once($CFG->dirroot . '/mod/lesson/pagetypes/multichoice.php');
require_once($CFG->dirroot . '/mod/lesson/pagetypes/truefalse.php');
require_once($CFG->dirroot . '/mod/lesson/pagetypes/shortanswer.php');
require_once($CFG->dirroot . '/mod/lesson/pagetypes/matching.php');
require_once($CFG->dirroot . '/mod/lesson/pagetypes/essay.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_course;
use context_module;
use Exception;

/**
 * Apply AI-generated lesson pages by creating them via Moodle's lesson API.
 *
 * Supports content pages (branchtable) and question pages:
 * multichoice, truefalse, shortanswer, matching, essay.
 */
class apply_lesson_pages extends external_api {
    /**
     * Describe the parameters accepted by execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'pages' => new external_value(PARAM_RAW, 'JSON array of pages'),
        ]);
    }

    /**
     * Execute the web service call.
     *
     * @return array
     */
    public static function execute($courseid, $cmid, $pages) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'pages' => $pages,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $items = json_decode($params['pages'], true);
        if (empty($items) || !is_array($items)) {
            return ['success' => false, 'created' => 0, 'message' => 'No pages to create.'];
        }

        // Get lesson instance.
        $cm = get_coursemodule_from_id('lesson', $params['cmid'], 0, false, MUST_EXIST);
        $lesson = new \lesson($DB->get_record('lesson', ['id' => $cm->instance], '*', MUST_EXIST));
        $modcontext = context_module::instance($cm->id);

        $created = 0;
        $errors = [];

        // Get the last page ID to chain new pages after existing content.
        $lastpageid = 0;
        $existingpages = $DB->get_records('lesson_pages', ['lessonid' => $lesson->id], 'id ASC');
        if (!empty($existingpages)) {
            // Find the page with nextpageid = 0 (last page in sequence).
            foreach ($existingpages as $ep) {
                if ((int) $ep->nextpageid === 0) {
                    $lastpageid = $ep->id;
                    break;
                }
            }
            // Fallback to max id.
            if (!$lastpageid) {
                $lastpageid = max(array_keys($existingpages));
            }
        }

        foreach ($items as $item) {
            $title = trim($item['title'] ?? '');
            $content = trim($item['content'] ?? '');
            if (empty($title)) {
                continue;
            }

            $pagetype = strtolower($item['page_type'] ?? $item['type'] ?? 'content');
            $qtype = strtolower($item['question_type'] ?? 'multichoice');

            try {
                $properties = new \stdClass();
                $properties->title = $title;
                $properties->contents_editor = [
                    'text' => $content ?: '<p></p>',
                    'format' => FORMAT_HTML,
                    'itemid' => 0,
                ];
                $properties->pageid = $lastpageid;
                $properties->qoption = 0;
                $properties->layout = 0;
                $properties->display = 0;

                $answers = $item['answers'] ?? [];

                if ($pagetype === 'content' || (empty($answers) && $qtype !== 'essay')) {
                    // Content page (branchtable) with navigation buttons.
                    $properties->qtype = \LESSON_PAGE_BRANCHTABLE;
                    $properties->answer_editor = [];
                    $properties->response_editor = [];
                    $properties->jumpto = [];
                    $properties->score = [];

                    if (!empty($answers)) {
                        foreach ($answers as $i => $ans) {
                            $properties->answer_editor[$i] = [
                                'text' => $ans['text'] ?? 'Continue',
                                'format' => FORMAT_HTML,
                            ];
                            $properties->response_editor[$i] = [
                                'text' => '',
                                'format' => FORMAT_HTML,
                            ];
                            $properties->jumpto[$i] = self::resolve_jump($ans['jump_to'] ?? 'next');
                            $properties->score[$i] = 0;
                        }
                    } else {
                        // Default Continue button.
                        $properties->answer_editor[0] = [
                            'text' => get_string('continue', 'lesson'),
                            'format' => FORMAT_HTML,
                        ];
                        $properties->response_editor[0] = ['text' => '', 'format' => FORMAT_HTML];
                        $properties->jumpto[0] = \LESSON_NEXTPAGE;
                        $properties->score[0] = 0;
                    }
                } else if ($qtype === 'truefalse') {
                    // True/False question — exactly 2 answers.
                    $properties->qtype = \LESSON_PAGE_TRUEFALSE;
                    $properties->answer_editor = [];
                    $properties->response_editor = [];
                    $properties->jumpto = [];
                    $properties->score = [];

                    // Ensure exactly 2 answers (True and False).
                    $tfanswers = array_slice($answers, 0, 2);
                    foreach ($tfanswers as $i => $ans) {
                        $properties->answer_editor[$i] = [
                            'text' => $ans['text'] ?? ($i === 0 ? 'True' : 'False'),
                            'format' => FORMAT_HTML,
                        ];
                        $feedback = $ans['feedback'] ?? '';
                        $properties->response_editor[$i] = [
                            'text' => $feedback,
                            'format' => FORMAT_HTML,
                        ];
                        $iscorrect = !empty($ans['score']);
                        $properties->jumpto[$i] = $iscorrect ? \LESSON_NEXTPAGE : \LESSON_THISPAGE;
                        $properties->score[$i] = $iscorrect ? 1 : 0;
                    }
                } else if ($qtype === 'shortanswer') {
                    // Short answer — text patterns to match.
                    $properties->qtype = \LESSON_PAGE_SHORTANSWER;
                    $properties->answer_editor = [];
                    $properties->response_editor = [];
                    $properties->jumpto = [];
                    $properties->score = [];

                    foreach ($answers as $i => $ans) {
                        $properties->answer_editor[$i] = [
                            'text' => $ans['text'] ?? '',
                            'format' => FORMAT_MOODLE, // Plain text patterns for shortanswer.
                        ];
                        $feedback = $ans['feedback'] ?? '';
                        $properties->response_editor[$i] = [
                            'text' => $feedback,
                            'format' => FORMAT_HTML,
                        ];
                        $iscorrect = !empty($ans['score']);
                        $properties->jumpto[$i] = $iscorrect ? \LESSON_NEXTPAGE : \LESSON_THISPAGE;
                        $properties->score[$i] = $iscorrect ? 1 : 0;
                    }
                } else if ($qtype === 'matching') {
                    // Matching question — pairs of answer/response.
                    // Moodle matching: first answer = prompt text, first response = prompt text.
                    // Then pairs: answer[n] = left side, response[n] = right side match.
                    $properties->qtype = \LESSON_PAGE_MATCHING;
                    $properties->answer_editor = [];
                    $properties->response_editor = [];
                    $properties->jumpto = [];
                    $properties->score = [];

                    // First two entries are for correct/wrong jump targets (Moodle convention).
                    $correctfeedback = $item['correct_feedback'] ?? 'Correct! Well done.';
                    $wrongfeedback = $item['wrong_feedback'] ?? 'Incorrect. Please try again.';

                    $properties->answer_editor[0] = ['text' => $correctfeedback, 'format' => FORMAT_HTML];
                    $properties->response_editor[0] = ['text' => '', 'format' => FORMAT_HTML];
                    $properties->jumpto[0] = \LESSON_NEXTPAGE;
                    $properties->score[0] = 1;

                    $properties->answer_editor[1] = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
                    $properties->response_editor[1] = ['text' => '', 'format' => FORMAT_HTML];
                    $properties->jumpto[1] = \LESSON_THISPAGE;
                    $properties->score[1] = 0;

                    // Remaining entries are the matching pairs.
                    foreach ($answers as $j => $pair) {
                        $idx = $j + 2;
                        $properties->answer_editor[$idx] = [
                            'text' => $pair['text'] ?? '',
                            'format' => FORMAT_HTML,
                        ];
                        $properties->response_editor[$idx] = [
                            'text' => $pair['match'] ?? '',
                            'format' => FORMAT_HTML,
                        ];
                        $properties->jumpto[$idx] = 0;
                        $properties->score[$idx] = 0;
                    }
                } else if ($qtype === 'essay') {
                    // Essay question — no predefined answers, just prompt.
                    $properties->qtype = \LESSON_PAGE_ESSAY;
                    // Essay needs at least one answer entry for the scoring.
                    $properties->answer_editor = [
                        0 => ['text' => '', 'format' => FORMAT_HTML],
                    ];
                    $properties->response_editor = [
                        0 => ['text' => '', 'format' => FORMAT_HTML],
                    ];
                    $properties->jumpto = [0 => \LESSON_NEXTPAGE];
                    $properties->score = [0 => 0];
                } else {
                    // Default: Multichoice question.
                    $properties->qtype = \LESSON_PAGE_MULTICHOICE;
                    $properties->answer_editor = [];
                    $properties->response_editor = [];
                    $properties->jumpto = [];
                    $properties->score = [];

                    foreach ($answers as $i => $ans) {
                        $properties->answer_editor[$i] = [
                            'text' => $ans['text'] ?? '',
                            'format' => FORMAT_HTML,
                        ];
                        $feedback = $ans['feedback'] ?? '';
                        $properties->response_editor[$i] = [
                            'text' => $feedback,
                            'format' => FORMAT_HTML,
                        ];
                        $iscorrect = !empty($ans['score']);
                        $properties->jumpto[$i] = $iscorrect ? \LESSON_NEXTPAGE : \LESSON_THISPAGE;
                        $properties->score[$i] = $iscorrect ? 1 : 0;
                    }
                }

                $page = \lesson_page::create($properties, $lesson, $modcontext, $cm->id);
                $lastpageid = $page->id;
                $created++;
            } catch (Exception $e) {
                $errors[] = $title . ': ' . $e->getMessage();
            }
        }

        $msg = $created . ' page(s) created successfully.';
        if (!empty($errors)) {
            $msg .= ' Errors: ' . implode('; ', $errors);
        }

        return [
            'success' => $created > 0,
            'created' => $created,
            'message' => $msg,
        ];
    }

    /**
     * Resolve a jump_to string from the AI response to a Moodle lesson jump constant.
     */
    private static function resolve_jump(string $jumpto): int {
        switch (strtolower($jumpto)) {
            case 'next':
                return \LESSON_NEXTPAGE;
            case 'this':
            case 'retry':
                return \LESSON_THISPAGE;
            case 'previous':
                return \LESSON_PREVIOUSPAGE;
            case 'end':
                return \LESSON_EOL;
            default:
                return \LESSON_NEXTPAGE;
        }
    }

    /**
     * Describe the return value of execute().
     *
     * @return \external_description
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether any pages were created'),
            'created' => new external_value(PARAM_INT, 'Number of pages created'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
        ]);
    }
}
