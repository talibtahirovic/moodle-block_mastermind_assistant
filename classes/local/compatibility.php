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

defined('MOODLE_INTERNAL') || die();

/**
 * Dashboard-reported plugin compatibility.
 *
 * The dashboard's test-connection response names the plugin version it
 * requires (the first version that sends no learner identity) and the date
 * after which older versions are refused. Both are recorded here so the
 * settings page can warn an administrator whose install is behind.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compatibility {

    /** @var string Plugin component. */
    const COMPONENT = 'block_mastermind_assistant';

    /**
     * Record the compatibility data carried by a test-connection response.
     *
     * @param array $response Decoded dashboard response.
     */
    public static function record(array $response): void {
        $plugin = $response['plugin'] ?? null;
        if (!is_array($plugin)) {
            return;
        }
        $required = $plugin['required_version'] ?? null;
        if (is_int($required) || (is_string($required) && ctype_digit($required))) {
            set_config('required_plugin_version', (string) (int) $required, self::COMPONENT);
        }
        $sunset = $plugin['legacy_identity_sunset'] ?? null;
        if (is_string($sunset) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sunset)) {
            set_config('legacy_identity_sunset', $sunset, self::COMPONENT);
        }
    }

    /**
     * The settings-page warning when the installed plugin is older than the
     * dashboard requires, or an empty string when current or unknown.
     *
     * @return string Plain-language notice.
     */
    public static function notice(): string {
        $installed = (int) get_config(self::COMPONENT, 'version');
        $required = (int) get_config(self::COMPONENT, 'required_plugin_version');
        if ($required <= 0 || $installed <= 0 || $installed >= $required) {
            return '';
        }
        $sunset = (string) get_config(self::COMPONENT, 'legacy_identity_sunset');
        return get_string('settings_plugin_outdated', self::COMPONENT, (object) [
            'installed' => $installed,
            'required' => $required,
            'sunset' => $sunset !== '' ? $sunset : get_string('settings_plugin_outdated_nosunset', self::COMPONENT),
        ]);
    }
}
