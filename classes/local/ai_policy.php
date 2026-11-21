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
 * The AI usage policy acceptance, enforced on the server.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Whether the current user accepted the AI usage policy.
 *
 * The browser shows the policy modal before any AI request (amd/src/ai_policy.js),
 * but a direct web-service call skips the browser. Every external function that
 * sends course or learner data to the dashboard calls require_accepted() before it
 * builds the payload, so acceptance holds on the server as well.
 */
class ai_policy {
    /** @var string User preference that records acceptance (1 = accepted). */
    const PREFERENCE = 'mastermind_ai_policy_accepted';

    /**
     * Whether the current user accepted the policy.
     *
     * @return bool
     */
    public static function is_accepted(): bool {
        return (bool) get_user_preferences(self::PREFERENCE, 0);
    }

    /**
     * Refuse unless the current user accepted the policy.
     *
     * @throws \moodle_exception With the message the policy modal shows on decline.
     */
    public static function require_accepted(): void {
        if (!self::is_accepted()) {
            throw new \moodle_exception('ai_policy_declined_msg', 'block_mastermind_assistant');
        }
    }
}
