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
 * Settings page module for Mastermind Assistant.
 * Handles the "Test Connection" button and displays account info.
 *
 * @module     block_mastermind_assistant/settings
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str', 'core/templates'], function(Ajax, Notification, Str, Templates) {

    /**
     * Call the test_connection web service and display results.
     * @param {HTMLElement} button
     * @param {HTMLElement} resultDiv
     */
    function setButtonLabel(button, key, fallback) {
        Str.get_string(key, 'block_mastermind_assistant').then(function(s) {
            button.textContent = s;
            return null;
        }).catch(function() {
            button.textContent = fallback;
        });
    }

    function testConnection(button, resultDiv) {
        button.disabled = true;
        Str.get_string('testing_connection', 'block_mastermind_assistant').then(function(s) {
            button.textContent = s;
            resultDiv.innerHTML = '<div class="alert alert-info">' + escapeHtml(s) + '</div>';
            return null;
        }).catch(function() {
            button.textContent = '...';
        });

        var savedResponse = null;
        Ajax.call([{
            methodname: 'block_mastermind_assistant_test_connection',
            args: {}
        }])[0].then(function(response) {
            button.disabled = false;
            savedResponse = response;
            setButtonLabel(button, 'test_connection', 'Test Connection');

            var context = {
                success: response.success,
                tier: response.tier || '',
                status: response.status || '',
                message: response.message || ''
            };
            return Templates.renderForPromise('block_mastermind_assistant/connection_result', context);
        }).then(function(result) {
            if (result) {
                resultDiv.innerHTML = result.html;
            }
            return null;
        }).catch(function(error) {
            button.disabled = false;
            setButtonLabel(button, 'test_connection', 'Test Connection');
            var msg = (savedResponse && savedResponse.message) || (error && error.message) || '';
            resultDiv.innerHTML = '<div class="alert alert-danger">' + escapeHtml(msg) + '</div>';
            if (!savedResponse) {
                Notification.exception(error);
            }
        });
    }

    /**
     * Escape HTML entities.
     * @param {string} text
     * @return {string}
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text || ''));
        return div.innerHTML;
    }

    return {
        init: function() {
            var button = document.getElementById('mastermind-test-connection');
            var resultDiv = document.getElementById('mastermind-connection-result');

            if (!button || !resultDiv) {
                return;
            }

            button.addEventListener('click', function() {
                // Check if the API key field is empty.
                var apiKeyInput = document.getElementById('id_s_block_mastermind_assistant_api_key');
                if (apiKeyInput && !apiKeyInput.value.trim()) {
                    Str.get_string('settings_save_api_key_first', 'block_mastermind_assistant').then(function(s) {
                        resultDiv.innerHTML = '<div class="alert alert-warning">' + escapeHtml(s) + '</div>';
                        return null;
                    }).catch(function() {
                        // Fallback.
                    });
                    return;
                }
                testConnection(button, resultDiv);
            });
        }
    };
});
