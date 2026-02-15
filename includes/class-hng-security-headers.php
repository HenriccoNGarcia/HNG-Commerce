<?php
/**
 * Security Headers
 * 
 * Adiciona headers de segurança para proteger contra ataques comuns
 * 
 * @package HNG_Commerce
 * @since 1.2.13
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adicionar security headers em todas as respostas
 */
add_action('send_headers', 'hng_add_security_headers');

function hng_add_security_headers() {
    // Prevenir clickjacking
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevenir MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy (restrict features)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // Content Security Policy (gradual implementation)
        // Iniciar com report-only para não quebrar funcionalidades existentes
        $csp_directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com https://js.stripe.com", // unsafe-inline/eval por enquanto (WordPress/plugins)
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https: http:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self' https://api.hngdesenvolvimentos.com.br https://*.asaas.com https://*.mercadopago.com",
            "frame-src 'self' https://www.google.com https://js.stripe.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests"
        ];
        
        // Aplicar CSP apenas em produção (modo report-only em dev)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            header('Content-Security-Policy-Report-Only: ' . implode('; ', $csp_directives));
        } else {
            // Em produção, começar com report-only também para monitorar violações
            // Depois de validar, trocar para Content-Security-Policy (enforcement)
            header('Content-Security-Policy-Report-Only: ' . implode('; ', $csp_directives));
        }
        
        // HSTS (HTTP Strict Transport Security) - apenas em HTTPS
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
}

/**
 * Remover informações sensíveis de headers
 */
add_filter('wp_headers', 'hng_remove_sensitive_headers');

function hng_remove_sensitive_headers($headers) {
    // Remover versão do WordPress
    unset($headers['X-Powered-By']);
    
    return $headers;
}

/**
 * Remover versão do WordPress de meta tags e scripts
 */
remove_action('wp_head', 'wp_generator');

add_filter('the_generator', '__return_empty_string');

/**
 * Desabilitar XML-RPC se não for usado
 * XML-RPC é frequentemente explorado em ataques de brute force
 */
if (apply_filters('hng_disable_xmlrpc', true)) {
    add_filter('xmlrpc_enabled', '__return_false');
}

/**
 * Desabilitar file editing no admin
 * Prevenir que atacantes editem arquivos PHP via admin
 */
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

/**
 * Limite de login attempts
 * Proteger contra brute force em wp-login.php
 */
add_action('wp_login_failed', 'hng_log_failed_login');
add_filter('authenticate', 'hng_check_login_attempts', 30, 3);

function hng_log_failed_login($username) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = 'hng_login_attempts_' . md5($ip);
    
    $attempts = (int) get_transient($key);
    set_transient($key, $attempts + 1, 900); // 15 minutos
    
    if ($attempts > 5) {
        error_log(sprintf(
            'HNG Security: Login brute force detected - IP: %s, Username: %s, Attempts: %d',
            $ip,
            sanitize_user($username),
            $attempts
        ));
    }
}

function hng_check_login_attempts($user, $username, $password) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = 'hng_login_attempts_' . md5($ip);
    
    $attempts = (int) get_transient($key);
    
    // Bloquear após 10 tentativas
    if ($attempts >= 10) {
        /* translators: %d: number of minutes to wait */
        return new WP_Error(
            'too_many_attempts',
            sprintf(
                /* translators: %d: number of minutes */
                __('<strong>ERRO</strong>: Muitas tentativas de login. Tente novamente em %d minutos.', 'hng-commerce'),
                ceil(900 / 60)
            )
        );
    }
    
    return $user;
}

/**
 * Sanitizar nome de arquivo em uploads
 * Prevenir uploads maliciosos com nomes especiais
 */
add_filter('sanitize_file_name', 'hng_sanitize_filename', 10, 1);

function hng_sanitize_filename($filename) {
    // Remover caracteres especiais e scripts
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Remover múltiplas extensões (double extension attack)
    $parts = explode('.', $filename);
    if (count($parts) > 2) {
        $ext = array_pop($parts);
        $filename = implode('_', $parts) . '.' . $ext;
    }
    
    return $filename;
}

/**
 * Validar extensões de arquivo em upload
 * Bloquear extensões perigosas
 */
add_filter('upload_mimes', 'hng_restrict_upload_mimes');

function hng_restrict_upload_mimes($mimes) {
    // Remover tipos perigosos
    unset($mimes['exe']);
    unset($mimes['com']);
    unset($mimes['bat']);
    unset($mimes['cmd']);
    unset($mimes['pif']);
    unset($mimes['scr']);
    unset($mimes['vbs']);
    unset($mimes['js']); // JavaScript puro (JSON é permitido)
    
    return $mimes;
}

/**
 * Adicionar validação extra em upload de arquivos
 */
add_filter('wp_handle_upload_prefilter', 'hng_validate_upload_security');

function hng_validate_upload_security($file) {
    // Verificar se é usuário admin
    if (!current_user_can('manage_options')) {
        // Não-admins: apenas imagens, PDFs e documentos
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        $filetype = wp_check_filetype($file['name']);
        
        if (!in_array($file['type'], $allowed_types)) {
            $file['error'] = __('Tipo de arquivo não permitido para seu nível de usuário.', 'hng-commerce');
        }
    }
    
    // Verificar tamanho (máx 10MB para não-admins)
    if (!current_user_can('manage_options') && $file['size'] > 10 * 1024 * 1024) {
        $file['error'] = __('Arquivo muito grande. Máximo: 10MB.', 'hng-commerce');
    }
    
    return $file;
}

