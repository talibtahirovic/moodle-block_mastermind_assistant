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
 * Tests for the request pseudonym.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * The pseudonym every AI request carries: the same one the enterprise companion sends with decisions.
 *
 * @covers \block_mastermind_assistant\local\actor
 */
final class actor_test extends \advanced_testcase {
    public function test_pseudonym_is_32_hex_and_stable(): void {
        $this->resetAfterTest();
        $this->assertSame(1, preg_match('/^[0-9a-f]{32}$/', actor::pseudonym(5)));
        $this->assertSame(actor::pseudonym(5), actor::pseudonym(5));
        $this->assertNotSame(actor::pseudonym(5), actor::pseudonym(6));
    }

    /**
     * Known answer shared with the enterprise companion's local\actor: both plugins must give one
     * person one pseudonym. (An earlier version of this test copied a formula without the
     * 'mastermind-actor:' prefix and so passed while the two plugins disagreed.)
     */
    public function test_pseudonym_matches_the_known_answer(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->siteidentifier = 'known-site-identifier';
        $this->assertSame('3eed251470ac5ad9fbb023bf25245320', actor::pseudonym(5));
    }

    public function test_pseudonym_equals_the_enterprise_companions(): void {
        $this->resetAfterTest();
        if (!class_exists(\local_mastermind_enterprise\local\actor::class)) {
            $this->markTestSkipped('The enterprise companion is not installed.');
        }
        foreach ([2, 5, 1234] as $userid) {
            $this->assertSame(\local_mastermind_enterprise\local\actor::pseudonym($userid), actor::pseudonym($userid));
        }
    }

    public function test_current_is_the_logged_in_users_pseudonym(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);
        $this->assertSame(actor::pseudonym((int) $teacher->id), actor::current(false));
    }

    public function test_current_is_null_for_guest_and_nobody(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $this->assertNull(actor::current(false));
        $this->setUser(0);
        $this->assertNull(actor::current(false));
    }

    public function test_current_is_null_in_cli(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        // Scheduled tasks run from the command line with the admin as $USER: that is not a person clicking.
        $this->assertNull(actor::current(true));
    }

    public function test_current_uses_the_real_user_when_logged_in_as(): void {
        global $USER;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $this->setAdminUser();
        $adminid = (int) $USER->id;
        \core\session\manager::loginas((int) $teacher->id, \context_system::instance());
        $this->assertSame((int) $teacher->id, (int) $USER->id);
        $this->assertSame(actor::pseudonym($adminid), actor::current(false));
    }
}
