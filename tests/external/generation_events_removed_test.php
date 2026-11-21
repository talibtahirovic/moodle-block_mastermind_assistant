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
 * Tests that the generation-event reporting from the training-data design is gone.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\external;

/**
 * The block no longer reports generate, apply, regenerate and discard events.
 *
 * Those events once fed a training-data store on the dashboard; that store was
 * removed in September 2026 and the dashboard has discarded them since.
 *
 * @coversNothing
 */
final class generation_events_removed_test extends \advanced_testcase {
    public function test_no_web_service_client_method_or_class_for_generation_events(): void {
        global $CFG;
        $functions = [];
        include($CFG->dirroot . '/blocks/mastermind_assistant/db/services.php');

        $this->assertArrayNotHasKey('block_mastermind_assistant_log_generation_feedback', $functions);
        $this->assertFalse(class_exists('\block_mastermind_assistant\external\log_generation_feedback'));
        $this->assertFalse(method_exists('\block_mastermind_assistant\api_client', 'log_generation_feedback'));
    }

    public function test_no_script_sends_generation_events(): void {
        global $CFG;
        foreach (glob($CFG->dirroot . '/blocks/mastermind_assistant/amd/{src,build}/*.js', GLOB_BRACE) as $file) {
            $this->assertStringNotContainsString('log_generation_feedback', file_get_contents($file), basename($file));
        }
    }

    public function test_policy_text_does_not_claim_a_record_is_kept_on_the_site(): void {
        global $CFG;
        $english = get_string('ai_policy_body', 'block_mastermind_assistant');
        $this->assertStringNotContainsString('may be saved in this site', $english);
        $this->assertStringContainsString('keeps no record', $english);

        $string = [];
        include($CFG->dirroot . '/blocks/mastermind_assistant/lang/de/block_mastermind_assistant.php');
        $this->assertStringNotContainsString('auf dieser Website gespeichert werden', $string['ai_policy_body']);
    }
}
