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
 * Event observer for Mastermind Assistant
 *
 * @package    block_mastermind_assistant
 * @copyright  2025 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core\event\course_created;

class observer {
    /**
     * Course created handler – automatically add block instance.
     *
     * @param course_created $event
     */
    public static function course_created(course_created $event) {
        global $DB;

        $courseid = $event->objectid;
        $context = context_course::instance($courseid);

        // Check if already exists in this course.
        $exists = $DB->record_exists('block_instances', [
            'blockname' => 'mastermind_assistant',
            'parentcontextid' => $context->id,
        ]);
        if ($exists) {
            return true;
        }

        // Check if there's a site-level sticky block (showinsubcontexts = 1).
        // If a sticky block exists, it will already be visible on all pages including this course.
        $stickyblock = $DB->record_exists('block_instances', [
            'blockname' => 'mastermind_assistant',
            'showinsubcontexts' => 1,
        ]);
        if ($stickyblock) {
            // Don't add a course-specific instance if there's already a sticky block.
            return true;
        }

        $record = new \stdClass();
        $record->blockname        = 'mastermind_assistant';
        $record->parentcontextid  = $context->id;
        $record->showinsubcontexts = 0;
        $record->pagetypepattern  = 'course-view-*';
        $record->subpagepattern   = NULL;
        $record->defaultregion    = 'side-pre';
        $record->defaultweight    = 5;
        $record->configdata       = NULL;
        $record->timecreated      = time();
        $record->timemodified     = time();

        $DB->insert_record('block_instances', $record);

        // Force theme cache purging to show block immediately.
        \cache_helper::purge_by_event('changesincourse');

        return true;
    }
}
