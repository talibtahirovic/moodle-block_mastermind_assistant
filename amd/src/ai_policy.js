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
define(['jquery', 'core/ajax', 'core/notification', 'core/modal_factory', 'core/modal_events'],
function($, Ajax, Notification, ModalFactory, ModalEvents) {

    /**
     * Check if user has accepted the AI policy
     * @return {Promise} Promise resolving to acceptance status
     */
    function checkPolicyAcceptance() {
        try {
            var requests = Ajax.call([{
                methodname: 'block_mastermind_assistant_check_ai_policy',
                args: {}
            }]);

            return requests[0];
        } catch (error) {
            throw error;
        }
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
        var modalBody = '<div style="padding: 20px;">' +
            '<h4>Welcome to the AI-powered features!</h4>' +
            '<p>This Artificial Intelligence (AI) feature is based on external Large Language Models (LLM) ' +
            'to improve your learning and teaching experience. Before you start using these AI services, ' +
            'please read this usage policy.</p>' +

            '<h5 style="margin-top: 20px;">Accuracy of AI-generated content</h5>' +
            '<p>AI can give useful suggestions and information, but its accuracy may vary. ' +
            'You should always double-check the information provided to make sure it\'s accurate, ' +
            'complete, and suitable for your specific situation.</p>' +

            '<h5 style="margin-top: 20px;">How your data is processed</h5>' +
            '<p>This AI feature uses external Large Language Models (LLM). If you use this feature, ' +
            'any information or personal data you share will be handled according to the privacy policy ' +
            'of those LLMs. We recommend that you read their privacy policy to understand how they will ' +
            'handle your data. Additionally, a record of your interactions with the AI features may be ' +
            'saved in this site.</p>' +

            '<p style="margin-top: 15px;">If you have questions about how your data is processed, ' +
            'please check with your teachers or learning organisation.</p>' +

            '<p style="margin-top: 15px;"><strong>By continuing, you acknowledge that you understand ' +
            'and agree to this policy.</strong></p>' +
        '</div>';

        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: 'AI usage policy',
            body: modalBody,
            large: true
        }).then(function(modal) {
            modal.setSaveButtonText('Accept and continue');

            // Handle save (accept)
            modal.getRoot().on(ModalEvents.save, function() {
                // Save acceptance to server
                savePolicyAcceptance(true).then(function() {
                    // Show success message
                    Notification.addNotification({
                        message: 'AI policy accepted. Proceeding with your request...',
                        type: 'success'
                    });

                    // Proceed with the action
                    if (onAccept) {
                        onAccept();
                    }
                }).catch(function(error) {
                    Notification.exception(error);
                });

                // Let modal close naturally
                modal.hide();
            });

            // Handle cancel (decline)
            modal.getRoot().on(ModalEvents.cancel, function() {
                // Show warning but DON'T save the decline
                // This allows the modal to appear again next time
                Notification.addNotification({
                    message: 'You must accept the AI usage policy to use AI features.',
                    type: 'warning'
                });

                // Call decline callback if provided
                if (onDecline) {
                    onDecline();
                }

                // Let modal close naturally
                modal.hide();
            });

            modal.show();
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
