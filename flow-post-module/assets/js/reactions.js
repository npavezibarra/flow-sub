/**
 * Flow Reactions JavaScript
 * 
 * Handles reaction button clicks and AJAX communication.
 * 
 * @package Flow_Sub
 * @since 1.0.1
 */

(function ($) {
    'use strict';

    /**
     * Reaction Handler Class
     */
    const FlowReactionHandler = {

        /**
         * Initialize the handler
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind click events to reaction buttons
         */
        bindEvents: function () {
            $(document).on('click', '.reaction-btn', this.handleReactionClick.bind(this));
        },

        /**
         * Handle reaction button click
         * 
         * @param {Event} event Click event
         */
        handleReactionClick: function (event) {
            event.preventDefault();

            const $button = $(event.currentTarget);
            const $container = $button.closest('.flow-reactions');

            // Prevent double-clicks
            if ($container.hasClass('processing')) {
                return;
            }

            const postId = $container.data('post-id');
            const reaction = $button.data('reaction');

            // Validate data
            if (!postId || reaction === undefined) {
                console.error('Flow Reactions: Missing post ID or reaction type');
                return;
            }

            // Send AJAX request
            this.toggleReaction(postId, reaction, $container);
        },

        /**
         * Send AJAX request to toggle reaction
         * 
         * @param {number} postId Post ID
         * @param {number} reaction Reaction type (1=like, 0=dislike)
         * @param {jQuery} $container Reaction container element
         */
        toggleReaction: function (postId, reaction, $container) {
            // Show loading state
            this.setLoadingState($container, true);

            $.ajax({
                url: flowReactions.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'flow_toggle_reaction',
                    post_id: postId,
                    reaction: reaction,
                    nonce: flowReactions.nonce
                },
                success: (response) => {
                    this.handleSuccess(response, $container);
                },
                error: (xhr, status, error) => {
                    this.handleError(xhr, $container);
                },
                complete: () => {
                    this.setLoadingState($container, false);
                }
            });
        },

        /**
         * Handle successful AJAX response
         * 
         * @param {Object} response AJAX response
         * @param {jQuery} $container Reaction container element
         */
        handleSuccess: function (response, $container) {
            if (response.success && response.data) {
                const data = response.data;

                // Update like count
                $container.find('.like-count').text(data.like_count);

                // Update dislike count
                $container.find('.dislike-count').text(data.dislike_count);

                // Update active states
                $container.find('.like-btn').toggleClass('active', data.user_reaction === 1);
                $container.find('.dislike-btn').toggleClass('active', data.user_reaction === 0);

                // Optional: Show success message
                if (data.message) {
                    this.showMessage(data.message, 'success');
                }

                // Trigger custom event for other scripts
                $(document).trigger('flow:reaction:updated', {
                    postId: $container.data('post-id'),
                    reaction: data.user_reaction,
                    counts: {
                        likes: data.like_count,
                        dislikes: data.dislike_count
                    }
                });
            } else {
                this.handleError({ responseJSON: response }, $container);
            }
        },

        /**
         * Handle AJAX error
         * 
         * @param {Object} xhr XHR object
         * @param {jQuery} $container Reaction container element
         */
        handleError: function (xhr, $container) {
            let message = flowReactions.strings.error;

            if (xhr.responseJSON && xhr.responseJSON.data) {
                if (xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }

                // Handle login required
                if (xhr.responseJSON.data.login_required) {
                    message = flowReactions.strings.loginRequired;
                }
            }

            this.showMessage(message, 'error');

            console.error('Flow Reactions Error:', xhr);
        },

        /**
         * Set loading state
         * 
         * @param {jQuery} $container Reaction container element
         * @param {boolean} isLoading Loading state
         */
        setLoadingState: function ($container, isLoading) {
            if (isLoading) {
                $container.addClass('processing');
                $container.find('.reaction-btn').addClass('disabled').prop('disabled', true);
                $container.find('.reaction-loading').show();
            } else {
                $container.removeClass('processing');
                $container.find('.reaction-btn').removeClass('disabled').prop('disabled', false);
                $container.find('.reaction-loading').hide();
            }
        },

        /**
         * Show message to user
         * 
         * @param {string} message Message text
         * @param {string} type Message type (success, error, info)
         */
        showMessage: function (message, type = 'info') {
            // Check if custom alert function exists (from archive-flow-post.php)
            if (typeof window.alert === 'function') {
                window.alert(message);
                return;
            }

            // Fallback: Create simple toast notification
            const $toast = $('<div>')
                .addClass('flow-reaction-toast')
                .addClass('toast-' + type)
                .text(message)
                .css({
                    position: 'fixed',
                    bottom: '20px',
                    right: '20px',
                    padding: '12px 20px',
                    background: type === 'error' ? '#ef4444' : '#10b981',
                    color: '#ffffff',
                    borderRadius: '8px',
                    boxShadow: '0 4px 6px rgba(0, 0, 0, 0.1)',
                    zIndex: 10000,
                    fontSize: '14px',
                    fontWeight: '500',
                    maxWidth: '300px',
                    animation: 'slideIn 0.3s ease'
                });

            $('body').append($toast);

            // Remove after 3 seconds
            setTimeout(() => {
                $toast.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Debounce function to prevent rapid clicks
         * 
         * @param {Function} func Function to debounce
         * @param {number} wait Wait time in milliseconds
         * @returns {Function} Debounced function
         */
        debounce: function (func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        FlowReactionHandler.init();
    });

    // Expose to global scope for debugging
    window.FlowReactionHandler = FlowReactionHandler;

})(jQuery);
