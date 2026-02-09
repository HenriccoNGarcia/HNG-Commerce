/**
 * HNG Commerce - Dark Mode System
 * Handles theme switching with smooth transitions and localStorage persistence
 */

(function($) {
    'use strict';

    const HNG_DarkMode = {
        init: function() {
            this.loadTheme();
            this.bindEvents();
            this.addToggleButton();
        },

        /**
         * Load saved theme preference
         */
        loadTheme: function() {
            const savedTheme = localStorage.getItem('hng_admin_theme') || 'light';
            this.setTheme(savedTheme, false);
        },

        /**
         * Set theme
         */
        setTheme: function(theme, animate = true) {
            const $body = $('body');
            
            // Add transition class for smooth animation
            if (animate) {
                $body.addClass('hng-theme-transitioning');
                
                setTimeout(function() {
                    $body.removeClass('hng-theme-transitioning');
                }, 300);
            }

            // Remove existing theme classes
            $body.removeClass('hng-theme-light hng-theme-dark');
            
            // Add new theme class
            $body.addClass('hng-theme-' + theme);
            
            // Save preference
            localStorage.setItem('hng_admin_theme', theme);
            
            // Update toggle button
            this.updateToggleButton(theme);
        },

        /**
         * Toggle between themes
         */
        toggleTheme: function() {
            const currentTheme = $('body').hasClass('hng-theme-dark') ? 'dark' : 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            this.setTheme(newTheme, true);
        },

        /**
         * Add theme toggle button to header
         */
        addToggleButton: function() {
            const $headerActions = $('.hng-header-actions');
            
            if ($headerActions.length === 0) return;

            const $toggleButton = $('<button>', {
                'class': 'hng-btn hng-btn-secondary hng-theme-toggle',
                'type': 'button',
                'aria-label': 'Toggle Dark Mode',
                'html': '<span class="dashicons dashicons-admin-appearance"></span> <span class="hng-theme-text">Tema</span>'
            });

            $headerActions.prepend($toggleButton);
        },

        /**
         * Update toggle button appearance
         */
        updateToggleButton: function(theme) {
            const $button = $('.hng-theme-toggle');
            const $icon = $button.find('.dashicons');
            
            if (theme === 'dark') {
                $icon.removeClass('dashicons-admin-appearance').addClass('dashicons-lightbulb');
            } else {
                $icon.removeClass('dashicons-lightbulb').addClass('dashicons-admin-appearance');
            }
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            const self = this;
            
            // Toggle button click
            $(document).on('click', '.hng-theme-toggle', function(e) {
                e.preventDefault();
                self.toggleTheme();
            });

            // Keyboard shortcut: Ctrl+Shift+D
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                    e.preventDefault();
                    self.toggleTheme();
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        HNG_DarkMode.init();
    });

})(jQuery);
