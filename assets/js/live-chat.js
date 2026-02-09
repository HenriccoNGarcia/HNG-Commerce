/**
 * HNG Commerce - Live Chat Frontend JavaScript
 * 
 * @package HNG_Commerce
 * @since 1.3.0
 */

(function($) {
    'use strict';

    // Chat controller
    const HNGLiveChat = {
        config: null,
        sessionKey: null,
        sessionId: null,
        guestToken: null,
        lastMessageId: 0,
        isOpen: false,
        isConnected: false,
        pollInterval: null,
        typingTimeout: null,
        unreadCount: 0,

        /**
         * Initialize
         */
        init: function() {
            this.config = window.hngLiveChatConfig || {};
            
            if (!this.config.ajaxUrl) {
                console.error('HNG Live Chat: Configuration missing');
                return;
            }

            this.bindEvents();
            this.restoreSession();
            this.applyCustomStyles();
            this.checkBusinessHours();
            this.restoreSoundPreference();
        },

        /**
         * Apply custom styles
         */
        applyCustomStyles: function() {
            const settings = this.config.settings || {};
            const widget = $('#hng-live-chat-widget');
            
            if (settings.primaryColor) {
                widget.css('--hng-chat-primary', settings.primaryColor);
            }
            
            // Apply start button color if configured
            if (settings.startButtonColor) {
                widget.css('--hng-chat-start-btn-color', settings.startButtonColor);
            }
            
            // Apply button text color
            if (settings.buttonTextColor) {
                widget.css('--hng-chat-btn-text-color', settings.buttonTextColor);
            }
            
            // Apply header colors
            if (settings.headerColor) {
                widget.css('--hng-chat-header-color', settings.headerColor);
            }
            if (settings.headerTextColor) {
                widget.css('--hng-chat-header-text-color', settings.headerTextColor);
            }
            
            // Apply chat background color
            if (settings.chatBgColor) {
                widget.css('--hng-chat-bg-color', settings.chatBgColor);
            }
            
            // Apply message text color
            if (settings.messageTextColor) {
                widget.css('--hng-chat-message-text-color', settings.messageTextColor);
            }
        },
        
        /**
         * Check business hours and show message if outside
         */
        checkBusinessHours: function() {
            const settings = this.config.settings || {};
            
            if (settings.businessHoursEnabled && !settings.isWithinBusinessHours) {
                // Show outside hours message
                $('.hng-chat-welcome').text(settings.outsideHoursMessage);
                $('.hng-chat-start-btn').prop('disabled', true).text(this.config.i18n.outsideHours || 'Fora do horário');
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Toggle chat
            $(document).on('click', '.hng-chat-bubble', function() {
                self.toggleChat();
            });

            // Close chat
            $(document).on('click', '.hng-chat-close', function() {
                self.closeChat();
            });

            // Minimize chat
            $(document).on('click', '.hng-chat-minimize', function() {
                self.minimizeChat();
            });

            // Sound toggle
            $(document).on('click', '.hng-chat-sound-toggle', function() {
                self.toggleSound();
            });

            // Start chat form
            $(document).on('submit', '.hng-chat-prechat-form', function(e) {
                e.preventDefault();
                self.startChat($(this));
            });

            // Send message
            $(document).on('click', '.hng-chat-send', function() {
                self.sendMessage();
            });

            $(document).on('keypress', '.hng-chat-input', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // Typing indicator
            $(document).on('input', '.hng-chat-input', function() {
                self.sendTypingIndicator(true);
                
                clearTimeout(self.typingTimeout);
                self.typingTimeout = setTimeout(function() {
                    self.sendTypingIndicator(false);
                }, 2000);
            });

            // File upload
            $(document).on('click', '.hng-chat-attach', function() {
                $('.hng-chat-file-input').click();
            });

            $(document).on('change', '.hng-chat-file-input', function() {
                self.uploadFile(this.files[0]);
                $(this).val('');
            });

            // End chat
            $(document).on('click', '.hng-chat-end-btn', function() {
                if (confirm(self.config.i18n.endChat + '?')) {
                    self.endChat();
                }
            });

            // Rating
            $(document).on('click', '.hng-chat-stars button', function() {
                const rating = $(this).data('rating');
                $(this).addClass('active').prevAll().addClass('active');
                $(this).nextAll().removeClass('active');
                self.selectedRating = rating;
            });

            $(document).on('click', '.hng-chat-rating-submit', function() {
                self.submitRating();
            });

            $(document).on('click', '.hng-chat-rating-skip', function() {
                self.closeRating();
            });

            // Auto-resize textarea
            $(document).on('input', '.hng-chat-input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });

            // Heartbeat for real-time updates
            $(document).on('heartbeat-send', function(e, data) {
                if (self.sessionKey) {
                    data.hng_live_chat_session = self.sessionKey;
                    data.hng_live_chat_last_id = self.lastMessageId;
                }
            });

            $(document).on('heartbeat-tick', function(e, data) {
                if (data.hng_live_chat) {
                    self.processNewMessages(data.hng_live_chat.messages);
                    
                    if (data.hng_live_chat.session_status === 'closed') {
                        self.handleSessionClosed();
                    }
                    
                    if (data.hng_live_chat.operator_typing) {
                        self.showTypingIndicator(self.config.i18n.isTyping);
                    } else {
                        self.hideTypingIndicator();
                    }
                }
            });
        },

        /**
         * Restore previous session
         */
        restoreSession: function() {
            const savedSession = localStorage.getItem('hng_live_chat_session');
            
            if (savedSession) {
                try {
                    const session = JSON.parse(savedSession);
                    
                    // Check if session is marked as closed locally
                    if (session.closed) {
                        localStorage.removeItem('hng_live_chat_session');
                        return;
                    }
                    
                    // Check if session is not too old (2 hours for active sessions)
                    if (session.timestamp && (Date.now() - session.timestamp) < 7200000) {
                        this.sessionKey = session.key;
                        this.sessionId = session.id;
                        this.lastMessageId = session.lastMessageId || 0;
                        
                        // Try to resume session
                        this.resumeSession();
                    } else {
                        localStorage.removeItem('hng_live_chat_session');
                    }
                } catch (e) {
                    localStorage.removeItem('hng_live_chat_session');
                }
            }
        },

        /**
         * Save session
         */
        saveSession: function() {
            if (this.sessionKey) {
                localStorage.setItem('hng_live_chat_session', JSON.stringify({
                    key: this.sessionKey,
                    id: this.sessionId,
                    lastMessageId: this.lastMessageId,
                    timestamp: Date.now()
                }));
            }
        },

        /**
         * Resume session
         */
        resumeSession: function() {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_get_messages',
                    nonce: this.config.nonce,
                    session_key: this.sessionKey,
                    last_id: 0
                },
                success: function(response) {
                    if (response.success) {
                        // Check if session is closed - if so, reset UI
                        if (response.data.session_status === 'closed') {
                            self.clearSession();
                            self.resetChatUI();
                            return;
                        }
                        
                        self.isConnected = true;
                        self.showChatArea();
                        self.processNewMessages(response.data.messages);
                        self.startPolling();
                        
                        if (response.data.operator_name) {
                            self.updateStatus(response.data.operator_name + ' ' + self.config.i18n.operatorJoined);
                        }
                    } else {
                        // Session invalid, clear
                        self.clearSession();
                    }
                },
                error: function() {
                    self.clearSession();
                }
            });
        },

        /**
         * Clear session
         */
        clearSession: function() {
            // Mark session as closed before removing
            const savedSession = localStorage.getItem('hng_live_chat_session');
            if (savedSession) {
                try {
                    const session = JSON.parse(savedSession);
                    session.closed = true;
                    localStorage.setItem('hng_live_chat_session', JSON.stringify(session));
                } catch (e) {}
            }
            
            this.sessionKey = null;
            this.sessionId = null;
            this.lastMessageId = 0;
            this.isConnected = false;
            localStorage.removeItem('hng_live_chat_session');
            this.stopPolling();
        },
        
        /**
         * Reset chat UI to initial state
         */
        resetChatUI: function() {
            const self = this;
            
            // Clear any existing polling
            this.stopPolling();
            
            // Reset state variables
            this.sessionKey = null;
            this.sessionId = null;
            this.lastMessageId = 0;
            this.isConnected = false;
            
            // Clear localStorage completely
            localStorage.removeItem('hng_live_chat_session');
            
            // Reset visibility with slight delay to ensure DOM is ready
            setTimeout(function() {
                $('.hng-chat-prechat').show();
                $('.hng-chat-messages').hide().empty();
                $('.hng-chat-footer').hide();
                $('.hng-chat-rating').hide();
                
                // Reset form if exists
                var $form = $('.hng-chat-prechat-form');
                if ($form.length && $form[0]) {
                    $form[0].reset();
                }
                $('.hng-chat-start-btn').prop('disabled', false).text(self.config.i18n.startChat);
                
                // Reset status
                self.updateStatus('');
            }, 100);
        },

        /**
         * Toggle chat window
         */
        toggleChat: function() {
            if (this.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        },

        /**
         * Open chat
         */
        openChat: function() {
            this.isOpen = true;
            $('.hng-chat-window').show();
            $('.hng-chat-bubble').hide();
            
            // Clear unread
            this.unreadCount = 0;
            this.updateBadge();
            
            // Scroll to bottom
            this.scrollToBottom();
            
            // Focus input
            setTimeout(function() {
                if ($('.hng-chat-messages').is(':visible')) {
                    $('.hng-chat-input').focus();
                } else {
                    $('.hng-chat-prechat-form input:first').focus();
                }
            }, 300);
        },

        /**
         * Close chat
         */
        closeChat: function() {
            this.isOpen = false;
            $('.hng-chat-window').hide();
            $('.hng-chat-bubble').show();
        },

        /**
         * Minimize chat
         */
        minimizeChat: function() {
            $('#hng-live-chat-widget').toggleClass('minimized');
        },

        /**
         * Start chat
         */
        startChat: function($form) {
            const self = this;
            const $btn = $form.find('.hng-chat-start-btn');
            
            // Prevent double submit
            if ($btn.prop('disabled')) {
                return;
            }
            
            $btn.prop('disabled', true).text(this.config.i18n.connecting);

            const initialMessage = $form.find('[name="initial_message"]').val() || '';
            
            const data = {
                action: 'hng_live_chat_start',
                nonce: this.config.nonce,
                guest_name: $form.find('[name="guest_name"]').val() || '',
                guest_email: $form.find('[name="guest_email"]').val() || '',
                subject: $form.find('[name="subject"]').val() || '',
                initial_message: initialMessage,
                page_url: window.location.href
            };

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.sessionKey = response.data.session_key;
                        self.sessionId = response.data.session_id;
                        self.guestToken = response.data.guest_token;
                        self.isConnected = true;
                        
                        // Mark initial message ID so we don't add it again from polling
                        self.lastMessageId = response.data.last_message_id || 0;
                        
                        self.saveSession();
                        self.showChatArea();
                        self.updateStatus(self.config.i18n.waitingOperator);
                        self.startPolling();
                        
                        // Show the initial message that was already sent to server
                        // (Server already saved it, just display locally)
                        if (initialMessage) {
                            self.addMessage({
                                sender_type: 'customer',
                                message: initialMessage,
                                time: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                            });
                        }
                    } else {
                        alert(response.data.message || self.config.i18n.error);
                        $btn.prop('disabled', false).text(self.config.i18n.startChat);
                    }
                },
                error: function() {
                    alert(self.config.i18n.error);
                    $btn.prop('disabled', false).text(self.config.i18n.startChat);
                }
            });
        },

        /**
         * Show chat area
         */
        showChatArea: function() {
            $('.hng-chat-prechat').hide();
            $('.hng-chat-messages').show().empty();
            $('.hng-chat-footer').show();
            $('.hng-chat-input').focus();
        },

        /**
         * Update status text
         */
        updateStatus: function(text) {
            $('.hng-chat-status').text(text);
        },

        /**
         * Send message
         */
        sendMessage: function() {
            const $input = $('.hng-chat-input');
            const message = $input.val().trim();

            if (!message || !this.sessionKey) {
                return;
            }

            const self = this;

            // Clear input
            $input.val('').css('height', 'auto');

            // Add message immediately
            this.addMessage({
                sender_type: 'customer',
                message: message,
                time: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}),
                pending: true
            });

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_send',
                    nonce: this.config.nonce,
                    session_key: this.sessionKey,
                    message: message
                },
                success: function(response) {
                    if (response.success) {
                        // Mark as sent
                        $('.hng-chat-message.pending').last().removeClass('pending');
                        self.lastMessageId = Math.max(self.lastMessageId, response.data.message_id);
                        self.saveSession();
                    } else {
                        alert(response.data.message || self.config.i18n.error);
                    }
                },
                error: function() {
                    alert(self.config.i18n.error);
                }
            });
        },

        /**
         * Add message to chat
         */
        addMessage: function(msg) {
            const $container = $('.hng-chat-messages');
            
            let html = '<div class="hng-chat-message ' + msg.sender_type;
            if (msg.pending) html += ' pending';
            html += '">';
            
            if (msg.sender_type === 'operator' && msg.sender_name) {
                html += '<div class="hng-chat-message-header">';
                html += '<span class="hng-chat-sender-name">' + this.escapeHtml(msg.sender_name) + '</span>';
                html += '</div>';
            }
            
            if (msg.message_type === 'image' && msg.attachment) {
                html += '<div class="hng-chat-attachment">';
                html += '<img src="' + msg.attachment.url + '" alt="' + this.escapeHtml(msg.attachment.name) + '" onclick="window.open(this.src)" />';
                html += '</div>';
            } else if (msg.message_type === 'file' && msg.attachment) {
                html += '<div class="hng-chat-attachment">';
                html += '<a href="' + msg.attachment.url + '" target="_blank" class="hng-chat-file-preview">';
                html += '<span class="hng-chat-file-icon">📎</span>';
                html += '<span class="hng-chat-file-info">';
                html += '<span class="hng-chat-file-name">' + this.escapeHtml(msg.attachment.name) + '</span>';
                html += '<span class="hng-chat-file-size">' + this.formatFileSize(msg.attachment.size) + '</span>';
                html += '</span>';
                html += '</a>';
                html += '</div>';
            } else {
                html += '<div class="hng-chat-message-content">' + this.escapeHtml(msg.message).replace(/\n/g, '<br>') + '</div>';
            }
            
            if (msg.time && msg.sender_type !== 'system') {
                html += '<div class="hng-chat-message-time">' + msg.time + '</div>';
            }
            
            html += '</div>';
            
            $container.append(html);
            this.scrollToBottom();
        },

        /**
         * Process new messages from polling/heartbeat
         */
        processNewMessages: function(messages) {
            const self = this;
            
            console.log('HNG Live Chat: processNewMessages called with', messages ? messages.length : 0, 'messages');
            
            if (!messages || !messages.length) {
                return;
            }

            messages.forEach(function(msg) {
                console.log('HNG Live Chat: Processing message id=' + msg.id + ', lastMessageId=' + self.lastMessageId);
                if (msg.id > self.lastMessageId) {
                    console.log('HNG Live Chat: Adding message', msg);
                    self.addMessage(msg);
                    self.lastMessageId = msg.id;
                    
                    // Play sound for operator messages (play if chat is not open, or if document is not visible)
                    if (msg.sender_type === 'operator' && self.config.settings.soundEnabled) {
                        // Always play sound for new operator messages
                        self.playNotificationSound();
                        
                        // Update badge only if chat is not open
                        if (!self.isOpen) {
                            self.unreadCount++;
                            self.updateBadge();
                        }
                    }
                    
                    // Update status when operator joins
                    if (msg.sender_type === 'system' && msg.message.includes('entrou')) {
                        self.updateStatus(msg.sender_name + ' ' + self.config.i18n.operatorJoined);
                    }
                }
            });

            this.saveSession();
        },

        /**
         * Start polling for new messages
         */
        startPolling: function() {
            const self = this;
            
            // Stop existing poll
            this.stopPolling();
            
            // Poll every 3 seconds
            this.pollInterval = setInterval(function() {
                self.pollMessages();
            }, 3000);
        },

        /**
         * Stop polling
         */
        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        /**
         * Poll for new messages
         */
        pollMessages: function() {
            const self = this;

            if (!this.sessionKey) {
                console.log('HNG Live Chat: No session key, stopping polling');
                this.stopPolling();
                return;
            }

            console.log('HNG Live Chat: Polling for messages, last_id=' + this.lastMessageId);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_get_messages',
                    nonce: this.config.nonce,
                    session_key: this.sessionKey,
                    last_id: this.lastMessageId
                },
                success: function(response) {
                    console.log('HNG Live Chat: Poll response', response);
                    if (response.success) {
                        self.processNewMessages(response.data.messages);
                        
                        if (response.data.session_status === 'closed') {
                            self.handleSessionClosed();
                        }
                        
                        if (response.data.operator_name) {
                            self.updateStatus(response.data.operator_name);
                        }
                    } else {
                        console.error('HNG Live Chat: Poll error', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('HNG Live Chat: AJAX error', status, error);
                }
            });
        },

        /**
         * Upload file
         */
        uploadFile: function(file) {
            const self = this;
            const settings = this.config.settings;

            if (!file || !this.sessionKey) {
                return;
            }

            // Validate size
            if (file.size > settings.maxFileSize) {
                alert(this.config.i18n.fileTooLarge);
                return;
            }

            // Validate type
            const ext = file.name.split('.').pop().toLowerCase();
            const allowed = settings.allowedFileTypes.split(',').map(s => s.trim().toLowerCase());
            
            if (!allowed.includes(ext)) {
                alert(this.config.i18n.fileNotAllowed);
                return;
            }

            // Show progress
            const $progress = $('<div class="hng-chat-upload-progress">' +
                '<span>' + this.config.i18n.uploading + '</span>' +
                '<div class="hng-chat-upload-progress-bar"><span></span></div>' +
                '</div>');
            
            $('.hng-chat-messages').append($progress);
            this.scrollToBottom();

            const formData = new FormData();
            formData.append('action', 'hng_live_chat_upload');
            formData.append('nonce', this.config.nonce);
            formData.append('session_key', this.sessionKey);
            formData.append('file', file);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = (e.loaded / e.total) * 100;
                            $progress.find('.hng-chat-upload-progress-bar span').css('width', percent + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    $progress.remove();
                    
                    if (response.success) {
                        self.addMessage(response.data.message);
                        self.lastMessageId = Math.max(self.lastMessageId, response.data.message_id);
                        self.saveSession();
                    } else {
                        alert(response.data.message || self.config.i18n.error);
                    }
                },
                error: function() {
                    $progress.remove();
                    alert(self.config.i18n.error);
                }
            });
        },

        /**
         * Send typing indicator
         */
        sendTypingIndicator: function(isTyping) {
            if (!this.sessionKey) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_typing',
                    nonce: this.config.nonce,
                    session_key: this.sessionKey,
                    is_typing: isTyping
                }
            });
        },

        /**
         * Show typing indicator
         */
        showTypingIndicator: function(name) {
            const $typing = $('.hng-chat-typing');
            $typing.find('.hng-typing-text').text(name);
            $typing.show();
            this.scrollToBottom();
        },

        /**
         * Hide typing indicator
         */
        hideTypingIndicator: function() {
            $('.hng-chat-typing').hide();
        },

        /**
         * End chat
         */
        endChat: function(rating, comment) {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_end',
                    nonce: this.config.nonce,
                    session_key: this.sessionKey,
                    rating: rating || 0,
                    comment: comment || ''
                },
                success: function(response) {
                    self.handleSessionClosed();
                    
                    // Show rating if not provided
                    if (!rating) {
                        self.showRating();
                    }
                }
            });
        },

        /**
         * Handle session closed
         */
        handleSessionClosed: function() {
            this.stopPolling();
            this.updateStatus(this.config.i18n.chatEnded);
            $('.hng-chat-input-container').hide();
            $('.hng-chat-end-container').hide();
            $('.hng-chat-footer').hide();
            
            // Only add message if not already added
            const $messages = $('.hng-chat-messages');
            const lastMessage = $messages.find('.hng-chat-message.system').last().text();
            
            if (!lastMessage.includes(this.config.i18n.chatEnded)) {
                this.addMessage({
                    sender_type: 'system',
                    message: this.config.i18n.chatEnded
                });
            }
            
            // Show rating after a brief delay
            const self = this;
            setTimeout(function() {
                self.showRating();
            }, 500);
        },

        /**
         * Show rating overlay
         */
        showRating: function() {
            // Only show if we have a session
            if (!this.sessionKey) {
                return;
            }
            
            $('.hng-chat-rating').show();
            this.selectedRating = 0;
        },

        /**
         * Submit rating
         */
        submitRating: function() {
            const self = this;
            const comment = $('.hng-chat-rating-comment').val();

            if (!this.selectedRating) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_end',
                    nonce: this.config.nonce,
                    session_key: this.sessionKey,
                    rating: this.selectedRating,
                    comment: comment
                },
                success: function() {
                    self.closeRating();
                    self.clearSession();
                    
                    // Show thanks
                    $('.hng-chat-rating').html('<p style="text-align:center;padding:20px;">' + self.config.i18n.thanks + '</p>');
                    
                    setTimeout(function() {
                        self.closeChat();
                        location.reload();
                    }, 2000);
                }
            });
        },

        /**
         * Close rating overlay
         */
        closeRating: function() {
            $('.hng-chat-rating').hide();
            this.clearSession();
        },

        /**
         * Scroll to bottom of messages
         */
        scrollToBottom: function() {
            const $messages = $('.hng-chat-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        },

        /**
         * Update badge count
         */
        updateBadge: function() {
            const $badge = $('.hng-chat-badge');
            
            if (this.unreadCount > 0) {
                $badge.text(this.unreadCount).show();
            } else {
                $badge.hide();
            }
        },

        /**
         * Play notification sound
         */
        playNotificationSound: function() {
            // Check if sound is enabled
            if (!this.config.settings.soundEnabled) {
                return;
            }
            
            try {
                // Use Web Audio API to generate a pleasant notification sound
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // Create oscillators for a pleasant ding-dong sound
                const osc1 = audioContext.createOscillator();
                const osc2 = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                osc1.connect(gainNode);
                osc2.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                // Pleasant notification frequencies (G5 and E5)
                osc1.frequency.value = 784;
                osc2.frequency.value = 659;
                osc1.type = 'sine';
                osc2.type = 'sine';
                
                // Fade in and out
                const now = audioContext.currentTime;
                gainNode.gain.setValueAtTime(0, now);
                gainNode.gain.linearRampToValueAtTime(0.3, now + 0.05);
                gainNode.gain.linearRampToValueAtTime(0.1, now + 0.15);
                gainNode.gain.linearRampToValueAtTime(0, now + 0.4);
                
                osc1.start(now);
                osc2.start(now + 0.15);
                osc1.stop(now + 0.2);
                osc2.stop(now + 0.4);
                
                console.log('HNG Chat: Notification sound played');
            } catch (e) {
                console.log('HNG Chat: Audio not supported', e);
            }
        },

        /**
         * Toggle sound on/off
         */
        toggleSound: function() {
            const self = this;
            const $btn = $('.hng-chat-sound-toggle');
            
            // Toggle sound setting
            this.config.settings.soundEnabled = !this.config.settings.soundEnabled;
            
            if (this.config.settings.soundEnabled) {
                $btn.addClass('sound-active');
                $btn.attr('title', this.config.i18n?.soundOn || 'Som ativado');
                // Play test sound to activate audio context
                this.playNotificationSound();
            } else {
                $btn.removeClass('sound-active');
                $btn.attr('title', this.config.i18n?.soundOff || 'Som desativado');
            }
            
            // Save preference in localStorage
            try {
                localStorage.setItem('hng_chat_sound_enabled', this.config.settings.soundEnabled ? '1' : '0');
            } catch (e) {}
            
            console.log('HNG Chat: Sound toggled to', this.config.settings.soundEnabled);
        },
        
        /**
         * Restore sound preference
         */
        restoreSoundPreference: function() {
            try {
                const savedPref = localStorage.getItem('hng_chat_sound_enabled');
                
                // Se nunca foi configurado, iniciar com som ATIVADO por padrão
                if (savedPref === null) {
                    this.config.settings.soundEnabled = true;
                    localStorage.setItem('hng_chat_sound_enabled', '1');
                    $('.hng-chat-sound-toggle').addClass('sound-active');
                    console.log('HNG Chat: Sound enabled by default');
                } else {
                    // Restaurar preferência salva
                    this.config.settings.soundEnabled = savedPref === '1';
                    if (this.config.settings.soundEnabled) {
                        $('.hng-chat-sound-toggle').addClass('sound-active');
                    }
                }
            } catch (e) {
                // Fallback: ativar som mesmo em caso de erro
                this.config.settings.soundEnabled = true;
                $('.hng-chat-sound-toggle').addClass('sound-active');
            }
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (!bytes) return '0 B';
            
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            
            while (bytes >= 1024 && i < units.length - 1) {
                bytes /= 1024;
                i++;
            }
            
            return bytes.toFixed(1) + ' ' + units[i];
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        HNGLiveChat.init();
    });

    // Expose globally
    window.HNGLiveChat = HNGLiveChat;

})(jQuery);
