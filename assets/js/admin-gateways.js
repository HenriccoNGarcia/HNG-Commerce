/**

 * HNG Admin Gateway Management Scripts

 */

(function($) {

    'use strict';



    // Global flag to prevent auto-check from running multiple times

    let statusCheckCompleted = false;



    // Notification helper

    window.hngShowNotification = function(message, type, duration) {

        type = type || 'info';

        duration = duration || 3000;

        

        const icons = {

            success: '<span class="dashicons dashicons-yes-alt"></span>',

            error: '<span class="dashicons dashicons-no-alt"></span>',

            warning: '<span class="dashicons dashicons-warning"></span>',

            info: '<span class="dashicons dashicons-info"></span>'

        };

        

        const $notification = $('<div class="hng-notification ' + type + '">' +

            '<div class="hng-notification-icon">' + (icons[type] || icons.info) + '</div>' +

            '<div class="hng-notification-content">' + message + '</div>' +

            '<div class="hng-notification-close">✕</div>' +

            '</div>');

        

        $('body').append($notification);

        

        const closeNotification = function() {

            $notification.addClass('removing');

            setTimeout(function() {

                $notification.remove();

            }, 300);

        };

        

        $notification.find('.hng-notification-close').on('click', closeNotification);

        

        if (duration > 0) {

            setTimeout(closeNotification, duration);

        }

        

        return $notification;

    };



    $(document).ready(function() {

        const ajaxUrl = hngGatewaysPage.ajaxUrl;

        const nonce = hngGatewaysPage.nonce;

        

        // Toggle do card de taxas HNG Commerce

        $('.fees-card-header').on('click', function(e) {
            // Ignorar cliques no botão de refresh
            if ($(e.target).closest('.refresh-fees-btn').length) {
                return;
            }

            const $content = $(this).siblings('.fees-card-content');

            const $icon = $(this).find('.fees-card-toggle .dashicons');

            

            $content.slideToggle(300);

            

        if ($icon.hasClass('dashicons-arrow-down-alt2')) {

                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');

            } else {

                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');

            }

        });

        

        // Debug: Check if nonce is available

        console.log('HNG Gateways Page loaded');

        console.log('Ajax URL:', ajaxUrl);

        console.log('Nonce:', nonce ? 'Present (' + nonce.substring(0, 10) + '...)' : 'MISSING!');



        // Category filter functionality

        $(document).on('click', '.filter-btn', function() {

            const filter = $(this).data('filter');

            

            // Update active button

            $('.filter-btn').removeClass('active');

            $(this).addClass('active');

            

            // Filter gateways

            if (filter === 'all') {

                $('.hng-gateway-item').show();

            } else {

                $('.hng-gateway-item').hide();

                $('.hng-gateway-item[data-category="' + filter + '"]').show();

            }

        });



        // Toggle configuration panel visibility

        $(document).on('click', '.hng-toggle-config', function(e) {

            e.preventDefault();

            const gateway = $(this).data('gateway');

            const wrapper = $(".hng-gateway-config-wrapper[data-gateway='" + gateway + "']");

            

            wrapper.slideToggle(300, function() {

                if (wrapper.hasClass('show')) {

                    wrapper.removeClass('show');

                    $(this).siblings('.hng-toggle-config').html('<span class="dashicons dashicons-admin-settings"></span> Configurar');

                } else {

                    wrapper.addClass('show');

                    $(this).siblings('.hng-toggle-config').html('<span class="dashicons dashicons-arrow-up-alt2"></span> Ocultar Configuração');

                }

            });

        });



        // Save gateway config

        $(document).on('click', '.gateway-config-form-inner .save-gateway-config', function() {

            const $form = $(this).closest('.gateway-config-form-inner');

            const gateway = $form.data('gateway');

            const $btn = $(this);

            const formData = new FormData($form[0]);

            

            // Encontra o nonce correto do formulário

            const $nonceField = $form.find('input[name^="_wpnonce_"]');

            if ($nonceField.length === 0) {

                window.hngShowNotification('Erro: Nonce não encontrado no formulário', 'error');

                return;

            }

            

            // Adiciona action e gateway

            formData.append('action', 'hng_save_gateway_config');

            formData.append('gateway', gateway);

            

            $btn.prop('disabled', true);

            

            $.ajax({

                url: ajaxUrl,

                type: 'POST',

                data: formData,

                processData: false,

                contentType: false,

                success: function(resp) {

                    const msg = resp && resp.data && resp.data.message ? resp.data.message : 'Salvo com sucesso!';

                    window.hngShowNotification(msg, 'success');

                },

                error: function(jqXHR) {

                    let msg = 'Erro desconhecido';

                    if (jqXHR.status === 400) {

                        msg = 'Erro: Validação falhou (400)';

                    } else if (jqXHR.responseJSON && jqXHR.responseJSON.data) {

                        msg = jqXHR.responseJSON.data.message || msg;

                    }

                    window.hngShowNotification(msg, 'error');

                    console.error('Save error:', jqXHR.status, jqXHR.responseJSON);

                },

                complete: function() {

                    $btn.prop('disabled', false);

                }

            });

        });



        // Test gateway connection (inline button inside form)

        $(document).on('click', '.test-gateway-inline', function() {

            const $form = $(this).closest('.gateway-config-form-inner');

            const gateway = $form.data('gateway');

            const $btn = $(this);

            

            $btn.prop('disabled', true);

            

            // Usar a mesma ação que o auto-check, mas com full_test=1

            $.ajax({

                url: ajaxUrl,

                type: 'POST',

                data: {

                    action: 'hng_check_gateway_status',

                    gateway: gateway,

                    full_test: 1  // Indicador para teste completo

                },

                headers: {

                    'X-Requested-With': 'XMLHttpRequest',

                    'X-WP-Nonce': nonce

                },

                dataType: 'json'

            })

                .done(function(resp) {

                    console.log('Inline test response for ' + gateway + ':', resp);

                    

                    if (resp && resp.success && resp.data) {

                        const msg = resp.data.message || 'Teste concluído';

                        const notificationType = resp.data.status === 'error' ? 'error' : (resp.data.status === 'warning' ? 'warning' : 'success');

                        window.hngShowNotification(msg, notificationType);

                    } else {

                        window.hngShowNotification('Erro: Credenciais não configuradas', 'error');

                    }

                })

                .fail(function(jqXHR) {

                    console.error('Inline test error:', jqXHR);

                    console.log('Status:', jqXHR.status);

                    console.log('Response:', jqXHR.responseJSON);

                    

                    let message = 'Erro ao testar gateway';

                    if (jqXHR.status === 403) {

                        message = 'Acesso negado. Verifique suas permissões de administrador.';

                    } else if (jqXHR.status === 429) {

                        message = 'Muitas tentativas. Aguarde 30 segundos e tente novamente.';

                    } else if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {

                        message = jqXHR.responseJSON.data.message;

                    }

                    

                    window.hngShowNotification(message, 'error', 5000);

                })

                .always(function() {

                    $btn.prop('disabled', false);

                });

        });



        // Test gateway connection (card button)

        $(document).on('click', '.gateway-test-btn', function() {

            const $btn = $(this);

            const gateway = $btn.data('gateway');

            const $item = $btn.closest('.hng-gateway-item');

            const $statusElement = $item.find('.gateway-api-status');

            

            // Show loading state

            $btn.prop('disabled', true);

            $statusElement.find('.status-dot').removeClass('status-unknown status-green status-yellow status-red').addClass('status-unknown');

            $statusElement.find('.status-text').text('Testando...');

            

            // Usar a mesma ação que o auto-check, mas com full_test=1

            $.ajax({

                url: ajaxUrl,

                type: 'POST',

                data: {

                    action: 'hng_check_gateway_status',

                    gateway: gateway,

                    full_test: 1  // Indicador para teste completo

                },

                headers: {

                    'X-Requested-With': 'XMLHttpRequest',

                    'X-WP-Nonce': nonce

                },

                dataType: 'json'

            })

                .done(function(resp) {

                    console.log('Test response for ' + gateway + ':', resp);

                    

                    // Simular diferentes status baseado na resposta

                    let statusClass = 'status-green';

                    let statusText = 'Funcional';

                    let notificationType = 'success';

                    let message = 'Gateway ' + gateway + ' está funcionando corretamente!';

                    

                    if (resp && resp.data && resp.data.message) {

                        message = resp.data.message;

                        

                        // Determinar status baseado na resposta

                        if (resp.data.status === 'error') {

                            statusClass = 'status-red';

                            statusText = 'Indisponível';

                            notificationType = 'error';

                        } else if (resp.data.status === 'warning') {

                            statusClass = 'status-yellow';

                            statusText = 'Instabilidade';

                            notificationType = 'warning';

                        } else if (resp.data.status === 'success') {

                            statusClass = 'status-green';

                            statusText = 'Funcional';

                            notificationType = 'success';

                        }

                    } else if (!resp.success) {

                        // Se não foi sucesso e não tem status field

                        statusClass = 'status-red';

                        statusText = 'Indisponível';

                        notificationType = 'error';

                        message = 'Erro: Credenciais não configuradas ou serviço indisponível';

                    }

                    

                    // Atualizar status visual

                    $statusElement.find('.status-dot').removeClass('status-unknown status-green status-yellow status-red').addClass(statusClass);

                    $statusElement.find('.status-text').text(statusText);

                    

                    // Mostrar notificação

                    window.hngShowNotification(message, notificationType);

                })

                .fail(function(jqXHR) {

                    console.error('Test error for ' + gateway + ':', jqXHR);

                    console.log('Status:', jqXHR.status);

                    console.log('Response:', jqXHR.responseJSON);

                    

                    let message = 'Erro ao conectar com o gateway ' + gateway;

                    if (jqXHR.status === 403) {

                        message = 'Acesso negado. Verifique suas permissões de administrador.';

                    } else if (jqXHR.status === 429) {

                        message = 'Muitas tentativas. Aguarde 30 segundos e tente novamente.';

                    } else if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {

                        message = jqXHR.responseJSON.data.message;

                    }

                    

                    // Marcar como indisponível

                    $statusElement.find('.status-dot').removeClass('status-unknown status-green status-yellow status-red').addClass('status-red');

                    $statusElement.find('.status-text').text('Indisponível');

                    

                    // Mostrar notificação de erro

                    window.hngShowNotification(message, 'error', 5000);

                })

                .always(function() {

                    $btn.prop('disabled', false);

                });

        });



        // Toggle gateway enable/disable

        $(document).on('change', '.gateway-toggle', function() {

            const $checkbox = $(this);

            const gateway = $checkbox.data('gateway');

            const enabled = $checkbox.is(':checked');

            

            // If enabling, disable all others immediately

            if (enabled) {

                $('.gateway-toggle').not($checkbox).each(function() {

                    if ($(this).is(':checked')) {

                        $(this).prop('checked', false);

                    }

                });

            }

            

            $.post(ajaxUrl, {

                action: 'hng_toggle_gateway',

                gateway: gateway,

                enabled: String(enabled),

                nonce: nonce

            }).done(function(resp){

                try {

                    const data = resp && resp.data ? resp.data : {};

                    // Ensure all disabled gateways are unchecked

                    if (enabled && Array.isArray(data.disabledGateways)) {

                        data.disabledGateways.forEach(function(id){

                            $(".gateway-toggle[data-gateway='" + id + "']").prop('checked', false);

                        });

                    }

                    // Show success message

                    if (data.message) {

                        const notificationType = enabled ? 'success' : 'info';

                        window.hngShowNotification(data.message, notificationType);

                    }

                } catch (e) {

                    console.error('Toggle gateway error:', e);

                }

            }).fail(function() {

                // Revert checkbox on error

                $checkbox.prop('checked', !enabled);

                window.hngShowNotification('Erro ao atualizar gateway', 'error');

            });

        });



        // Test all gateways

        $('#hng-test-all-gateways').on('click', function() {

            window.hngShowNotification('Iniciando teste de todos os gateways...', 'info', 5000);

            

            $.post(ajaxUrl, {

                action: 'hng_test_all_gateways',

                nonce: nonce

            })

                .done(function(resp) {

                    const msg = resp && resp.data && resp.data.message ? resp.data.message : 'Testes iniciados!';

                    window.hngShowNotification(msg, 'success');

                })

                .fail(function() {

                    window.hngShowNotification('Erro ao testar gateways', 'error');

                });

        });



        // Toggle advanced integration

        $(document).on('change', '.advanced-toggle', function(){

            const gateway = $(this).data('gateway');

            const enabled = $(this).is(':checked');

            const $form = $(this).closest('.gateway-config-form-inner');

            $.post(ajaxUrl, {

                action: 'hng_toggle_advanced_integration',

                gateway: gateway,

                enabled: String(enabled),

                nonce: nonce

            }).done(function(resp){

                const msg = resp && resp.data && resp.data.message ? resp.data.message : (enabled ? 'Ativado' : 'Desativado');

                window.hngShowNotification(msg, 'success');

            }).fail(function(){

                window.hngShowNotification('Erro ao atualizar integração avançada', 'error');

            });

        });



    // ==========================================

    // AUTO-CHECK DESABILITADO: O status agora é carregado do banco de dados
    // e não precisa mais fazer requisições AJAX ao carregar a página.
    // O status é salvo quando o usuário clica em "Testar Conexão".
    // ==========================================
    
    // OAuth Mercado Pago - Disconnect handler
    $(document).on('click', '.mp-oauth-disconnect', function(e) {
        e.preventDefault();
        
        if (!confirm('Tem certeza que deseja desconectar sua conta Mercado Pago?\n\nIsso desabilitará o Split Payment automático.')) {
            return;
        }
        
        var $btn = $(this);
        var merchantId = $btn.data('merchant');
        
        $btn.prop('disabled', true).text('Desconectando...');
        
        $.ajax({
            url: 'https://api.hngdesenvolvimentos.com.br/oauth/mercadopago/disconnect',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ merchant_id: merchantId }),
            success: function(response) {
                if (response.success) {
                    hngShowNotification('Mercado Pago desconectado com sucesso!', 'success');
                    // Reload para atualizar a seção OAuth
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    hngShowNotification('Erro: ' + (response.error || 'Falha ao desconectar'), 'error');
                    $btn.prop('disabled', false).text('Desconectar');
                }
            },
            error: function() {
                hngShowNotification('Erro de conexão com a API', 'error');
                $btn.prop('disabled', false).text('Desconectar');
            }
        });
    });
    
    // Check for OAuth callback messages
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('oauth_success') === 'mercadopago') {
        var mpUser = urlParams.get('mp_user');
        hngShowNotification('Mercado Pago conectado com sucesso!' + (mpUser ? ' (' + mpUser + ')' : ''), 'success', 5000);
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname + '?page=hng-gateways');
    }
    if (urlParams.get('oauth_error')) {
        hngShowNotification('Erro ao conectar Mercado Pago: ' + urlParams.get('oauth_error'), 'error', 5000);
        window.history.replaceState({}, document.title, window.location.pathname + '?page=hng-gateways');
    }
    
    // Refresh fees button handler
    $(document).on('click', '.refresh-fees-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $btn = $(this);
        var $icon = $btn.find('.dashicons');
        
        // Animate icon
        $icon.addClass('spin');
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'hng_refresh_fees',
                nonce: nonce
            },
            success: function(response) {
                $icon.removeClass('spin');
                $btn.prop('disabled', false);
                
                if (response.success) {
                    hngShowNotification('Taxas atualizadas com sucesso!', 'success');
                    // Reload page to show new fees
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    hngShowNotification('Erro: ' + (response.data.message || 'Falha ao atualizar taxas'), 'error');
                }
            },
            error: function() {
                $icon.removeClass('spin');
                $btn.prop('disabled', false);
                hngShowNotification('Erro de conexão ao atualizar taxas', 'error');
            }
        });
    });
    });



})(jQuery);

