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
 * External function to test dashboard connection
 *
 * @package    block_mastermind_assistant
 * @copyright  2025 The Namers <info@mastermindassistant.ai>
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
use Exception;

class test_connection extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Test connection to the Mastermind Dashboard API.
     * @return array
     */
    public static function execute() {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        try {
            $client = new \block_mastermind_assistant\api_client();

            // Test basic connectivity.
            $client->testConnection();

            // Fetch account info (tier and status only).
            $accountResult = $client->getAccount();

            return [
                'success' => true,
                'tier' => $accountResult['tier'] ?? '',
                'status' => $accountResult['status'] ?? '',
                'message' => get_string('connection_success', 'block_mastermind_assistant')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'tier' => '',
                'status' => '',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Connection success'),
            'tier' => new external_value(PARAM_TEXT, 'Account tier'),
            'status' => new external_value(PARAM_TEXT, 'Account status'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
