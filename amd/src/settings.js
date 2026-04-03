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
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /**
     * Call the test_connection web service and display results.
     * @param {HTMLElement} button
     * @param {HTMLElement} resultDiv
     */
    function testConnection(button, resultDiv) {
        button.disabled = true;
        button.textContent = 'Testing...';
        resultDiv.innerHTML = '<div class="alert alert-info">Testing connection to Mastermind Dashboard...</div>';

        Ajax.call([{
            methodname: 'block_mastermind_assistant_test_connection',
            args: {}
        }])[0].then(function(response) {
            button.disabled = false;
            button.textContent = 'Test Connection';

            if (response.success) {
                var html = '<div class="alert alert-success">';
                html += '<strong>&#10003; Connected successfully</strong>';
                html += '</div>';
                html += '<table class="table table-sm table-bordered" style="max-width: 400px;">';
                html += '<tbody>';
                html += '<tr><td><strong>Tier</strong></td><td>' + escapeHtml(response.tier) + '</td></tr>';
                html += '<tr><td><strong>Status</strong></td><td>' + escapeHtml(response.status) + '</td></tr>';
                html += '</tbody>';
                html += '</table>';
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">' +
                    '<strong>&#10007; Connection failed:</strong> ' + escapeHtml(response.message) +
                    '</div>';
            }
        }).catch(function(error) {
            button.disabled = false;
            button.textContent = 'Test Connection';
            resultDiv.innerHTML = '<div class="alert alert-danger">' +
                '<strong>&#10007; Error:</strong> ' + escapeHtml(error.message || 'Unknown error') +
                '</div>';
            Notification.exception(error);
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
                    resultDiv.innerHTML = '<div class="alert alert-warning">' +
                        'Please enter your API key above and <strong>save changes</strong> first.' +
                        '</div>';
                    return;
                }
                testConnection(button, resultDiv);
            });
        }
    };
});