/**
 * Proteger diretórios sensíveis com .htaccess
 */
add_action('admin_init', 'hng_protect_sensitive_directories');

function hng_protect_sensitive_directories() {
    $directories = [
        WP_CONTENT_DIR . '/uploads/hng-downloads',
        WP_CONTENT_DIR . '/plugins/_api-server/logs',
        WP_CONTENT_DIR . '/plugins/_api-server/cache',
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# HNG Commerce Security\nDeny from all\n";
            @file_put_contents($htaccess, $content);
        }
        
        // Adicionar index.php vazio
        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, '<?php // Silence is golden');
        }
    }
}

/**
 * Monitorar e bloquear requisições suspeitas
 */
add_action('init', 'hng_detect_suspicious_requests', 1);

function hng_detect_suspicious_requests() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    // Permitir webhooks Asaas (evita bloqueio por user agent)
    if (strpos($request_uri, '/wp-json/hng-commerce/v1/asaas/webhook') !== false) {
        return;
    }
    
    // Lista de user agents maliciosos conhecidos
    $bad_user_agents = [
        'libwww-perl',
        'wget',
        'python-requests',
        'curl',
        'java',
        'nmap',
        'nikto',
        'sqlmap',
        'masscan'
    ];
    
    foreach ($bad_user_agents as $bad_agent) {
        if (stripos($user_agent, $bad_agent) !== false) {
            // Permitir apenas para admins logados (para testes)
            if (!is_user_logged_in() || !current_user_can('manage_options')) {
                error_log(sprintf(
                    'HNG Security: Suspicious user agent blocked - UA: %s, URI: %s, IP: %s',
                    substr($user_agent, 0, 100),
                    $request_uri,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ));
                
                wp_die(
                    'Forbidden',
                    'Access Denied',
                    ['response' => 403]
                );
            }
        }
    }
    
    // Detectar tentativas de SQL injection na URL
    $sql_patterns = [
        'union.*select',
        'concat.*\(',
        'information_schema',
        'load_file',
        'into.*outfile',
        '@@version'
    ];
    
    foreach ($sql_patterns as $pattern) {
        if (preg_match('/' . $pattern . '/i', $request_uri)) {
            error_log(sprintf(
                'HNG Security: SQL injection attempt detected - URI: %s, IP: %s',
                $request_uri,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ));
            
            wp_die(
                'Forbidden',
                'Access Denied',
                ['response' => 403]
            );
        }
    }
}

/**
 * Adicionar nonce a todos os formulários HTML do plugin
 */
add_filter('hng_form_html', 'hng_add_nonce_to_forms', 10, 2);

function hng_add_nonce_to_forms($html, $form_id) {
    if (strpos($html, 'wp_nonce_field') === false) {
        $nonce = wp_nonce_field('hng_' . $form_id, '_wpnonce', true, false);
        $html = str_replace('<form', $nonce . '<form', $html);
    }
    return $html;
}

/**
 * Log de atividades de segurança críticas
 */
function hng_security_log($event, $severity = 'info', $data = []) {
    $log_entry = sprintf(
        '[%s] %s | %s | IP: %s | Data: %s',
        gmdate('Y-m-d H:i:s'),
        strtoupper($severity),
        $event,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        json_encode($data)
    );
    
    error_log($log_entry);
    
    // Para eventos críticos, também salvar em arquivo dedicado
    if ($severity === 'critical') {
        $log_file = WP_CONTENT_DIR . '/hng-security-critical.log';
        @file_put_contents($log_file, $log_entry . "\n", FILE_APPEND);
    }
}

/**
 * Adicionar meta box de auditoria de segurança no admin
 */
add_action('admin_init', 'hng_register_security_dashboard');

function hng_register_security_dashboard() {
    if (current_user_can('manage_options')) {
        add_action('admin_notices', 'hng_security_dashboard_widget');
    }
}

function hng_security_dashboard_widget() {
    // Verificar configurações de segurança
    $issues = [];
    
    // 1. HTTPS habilitado?
    if (!is_ssl()) {
        $issues[] = 'HTTPS não está habilitado (recomendado para segurança)';
    }
    
    // 2. Debug mode em produção?
    if (defined('WP_DEBUG') && WP_DEBUG && !defined('WP_DEBUG_DISPLAY')) {
        $issues[] = 'WP_DEBUG habilitado sem WP_DEBUG_DISPLAY=false';
    }
    
    // 3. File editing habilitado?
    if (!defined('DISALLOW_FILE_EDIT') || !DISALLOW_FILE_EDIT) {
        $issues[] = 'Edição de arquivos no admin está habilitada (risco de segurança)';
    }
    
    if (!empty($issues)) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>HNG Commerce - Alertas de Segurança:</strong></p>';
        echo '<ul>';
        foreach ($issues as $issue) {
            echo '<li>' . esc_html($issue) . '</li>';
        }
        echo '</ul>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=hng-settings&tab=security')) . '">Configurar Segurança</a></p>';
        echo '</div>';
    }
}
