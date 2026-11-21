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
 * The course's other activities, sent as context with a generation request.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * The activity list the content generators put into their prompt.
 */
class prompt_activities {
    /**
     * The list the browser sent (a JSON array of "Name (module)"), or else the
     * course's visible activities that have a page of their own.
     *
     * @param int $courseid Course id.
     * @param string $json The courseactivities parameter as sent.
     * @return string[] "Name (module)" entries.
     */
    public static function names(int $courseid, string $json): array {
        $sent = json_decode($json, true);
        if (is_array($sent) && $sent !== []) {
            return array_values(array_map('strval', array_filter($sent, 'is_scalar')));
        }
        $names = [];
        foreach (get_fast_modinfo($courseid)->get_cms() as $cminfo) {
            if ($cminfo->visible && $cminfo->has_view()) {
                $names[] = $cminfo->name . ' (' . $cminfo->modname . ')';
            }
        }
        return $names;
    }
}
