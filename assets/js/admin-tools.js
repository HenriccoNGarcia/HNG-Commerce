/**
 * HNG Admin Tools Page Scripts
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Migrate subscription notes
        $('#hng-migrate-notes').on('click', function (e) {
            e.preventDefault();
            if (!confirm(hngToolsPage.i18n.confirmMigrate)) return;

            $('#hng-migrate-notes-result').text(hngToolsPage.i18n.starting);

            $.post(hngToolsPage.ajaxUrl, {
                action: 'hng_migrate_subscription_notes',
                nonce: hngToolsPage.nonce
            }, function (resp) {
                if (!resp.success) {
                    $('#hng-migrate-notes-result').text(
                        resp.data && resp.data.message ? resp.data.message : hngToolsPage.i18n.error
                    );
                    return;
                }
                $('#hng-migrate-notes-result').text(
                    hngToolsPage.i18n.migrated + ': ' + (resp.data.migrated || 0)
                );
            }, 'json').fail(function () {
                $('#hng-migrate-notes-result').text(hngToolsPage.i18n.requestError);
            });
        });

        // --- CSV Import Logic (Tab: Importar CSV) ---
        $('#hng-csv-file-input').on('change', function () {
            var fileName = $(this).val().split('\\').pop();
            if (fileName) {
                $('#hng-csv-file-name').text('Arquivo: ' + fileName);
                $('#hng-start-import-csv').removeAttr('disabled').removeClass('button-secondary').addClass('button-primary');
            } else {
                $('#hng-csv-file-name').text('');
                $('#hng-start-import-csv').attr('disabled', 'disabled');
            }
        });

        $('#hng-start-import-csv').on('click', function (e) {
            e.preventDefault();
            var fileInput = $('#hng-csv-file-input')[0];

            if (fileInput.files.length === 0) {
                alert('Por favor, selecione um arquivo CSV.');
                return;
            }

            var btn = $(this);
            var originalText = btn.html();
            btn.attr('disabled', 'disabled').html('<span class="dashicons dashicons-update hng-spin"></span> Importando...');

            var formData = new FormData();
            formData.append('action', 'hng_import_csv');
            formData.append('nonce', hngToolsPage.importNonce);
            formData.append('file', fileInput.files[0]);

            $('#hng-import-progress').slideDown();
            $('#hng-import-progress .progress-text').text('Enviando arquivo...');
            $('#hng-import-progress div div').css('width', '50%');

            $.ajax({
                url: hngToolsPage.ajaxUrl,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    $('#hng-import-progress div div').css('width', '100%');

                    if (response.success) {
                        var msg = 'Importação concluída!\n';
                        msg += 'Importados: ' + (response.data.imported ? response.data.imported.length : 0) + '\n';
                        msg += 'Atualizados: ' + (response.data.updated ? response.data.updated.length : 0) + '\n';
                        msg += 'Erros: ' + (response.data.errors ? response.data.errors.length : 0);

                        if (response.data.errors && response.data.errors.length > 0) {
                            msg += '\n\nVerifique o console para detalhes dos erros.';
                            console.log('Erros:', response.data.errors);
                        }

                        $('#hng-import-progress .progress-text').text('Concluído!');
                        alert(msg);
                        location.reload();
                    } else {
                        $('#hng-import-progress .progress-text').text('Falha na importação.');
                        alert('Erro: ' + (response.data.message || 'Erro desconhecido'));
                        btn.removeAttr('disabled').html(originalText);
                    }
                },
                error: function () {
                    $('#hng-import-progress .progress-text').text('Erro de servidor.');
                    alert('Erro de comunicação com o servidor.');
                    btn.removeAttr('disabled').html(originalText);
                }
            });
        });

        // --- WooCommerce / General Data Import Logic (Tab: Importar Dados) ---

        // Toggle instructions
        $('input[name="hng_import_type"]').on('change', function () {
            var type = $(this).val();
            $('.hng-fmt-info').hide();
            if (type === 'orders') {
                $('#hng-fmt-orders').show();
            } else {
                $('#hng-fmt-products').show();
            }
        });

        // File selection
        $('#hng-woo-file').on('change', function () {
            var fileName = $(this).val().split('\\').pop();
            if (fileName) {
                $('#hng-woo-filename').text(fileName);
                $('#hng-start-woo-import').removeAttr('disabled');
            } else {
                $('#hng-woo-filename').text('Nenhum arquivo selecionado');
                $('#hng-start-woo-import').attr('disabled', 'disabled');
            }
        });

        // Start Import
        $('#hng-start-woo-import').on('click', function (e) {
            e.preventDefault();
            var fileInput = $('#hng-woo-file')[0];
            var importType = $('input[name="hng_import_type"]:checked').val();

            if (fileInput.files.length === 0) {
                alert('Selecione um arquivo CSV.');
                return;
            }

            var btn = $(this);
            var originalText = btn.html();
            btn.attr('disabled', 'disabled').html('<span class="dashicons dashicons-update hng-spin"></span> Processando...');

            // 1. Upload CSV
            var formData = new FormData();
            formData.append('action', 'hng_upload_csv');
            formData.append('nonce', hngToolsPage.nonce);
            formData.append('file', fileInput.files[0]);

            $('#hng-woo-progress').slideDown();
            $('#hng-woo-progress .progress-text').text('Enviando arquivo...');
            $('#hng-woo-progress div div').css('width', '30%');

            $.ajax({
                url: hngToolsPage.ajaxUrl,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (respUpload) {
                    if (respUpload.success) {
                        // 2. Trigger Import
                        $('#hng-woo-progress .progress-text').text('Importando dados (' + importType + ')...');
                        $('#hng-woo-progress div div').css('width', '60%');

                        var attachmentId = respUpload.data.attachment_id || 0;

                        $.post(hngToolsPage.ajaxUrl, {
                            action: 'hng_import_woocommerce',
                            nonce: hngToolsPage.nonce,
                            attachment_id: attachmentId,
                            import_type: importType
                        }, function (respImport) {
                            $('#hng-woo-progress div div').css('width', '100%');

                            if (respImport.success) {
                                $('#hng-woo-progress .progress-text').text('Concluído!');
                                alert('Importação realizada com sucesso!');
                                location.reload();
                            } else {
                                $('#hng-woo-progress .progress-text').text('Erro na importação.');
                                var errMsg = respImport.data && respImport.data.message ? respImport.data.message : 'Erro desconhecido';
                                alert('Erro: ' + errMsg);
                                btn.removeAttr('disabled').html(originalText);
                            }
                        }).fail(function () {
                            $('#hng-woo-progress .progress-text').text('Erro no servidor.');
                            alert('Erro ao processar importação (500/timeout).');
                            btn.removeAttr('disabled').html(originalText);
                        });
                    } else {
                        $('#hng-woo-progress .progress-text').text('Erro no upload.');
                        alert('Erro no upload: ' + (respUpload.data.message || 'Erro'));
                        btn.removeAttr('disabled').html(originalText);
                    }
                },
                error: function () {
                    alert('Erro de conexão no upload.');
                    btn.removeAttr('disabled').html(originalText);
                }
            });
        });

    });

    // Global functions for onclick handlers
    window.hngClearCache = function () {
        if (!confirm(hngToolsPage.i18n.confirmClearCache)) return;

        $.post(hngToolsPage.ajaxUrl, {
            action: 'hng_clear_cache',
            nonce: hngToolsPage.clearCacheNonce
        }, function (r) {
            alert(r.success ? hngToolsPage.i18n.cacheCleared : hngToolsPage.i18n.cacheError);
        });
    };

    window.hngRecreatePages = function () {
        if (!confirm(hngToolsPage.i18n.confirmRecreatePages)) return;

        $.post(hngToolsPage.ajaxUrl, {
            action: 'hng_recreate_default_pages',
            nonce: hngToolsPage.recreatePagesNonce
        }, function (r) {
            alert(r.data.message || (r.success ? hngToolsPage.i18n.pagesRecreated : hngToolsPage.i18n.error));
            if (r.success) location.reload();
        });
    };

    window.hngRecreateTables = function () {
        if (!confirm(hngToolsPage.i18n.confirmRecreateTables)) return;

        $.post(hngToolsPage.ajaxUrl, {
            action: 'hng_recreate_tables',
            nonce: hngToolsPage.recreateTablesNonce
        }, function (r) {
            alert(r.data.message || (r.success ? hngToolsPage.i18n.tablesRecreated : hngToolsPage.i18n.error));
        });
    };

    // SQL Import Handler
    $(document).ready(function () {
        $('#hng-import-sql-file').on('change', function () {
            var file = this.files[0];
            if (!file) return;

            if (!confirm('ATENÇÃO: Importar um arquivo SQL pode sobrescrever dados existentes e corromper o site se o arquivo estiver incorreto. Deseja continuar?')) {
                $(this).val('');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'hng_run_sql_import');
            formData.append('nonce', hngToolsPage.nonce);
            formData.append('file', file);

            var btn = $(this).next('button');
            var originalText = btn.html();
            btn.prop('disabled', true).text('Executando...');

            $.ajax({
                url: hngToolsPage.ajaxUrl,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (res) {
                    if (res.success) {
                        alert(res.data.message || 'SQL executado com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro: ' + (res.data.message || 'Erro desconhecido'));
                    }
                },
                error: function () {
                    alert('Erro de conexão.');
                },
                complete: function () {
                    btn.prop('disabled', false).html(originalText);
                    $('#hng-import-sql-file').val('');
                }
            });
        });
    });

})(jQuery);
