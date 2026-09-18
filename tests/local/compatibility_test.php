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

namespace block_mastermind_assistant\local;

/**
 * Tests for the dashboard-reported plugin compatibility notice.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_mastermind_assistant\local\compatibility
 */
final class compatibility_test extends \advanced_testcase {

    public function test_records_the_required_version_reported_by_the_dashboard(): void {
        $this->resetAfterTest();

        compatibility::record([
            'success' => true,
            'plugin' => ['required_version' => 2026091700, 'legacy_identity_sunset' => '2027-03-31'],
        ]);

        $this->assertSame('2026091700', get_config('block_mastermind_assistant', 'required_plugin_version'));
        $this->assertSame('2027-03-31', get_config('block_mastermind_assistant', 'legacy_identity_sunset'));
    }

    public function test_ignores_a_response_without_compatibility_data(): void {
        $this->resetAfterTest();
        set_config('required_plugin_version', '2026091700', 'block_mastermind_assistant');

        compatibility::record(['success' => true]);
        compatibility::record(['plugin' => ['required_version' => 'not a version']]);

        $this->assertSame('2026091700', get_config('block_mastermind_assistant', 'required_plugin_version'));
    }

    public function test_notice_is_empty_when_the_installed_plugin_is_current(): void {
        $this->resetAfterTest();
        $installed = (int) get_config('block_mastermind_assistant', 'version');
        set_config('required_plugin_version', (string) $installed, 'block_mastermind_assistant');

        $this->assertSame('', compatibility::notice());
    }

    public function test_notice_is_empty_when_nothing_has_been_reported(): void {
        $this->resetAfterTest();
        $this->assertSame('', compatibility::notice());
    }

    public function test_notice_names_both_versions_and_the_sunset_when_outdated(): void {
        $this->resetAfterTest();
        $installed = (int) get_config('block_mastermind_assistant', 'version');
        $required = $installed + 100;
        set_config('required_plugin_version', (string) $required, 'block_mastermind_assistant');
        set_config('legacy_identity_sunset', '2027-03-31', 'block_mastermind_assistant');

        $notice = compatibility::notice();

        $this->assertStringContainsString((string) $installed, $notice);
        $this->assertStringContainsString((string) $required, $notice);
        $this->assertStringContainsString('2027-03-31', $notice);
    }
}
