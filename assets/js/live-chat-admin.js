/**
 * HNG Commerce - Live Chat Admin JavaScript
 * 
 * @package HNG_Commerce
 * @since 1.3.0
 */

(function($) {
    'use strict';

    const HNGLiveChatAdmin = {
        config: null,
        currentSessionId: null,
        lastMessageId: 0,
        pollInterval: null,
        soundEnabled: true,
        notificationSound: null,
        isLoadingSession: false, // Flag to prevent sound when switching chats
        soundActiveChats: true, // Play sound for active chats

        /**
         * Initialize
         */
        init: function() {
            this.config = window.hngLiveChatAdmin || {};
            
            if (!this.config.ajaxUrl) {
                console.error('HNG Live Chat Admin: Configuration missing');
                return;
            }

            this.bindEvents();
            this.loadSessions();
            this.startSessionsPolling();
            this.initNotificationSound();
        },

        /**
         * Initialize notification sound
         */
        initNotificationSound: function() {
            const self = this;
            this.soundReady = false;
            
            if (this.config.soundUrl) {
                this.notificationSound = new Audio(this.config.soundUrl);
                this.notificationSound.volume = 0.5;
                
                // Test if sound can be loaded
                this.notificationSound.addEventListener('canplaythrough', function() {
                    self.soundReady = true;
                });
                
                this.notificationSound.addEventListener('error', function() {
                    self.soundReady = false;
                    self.notificationSound = null;
                });
                
                // Preload
                this.notificationSound.load();
            }
            
            // Initialize AudioContext for fallback
            try {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            } catch (e) {
                this.audioContext = null;
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Filter change
            $('#hng-chat-status-filter').on('change', function() {
                self.loadSessions();
            });

            // Sound test button - toggle sound on/off
            $('#hng-chat-sound-test').on('click', function() {
                const $btn = $(this);
                
                // Toggle sound state
                self.soundEnabled = !self.soundEnabled;
                
                if (self.soundEnabled) {
                    // Play a test sound to enable audio
                    self.playNotificationSound();
                    
                    // Visual feedback - ATIVO
                    $btn.addClass('sound-active');
                    $btn.find('.dashicons').removeClass('dashicons-megaphone').addClass('dashicons-controls-volumeon');
                    $btn.attr('title', 'Som ativado!');
                    
                    // Show confirmation
                    self.showVisualNotification('🔔 Notificações sonoras ativadas!');
                    
                    console.log('[HNG Chat Admin] Sound enabled by user interaction');
                } else {
                    // Visual feedback - DESATIVADO
                    $btn.removeClass('sound-active');
                    $btn.find('.dashicons').removeClass('dashicons-controls-volumeon').addClass('dashicons-megaphone');
                    $btn.attr('title', 'Clique para ativar notificações sonoras');
                    
                    // Show confirmation
                    self.showVisualNotification('🔕 Notificações sonoras desativadas');
                    
                    console.log('[HNG Chat Admin] Sound disabled by user interaction');
                }
            });

            // Refresh button
            $('#hng-chat-refresh').on('click', function() {
                const $icon = $(this).find('.dashicons');
                $icon.addClass('spin');
                self.loadSessions(function() {
                    $icon.removeClass('spin');
                });
            });

            // Session click
            $(document).on('click', '.hng-chat-session-item', function() {
                const sessionId = $(this).data('session-id');
                self.selectSession(sessionId);
            });

            // Send message
            $('#hng-chat-send').on('click', function() {
                self.sendMessage();
            });

            $('#hng-chat-input').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // Close session
            $(document).on('click', '.hng-chat-action-close', function() {
                if (confirm('Encerrar esta conversa?')) {
                    self.closeSession();
                }
            });
            
            // File upload
            $('#hng-chat-attach').on('click', function() {
                $('#hng-chat-file-input').click();
            });
            
            $('#hng-chat-file-input').on('change', function() {
                if (this.files && this.files[0]) {
                    self.uploadFile(this.files[0]);
                    $(this).val('');
                }
            });

            // Auto-resize textarea
            $('#hng-chat-input').on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });

            // Heartbeat
            $(document).on('heartbeat-send', function(e, data) {
                data.hng_live_chat_admin = true;
            });

            $(document).on('heartbeat-tick', function(e, data) {
                if (data.hng_live_chat_admin) {
                    if (data.hng_live_chat_admin.waiting_count > 0) {
                        // Play notification sound
                        self.playNotificationSound();
                        // Reload sessions if there are waiting
                        self.loadSessions();
                    }
                }
            });
        },
        
        /**
         * Upload file
         */
        uploadFile: function(file) {
            const self = this;
            
            if (!file || !this.currentSessionId) {
                return;
            }
            
            // Validate size (5MB default)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('Arquivo muito grande. Máximo: 5MB');
                return;
            }
            
            const $sendBtn = $('#hng-chat-send');
            const originalText = $sendBtn.html();
            $sendBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');
            
            const formData = new FormData();
            formData.append('action', 'hng_live_chat_admin_upload');
            formData.append('nonce', this.config.nonce);
            formData.append('session_id', this.currentSessionId);
            formData.append('file', file);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $sendBtn.prop('disabled', false).html(originalText);
                    
                    if (response.success) {
                        self.addMessage(response.data.message);
                        self.lastMessageId = Math.max(self.lastMessageId, response.data.message_id);
                        self.scrollToBottom();
                    } else {
                        alert(response.data.message || 'Erro ao enviar arquivo');
                    }
                },
                error: function() {
                    $sendBtn.prop('disabled', false).html(originalText);
                    alert('Erro ao enviar arquivo');
                }
            });
        },

        /**
         * Load sessions
         */
        loadSessions: function(callback) {
            const self = this;
            const status = $('#hng-chat-status-filter').val();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_admin_get_sessions',
                    nonce: this.config.nonce,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        // Check for new waiting sessions
                        self.checkNewWaitingSessions(response.data.sessions);
                        
                        self.renderSessions(response.data.sessions);
                    }
                    if (typeof callback === 'function') {
                        callback();
                    }
                },
                error: function() {
                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            });
        },
        
        /**
         * Check for new waiting sessions and new messages in active sessions
         */
        checkNewWaitingSessions: function(sessions) {
            if (!sessions) return;
            
            const self = this;
            const waitingSessions = sessions.filter(s => s.status === 'waiting');
            const activeSessions = sessions.filter(s => s.status === 'active');
            const newWaitingCount = waitingSessions.length;
            
            // Initialize tracking if not set
            if (typeof this.previousWaitingCount === 'undefined') {
                this.previousWaitingCount = newWaitingCount;
                this.previousWaitingIds = waitingSessions.map(s => s.id);
                this.previousUnreadCounts = {};
                sessions.forEach(function(s) {
                    self.previousUnreadCounts[s.id] = parseInt(s.unread_count) || 0;
                });
                return;
            }
            
            // Check if there are new waiting sessions
            const currentIds = waitingSessions.map(s => s.id);
            const newSessionIds = currentIds.filter(id => !this.previousWaitingIds.includes(id));
            
            if (newSessionIds.length > 0) {
                // New customer waiting!
                this.playNotificationSound();
                this.showNotification('Novo cliente aguardando atendimento!');
            }
            
            // Check for new unread messages in ALL sessions (when no chat is open)
            if (!this.currentSessionId) {
                let hasNewMessages = false;
                
                sessions.forEach(function(s) {
                    const prevUnread = self.previousUnreadCounts[s.id] || 0;
                    const currentUnread = parseInt(s.unread_count) || 0;
                    
                    if (currentUnread > prevUnread) {
                        hasNewMessages = true;
                    }
                    self.previousUnreadCounts[s.id] = currentUnread;
                });
                
                if (hasNewMessages) {
                    this.playNotificationSound();
                    this.showNotification('Nova mensagem recebida!');
                }
            } else {
                // Update unread counts for tracking
                sessions.forEach(function(s) {
                    self.previousUnreadCounts[s.id] = parseInt(s.unread_count) || 0;
                });
            }
            
            this.previousWaitingCount = newWaitingCount;
            this.previousWaitingIds = currentIds;
        },
        
        /**
         * Show browser notification
         */
        showNotification: function(message) {
            // Try browser notification
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('HNG Chat ao Vivo', {
                    body: message,
                    icon: this.config.iconUrl || ''
                });
            } else if ('Notification' in window && Notification.permission !== 'denied') {
                Notification.requestPermission();
            }
            
            // Also show visual indicator
            this.showVisualNotification(message);
        },
        
        /**
         * Show visual notification on page
         */
        showVisualNotification: function(message) {
            let $indicator = $('.hng-chat-sound-indicator');
            
            if (!$indicator.length) {
                $indicator = $('<div class="hng-chat-sound-indicator"><span class="dashicons dashicons-megaphone"></span><span class="message"></span></div>');
                $('body').append($indicator);
            }
            
            $indicator.find('.message').text(message);
            $indicator.addClass('show');
            
            setTimeout(function() {
                $indicator.removeClass('show');
            }, 5000);
        },

        /**
         * Render sessions list
         */
        renderSessions: function(sessions) {
            const $container = $('#hng-chat-sessions-list');
            
            if (!sessions || sessions.length === 0) {
                $container.html(
                    '<div class="hng-chat-empty">' +
                    '<span class="dashicons dashicons-format-chat"></span>' +
                    '<p>' + this.config.i18n.noSessions + '</p>' +
                    '</div>'
                );
                return;
            }

            let html = '';
            const self = this;

            sessions.forEach(function(session) {
                const isActive = session.id === self.currentSessionId;
                const statusClass = session.status;
                const hasUnread = parseInt(session.unread_count) > 0;
                
                html += '<div class="hng-chat-session-item ' + statusClass + (isActive ? ' active' : '') + (hasUnread ? ' has-unread' : '') + '" data-session-id="' + session.id + '">';
                html += '<div class="hng-chat-session-header">';
                html += '<span class="hng-chat-session-name">';
                
                if (session.user_info) {
                    html += '<span class="dashicons dashicons-admin-users"></span>';
                } else {
                    html += '<span class="dashicons dashicons-groups"></span>';
                }
                
                html += self.escapeHtml(session.guest_name || self.config.i18n.guest);
                html += '</span>';
                html += '<span class="hng-chat-session-time">' + self.formatTime(session.last_activity) + '</span>';
                html += '</div>';
                
                if (session.last_message) {
                    html += '<div class="hng-chat-session-preview">' + self.escapeHtml(session.last_message) + '</div>';
                }
                
                html += '<div class="hng-chat-session-meta">';
                html += '<span class="hng-chat-session-status ' + statusClass + '">';
                html += self.getStatusLabel(session.status);
                html += '</span>';
                
                if (hasUnread) {
                    html += '<span class="hng-chat-session-unread pulse">' + session.unread_count + '</span>';
                }
                
                html += '</div>';
                html += '</div>';
            });

            $container.html(html);
            
            // Update document title if there are unread messages
            const totalUnread = sessions.reduce(function(sum, s) { return sum + (parseInt(s.unread_count) || 0); }, 0);
            if (totalUnread > 0) {
                document.title = '(' + totalUnread + ') Chat ao Vivo - HNG';
            } else {
                document.title = 'Chat ao Vivo - HNG';
            }
        },

        /**
         * Get status label
         */
        getStatusLabel: function(status) {
            const labels = {
                'waiting': this.config.i18n.waiting,
                'active': this.config.i18n.active,
                'closed': this.config.i18n.closed
            };
            return labels[status] || status;
        },

        /**
         * Select session
         */
        selectSession: function(sessionId) {
            this.currentSessionId = sessionId;
            this.lastMessageId = 0;
            this.isLoadingSession = true; // Set flag to prevent sound on initial load
            
            // Update UI
            $('.hng-chat-session-item').removeClass('active');
            $('.hng-chat-session-item[data-session-id="' + sessionId + '"]').addClass('active');
            
            // Show conversation
            $('#hng-chat-placeholder').hide();
            $('#hng-chat-conversation').css('display', 'grid');
            $('#hng-chat-info').show();
            $('#hng-chat-messages').empty();
            
            // Load messages (will reset isLoadingSession when done)
            this.loadMessages(true);
            
            // Start polling
            this.startMessagesPolling();
        },

        /**
         * Load messages
         */
        loadMessages: function(isInitialLoad) {
            const self = this;

            if (!this.currentSessionId) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_admin_get_messages',
                    nonce: this.config.nonce,
                    session_id: this.currentSessionId,
                    last_id: this.lastMessageId
                },
                success: function(response) {
                    if (response.success) {
                        self.processMessages(response.data.messages);
                        self.updateSessionInfo(response.data.session);
                        
                        if (response.data.customer_typing) {
                            self.showTypingIndicator(response.data.session.guest_name);
                        } else {
                            self.hideTypingIndicator();
                        }
                    }
                    
                    // Reset loading flag after initial load is complete
                    if (isInitialLoad) {
                        self.isLoadingSession = false;
                    }
                }
            });
        },

        /**
         * Process messages
         */
        processMessages: function(messages) {
            const self = this;
            const $container = $('#hng-chat-messages');
            
            if (!messages || !messages.length) {
                return;
            }

            let hasNewCustomerMessage = false;
            
            messages.forEach(function(msg) {
                if (msg.id > self.lastMessageId) {
                    self.addMessage(msg);
                    self.lastMessageId = msg.id;
                    
                    // Track if there are new customer messages
                    if (msg.sender_type === 'customer' && !self.isLoadingSession) {
                        hasNewCustomerMessage = true;
                    }
                }
            });
            
            // Play sound only once for all new customer messages
            if (hasNewCustomerMessage && self.soundEnabled) {
                self.playNotificationSound();
            }
        },

        /**
         * Add message to chat
         */
        addMessage: function(msg) {
            const $container = $('#hng-chat-messages');
            
            let html = '<div class="hng-chat-admin-message ' + msg.sender_type + '">';
            
            if (msg.sender_type === 'customer' && msg.sender_name) {
                html += '<div class="hng-chat-admin-message-header">';
                html += '<span class="hng-chat-admin-sender-name">' + this.escapeHtml(msg.sender_name) + '</span>';
                html += '</div>';
            }
            
            if (msg.message_type === 'image' && msg.attachment) {
                html += '<div class="hng-chat-admin-attachment">';
                html += '<img src="' + msg.attachment.url + '" alt="' + this.escapeHtml(msg.attachment.name) + '" onclick="window.open(this.src)" />';
                html += '</div>';
            } else if (msg.message_type === 'file' && msg.attachment) {
                html += '<div class="hng-chat-admin-attachment">';
                html += '<a href="' + msg.attachment.url + '" target="_blank" class="hng-chat-admin-file-preview">';
                html += '<span class="dashicons dashicons-media-default"></span>';
                html += '<span>' + this.escapeHtml(msg.attachment.name) + '</span>';
                html += '</a>';
                html += '</div>';
            } else {
                html += '<div class="hng-chat-admin-message-content">' + this.escapeHtml(msg.message).replace(/\n/g, '<br>') + '</div>';
            }
            
            if (msg.time && msg.sender_type !== 'system') {
                html += '<div class="hng-chat-admin-message-time">' + msg.time + '</div>';
            }
            
            html += '</div>';
            
            $container.append(html);
            this.scrollToBottom();
        },

        /**
         * Update session info sidebar
         */
        updateSessionInfo: function(session) {
            const $container = $('#hng-chat-info-content');
            
            // Update header
            $('.hng-chat-user-name').text(session.guest_name || this.config.i18n.guest);
            $('.hng-chat-user-email').text(session.guest_email || '');
            
            // Load full session info
            this.loadSessionDetails();
        },

        /**
         * Load session details for sidebar
         */
        loadSessionDetails: function() {
            const self = this;
            const $container = $('#hng-chat-info-content');
            
            // Get the session from the list
            const $sessionItem = $('.hng-chat-session-item[data-session-id="' + this.currentSessionId + '"]');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_admin_get_sessions',
                    nonce: this.config.nonce,
                    status: 'all'
                },
                success: function(response) {
                    if (response.success) {
                        const session = response.data.sessions.find(s => s.id === self.currentSessionId);
                        
                        if (session) {
                            self.renderSessionInfo(session);
                        }
                    }
                }
            });
        },

        /**
         * Render session info sidebar
         */
        renderSessionInfo: function(session) {
            const $container = $('#hng-chat-info-content');
            let html = '';
            
            // User info
            html += '<div class="hng-chat-info-section">';
            html += '<h4>' + this.config.i18n.userInfo + '</h4>';
            
            if (session.user_info) {
                html += '<div class="hng-chat-user-avatar">';
                html += '<img src="' + session.user_info.avatar + '" alt="' + this.escapeHtml(session.user_info.name) + '" />';
                html += '<div class="hng-chat-user-avatar-info">';
                html += '<span class="hng-chat-user-avatar-name">' + this.escapeHtml(session.user_info.name) + '</span>';
                html += '<span class="hng-chat-user-avatar-email">' + this.escapeHtml(session.user_info.email) + '</span>';
                html += '</div>';
                html += '</div>';
                
                html += '<div class="hng-chat-stats">';
                html += '<div class="hng-chat-stat">';
                html += '<span class="hng-chat-stat-value">' + (session.user_info.orders_count || 0) + '</span>';
                html += '<span class="hng-chat-stat-label">Pedidos</span>';
                html += '</div>';
                html += '<div class="hng-chat-stat">';
                html += '<span class="hng-chat-stat-value">' + this.formatDate(session.user_info.registered) + '</span>';
                html += '<span class="hng-chat-stat-label">Cadastro</span>';
                html += '</div>';
                html += '</div>';
            } else {
                html += '<div class="hng-chat-info-item">';
                html += '<span class="hng-chat-info-label">Nome</span>';
                html += '<span class="hng-chat-info-value">' + this.escapeHtml(session.guest_name || 'Visitante') + '</span>';
                html += '</div>';
                
                if (session.guest_email) {
                    html += '<div class="hng-chat-info-item">';
                    html += '<span class="hng-chat-info-label">Email</span>';
                    html += '<span class="hng-chat-info-value"><a href="mailto:' + session.guest_email + '">' + this.escapeHtml(session.guest_email) + '</a></span>';
                    html += '</div>';
                }
            }
            
            html += '</div>';
            
            // Session info
            html += '<div class="hng-chat-info-section">';
            html += '<h4>' + this.config.i18n.sessionInfo + '</h4>';
            
            if (session.subject) {
                html += '<div class="hng-chat-info-item">';
                html += '<span class="hng-chat-info-label">Assunto</span>';
                html += '<span class="hng-chat-info-value">' + this.escapeHtml(session.subject) + '</span>';
                html += '</div>';
            }
            
            if (session.page_url) {
                html += '<div class="hng-chat-info-item">';
                html += '<span class="hng-chat-info-label">Página</span>';
                html += '<span class="hng-chat-info-value"><a href="' + session.page_url + '" target="_blank">' + this.truncate(session.page_url, 30) + '</a></span>';
                html += '</div>';
            }
            
            html += '<div class="hng-chat-info-item">';
            html += '<span class="hng-chat-info-label">IP</span>';
            html += '<span class="hng-chat-info-value">' + (session.user_ip || 'N/A') + '</span>';
            html += '</div>';
            
            html += '<div class="hng-chat-info-item">';
            html += '<span class="hng-chat-info-label">Iniciado em</span>';
            html += '<span class="hng-chat-info-value">' + this.formatDateTime(session.started_at) + '</span>';
            html += '</div>';
            
            if (session.operator_name) {
                html += '<div class="hng-chat-info-item">';
                html += '<span class="hng-chat-info-label">Atendente</span>';
                html += '<span class="hng-chat-info-value">' + this.escapeHtml(session.operator_name) + '</span>';
                html += '</div>';
            }
            
            html += '</div>';
            
            $container.html(html);
        },

        /**
         * Send message
         */
        sendMessage: function() {
            const $input = $('#hng-chat-input');
            const message = $input.val().trim();

            if (!message || !this.currentSessionId) {
                return;
            }

            const self = this;

            // Clear input
            $input.val('').css('height', 'auto');

            // Add message immediately
            this.addMessage({
                sender_type: 'operator',
                sender_name: this.config.currentUserName,
                message: message,
                time: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}),
                pending: true
            });

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_admin_send',
                    nonce: this.config.nonce,
                    session_id: this.currentSessionId,
                    message: message
                },
                success: function(response) {
                    if (response.success) {
                        // Update last message ID
                        self.lastMessageId = Math.max(self.lastMessageId, response.data.message_id);
                    } else {
                        alert(response.data.message || 'Erro ao enviar mensagem');
                    }
                },
                error: function() {
                    alert('Erro ao enviar mensagem');
                }
            });
        },

        /**
         * Close session
         */
        closeSession: function() {
            const self = this;

            if (!this.currentSessionId) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_live_chat_admin_close',
                    nonce: this.config.nonce,
                    session_id: this.currentSessionId
                },
                success: function(response) {
                    if (response.success) {
                        self.loadSessions();
                        self.addMessage({
                            sender_type: 'system',
                            message: 'Chat encerrado'
                        });
                    }
                }
            });
        },

        /**
         * Start sessions polling
         */
        startSessionsPolling: function() {
            const self = this;
            
            // Poll every 5 seconds for faster response
            setInterval(function() {
                self.loadSessions();
            }, 5000);
        },

        /**
         * Start messages polling
         */
        startMessagesPolling: function() {
            const self = this;
            
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
            
            this.pollInterval = setInterval(function() {
                if (self.currentSessionId) {
                    self.loadMessages();
                }
            }, 3000);
        },

        /**
         * Show typing indicator
         */
        showTypingIndicator: function(name) {
            const $typing = $('#hng-chat-typing');
            $typing.find('.hng-typing-text').text(name + ' está digitando...');
            $typing.show();
        },

        /**
         * Hide typing indicator
         */
        hideTypingIndicator: function() {
            $('#hng-chat-typing').hide();
        },

        /**
         * Play notification sound
         */
        playNotificationSound: function() {
            if (!this.soundEnabled) {
                return;
            }
            
            const self = this;
            
            try {
                // Try HTML5 Audio first if ready
                if (this.soundReady && this.notificationSound) {
                    this.notificationSound.currentTime = 0;
                    this.notificationSound.play().catch(function(e) {
                        // If play fails, use Web Audio API
                        self.playBeepSound();
                    });
                } else {
                    // Use Web Audio API fallback
                    this.playBeepSound();
                }
            } catch (e) {
                // Silent fail
            }
        },
        
        /**
         * Play beep sound using Web Audio API
         */
        playBeepSound: function() {
            try {
                if (!this.audioContext) {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
                
                // Resume audio context if suspended (browser autoplay policy)
                if (this.audioContext.state === 'suspended') {
                    this.audioContext.resume();
                }
                
                const oscillator = this.audioContext.createOscillator();
                const gainNode = this.audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(this.audioContext.destination);
                
                // Create a pleasant notification sound
                oscillator.frequency.value = 880; // A5 note
                oscillator.type = 'sine';
                gainNode.gain.setValueAtTime(0.4, this.audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + 0.3);
                
                oscillator.start(this.audioContext.currentTime);
                oscillator.stop(this.audioContext.currentTime + 0.3);
                
                // Play a second tone for a "ding-dong" effect
                const oscillator2 = this.audioContext.createOscillator();
                const gainNode2 = this.audioContext.createGain();
                
                oscillator2.connect(gainNode2);
                gainNode2.connect(this.audioContext.destination);
                
                oscillator2.frequency.value = 660; // E5 note
                oscillator2.type = 'sine';
                gainNode2.gain.setValueAtTime(0.3, this.audioContext.currentTime + 0.15);
                gainNode2.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + 0.5);
                
                oscillator2.start(this.audioContext.currentTime + 0.15);
                oscillator2.stop(this.audioContext.currentTime + 0.5);
            } catch (e) {
                console.log('Web Audio error:', e);
                // Ignore audio errors
            }
        },

        /**
         * Scroll to bottom
         */
        scrollToBottom: function() {
            const $messages = $('#hng-chat-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        },

        /**
         * Format time (relative)
         */
        formatTime: function(datetime) {
            if (!datetime) return '';
            
            const date = new Date(datetime);
            const now = new Date();
            const diff = (now - date) / 1000; // seconds
            
            if (diff < 60) {
                return 'agora';
            } else if (diff < 3600) {
                const mins = Math.floor(diff / 60);
                return mins + ' min';
            } else if (diff < 86400) {
                const hours = Math.floor(diff / 3600);
                return hours + 'h';
            } else {
                return date.toLocaleDateString('pt-BR');
            }
        },

        /**
         * Format date
         */
        formatDate: function(datetime) {
            if (!datetime) return 'N/A';
            const date = new Date(datetime);
            return date.toLocaleDateString('pt-BR');
        },

        /**
         * Format datetime
         */
        formatDateTime: function(datetime) {
            if (!datetime) return 'N/A';
            const date = new Date(datetime);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        },

        /**
         * Truncate text
         */
        truncate: function(text, length) {
            if (!text) return '';
            if (text.length <= length) return text;
            return text.substring(0, length) + '...';
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('.hng-live-chat-admin').length) {
            HNGLiveChatAdmin.init();
            
            // Ensure conversation always uses display:grid when visible
            setInterval(function() {
                var $conv = $('#hng-chat-conversation');
                if ($conv.length && $conv.css('display') !== 'none' && $conv.css('display') !== 'grid') {
                    $conv.css('display', 'grid');
                }
            }, 500);
        }
    });

    // Expose globally
    window.HNGLiveChatAdmin = HNGLiveChatAdmin;

    // Add CSS for spinning icon
    $('<style>')
        .text('.dashicons.spin { animation: spin 1s linear infinite; } @keyframes spin { 100% { transform: rotate(360deg); } }')
        .appendTo('head');

})(jQuery);
