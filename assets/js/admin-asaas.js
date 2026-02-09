/**

 * HNG Admin Asaas Data Page Scripts

 */

(function($) {

    'use strict';



    $(document).ready(function() {

        /**

         * Trigger Asaas sync

         */

        function triggerSync(action, btn, extraData = {}) {

            const originalText = btn.html();

            btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + hngAsaasPage.i18n.syncing);

            

            $.post(hngAsaasPage.ajaxUrl, {

                action: action,

                nonce: hngAsaasPage.nonce,

                ...extraData

            })

            .done(function(response) {

                // Verificar se a resposta é válida

                if (!response || typeof response !== 'object') {

                    alert(hngAsaasPage.i18n.syncError + ' ' + hngAsaasPage.i18n.unknownError);

                    return;

                }

                

                if (response.success) {

                    const data = response.data || {};

                    const message = hngAsaasPage.i18n.syncSuccess + '\n' +

                                  hngAsaasPage.i18n.processed + ': ' + (data.processed || 0) + '\n' +

                                  hngAsaasPage.i18n.created + ': ' + (data.created || 0) + '\n' +

                                  hngAsaasPage.i18n.updated + ': ' + (data.updated || 0);

                    alert(message);

                    location.reload();

                } else {

                    const errorMsg = (response.data && response.data.error) || hngAsaasPage.i18n.unknownError;

                    alert(hngAsaasPage.i18n.syncError + ' ' + errorMsg);

                }

            })

            .fail(function(jqXHR) {

                // Se recebeu HTML ao invés de JSON, mostrar erro mais informativo

                var responseSnippet = '';

                if (jqXHR && jqXHR.responseText) {

                    responseSnippet = '\n\nDetalhe: ' + jqXHR.responseText.substring(0, 400);

                }

                if (jqXHR.status === 200 && jqXHR.responseText && jqXHR.responseText.indexOf('<!DOCTYPE') !== -1) {

                    alert(hngAsaasPage.i18n.syncError + ' Erro de servidor (HTML retornado).' + responseSnippet);

                } else {

                    alert(hngAsaasPage.i18n.connectionError + ' (HTTP ' + jqXHR.status + ')' + responseSnippet);

                }

            })

            .always(function() {

                btn.prop('disabled', false).html(originalText);

            });

        }



        // Sync subscriptions button

        $('#hng-sync-subscriptions').on('click', function() {

            const start = $('#hng-sync-subscriptions-start').val();

            const end = $('#hng-sync-subscriptions-end').val();

            const payload = {};

            if (start) payload.start_date = start;

            if (end) payload.end_date = end;

            triggerSync('hng_asaas_sync_subscriptions', $(this), payload);

        });



        // Sync customers button

        $('#hng-sync-customers').on('click', function() {

            const start = $('#hng-sync-customers-start').val();

            const end = $('#hng-sync-customers-end').val();

            const payload = {};

            if (start) payload.start_date = start;

            if (end) payload.end_date = end;

            triggerSync('hng_asaas_sync_customers', $(this), payload);

        });



        // Sync payments button

        $('#hng-sync-payments').on('click', function() {

            const start = $('#hng-sync-payments-start').val();

            const end = $('#hng-sync-payments-end').val();

            const payload = {};

            if (start) payload.start_date = start;

            if (end) payload.end_date = end;

            triggerSync('hng_asaas_sync_payments', $(this), payload);

        });

    });



})(jQuery);

