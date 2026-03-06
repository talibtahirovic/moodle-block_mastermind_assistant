/**
 * Settings page module for Mastermind Assistant.
 * Handles the "Test Connection" button and displays account info.
 *
 * @module block_mastermind_assistant/settings
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
                testConnection(button, resultDiv);
            });
        }
    };
});
