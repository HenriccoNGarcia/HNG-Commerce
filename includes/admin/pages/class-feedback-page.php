<?php
/**
 * Feedback Page - Elogios, Críticas, Correções e Sugestões
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Feedback_Page {
    
    public static function render() {        // Enqueue scripts
        wp_enqueue_script(
            'hng-admin-feedback',
            HNG_COMMERCE_URL . 'assets/js/admin-feedback.js',
            ['jquery'],
            HNG_COMMERCE_VERSION,
            true
        );
        
        wp_localize_script('hng-admin-feedback', 'hngFeedbackPage', [
            'i18n' => [
                'fileTooLarge' => __('Arquivo muito grande! Tamanho máximo: 5MB', 'hng-commerce'),
            ]
        ]);
                // Processar envio do formulá¡Â¡rio
        if (isset($_POST['hng_feedback_submit']) && check_admin_referer('hng_feedback_form', 'hng_feedback_nonce')) {
            self::process_feedback();
        }
        
        ?>
        <div class="wrap hng-admin-wrap">
            <h1 class="hng-page-title">
                <span class="dashicons dashicons-heart"></span>
                <?php esc_html_e('Feedback do Sistema', 'hng-commerce'); ?>
            </h1>
            
            <p class="description">
                <?php esc_html_e('Sua opinião é muito importante! Envie elogios, críticas, reporte problemas ou sugira melhorias.', 'hng-commerce'); ?>
            </p>

            <div class="hng-card" style="max-width: 800px; margin-top: 20px;">
                <div class="hng-card-content">
                    <form method="post" enctype="multipart/form-data" id="hng-feedback-form">
                        <?php wp_nonce_field('hng_feedback_form', 'hng_feedback_nonce'); ?>
                        
                        <!-- Tipo de Feedback -->
                        <div class="hng-form-group">
                            <label for="feedback_type" class="hng-label">
                                <?php esc_html_e('Tipo de Feedback', 'hng-commerce'); ?>
                                <span class="required">*</span>
                            </label>
                            <select name="feedback_type" id="feedback_type" class="hng-input" required>
                                <option value=""><?php esc_html_e('Selecione...', 'hng-commerce'); ?></option>
                                <option value="elogio"><?php esc_html_e('Elogio', 'hng-commerce'); ?></option>
                                <option value="critica"><?php esc_html_e('Crítica', 'hng-commerce'); ?></option>
                                <option value="correcao"><?php esc_html_e('Correção/Bug', 'hng-commerce'); ?></option>
                                <option value="sugestao"><?php esc_html_e('Sugestão de Melhoria', 'hng-commerce'); ?></option>
                            </select>
                        </div>

                        <!-- Título -->
                        <div class="hng-form-group">
                            <label for="feedback_title" class="hng-label">
                                <?php esc_html_e('Título', 'hng-commerce'); ?>
                                <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="feedback_title" 
                                id="feedback_title" 
                                class="hng-input" 
                                placeholder="<?php esc_attr_e('Resuma em poucas palavras', 'hng-commerce'); ?>"
                                required
                                maxlength="100"
                            >
                        </div>

                        <!-- Subtítulo -->
                        <div class="hng-form-group">
                            <label for="feedback_subtitle" class="hng-label">
                                <?php esc_html_e('Subtítulo', 'hng-commerce'); ?>
                            </label>
                            <input 
                                type="text" 
                                name="feedback_subtitle" 
                                id="feedback_subtitle" 
                                class="hng-input" 
                                placeholder="<?php esc_attr_e('Informações adicionais (opcional)', 'hng-commerce'); ?>"
                                maxlength="150"
                            >
                        </div>

                        <!-- Texto -->
                        <div class="hng-form-group">
                            <label for="feedback_text" class="hng-label">
                                <?php esc_html_e('Descrição Detalhada', 'hng-commerce'); ?>
                                <span class="required">*</span>
                            </label>
                            <textarea 
                                name="feedback_text" 
                                id="feedback_text" 
                                class="hng-input" 
                                rows="8"
                                placeholder="<?php esc_attr_e('Descreva com detalhes...', 'hng-commerce'); ?>"
                                required
                            ></textarea>
                            <p class="description">
                                <?php esc_html_e('Para correções de bugs, descreva os passos para reproduzir o problema.', 'hng-commerce'); ?>
                            </p>
                        </div>

                        <!-- Print/Screenshot -->
                        <div class="hng-form-group">
                            <label for="feedback_screenshot" class="hng-label">
                                <?php esc_html_e('Screenshot/Print', 'hng-commerce'); ?>
                                <span id="screenshot-required" style="display:none;" class="required">*</span>
                            </label>
                            <input 
                                type="file" 
                                name="feedback_screenshot" 
                                id="feedback_screenshot" 
                                accept="image/*,.pdf"
                                class="hng-input"
                            >
                            <p class="description">
                                <?php esc_html_e('Formatos aceitos: JPG, PNG, GIF, PDF. Máximo: 5MB', 'hng-commerce'); ?>
                                <br>
                                <strong id="screenshot-note" style="display:none; color: #d63638;">
                                    <?php esc_html_e('Screenshot obrigatório para reportar correções/bugs', 'hng-commerce'); ?>
                                </strong>
                            </p>
                        </div>

                        <!-- Informações do Sistema -->
                        <div class="hng-form-group">
                            <label class="hng-label">
                                <?php esc_html_e('Informações do Sistema', 'hng-commerce'); ?>
                            </label>
                            <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; font-size: 12px; color: #646970;">
                                <strong><?php esc_html_e('Essas informações serão incluídas automaticamente:', 'hng-commerce'); ?></strong><br>
                                • WordPress: <?php echo esc_html(get_bloginfo('version')); ?><br>
                                • PHP: <?php echo esc_html(PHP_VERSION); ?><br>
                                • HNG Commerce: <?php echo esc_html(defined('HNG_COMMERCE_VERSION') ? HNG_COMMERCE_VERSION : '1.0.0'); ?><br>
                                • URL: <?php echo esc_url(home_url()); ?>
                            </div>
                        </div>

                        <!-- Botáµes -->
                        <div class="hng-form-group" style="margin-top: 30px;">
                            <button type="submit" name="hng_feedback_submit" class="button button-primary button-large">
                                <span class="dashicons dashicons-email"></span>
                                <?php esc_html_e('Enviar Feedback', 'hng-commerce'); ?>
                            </button>
                            <button type="reset" class="button button-secondary button-large" style="margin-left: 10px;">
                                <?php esc_html_e('Limpar Formulário', 'hng-commerce'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <style>
            .hng-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
            }
            .required {
                color: #d63638;
            }
            #hng-feedback-form textarea {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
        </style>
        <?php
    }

    private static function process_feedback() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render() method via check_admin_referer('hng_feedback_form')
        $post = wp_unslash($_POST);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render() method
        $type = isset($post['feedback_type']) ? sanitize_text_field($post['feedback_type']) : '';
        $title = isset($post['feedback_title']) ? sanitize_text_field($post['feedback_title']) : '';
        $subtitle = isset($post['feedback_subtitle']) ? sanitize_text_field($post['feedback_subtitle']) : '';
        $text = isset($post['feedback_text']) ? sanitize_textarea_field($post['feedback_text']) : '';
        
        // Validar tipo de feedback
        $valid_types = ['elogio', 'critica', 'correcao', 'sugestao'];
        if (!in_array($type, $valid_types)) {
            self::show_notice('error', __('Tipo de feedback inválido.', 'hng-commerce'));
            return;
        }

        // Validar screenshot obrigatá¡Â³rio para correá§áµes
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render() method via check_admin_referer()
        if ($type === 'correcao' && (!isset($_FILES['feedback_screenshot']['name']) || empty($_FILES['feedback_screenshot']['name']))) {
            self::show_notice('error', __('Screenshot é obrigatório para reportar correções/bugs.', 'hng-commerce'));
            return;
        }

        // Processar upload de arquivo
        $attachment_id = null;
        $attachment_url = '';
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in render() method via check_admin_referer()
        if (isset($_FILES['feedback_screenshot']) && !empty($_FILES['feedback_screenshot']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $file = $_FILES['feedback_screenshot'];

            // Validações adicionais de segurança
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                self::show_notice('error', __('Upload inválido. Tente novamente.', 'hng-commerce'));
                return;
            }

            if (!isset($file['size']) || (int) $file['size'] > 5 * 1024 * 1024) { // 5MB
                self::show_notice('error', __('Arquivo muito grande! Tamanho máximo: 5MB', 'hng-commerce'));
                return;
            }

            $allowed_mimes = array(
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'gif'      => 'image/gif',
                'pdf'      => 'application/pdf',
            );

            // Executa upload com whitelisting de MIME types
            $upload = wp_handle_upload($file, array(
                'test_form' => false,
                'mimes'     => $allowed_mimes,
            ));

            if (isset($upload['error'])) {
                self::show_notice('error', $upload['error']);
                return;
            }

            $attachment_url = $upload['url'];
        }

        // Preparar informações do sistema
        $system_info = array(
            'WordPress' => get_bloginfo('version'),
            'PHP' => PHP_VERSION,
            'Plugin' => defined('HNG_COMMERCE_VERSION') ? HNG_COMMERCE_VERSION : '1.0.0',
            'URL' => home_url(),
            'Tema' => wp_get_theme()->get('Name'),
            'Data/Hora' => current_time('mysql'),
        );

        // Montar email
        $type_labels = array(
            'elogio' => 'ELOGIO',
            'critica' => 'CRÍTICA',
            'correcao' => 'CORREÇÁO/BUG',
            'sugestao' => 'SUGESTÁO',
        );

        $subject = '[HNG Commerce] ' . $type_labels[$type] . ' - ' . $title;
        
        $message = "=== FEEDBACK DO SISTEMA HNG COMMERCE ===\n\n";
        $message .= "TIPO: " . $type_labels[$type] . "\n";
        $message .= "TÍTULO: " . $title . "\n";
        if (!empty($subtitle)) {
            $message .= "SUBTÍTULO: " . $subtitle . "\n";
        }
        $message .= "\n--- DESCRIÇÁO ---\n";
        $message .= $text . "\n\n";
        
        if (!empty($attachment_url)) {
            $message .= "--- SCREENSHOT ---\n";
            $message .= $attachment_url . "\n\n";
        }
        
        $message .= "--- INFORMAÇÕES DO SISTEMA ---\n";
        foreach ($system_info as $key => $value) {
            $message .= $key . ": " . $value . "\n";
        }
        
        $message .= "\n--- USUÁRIO ---\n";
        $current_user = wp_get_current_user();
        $message .= "Nome: " . $current_user->display_name . "\n";
        $message .= "Email: " . $current_user->user_email . "\n";
        $message .= "Login: " . $current_user->user_login . "\n";

        // Enviar email
        $to = 'henricco@hngdesenvolvimentos.com.br';
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: HNG Commerce <wordpress@' . $host . '>',
            'Reply-To: ' . $current_user->user_email,
        );

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            self::show_notice('success', __('Feedback enviado com sucesso! Obrigado pela sua contribuição.', 'hng-commerce'));
        } else {
            self::show_notice('error', __('Erro ao enviar feedback. Por favor, tente novamente.', 'hng-commerce'));
        }
    }

    private static function show_notice($type, $message) {
        $class = $type === 'success' ? 'notice-success' : 'notice-error';
        add_action('admin_notices', function() use ($class, $message) {
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
    }
}
