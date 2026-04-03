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
 * External function to apply glossary entries
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/glossary/lib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_course;
use Exception;

/**
 * Apply AI-generated glossary entries by creating them via Moodle's glossary API.
 */
class apply_glossary_entries extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'entries' => new external_value(PARAM_RAW, 'JSON array of entries [{concept, definition}]'),
        ]);
    }

    public static function execute($courseid, $cmid, $entries) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/glossary/classes/external.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'entries' => $entries,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $items = json_decode($params['entries'], true);
        if (empty($items) || !is_array($items)) {
            return ['success' => false, 'created' => 0, 'message' => 'No entries to create.'];
        }

        // Get glossary instance from course module.
        $cm = get_coursemodule_from_id('glossary', $params['cmid'], 0, false, MUST_EXIST);
        $glossary = $DB->get_record('glossary', ['id' => $cm->instance], '*', MUST_EXIST);

        $created = 0;
        $errors = [];

        foreach ($items as $entry) {
            $concept = trim($entry['concept'] ?? $entry['term'] ?? '');
            $definition = trim($entry['definition'] ?? '');
            if (empty($concept)) {
                continue;
            }
            if (empty($definition)) {
                $definition = '<p>' . $concept . '</p>';
            }
            // Wrap in HTML if not already.
            if (strpos($definition, '<') === false) {
                $definition = '<p>' . nl2br(htmlspecialchars($definition)) . '</p>';
            }

            try {
                $options = [];
                if (!empty($entry['keywords'])) {
                    $aliases = is_array($entry['keywords'])
                        ? implode(',', $entry['keywords'])
                        : $entry['keywords'];
                    $options[] = ['name' => 'aliases', 'value' => $aliases];
                }
                \mod_glossary_external::add_entry(
                    $glossary->id,
                    $concept,
                    $definition,
                    FORMAT_HTML,
                    $options
                );
                $created++;
            } catch (Exception $e) {
                $errors[] = $concept . ': ' . $e->getMessage();
            }
        }

        $msg = $created . ' entr' . ($created === 1 ? 'y' : 'ies') . ' created successfully.';
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
            'success' => new external_value(PARAM_BOOL, 'Whether any entries were created'),
            'created' => new external_value(PARAM_INT, 'Number of entries created'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
        ]);
    }
}
