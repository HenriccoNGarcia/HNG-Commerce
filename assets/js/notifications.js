/**
 * HNG Commerce - Toast Notifications System
 * Modern toast notifications with animations
 */

(function ($) {
    'use strict';

    const HNG_Notifications = {
        container: null,

        init: function () {
            this.createContainer();
        },

        /**
         * Create notifications container
         */
        createContainer: function () {
            if ($('#hng-notifications-container').length) {
                this.container = $('#hng-notifications-container');
                return;
            }

            this.container = $('<div>', {
                id: 'hng-notifications-container',
                class: 'hng-notifications-container'
            }).appendTo('body');
        },

        /**
         * Show notification
         */
        show: function (message, type = 'info', duration = 4000) {
            const icons = {
                success: 'yes-alt',
                error: 'dismiss',
                warning: 'warning',
                info: 'info'
            };

            const $toast = $('<div>', {
                class: 'hng-toast hng-toast-' + type,
                html: '<span class="dashicons dashicons-' + icons[type] + '"></span>' +
                    '<span class="hng-toast-message">' + message + '</span>' +
                    '<button class="hng-toast-close"><span class="dashicons dashicons-no-alt"></span></button>'
            });

            this.container.append($toast);

            // Animate in
            setTimeout(() => {
                $toast.addClass('hng-toast-show');
            }, 10);

            // Auto dismiss
            if (duration > 0) {
                setTimeout(() => {
                    this.dismiss($toast);
                }, duration);
            }

            // Manual dismiss
            $toast.find('.hng-toast-close').on('click', () => {
                this.dismiss($toast);
            });

            return $toast;
        },

        /**
         * Dismiss notification
         */
        dismiss: function ($toast) {
            $toast.removeClass('hng-toast-show');
            setTimeout(() => {
                $toast.remove();
            }, 300);
        },

        /**
         * Show success notification
         */
        success: function (message, duration) {
            return this.show(message, 'success', duration);
        },

        /**
         * Show error notification
         */
        error: function (message, duration) {
            return this.show(message, 'error', duration);
        },

        /**
         * Show warning notification
         */
        warning: function (message, duration) {
            return this.show(message, 'warning', duration);
        },

        /**
         * Show info notification
         */
        info: function (message, duration) {
            return this.show(message, 'info', duration);
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        HNG_Notifications.init();
    });

    // Make it globally accessible
    window.HNG_Notifications = HNG_Notifications;

})(jQuery);
