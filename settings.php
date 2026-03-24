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
 * Settings page for Mastermind Assistant
 *
 * @package    block_mastermind_assistant
 * @copyright  2025 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Dashboard URL.
    $settings->add(new admin_setting_configtext(
        'block_mastermind_assistant/dashboard_url',
        get_string('settings_dashboard_url', 'block_mastermind_assistant'),
        get_string('settings_dashboard_url_desc', 'block_mastermind_assistant'),
        'https://mastermindassistant.ai'
    ));

    // API Key.
    $settings->add(new admin_setting_configtext(
        'block_mastermind_assistant/api_key',
        get_string('settings_api_key', 'block_mastermind_assistant'),
        get_string('settings_api_key_desc', 'block_mastermind_assistant'),
        ''
    ));

    // Registration and support info.
    $infohtml = '<div style="margin-top: 0.5rem;">';
    $infohtml .= '<p>' . get_string('settings_register_desc', 'block_mastermind_assistant') . ' ';
    $infohtml .= '<a href="https://mastermindassistant.ai" target="_blank" rel="noopener">';
    $infohtml .= 'mastermindassistant.ai</a></p>';
    $infohtml .= '<p>' . get_string('settings_support_desc', 'block_mastermind_assistant') . ' ';
    $infohtml .= '<a href="mailto:info@mastermindassistant.ai">info@mastermindassistant.ai</a></p>';
    $infohtml .= '</div>';

    $settings->add(new admin_setting_heading(
        'block_mastermind_assistant/info_heading',
        get_string('settings_info_heading', 'block_mastermind_assistant'),
        $infohtml
    ));

    // Test Connection button — always visible. JS validates that fields are filled.
    $html = '<div id="mastermind-settings-info" style="margin-top: 1rem;">';
    $html .= '<p style="margin-bottom: 0.5rem; color: #666; font-size: 0.85em;">';
    $html .= get_string('test_connection_desc', 'block_mastermind_assistant');
    $html .= '</p>';
    $html .= '<button type="button" id="mastermind-test-connection" class="btn btn-secondary">';
    $html .= get_string('test_connection', 'block_mastermind_assistant');
    $html .= '</button>';
    $html .= '<div id="mastermind-connection-result" style="margin-top: 0.75rem;"></div>';
    $html .= '</div>';

    $settings->add(new admin_setting_heading(
        'block_mastermind_assistant/connection_heading',
        get_string('connection_status', 'block_mastermind_assistant'),
        $html
    ));

    // Load the settings JS module.
    global $PAGE;
    $PAGE->requires->js_call_amd('block_mastermind_assistant/settings', 'init');
}
