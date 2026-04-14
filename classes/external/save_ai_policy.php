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
 * External function to save AI policy acceptance
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_mastermind_assistant\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class save_ai_policy extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'accepted' => new external_value(PARAM_BOOL, 'Whether user accepted AI policy')
        ]);
    }

    /**
     * Save user's AI policy acceptance
     * @param bool $accepted
     * @return array
     */
    public static function execute($accepted) {
        global $USER;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'accepted' => $accepted
        ]);

        // Validate context and capability.
        $context = \context_system::instance();
        self::validate_context($context);
        require_login();
        require_capability('block/mastermind_assistant:view', $context);

        // Save user preference
        $value = $params['accepted'] ? 1 : 0;
        \set_user_preference('mastermind_ai_policy_accepted', $value, $USER);

        return [
            'success' => true,
            'message' => $params['accepted'] ? 'AI policy accepted' : 'AI policy declined'
        ];
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'message' => new external_value(PARAM_TEXT, 'Status message')
        ]);
    }
}

