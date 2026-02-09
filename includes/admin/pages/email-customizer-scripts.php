<?php
/**
 * JavaScript para Email Customizer Page v2
 * Funcionalidades de customização e preview de emails
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<script>

jQuery(document).ready(function($) {

    // Fallback para variável global
    if (typeof hngEmailCustomizer === 'undefined') {
        var hngEmailCustomizer = {
            ajax_url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo esc_attr(wp_create_nonce('hng_email_customizer')); ?>',
            current_type: '<?php echo esc_attr($current_type); ?>',
            i18n: {}
        };
        var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
    }
    
    // Definir i18n corretamente
    var i18n = {
        saving: '<?php echo esc_js(__('Salvando...', 'hng-commerce')); ?>',
        saved: '<?php echo esc_js(__('Salvo!', 'hng-commerce')); ?>',
        error: '<?php echo esc_js(__('Erro', 'hng-commerce')); ?>',
        sending: '<?php echo esc_js(__('Enviando...', 'hng-commerce')); ?>',
        sent: '<?php echo esc_js(__('Enviado!', 'hng-commerce')); ?>',
        testSent: '<?php echo esc_js(__('Email de teste enviado com sucesso!', 'hng-commerce')); ?>',
        confirmReset: '<?php echo esc_js(__('Tem certeza que deseja restaurar o template padrão? Esta ação não pode ser desfeita.', 'hng-commerce')); ?>',
        selectLogo: '<?php echo esc_js(__('Selecionar Logo', 'hng-commerce')); ?>',
        useLogo: '<?php echo esc_js(__('Usar este logo', 'hng-commerce')); ?>',
        clickToAdd: '<?php echo esc_js(__('Clique para adicionar logo', 'hng-commerce')); ?>'
    };

    let previewTimeout;
    let isPreviewLock = false;

    

    /* ============================================
       TAB SWITCHING
       ============================================ */

    $('.tab-btn').on('click', function() {

        const tabName = $(this).data('tab');

        

        $('.tab-btn').removeClass('active');

        $(this).addClass('active');

        

        $('.tab-content').removeClass('active');

        $('.tab-content[data-tab="' + tabName + '"]').addClass('active');

    });

    

    /* ============================================
       COLOR PICKER
       ============================================ */

    // Destruir color pickers existentes antes de inicializar
    if ($.isFunction($.fn.wpColorPicker)) {
        // Destruir instâncias antigas
        $('.color-picker').each(function() {
            if ($(this).hasClass('wp-color-picker')) {
                $(this).wpColorPicker('destroy');
            }
        });
        
        // Inicializar novamente sem paleta de cores
        $('.color-picker').wpColorPicker({
            palettes: false, // Desabilita paleta de cores
            change: function() {
                updatePreview();
            }
        });
    }

    

    /* ============================================
       SAVE EMAIL TEMPLATE
       ============================================ */

    $('#save-email-template').on('click', function(e) {

        e.preventDefault();

        

        const $button = $(this);

        const originalText = $button.html();

        

        const data = {

            action: 'hng_save_email_template',

            nonce: $('#hng_email_nonce').val(),

            email_type: $('#current-email-type').val(),

            subject: $('#email-subject').val(),

            from_name: $('#from-name').val(),

            from_email: $('#from-email').val(),

            header_color: $('#header-color').val(),

            button_color: $('#button-color').val(),

            text_color: $('#text-color').val(),

            bg_color: $('#bg-color').val(),

            logo: $('#email-logo').val(),

            content: tinymce.get('email-content-editor') ? tinymce.get('email-content-editor').getContent() : $('#email-content-editor').val()

        };

        

        $button.prop('disabled', true).html('<span class="spinner is-active" style="float: left;"></span> ' + i18n.saving);

        

        $.post(ajaxurl, data, function(response) {

            if (response.success) {

                $button.html('✓ ' + i18n.saved).css('background-color', '#46b450').css('border-color', '#46b450');

                setTimeout(function() {

                    $button.html(originalText).prop('disabled', false).css('background-color', '').css('border-color', '');

                }, 2000);

                updatePreview();

            } else {

                alert(i18n.error + ': ' + (response.data.message || 'Erro desconhecido'));

                $button.prop('disabled', false).html(originalText);

            }

        });

    });

    

    /* ============================================
       RESET EMAIL TEMPLATE
       ============================================ */

    $('#reset-template').on('click', function(e) {

        e.preventDefault();

        

        if (!confirm(i18n.confirmReset)) {

            return;

        }

        

        const $button = $(this);

        const originalText = $button.html();

        

        $.post(ajaxurl, {

            action: 'hng_reset_email_template',

            nonce: $('#hng_email_nonce').val(),

            email_type: $('#current-email-type').val()

        }, function(response) {

            if (response.success) {

                location.reload();

            } else {

                alert(i18n.error);

            }

        });

    });
    
    
    
    /* ============================================
       USE GLOBAL SETTINGS BUTTON
       ============================================ */

    $('#use-global-settings').on('click', function(e) {

        e.preventDefault();

        

        if (!confirm('Deseja usar as cores e logo das Configurações Globais?\nSuas configurações personalizadas de cores/logo serão removidas.')) {

            return;

        }

        

        const $button = $(this);

        const originalText = $button.html();

        $button.prop('disabled', true).html('<span class="spinner is-active"></span> Aplicando...');

        

        $.post(ajaxurl, {

            action: 'hng_use_global_settings',

            nonce: $('#hng_email_nonce').val(),

            email_type: $('#current-email-type').val()

        }, function(response) {

            if (response.success) {

                alert(response.data.message || 'Configurações globais aplicadas!');

                location.reload();

            } else {

                alert(response.data && response.data.message ? response.data.message : 'Erro ao aplicar configurações');

                $button.prop('disabled', false).html(originalText);

            }

        });

    });

    

    /* ============================================
       LOGO UPLOAD
       ============================================ */

    let mediaFrame;

    

    $('#upload-logo').on('click', function(e) {

        e.preventDefault();

        

        if (mediaFrame) {

            mediaFrame.open();

            return;

        }

        

        mediaFrame = wp.media({

            title: i18n.selectLogo,

            button: { text: i18n.useLogo },

            multiple: false,

            library: { type: 'image' }

        });

        

        mediaFrame.on('select', function() {

            const attachment = mediaFrame.state().get('selection').first().toJSON();

            $('#email-logo').val(attachment.url);

            updateLogoPreview(attachment.url);

            updatePreview();

        });

        

        mediaFrame.open();

    });

    

    $('#remove-logo').on('click', function(e) {

        e.preventDefault();

        $('#email-logo').val('');

        updateLogoPreview('');

        updatePreview();

    });

    

    function updateLogoPreview(url) {

        const $preview = $('.logo-preview-box');

        

        if (url) {

            $preview.html('<img src="' + url + '" class="logo-img">');

            $('#remove-logo').show();

        } else {

            $preview.html('<div class="logo-placeholder"><span class="dashicons dashicons-format-image"></span><p>' + i18n.clickToAdd + '</p></div>');

            $('#remove-logo').hide();

        }

    }

    

    /* ============================================
       LIVE PREVIEW UPDATE
       ============================================ */

    function updatePreview() {

        if (isPreviewLock) return;

        

        clearTimeout(previewTimeout);

        previewTimeout = setTimeout(function() {

            loadPreview();

        }, 500);

    }

    

    function loadPreview() {
        // Mostrar loading
        $('.preview-loading').show();
        $('#email-preview-editable').css('opacity', '0.5');
        
        // Pegar conteúdo atual do editor (não salvo)
        var currentContent = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('email-content-editor')) {
            currentContent = tinymce.get('email-content-editor').getContent();
        } else {
            currentContent = $('#email-content-editor').val();
        }
        
        $.post(ajaxurl, {
            action: 'hng_get_email_preview',
            nonce: $('#hng_email_nonce').val(),
            email_type: $('#current-email-type').val(),
            order_id: $('#preview-order').val(),
            // Enviar dados atuais para preview em tempo real
            live_content: currentContent,
            live_logo: $('#email-logo').val(),
            live_header_color: $('#header-color').val(),
            live_button_color: $('#button-color').val(),
            live_text_color: $('#text-color').val(),
            live_bg_color: $('#bg-color').val()
        }, function(response) {
            // Esconder loading
            $('.preview-loading').hide();
            $('#email-preview-editable').css('opacity', '1');
            
            if (response.success) {
                $('#email-preview-editable').html(response.data.html);
                // Também atualizar o editor de código
                $('#email-code-editor').val(response.data.html);
            } else {
                $('#email-preview-editable').html('<div style="padding: 20px; color: red;">Erro ao carregar preview: ' + (response.data ? response.data.message : 'Erro desconhecido') + '</div>');
            }
        }).fail(function(xhr, status, error) {
            // Esconder loading em caso de erro
            $('.preview-loading').hide();
            $('#email-preview-editable').css('opacity', '1').html('<div style="padding: 20px; color: red;">Erro de conexão: ' + error + '</div>');
        });

    }

    

    /* ============================================
       PREVIEW CONTROLS
       ============================================ */

    $('#refresh-preview').on('click', function() {

        loadPreview();

    });

    

    $('#send-test-email').on('click', function(e) {

        e.preventDefault();

        

        const $button = $(this);

        const originalText = $button.html();

        

        $.post(ajaxurl, {

            action: 'hng_send_test_email',

            nonce: $('#hng_email_nonce').val(),

            email_type: $('#current-email-type').val()

        }, function(response) {

            if (response.success) {

                alert(i18n.testSent);

                $button.html('✓ ' + i18n.sent).css('background-color', '#46b450');

                setTimeout(function() {

                    $button.html(originalText).css('background-color', '');

                }, 2000);

            } else {

                alert(i18n.error + ': ' + (response.data.message || ''));

            }

        });

    });

    

    /* ============================================
       MODE TOGGLE
       ============================================ */

    $('.mode-btn').on('click', function() {

        const mode = $(this).data('mode');

        

        $('.mode-btn').removeClass('active');

        $(this).addClass('active');

        

        if (mode === 'visual') {

            $('#visual-mode-preview').show();

            $('#code-mode-preview').hide();

        } else {

            $('#visual-mode-preview').hide();

            $('#code-mode-preview').show();

        }

    });

    

    /* ============================================
       DEVICE TABS
       ============================================ */

    $('.device-tab').on('click', function() {

        const device = $(this).data('device');

        

        $('.device-tab').removeClass('active');

        $(this).addClass('active');

        

        $('.preview-viewport').hide();

        $('.preview-viewport.' + device).show();

    });

    

    /* ============================================
       VARIABLES DRAGGING
       ============================================ */

    $('.variable-item').on('dragstart', function(e) {

        const variable = $(this).data('variable');

        e.originalEvent.dataTransfer.setData('text/plain', variable);

        $(this).css('opacity', '0.5');

    }).on('dragend', function() {

        $(this).css('opacity', '1');

    });

    

    $('.copy-var-btn').on('click', function(e) {

        e.preventDefault();

        const variable = $(this).closest('.variable-item').data('variable');

        

        if (tinymce.get('email-content-editor')) {

            tinymce.get('email-content-editor').execCommand('mceInsertContent', false, variable);

        } else {

            $('#email-content-editor').val($('#email-content-editor').val() + variable);

        }

    });

    

    /* ============================================
       PREVIEW EDITOR DRAG DROP
       ============================================ */

    const $previewEditor = $('#email-preview-editable');

    

    $previewEditor.on('dragover', function(e) {

        e.preventDefault();

        $(this).css('background-color', '#f0f8ff');

    }).on('dragleave', function() {

        $(this).css('background-color', '');

    }).on('drop', function(e) {

        e.preventDefault();

        $(this).css('background-color', '');

        

        const variable = e.originalEvent.dataTransfer.getData('text/plain');

        if (variable) {

            $(this).append(' ' + variable);

            updatePreview();

        }

    });

    

    /* ============================================
       INITIALIZATION
       ============================================ */

    // Load preview after DOM is ready
    setTimeout(function() {
        loadPreview();
    }, 500);

    

    // Auto-update on content changes
    setTimeout(function() {
        var editor = tinymce.get('email-content-editor');
        if (editor) {
            editor.on('change', function() {
                updatePreview();
            });
        } else {
            $('#email-content-editor').on('change keyup', function() {
                updatePreview();
            });
        }
    }, 500);

    

    // Input changes trigger preview update
    $('#email-subject, #from-name, #from-email, #email-logo, .color-picker').on('change keyup', function() {

        updatePreview();

    });

});

</script>
