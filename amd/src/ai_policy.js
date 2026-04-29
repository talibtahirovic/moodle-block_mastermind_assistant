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
 * AI Usage Policy Modal.
 *
 * @module     block_mastermind_assistant/ai_policy
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/modal_factory', 'core/modal_events', 'core/str'],
function($, Ajax, Notification, ModalFactory, ModalEvents, Str) {

    /**
     * Check if user has accepted the AI policy
     * @return {Promise} Promise resolving to acceptance status
     */
    function checkPolicyAcceptance() {
        var requests = Ajax.call([{
            methodname: 'block_mastermind_assistant_check_ai_policy',
            args: {}
        }]);

        return requests[0];
    }

    /**
     * Save user's policy acceptance
     * @param {boolean} accepted Whether user accepted the policy
     * @return {Promise} Promise resolving when saved
     */
    function savePolicyAcceptance(accepted) {
        var requests = Ajax.call([{
            methodname: 'block_mastermind_assistant_save_ai_policy',
            args: {
                accepted: accepted
            }
        }]);

        return requests[0];
    }

    /**
     * Show AI usage policy modal
     * @param {function} onAccept Callback when user accepts
     * @param {function} onDecline Callback when user declines
     */
    function showPolicyModal(onAccept, onDecline) {
        // Load all required strings first.
        var stringRequests = [
            {key: 'ai_policy_title', component: 'block_mastermind_assistant'},
            {key: 'ai_policy_body', component: 'block_mastermind_assistant'},
            {key: 'ai_policy_accept_button', component: 'block_mastermind_assistant'},
            {key: 'ai_policy_accepted_msg', component: 'block_mastermind_assistant'},
            {key: 'ai_policy_declined_msg', component: 'block_mastermind_assistant'},
        ];

        Str.get_strings(stringRequests).then(function(strings) {
            var title = strings[0];
            var body = strings[1];
            var acceptBtn = strings[2];
            var acceptedMsg = strings[3];
            var declinedMsg = strings[4];

            return ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: title,
                body: '<div style="padding: 20px;">' + body + '</div>',
                large: true
            }).then(function(modal) {
                modal.setSaveButtonText(acceptBtn);

                // Handle save (accept)
                modal.getRoot().on(ModalEvents.save, function() {
                    savePolicyAcceptance(true).then(function() {
                        Notification.addNotification({
                            message: acceptedMsg,
                            type: 'success'
                        });

                        if (onAccept) {
                            onAccept();
                        }
                    }).catch(function(error) {
                        Notification.exception(error);
                    });

                    modal.hide();
                });

                // Handle cancel (decline)
                modal.getRoot().on(ModalEvents.cancel, function() {
                    Notification.addNotification({
                        message: declinedMsg,
                        type: 'warning'
                    });

                    if (onDecline) {
                        onDecline();
                    }

                    modal.hide();
                });

                modal.show();
            });
        }).catch(function(error) {
            Notification.exception(error);
        });
    }

    /**
     * Check policy and proceed with callback
     * @param {function} callback Function to call if policy is accepted
     */
    function checkAndProceed(callback) {
        checkPolicyAcceptance().then(function(response) {
            if (response.accepted) {
                callback();
            } else {
                showPolicyModal(callback, null);
            }
        }).catch(function(error) {
            Notification.exception(error);
        });
    }

    return {
        checkAndProceed: checkAndProceed,
        showPolicyModal: showPolicyModal
    };
});
