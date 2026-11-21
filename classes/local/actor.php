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
 * The pseudonym every request to an AI feature carries.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * A pseudonym of the person making a request, so the dashboard can tell requests apart by person
 * without knowing who it is.
 *
 * It is the formula of the enterprise companion's local\actor (decisions on suggested actions), so
 * one person has one pseudonym in both. Keyed with the site identifier, the dashboard cannot
 * reverse it; this site can recompute it to tell who it was.
 */
class actor {
    /**
     * The pseudonym of a user: an HMAC-SHA256 of 'mastermind-actor:' and the user id, keyed with the site
     * identifier, 32 hex characters. Byte for byte the enterprise companion's formula (see actor_test).
     *
     * @param int $userid Moodle user id.
     * @return string
     */
    public static function pseudonym(int $userid): string {
        global $CFG;
        return substr(hash_hmac('sha256', 'mastermind-actor:' . $userid, (string) $CFG->siteidentifier), 0, 32);
    }

    /**
     * The pseudonym of the person making the current request, or null when no person is making it.
     *
     * Null for guests and users who are not logged in, and on the command line: scheduled tasks run
     * there with the admin as $USER, which is not a person clicking. During "log in as" it is the
     * real user, the person who actually acts.
     *
     * @param bool|null $cli Whether this is a command-line run; by default CLI_SCRIPT, except under PHPUnit.
     * @return string|null
     */
    public static function current(?bool $cli = null): ?string {
        global $USER;
        $cli ??= defined('CLI_SCRIPT') && CLI_SCRIPT && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST);
        if ($cli || !isloggedin() || isguestuser()) {
            return null;
        }
        $userid = \core\session\manager::is_loggedinas() ? (int) $USER->realuser : (int) $USER->id;
        return $userid > 0 ? self::pseudonym($userid) : null;
    }
}
