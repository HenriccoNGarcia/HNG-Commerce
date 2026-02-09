/**
 * HNG Commerce - Global Admin Notifications
 * 
 * Sistema de notificações globais para:
 * - Chats aguardando atendimento
 * - Novos pedidos
 * 
 * @package HNG_Commerce
 * @version 1.0.0
 */

(function($) {
    'use strict';

    const HNGAdminNotifications = {
        /**
         * Contadores
         */
        counts: {
            waitingChats: 0,
            newOrders: 0
        },

        /**
         * Sons de notificação
         */
        soundEnabled: true,
        notificationSound: null,
        audioContext: null,

        /**
         * Initialize
         */
        init: function() {
            console.log('[HNG Notifications] Initializing...');
            this.initSound();
            this.bindEvents();
            this.startHeartbeat();
            this.checkInitialCounts();
            console.log('[HNG Notifications] Initialized successfully');
        },

        /**
         * Inicializar som de notificação
         */
        initSound: function() {
            const self = this;
            
            // Tentar carregar o som se existir URL
            if (window.hngAdminNotifications && window.hngAdminNotifications.soundUrl) {
                this.notificationSound = new Audio(window.hngAdminNotifications.soundUrl);
                this.notificationSound.volume = 0.5;
                this.notificationSound.load();
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Detectar interação do usuário para permitir sons
            $(document).one('click keypress', function() {
                self.soundEnabled = true;
                console.log('[HNG Notifications] Sound enabled');
            });
        },

        /**
         * Iniciar WordPress Heartbeat para polling
         */
        startHeartbeat: function() {
            const self = this;

            console.log('[HNG Notifications] Starting heartbeat...');

            // Configurar Heartbeat para 30 segundos
            $(document).on('heartbeat-send', function(e, data) {
                console.log('[HNG Notifications] Heartbeat send');
                data.hng_check_notifications = true;
            });

            // Receber dados do Heartbeat
            $(document).on('heartbeat-tick', function(e, data) {
                console.log('[HNG Notifications] Heartbeat tick:', data);
                if (data.hng_notifications) {
                    self.updateNotifications(data.hng_notifications);
                }
            });

            // Forçar início do Heartbeat
            if (typeof wp !== 'undefined' && wp.heartbeat) {
                console.log('[HNG Notifications] WP Heartbeat available, setting interval');
                wp.heartbeat.interval(30); // 30 segundos
                
                // Conectar heartbeat se não estiver conectado
                if (!wp.heartbeat.hasConnectionError()) {
                    wp.heartbeat.connectNow();
                }
            } else {
                console.warn('[HNG Notifications] WP Heartbeat NOT available!');
            }
        },

        /**
         * Verificar contagens iniciais
         */
        checkInitialCounts: function() {
            const self = this;

            console.log('[HNG Notifications] Checking initial counts...');
            
            // Usar ajaxurl do objeto localizado ou variável global
            const ajaxUrl = (window.hngAdminNotifications && window.hngAdminNotifications.ajaxurl) 
                            || window.ajaxurl 
                            || '/wp-admin/admin-ajax.php';
            
            console.log('[HNG Notifications] AJAX URL:', ajaxUrl);
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hng_get_notification_counts',
                    nonce: window.hngAdminNotifications?.nonce || ''
                },
                success: function(response) {
                    console.log('[HNG Notifications] Initial response:', response);
                    if (response.success && response.data) {
                        self.updateNotifications(response.data, false); // false = não tocar som na primeira carga
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[HNG Notifications] AJAX error:', error);
                }
            });
        },

        /**
         * Atualizar notificações
         */
        updateNotifications: function(data, playSound = true) {
            console.log('[HNG Notifications] Updating notifications:', data);
            
            const oldWaitingChats = this.counts.waitingChats;
            const oldNewOrders = this.counts.newOrders;

            this.counts.waitingChats = parseInt(data.waiting_chats) || 0;
            this.counts.newOrders = parseInt(data.new_orders) || 0;

            console.log('[HNG Notifications] New counts - Chats:', this.counts.waitingChats, 'Orders:', this.counts.newOrders);

            // Detectar novos itens
            const hasNewChats = this.counts.waitingChats > oldWaitingChats;
            const hasNewOrders = this.counts.newOrders > oldNewOrders;

            // Atualizar badges no menu
            this.updateMenuBadges();

            // Tocar som e mostrar notificação se houver novos itens
            if (playSound) {
                if (hasNewChats) {
                    this.playSound();
                    this.showNotification('💬 Novo chat aguardando atendimento!', 'hng-live-chat');
                }
                if (hasNewOrders) {
                    this.playSound();
                    this.showNotification('🛒 Novo pedido recebido!', 'hng-orders');
                }
            }
        },

        /**
         * Atualizar badges no menu
         */
        updateMenuBadges: function() {
            const totalCount = this.counts.waitingChats + this.counts.newOrders;

            console.log('[HNG Notifications] Updating badges - Total:', totalCount);

            // NÃO adicionar badge no menu principal - apenas nos submenus

            // Badge no submenu de Chat (apenas no menu lateral)
            this.updateMenuBadge('#adminmenu a[href*="hng-live-chat"]', this.counts.waitingChats);

            // Badge no submenu de Pedidos (apenas no menu lateral)
            this.updateMenuBadge('#adminmenu a[href*="hng-orders"]', this.counts.newOrders);
        },

        /**
         * Atualizar badge individual
         */
        updateMenuBadge: function(selector, count) {
            const $menuItem = $(selector);
            
            console.log('[HNG Notifications] Updating badge for', selector, '- Count:', count, 'Found:', $menuItem.length);
            
            if (!$menuItem.length) {
                console.warn('[HNG Notifications] Menu item not found:', selector);
                return;
            }

            // Se encontrou múltiplos, usar apenas o primeiro (menu lateral)
            const $target = $menuItem.first();

            // Remover badge existente
            $target.find('.hng-notification-badge').remove();

            // Adicionar novo badge se count > 0
            if (count > 0) {
                const badge = $('<span class="hng-notification-badge"></span>')
                    .text(count > 99 ? '99+' : count);
                
                $target.append(badge);
            }
        },

        /**
         * Mostrar notificação visual
         */
        showNotification: function(message, page) {
            // Criar notificação temporária no topo
            const $notification = $('<div class="hng-global-notification"></div>')
                .html(`<span class="message">${message}</span>`)
                .appendTo('body');

            // Adicionar link se houver página
            if (page) {
                const $link = $('<a></a>')
                    .attr('href', 'admin.php?page=' + page)
                    .text('Ver agora')
                    .appendTo($notification);
            }

            // Animar entrada
            setTimeout(function() {
                $notification.addClass('show');
            }, 100);

            // Remover após 5 segundos
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 5000);

            // Fechar ao clicar
            $notification.on('click', function(e) {
                if (!$(e.target).is('a')) {
                    $(this).removeClass('show');
                    setTimeout(function() {
                        $notification.remove();
                    }, 300);
                }
            });
        },

        /**
         * Tocar som de notificação
         */
        playSound: function() {
            if (!this.soundEnabled) {
                return;
            }

            try {
                // Tentar tocar o som do arquivo
                if (this.notificationSound) {
                    this.notificationSound.currentTime = 0;
                    this.notificationSound.play().catch(() => {
                        this.playBeep();
                    });
                } else {
                    // Fallback para beep gerado
                    this.playBeep();
                }
            } catch (e) {
                console.error('[HNG Notifications] Error playing sound:', e);
            }
        },

        /**
         * Tocar beep com Web Audio API
         */
        playBeep: function() {
            try {
                if (!this.audioContext) {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }

                const oscillator = this.audioContext.createOscillator();
                const gainNode = this.audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(this.audioContext.destination);

                oscillator.frequency.value = 880; // A5 note
                oscillator.type = 'sine';
                gainNode.gain.setValueAtTime(0.3, this.audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + 0.3);

                oscillator.start(this.audioContext.currentTime);
                oscillator.stop(this.audioContext.currentTime + 0.3);
            } catch (e) {
                // Silent fail
            }
        }
    };

    // Inicializar quando o DOM estiver pronto
    $(document).ready(function() {
        HNGAdminNotifications.init();
    });

})(jQuery);
