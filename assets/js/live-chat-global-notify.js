(function($) {
    'use strict';

    const GlobalChatNotify = {
        config: null,
        lastSignature: '',
        lastUnread: 0,
        lastWaiting: 0,
        pollTimer: null,
        eventSource: null,
        websocket: null,
        sound: null,
        soundReady: false,
        soundUnlocked: false,

        init() {
            this.config = window.hngLiveChatGlobalNotify || {};
            if (!this.config.ajaxUrl || !this.config.nonce) {
                return;
            }

            this.initSound();
            this.ensureUi();
            this.start();
        },

        initSound() {
            if (!this.config.enabled || !this.config.soundUrl) {
                return;
            }

            try {
                this.sound = new Audio(this.config.soundUrl);
                this.sound.volume = 0.5;
                this.sound.preload = 'auto';
                this.sound.addEventListener('canplaythrough', () => {
                    this.soundReady = true;
                });
                this.sound.addEventListener('canplay', () => {
                    this.soundReady = true;
                });
                this.sound.addEventListener('loadeddata', () => {
                    this.soundReady = true;
                });
                this.sound.load();
                this.bindSoundUnlock();
            } catch (e) {
                this.sound = null;
            }
        },

        bindSoundUnlock() {
            if (!this.sound || this.soundUnlocked) {
                return;
            }

            const unlock = () => {
                if (!this.sound || this.soundUnlocked) {
                    return;
                }

                try {
                    const playPromise = this.sound.play();
                    if (playPromise && typeof playPromise.then === 'function') {
                        playPromise
                            .then(() => {
                                this.sound.pause();
                                this.sound.currentTime = 0;
                                this.soundUnlocked = true;
                            })
                            .catch(() => {});
                    }
                } catch (e) {}

                document.removeEventListener('click', unlock, true);
                document.removeEventListener('touchstart', unlock, true);
                document.removeEventListener('keydown', unlock, true);
            };

            document.addEventListener('click', unlock, true);
            document.addEventListener('touchstart', unlock, true);
            document.addEventListener('keydown', unlock, true);
        },

        ensureUi() {
            if ($('#hng-chat-global-notify').length) return;
            const html = [
                '<div id="hng-chat-global-notify" style="display:none;position:fixed;right:20px;bottom:20px;z-index:99999;background:#1d2327;color:#fff;padding:12px 14px;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.25);font-size:13px;max-width:320px;">',
                '<div class="hng-chat-global-notify-text" style="margin-bottom:8px;"></div>',
                '<a class="button button-primary" href="admin.php?page=hng-live-chat" style="text-decoration:none;">',
                this.escapeHtml(this.config.i18n?.openChat || 'Abrir Chat'),
                '</a>',
                '</div>'
            ].join('');
            $('body').append(html);
        },

        start() {
            const method = (this.config.notificationMethod || 'polling').toLowerCase();

            if (method === 'websocket' && this.config.websocketUrl) {
                this.startWebSocket();
                return;
            }

            if (method === 'sse') {
                this.startSse();
                return;
            }

            if (method === 'long_polling') {
                this.startLongPolling();
                return;
            }

            this.startPolling();
        },

        startPolling() {
            const interval = Math.max(1000, parseInt(this.config.pollingInterval || 3000, 10));
            this.fetchSummary(false);
            this.pollTimer = setInterval(() => this.fetchSummary(false), interval);
        },

        startLongPolling() {
            const loop = () => {
                this.fetchSummary(true).always(() => {
                    setTimeout(loop, 500);
                });
            };
            loop();
        },

        startSse() {
            if (!window.EventSource) {
                this.startPolling();
                return;
            }

            const connect = () => {
                const url = this.config.ajaxUrl +
                    '?action=hng_live_chat_admin_notifications_sse&nonce=' + encodeURIComponent(this.config.nonce) +
                    '&last_signature=' + encodeURIComponent(this.lastSignature || '');

                this.eventSource = new EventSource(url);

                this.eventSource.addEventListener('summary', (event) => {
                    try {
                        const data = JSON.parse(event.data || '{}');
                        this.handleSummary(data);
                    } catch (e) {}
                });

                this.eventSource.addEventListener('timeout', () => {
                    if (this.eventSource) {
                        this.eventSource.close();
                        this.eventSource = null;
                    }
                    setTimeout(connect, 400);
                });

                this.eventSource.onerror = () => {
                    if (this.eventSource) {
                        this.eventSource.close();
                        this.eventSource = null;
                    }
                    setTimeout(connect, 2000);
                };
            };

            connect();
        },

        startWebSocket() {
            try {
                this.websocket = new WebSocket(this.config.websocketUrl);

                this.websocket.onopen = () => {
                    this.fetchSummary(false);
                };

                this.websocket.onmessage = () => {
                    this.fetchSummary(false);
                };

                this.websocket.onerror = () => {
                    this.fallbackToPolling();
                };

                this.websocket.onclose = () => {
                    this.fallbackToPolling();
                };
            } catch (e) {
                this.fallbackToPolling();
            }
        },

        fallbackToPolling() {
            if (this.websocket) {
                try { this.websocket.close(); } catch (e) {}
                this.websocket = null;
            }
            if (!this.pollTimer) {
                this.startPolling();
            }
        },

        fetchSummary(longPoll) {
            return $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hng_live_chat_admin_notifications',
                    nonce: this.config.nonce,
                    long_poll: longPoll ? 1 : 0,
                    last_signature: this.lastSignature || ''
                }
            }).done((response) => {
                if (response && response.success && response.data) {
                    this.handleSummary(response.data);
                }
            });
        },

        handleSummary(data) {
            const unread = parseInt(data.unread_count || 0, 10);
            const waiting = parseInt(data.waiting_count || 0, 10);
            const signature = data.signature || '';

            const hadInitialData = this.lastSignature !== '';
            const hasNewUnread = hadInitialData && unread > this.lastUnread;
            const hasNewWaiting = hadInitialData && waiting > this.lastWaiting;

            this.lastUnread = unread;
            this.lastWaiting = waiting;
            this.lastSignature = signature;

            if (hasNewUnread || hasNewWaiting) {
                const text = hasNewWaiting
                    ? (this.config.i18n?.newWaiting || 'Novo cliente aguardando atendimento')
                    : (this.config.i18n?.newMessage || 'Nova mensagem no chat');
                this.showToast(text);
                this.playSound();
                this.showBrowserNotification(text);
            }
        },

        showToast(message) {
            const $box = $('#hng-chat-global-notify');
            $box.find('.hng-chat-global-notify-text').text(message);
            $box.stop(true, true).fadeIn(180);
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                $box.fadeOut(250);
            }, 6500);
        },

        playSound() {
            if (!this.config.enabled) return;
            if (this.sound) {
                try {
                    this.sound.currentTime = 0;
                    const playPromise = this.sound.play();
                    if (playPromise && typeof playPromise.catch === 'function') {
                        playPromise.catch(() => {
                            this.bindSoundUnlock();
                        });
                    }
                } catch (e) {}
            }
        },

        showBrowserNotification(message) {
            if (!('Notification' in window)) return;

            if (Notification.permission === 'granted') {
                new Notification('HNG Chat ao Vivo', {
                    body: message,
                    icon: this.config.iconUrl || ''
                });
                return;
            }

            if (Notification.permission !== 'denied') {
                Notification.requestPermission();
            }
        },

        escapeHtml(text) {
            return String(text || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    $(document).ready(function() {
        GlobalChatNotify.init();
    });
})(jQuery);
