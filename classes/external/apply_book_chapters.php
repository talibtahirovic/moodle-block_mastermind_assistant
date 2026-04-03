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
 * External function to apply book chapters
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/book/locallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_course;
use context_module;
use Exception;

/**
 * Apply AI-generated book chapters by inserting them directly into the database.
 */
class apply_book_chapters extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'chapters' => new external_value(PARAM_RAW, 'JSON array of chapters [{title, content}]'),
        ]);
    }

    public static function execute($courseid, $cmid, $chapters) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'chapters' => $chapters,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $items = json_decode($params['chapters'], true);
        if (empty($items) || !is_array($items)) {
            return ['success' => false, 'created' => 0, 'message' => 'No chapters to create.'];
        }

        // Get book instance from course module.
        $cm = get_coursemodule_from_id('book', $params['cmid'], 0, false, MUST_EXIST);
        $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
        $modcontext = context_module::instance($cm->id);

        $created = 0;
        $errors = [];

        // Get current max page number.
        $maxpagenum = (int) $DB->get_field('book_chapters', 'MAX(pagenum)', ['bookid' => $book->id]);

        foreach ($items as $item) {
            $title = trim($item['title'] ?? '');
            $content = trim($item['content'] ?? '');
            if (empty($title)) {
                continue;
            }
            if (empty($content)) {
                $content = '<p></p>';
            }
            // Wrap in HTML if not already.
            if (strpos($content, '<') === false) {
                $content = '<p>' . nl2br(htmlspecialchars($content)) . '</p>';
            }

            $maxpagenum++;
            $issubchapter = !empty($item['subchapter']) ? 1 : 0;
            // First chapter cannot be a subchapter.
            if ($maxpagenum === 1) {
                $issubchapter = 0;
            }

            try {
                $chapter = new \stdClass();
                $chapter->bookid = $book->id;
                $chapter->pagenum = $maxpagenum;
                $chapter->subchapter = $issubchapter;
                $chapter->title = $title;
                $chapter->content = $content;
                $chapter->contentformat = FORMAT_HTML;
                $chapter->hidden = 0;
                $chapter->importsrc = '';
                $chapter->timecreated = time();
                $chapter->timemodified = time();

                $chapter->id = $DB->insert_record('book_chapters', $chapter);

                // Trigger event.
                \mod_book\event\chapter_created::create_from_chapter($book, $modcontext, $chapter)->trigger();

                $created++;
            } catch (Exception $e) {
                $errors[] = $title . ': ' . $e->getMessage();
            }
        }

        if ($created > 0) {
            // Update book revision.
            $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);
            // Fix chapter structure.
            book_preload_chapters($book);
        }

        $msg = $created . ' chapter(s) created successfully.';
        if (!empty($errors)) {
            $msg .= ' Errors: ' . implode('; ', $errors);
        }

        return [
            'success' => $created > 0,
            'created' => $created,
            'message' => $msg,
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether any chapters were created'),
            'created' => new external_value(PARAM_INT, 'Number of chapters created'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
        ]);
    }
}
