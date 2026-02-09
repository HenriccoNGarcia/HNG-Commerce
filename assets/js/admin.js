jQuery(document).ready(function ($) {

    'use strict';



    // HNG Admin JS - Enhanced

    console.log('HNG Admin Enhanced loaded');



    const ajaxEndpoint = (window.hngCommerceAdmin && (hngCommerceAdmin.ajaxurl || hngCommerceAdmin.ajax_url)) || window.ajaxurl;

    const ajaxurl = ajaxEndpoint; // fallback for existing handlers

    const adminNonce = (window.hngCommerceAdmin && hngCommerceAdmin.nonce) || '';

    const recreatePagesNonce = (window.hngCommerceAdmin && hngCommerceAdmin.nonces && hngCommerceAdmin.nonces.recreate_pages) || '';

    const i18n = (window.hngCommerceAdmin && hngCommerceAdmin.i18n) || {};



    /**

     * Toggle filters

     */

    $('.hng-filter-toggle').on('click', function () {

        $('.hng-filters').slideToggle(300);

    });



    /**

     * Dismiss notices

     */

    $('.hng-notice-dismiss').on('click', function () {

        $(this).closest('.hng-notice').fadeOut(300);

    });



    /**

     * Mark Order as Processing/Completed

     */

    $(document).on('click', '.hng-mark-processing, .hng-mark-completed', function (e) {

        e.preventDefault();

        const $btn = $(this);

        const orderId = $btn.data('order-id');

        let newStatus = 'hng-processing';

        let action = 'hng_mark_processing';



        if ($btn.hasClass('hng-mark-completed')) {

            newStatus = 'hng-completed';

            action = 'hng_mark_completed';

        }



        if (!orderId) {

            alert('ID do pedido não encontrado');

            return;

        }



        $btn.prop('disabled', true).text('Atualizando...');



        $.ajax({

            url: ajaxurl,

            method: 'POST',

            data: {

                action: action,

                order_id: orderId,

                nonce: adminNonce

            },

            success: function (response) {

                if (response.success) {

                    // Show success message

                    alert('Status atualizado com sucesso!');

                    // Reload the page to reflect the new status

                    location.reload();

                } else {

                    alert('Erro: ' + (response.data?.message || 'Erro ao atualizar status'));

                    $btn.prop('disabled', false).text(newStatus === 'hng-processing' ? 'Processar' : 'Concluir');

                }

            },

            error: function () {

                alert('Erro ao comunicar com o servidor');

                $btn.prop('disabled', false).text(newStatus === 'hng-processing' ? 'Processar' : 'Concluir');

            }

        });

    });



    /**

     * Check Payment Status (Asaas)

     */

    $(document).on('click', '.hng-check-payment', function (e) {

        e.preventDefault();

        const $btn = $(this);

        const orderId = $btn.data('post-id'); // post_id para compatibilidade com API

        const originalText = $btn.text();



        if (!orderId) {

            alert('ID do pedido não encontrado');

            return;

        }



        $btn.prop('disabled', true).html('⏳ Consultando...');



        $.ajax({

            url: ajaxurl,

            method: 'POST',

            data: {

                action: 'hng_check_payment_status',

                order_id: orderId,

                nonce: (window.hngCommerceAdmin && hngCommerceAdmin.paymentCheckNonce) || ''

            },

            success: function (response) {

                if (response.success) {

                    if (response.data.paid) {

                        alert('✅ Pagamento confirmado! O pedido será atualizado automaticamente.');

                        location.reload();

                    } else {

                        alert('ℹ️ Status: ' + (response.data.status || 'PENDING') + '\n\nPagamento ainda não confirmado.');

                        $btn.prop('disabled', false).html(originalText);

                    }

                } else {

                    alert('Erro: ' + (response.data?.message || 'Erro ao verificar pagamento'));

                    $btn.prop('disabled', false).html(originalText);

                }

            },

            error: function () {

                alert('Erro ao comunicar com o servidor');

                $btn.prop('disabled', false).html(originalText);

            }

        });

    });



    /**

     * Shipping Modal

     */

    $('#hng-add-shipping-method').on('click', function (e) {

        e.preventDefault();

        $('#hng-shipping-modal').addClass('active');

    });



    $('.hng-close-modal').on('click', function () {

        $('.hng-modal').removeClass('active');

    });



    $(window).on('click', function (e) {

        if ($(e.target).hasClass('hng-modal')) {

            $(e.target).removeClass('active');

        }

    });



    /**

     * Payment Settings Tabs

     */

    $('.hng-payment-tab').on('click', function (e) {

        var target = $(this).data('target');

        // If data-target is not provided, let the link work normally (server-side tab)

        if (typeof target === 'undefined' || target === null) {

            // force navigation in case another handler prevented default

            var href = $(this).attr('href');

            if (href) {

                e.preventDefault();

                console.log('hng-payment-tab: forcing navigation to', href);

                window.location.href = href;

            }

            return;

        }

        e.preventDefault();



        // Update tabs (client-side behavior)

        $('.hng-payment-tab').removeClass('active');

        $(this).addClass('active');



        // Update content

        $('.hng-payment-content').removeClass('active');

        $('#' + target).addClass('active');

    });



    /**

     * Product Data Tabs

     */

    $('.hng-tabs-nav a').on('click', function (e) {

        e.preventDefault();



        // Get target tab

        var target = $(this).attr('href');



        // Update nav classes

        $('.hng-tabs-nav li').removeClass('active');

        $(this).parent().addClass('active');



        // Update content classes with animation

        $('.hng-tab-pane').removeClass('active');

        $(target).addClass('active fade-in');

    });



    /**

     * Stock Management Toggle

     */

    $('input[name="_manage_stock"]').on('change', function () {

        if ($(this).is(':checked')) {

            $('.stock_qty_field').slideDown(200);

        } else {

            $('.stock_qty_field').slideUp(200);

        }

    }).trigger('change');



    /**

     * One-off payment links (product metabox)

     */

    const OneOffLinks = {

        init: function () {

            console.log('[HNG] OneOffLinks.init() called');

            $(document).on('click', '.hng-generate-oneoff-link', this.generate);

            $(document).on('click', '.hng-copy-oneoff-link', this.copy);

            console.log('[HNG] OneOffLinks handlers registered');

        },



        generate: function (e) {

            e.preventDefault();

            const $container = $(this).closest('.hng-oneoff-link-box');

            const productId = $(this).data('product-id') || $container.data('product-id');

            const price = parseFloat($container.find('.hng-oneoff-price-input').val());

            const expiresDays = parseInt($container.find('.hng-oneoff-expires').val(), 10) || 0;

            const nonceField = $container.find('.hng-oneoff-nonce').val();

            const nonce = nonceField || adminNonce;

            const $btn = $(this);

            const $result = $container.find('.hng-oneoff-result');

            const $resultInput = $result.find('.hng-oneoff-link-input');

            const $openBtn = $result.find('.hng-open-oneoff-link');

            const $copyBtn = $result.find('.hng-copy-oneoff-link');



            console.log('OneOffLinks.generate called:', { productId, price, expiresDays, nonce, adminNonce, hngCommerceAdmin: window.hngCommerceAdmin });



            if (!price || price <= 0) {

                HNG_Notifications.error(i18n.enter_valid_price || 'Informe um preço válido.');

                return;

            }



            $btn.prop('disabled', true).text(i18n.loading || 'Carregando...');

            $result.css('display', 'block').show();

            $resultInput.val('');

            $resultInput.attr('placeholder', i18n.loading || 'Gerando link...');

            $openBtn.hide();

            $copyBtn.prop('disabled', true).hide();



            $.post(ajaxEndpoint, {

                action: 'hng_generate_oneoff_link',

                nonce: nonce,

                product_id: productId,

                price: price,

                expires_days: expiresDays

            }, function (resp) {

                console.log('OneOffLinks.generate response:', resp);



                if (!resp || !resp.success) {

                    const msg = (resp && resp.data && resp.data.message) || (i18n.error) || 'Erro ao processar.';

                    console.error('OneOffLinks.generate error:', msg);

                    $result.css('display', 'block').show();

                    $resultInput.val('');

                    $resultInput.attr('placeholder', msg);

                    $openBtn.hide();

                    $copyBtn.prop('disabled', true).hide();

                    HNG_Notifications.error(msg);

                    return;

                }



                const url = resp.data && resp.data.payment_url ? resp.data.payment_url : '';

                if (url) {

                    console.log('Payment URL generated:', url);

                    $resultInput.val(url);

                    $openBtn.attr('href', url).show();

                    $copyBtn.data('url', url).prop('disabled', false).show();

                    HNG_Notifications.success(i18n.generated || 'Link gerado com sucesso!');

                } else {

                    $result.css('display', 'block').show();

                    $resultInput.val('');

                    $resultInput.attr('placeholder', i18n.link_unavailable || 'Gateway não retornou link.');

                    $openBtn.hide();

                    $copyBtn.prop('disabled', true).hide();

                }

            }, 'json')

                .fail(function (jqXHR, textStatus, errorThrown) {

                    console.error('OneOffLinks.generate AJAX failed:', textStatus, errorThrown);

                    $result.css('display', 'block').show();

                    $resultInput.val('');

                    $resultInput.attr('placeholder', i18n.error || 'Erro ao processar.');

                    $openBtn.hide();

                    $copyBtn.prop('disabled', true).hide();

                    HNG_Notifications.error(i18n.error || 'Erro ao processar.');

                })

                .always(function () {

                    $btn.prop('disabled', false).text(i18n.generate_link || 'Gerar Link Avulso');

                });

        },



        copy: function (e) {

            e.preventDefault();

            const url = $(this).data('url');

            if (!url) return;

            navigator.clipboard.writeText(url).then(function () {

                HNG_Notifications.success(i18n.copied || 'Link copiado!');

            }).catch(function () {

                HNG_Notifications.error(i18n.error || 'Erro ao processar.');

            });

        }

    };



    /**

     * Admin pages actions (recreate default pages)

     */

    const PagesActions = {

        init: function () {

            $(document).on('click', '.hng-recreate-pages', this.recreate);

        },



        recreate: function (e) {

            e.preventDefault();

            const $btn = $(this);

            const nonce = $btn.data('nonce') || recreatePagesNonce || adminNonce;



            if (!(hngCommerceAdmin?.i18n?.recreate_confirm ? confirm(hngCommerceAdmin.i18n.recreate_confirm) : confirm('Recriar páginas padrão?'))) {

                return;

            }



            $btn.prop('disabled', true).addClass('hng-loading');



            $.post(ajaxEndpoint, {

                action: 'hng_recreate_default_pages',

                nonce: nonce

            }, function (response) {

                if (response && response.success) {

                    HNG_Notifications.success(response.data && response.data.message ? response.data.message : 'Páginas recriadas.');

                    setTimeout(function () { window.location.reload(); }, 600);

                } else {

                    const msg = response && response.data && response.data.message ? response.data.message : (hngCommerceAdmin?.i18n?.error || 'Erro ao processar.');

                    HNG_Notifications.error(msg);

                    $btn.prop('disabled', false).removeClass('hng-loading');

                }

            }).fail(function () {

                HNG_Notifications.error(hngCommerceAdmin?.i18n?.error || 'Erro ao processar.');

                $btn.prop('disabled', false).removeClass('hng-loading');

            });

        }

    };



    /**

     * Gateway Management

     */

    const GatewayManager = {

        init: function () {

            this.bindEvents();

        },



        bindEvents: function () {

            // Toggle gateway

            $(document).on('change', '.hng-gateway-toggle', this.toggleGateway);



            // Configure gateway - DESATIVADO: usando admin-gateways.js para este handler

            // $(document).on('click', '.hng-toggle-config', this.openConfiguration);



            // Test connection

            $(document).on('click', '.hng-test-gateway', this.testConnection);

        },



        toggleGateway: function (e) {

            const $toggle = $(this);

            const gatewayId = $toggle.data('gateway');

            const isEnabled = $toggle.is(':checked');



            $.ajax({

                url: ajaxurl,

                type: 'POST',

                data: {

                    action: 'hng_toggle_gateway',

                    gateway: gatewayId,

                    enabled: isEnabled ? 'yes' : 'no',

                    nonce: hngCommerceAdmin.nonce

                },

                beforeSend: function () {

                    $toggle.prop('disabled', true);

                },

                success: function (response) {

                    if (response.success) {

                        HNG_Notifications.success(response.data.message);



                        // Update card status

                        $toggle.closest('.hng-gateway-card').toggleClass('active', isEnabled);



                        // If activating this gateway, uncheck all others

                        if (isEnabled) {

                            $('.hng-gateway-toggle').not($toggle).each(function () {

                                $(this).prop('checked', false);

                                $(this).closest('.hng-gateway-card').removeClass('active');

                            });

                        }

                    } else {

                        HNG_Notifications.error(response.data.message);

                        $toggle.prop('checked', !isEnabled);

                    }

                },

                error: function () {

                    HNG_Notifications.error('Erro ao atualizar gateway');

                    $toggle.prop('checked', !isEnabled);

                },

                complete: function () {

                    $toggle.prop('disabled', false);

                }

            });

        },



        testConnection: function (e) {

            e.preventDefault();

            const gatewayId = $(this).data('gateway');

            const $button = $(this);



            $.ajax({

                url: ajaxurl,

                type: 'POST',

                data: {

                    action: 'hng_test_gateway_connection',

                    gateway_id: gatewayId,

                    nonce: hngCommerceAdmin.nonce

                },

                beforeSend: function () {

                    $button.prop('disabled', true).addClass('hng-loading');

                },

                success: function (response) {

                    if (response && typeof response === 'object' && response.success) {

                        HNG_Notifications.success('Conexão testada com sucesso!');

                    } else if (response && typeof response === 'object' && !response.success && response.data && response.data.message) {

                        HNG_Notifications.error(response.data.message);

                    } else {

                        HNG_Notifications.error('Falha no teste de conexão');

                    }

                },

                error: function () {

                    HNG_Notifications.error('Erro ao testar conexão');

                },

                complete: function () {

                    $button.prop('disabled', false).removeClass('hng-loading');

                }

            });

        }

    };



    /**

     * Order Management

     */

    const OrderManager = {

        init: function () {

            this.bindEvents();

        },



        bindEvents: function () {

            // Update order status

            $(document).on('click', '#update-order-status', this.updateStatus);



            // Resend email

            $(document).on('click', '#resend-email', this.resendEmail);



            // Add order note

            $(document).on('click', '#add-order-note', this.addNote);

        },



        updateStatus: function () {

            const orderId = $(this).data('order-id');

            const newStatus = $('#order-status').val();



            if (!confirm(hngCommerceAdmin.i18n.confirm_delete)) { // Reusing translation

                return;

            }



            $.ajax({

                url: ajaxurl,

                type: 'POST',

                data: {

                    action: 'hng_update_order_status',

                    order_id: orderId,

                    status: newStatus,

                    nonce: hngCommerceAdmin.nonce

                },

                beforeSend: function () {

                    $('#update-order-status').addClass('hng-loading');

                },

                success: function (response) {

                    if (response.success) {

                        HNG_Notifications.success('Status atualizado!');

                        setTimeout(() => location.reload(), 1000);

                    } else {

                        HNG_Notifications.error(response.data.message);

                    }

                },

                complete: function () {

                    $('#update-order-status').removeClass('hng-loading');

                }

            });

        },



        resendEmail: function () {

            const orderId = $(this).data('order-id');



            $.ajax({

                url: ajaxurl,

                type: 'POST',

                data: {

                    action: 'hng_resend_order_email',

                    order_id: orderId,

                    nonce: hngCommerceAdmin.nonce

                },

                beforeSend: function () {

                    HNG_Notifications.info('Enviando email...');

                },

                success: function (response) {

                    if (response.success) {

                        HNG_Notifications.success('Email enviado!');

                    } else {

                        HNG_Notifications.error(response.data.message);

                    }

                }

            });

        },



        addNote: function () {

            const orderId = $(this).data('order-id');

            const note = $('#new-order-note').val();



            if (!note.trim()) {

                HNG_Notifications.warning('Digite uma nota');

                return;

            }



            $.ajax({

                url: ajaxurl,

                type: 'POST',

                data: {

                    action: 'hng_add_order_note',

                    order_id: orderId,

                    note: note,

                    nonce: hngCommerceAdmin.nonce

                },

                beforeSend: function () {

                    $('#add-order-note').addClass('hng-loading');

                },

                success: function (response) {

                    if (response.success) {

                        HNG_Notifications.success('Nota adicionada!');

                        $('#new-order-note').val('');

                        // Reload notes list

                        location.reload();

                    } else {

                        HNG_Notifications.error(response.data.message);

                    }

                },

                complete: function () {

                    $('#add-order-note').removeClass('hng-loading');

                }

            });

        }

    };



    /**

     * Import/Export Tools

     */

    const ImportExport = {

        init: function () {

            console.log('ImportExport: init called');

            this.bindEvents();

            this.initDragDrop();

        },



        bindEvents: function () {

            // Import WooCommerce

            console.log('ImportExport: binding events');

            $(document).on('click', '#hng-import-woocommerce', function (e) { console.log('ImportExport: #hng-import-woocommerce clicked'); return ImportExport.importWooCommerce.call(this, e); });



            // Export products

            $(document).on('click', '#hng-export-products', function (e) { console.log('ImportExport: #hng-export-products clicked'); return ImportExport.exportProducts.call(this, e); });



            // Export orders

            $(document).on('click', '#hng-export-orders', function (e) { console.log('ImportExport: #hng-export-orders clicked'); return ImportExport.exportOrders.call(this, e); });

        },



        initDragDrop: function () {

            const $dropzone = $('.hng-dropzone');



            if (!$dropzone.length) return;



            $dropzone.on('drag dragstart dragend dragover dragenter dragleave drop', function (e) {

                e.preventDefault();

                e.stopPropagation();

            })

                .on('dragover drag enter', function () {

                    $(this).addClass('drag-over');

                })

                .on('dragleave dragend drop', function () {

                    $(this).removeClass('drag-over');

                })

                .on('drop', function (e) {

                    const files = e.originalEvent.dataTransfer.files;

                    ImportExport.handleFiles(files);

                });



            // File input

            $('#hng-csv-file-input').on('change', function () {

                ImportExport.handleFiles(this.files);

            });

        },



        handleFiles: function (files) {

            if (files.length === 0) return;



            const file = files[0];



            if (!file.name.endsWith('.csv')) {

                HNG_Notifications.error('Por favor, selecione um arquivo CSV');

                return;

            }



            // Upload file

            const formData = new FormData();

            formData.append('file', file);

            formData.append('action', 'hng_upload_csv');

            formData.append('nonce', hngCommerceAdmin.nonce);



            $.ajax({

                url: ajaxurl,

                type: 'POST',

                data: formData,

                processData: false,

                contentType: false,

                beforeSend: function () {

                    $('.hng-dropzone').addClass('hng-loading');

                },

                success: function (response) {

                    if (response.success) {

                        HNG_Notifications.success('Arquivo carregado! Pronto para importação.');

                        // guardar attachment_id retornado para importação

                        try {

                            if (response.data && response.data.attachment_id) {

                                window.hngLastUploadedCSV = response.data.attachment_id;

                                // opcional: atualizar campo oculto se existir

                                var $hidden = $('#hng-last-uploaded-csv');

                                if ($hidden.length) $hidden.val(response.data.attachment_id);

                            }

                        } catch (err) { /* ignore */ }

                    } else {

                        HNG_Notifications.error(response.data.message);

                    }

                },

                complete: function () {

                    $('.hng-dropzone').removeClass('hng-loading');

                }

            });

        },



        importWooCommerce: function (e) {

            e.preventDefault();

            const importType = $('input[name="import_type"]:checked').val();



            if (!confirm('Iniciar importação do WooCommerce?')) {

                return;

            }



            $.ajax({

                url: ajaxurl,

                type: 'POST',

                data: {

                    action: 'hng_import_woocommerce',

                    import_type: importType,

                    attachment_id: window.hngLastUploadedCSV || $('#hng-last-uploaded-csv').val(),

                    nonce: hngCommerceAdmin.nonce

                },

                beforeSend: function () {

                    HNG_Notifications.info('Importação iniciada...');

                    $('#hng-import-woocommerce').addClass('hng-loading');

                },

                success: function (response) {

                    if (response.success) {

                        HNG_Notifications.success(response.data.message);

                    } else {

                        HNG_Notifications.error(response.data.message);

                    }

                },

                complete: function () {

                    $('#hng-import-woocommerce').removeClass('hng-loading');

                }

            });

        },



        exportProducts: function (e) {

            e.preventDefault();

            window.location.href = ajaxurl + '?action=hng_export_products&nonce=' + hngCommerceAdmin.nonce;

            HNG_Notifications.success('Download iniciado!');

        },



        exportOrders: function (e) {

            e.preventDefault();

            window.location.href = ajaxurl + '?action=hng_export_orders&nonce=' + hngCommerceAdmin.nonce;

            HNG_Notifications.success('Download iniciado!');

        }

    };



    // Initialize all modules on document ready to avoid race conditions

    $(document).ready(function () {

        try {

            GatewayManager.init();

        } catch (e) { console.warn('GatewayManager init failed', e); }

        try {

            OrderManager.init();

        } catch (e) { console.warn('OrderManager init failed', e); }

        try {

            ImportExport.init();

        } catch (e) { console.warn('ImportExport init failed', e); }



        // Debug: log clicks on import/export controls to ensure events fire

        $(document).on('click', '#hng-import-woocommerce, #hng-export-products, #hng-export-orders', function (e) {

            console.log('Debug: clicked', this.id);

        });

    });



    // Capture phase fallback: ensure server-side tab links navigate even if other handlers stop propagation

    document.addEventListener('click', function (e) {

        try {

            var a = e.target.closest && e.target.closest('a.hng-payment-tab, a.hng-tool-tab');

            if (!a) return;

            // prefer explicit data-hng-url if present (absolute admin URL)

            var hurl = a.getAttribute('data-hng-url') || a.getAttribute('href');

            var target = a.getAttribute('data-target');

            if (!target && hurl) {

                // Force navigation on capture before other handlers run

                console.log('Capture fallback: forcing navigation to', hurl);

                e.preventDefault();

                window.location.href = hurl;

            }

        } catch (err) { /* ignore */ }

    }, true);



    // Capture-phase fallback for import/export buttons (ensures handler runs even if other scripts block)

    document.addEventListener('pointerdown', function (e) {

        try {

            const btn = e.target.closest && e.target.closest('#hng-import-woocommerce, #hng-export-products, #hng-export-orders');

            if (!btn) return;

            const id = btn.id;

            console.log('Capture: pointerdown on', id);

            // Call corresponding handler directly

            if (id === 'hng-import-woocommerce') {

                e.preventDefault();

                ImportExport.importWooCommerce.call(btn, e);

            } else if (id === 'hng-export-products') {

                e.preventDefault();

                ImportExport.exportProducts.call(btn, e);

            } else if (id === 'hng-export-orders') {

                e.preventDefault();

                ImportExport.exportOrders.call(btn, e);

            }

        } catch (err) {

            console.warn('Capture handler error', err);

        }

    }, true);



    // Capture-phase pointerdown to force navigation for server-side tab links

    document.addEventListener('pointerdown', function (e) {

        try {

            var a = e.target.closest && e.target.closest('a.hng-payment-tab, a.hng-tool-tab');

            if (!a) return;

            var hurl = a.getAttribute('data-hng-url') || a.getAttribute('href');

            if (hurl) {

                console.log('Capture pointerdown: forcing navigation to', hurl);

                e.preventDefault();

                window.location.href = hurl;

            }

        } catch (err) { /* ignore */ }

    }, true);



    // Also listen for mousedown and touchstart as some environments block pointer events

    ['mousedown', 'touchstart'].forEach(function (evtName) {

        document.addEventListener(evtName, function (e) {

            try {

                var a = e.target.closest && e.target.closest('a.hng-payment-tab, a.hng-tool-tab');

                if (!a) return;

                var hurl = a.getAttribute('data-hng-url') || a.getAttribute('href');

                if (hurl) {

                    console.log('Capture ' + evtName + ': forcing navigation to', hurl);

                    e.preventDefault();

                    window.location.href = hurl;

                }

            } catch (err) { /* ignore */ }

        }, true);

    });



    // Diagnostic: log tab elements and computed styles to help debug overlays

    try {

        var tabs = document.querySelectorAll('a.hng-payment-tab');

        console.log('HNG Tabs detected:', tabs.length);

        tabs.forEach(function (t, i) {

            try {

                var cs = window.getComputedStyle(t);

                console.log('Tab #' + i, t.getAttribute('href') || t.getAttribute('data-hng-url'), {

                    pointerEvents: cs.pointerEvents,

                    zIndex: cs.zIndex,

                    display: cs.display,

                    visibility: cs.visibility,

                    opacity: cs.opacity

                }, t);

            } catch (e) { }

        });

    } catch (e) { }



    /**

     * Enhanced Table Interactions

     */

    // Acessibilidade: navegação por teclado nas linhas da tabela

    $('.hng-table tbody tr').attr('tabindex', 0);

    $('.hng-table tbody tr').on('focus', function () {

        $(this).addClass('hng-row-focus');

    }).on('blur', function () {

        $(this).removeClass('hng-row-focus');

    });

    // Hover visual já tratado no CSS



    /**

     * Search with debounce

     */

    let searchTimeout;

    $('.hng-search-input').on('input', function () {

        const $input = $(this);

        const searchTerm = $input.val();



        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(function () {

            // Perform search

            console.log('Searching for:', searchTerm);

        }, 500);

    });



    /**

     * Auto-save settings

     */

    $('.hng-auto-save').on('change', function () {

        const $field = $(this);

        const fieldName = $field.attr('name');

        const fieldValue = $field.val();



        $.ajax({

            url: ajaxurl,

            type: 'POST',

            data: {

                action: 'hng_auto_save_setting',

                field: fieldName,

                value: fieldValue,

                nonce: hngCommerceAdmin.nonce

            },

            success: function (response) {

                if (response.success) {

                    // Show subtle save indicator

                    $field.after('<span class="hng-saved-indicator">✓</span>');

                    setTimeout(function () {

                        $('.hng-saved-indicator').fadeOut(function () {

                            $(this).remove();

                        });

                    }, 2000);

                }

            }

        });

    });



    /**

     * Keyboard Shortcuts e navegação por tab

     */

    $(document).on('keydown', function (e) {

        // Ctrl+S para salvar (impede diálogo padrão) - Apenas se estiver em uma página HNG

        if (e.ctrlKey && e.key === 's') {

            if ($('.hng-admin-panel').length || $('.hng-wrap').length) {

                e.preventDefault();

                $('.button-primary, .hng-btn-primary').first().focus().click();

                HNG_Notifications.info('Salvando...');

            }

        }

        // Fechar modal com ESC

        if (e.key === 'Escape') {

            $('.hng-modal.active').remove();

        }

        // Tabulação acessível em modais

        if ($('.hng-modal.active').length) {

            const focusable = $('.hng-modal.active').find('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');

            if (e.key === 'Tab') {

                let first = focusable.first()[0];

                let last = focusable.last()[0];

                if (e.shiftKey) {

                    if (document.activeElement === first) {

                        e.preventDefault();

                        last.focus();

                    }

                } else {

                    if (document.activeElement === last) {

                        e.preventDefault();

                        first.focus();

                    }

                }

            }

        }

    });



    // Foco automático em modais

    $(document).on('DOMNodeInserted', function (e) {

        if ($(e.target).hasClass('hng-modal')) {

            setTimeout(function () {

                $(e.target).find('input, select, textarea, button, a, [tabindex]:not([tabindex="-1"])').filter(':visible').first().focus();

            }, 100);

        }

    });

    // Feedback visual para botões em loading - Apenas dentro de containers HNG

    $(document).on('click', '.hng-admin-panel .hng-btn, .hng-admin-panel .button-primary, .hng-wrap .button-primary', function () {

        $(this).addClass('hng-loading');

        setTimeout(() => $(this).removeClass('hng-loading'), 1500);

    });

    

    // Inicializar OneOffLinks para metabox de link avulso

    if (typeof OneOffLinks !== 'undefined') {

        OneOffLinks.init();

    } else {

        console.error('[HNG] OneOffLinks not defined!');

    }

    

    console.log('HNG Admin Enhanced initialized');

});