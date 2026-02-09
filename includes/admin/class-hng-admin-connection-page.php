<?php
/**
 * Admin Page: API Connection & Security Dashboard
 * 
 * Permite conectar/desconectar da API, ver status de domínio,
 * visualizar logs de auditoria, e configurar webhooks
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Admin_Connection_Page {
    
    /**
     * Render a página
     */
    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão', 'hng-commerce'));
        }
        
        // Processar formulário
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['_wpnonce'])) {
                if (!wp_verify_nonce($_POST['_wpnonce'], 'hng_connection_nonce')) {
                    wp_die(esc_html__('Nonce inválido', 'hng-commerce'));
                }
                
                self::handle_form_submission($_POST);
            }
        }
        
        $connection_status = self::get_connection_status();
        $domain_status = class_exists('HNG_Domain_Heartbeat') 
            ? HNG_Domain_Heartbeat::get_status() 
            : null;
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('HNG Commerce - Conexão com API', 'hng-commerce'); ?></h1>
            
            <?php self::render_connection_card($connection_status); ?>
            <?php if ($domain_status) self::render_domain_card($domain_status); ?>
            <?php self::render_audit_logs_card(); ?>
            <?php self::render_webhook_config_card(); ?>
        </div>
        <?php
    }
    
    /**
     * Card de status de conexão
     */
    private static function render_connection_card($status) {
        $api_key = get_option('hng_api_key', '');
        $api_url = get_option('hng_api_url', '');
        $last_heartbeat = get_option('hng_api_heartbeat_last', 0);
        
        ?>
        <div class="card">
            <h2><?php esc_html_e('Status de Conexão', 'hng-commerce'); ?></h2>
            
            <div style="margin: 20px 0;">
                <?php if ($status['connected']): ?>
                    <p style="color: green; font-weight: bold;">
                        ✅ <?php esc_html_e('Conectado', 'hng-commerce'); ?>
                    </p>
                <?php else: ?>
                    <p style="color: red; font-weight: bold;">
                        ❌ <?php esc_html_e('Desconectado', 'hng-commerce'); ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($last_heartbeat > 0): ?>
                    <p>
                        <?php
                        printf(
                            /* translators: %s: date and time of last heartbeat */
                            esc_html__('Último heartbeat: %s', 'hng-commerce'),
                            esc_html(gmdate('d/m/Y H:i:s', $last_heartbeat))
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('hng_connection_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hng_api_url">
                                <?php esc_html_e('URL da API', 'hng-commerce'); ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="url" 
                                id="hng_api_url" 
                                name="hng_api_url" 
                                value="<?php echo esc_attr($api_url); ?>"
                                placeholder="https://api.example.com"
                                class="regular-text"
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hng_api_key">
                                <?php esc_html_e('Chave de API', 'hng-commerce'); ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="password" 
                                id="hng_api_key" 
                                name="hng_api_key" 
                                value="<?php echo esc_attr($api_key); ?>"
                                placeholder="cole a chave aqui"
                                class="regular-text"
                            />
                            <p class="description">
                                <?php esc_html_e('A chave será salva com segurança no banco de dados. Use senhas fortes e únicas.', 'hng-commerce'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button 
                        type="submit" 
                        name="action" 
                        value="test_connection" 
                        class="button button-secondary"
                    >
                        <?php esc_html_e('Testar Conexão', 'hng-commerce'); ?>
                    </button>
                    
                    <button 
                        type="submit" 
                        name="action" 
                        value="save_connection" 
                        class="button button-primary"
                    >
                        <?php esc_html_e('Salvar Conexão', 'hng-commerce'); ?>
                    </button>
                    
                    <?php if ($status['connected']): ?>
                        <button 
                            type="submit" 
                            name="action" 
                            value="disconnect" 
                            class="button button-link-delete"
                        >
                            <?php esc_html_e('Desconectar', 'hng-commerce'); ?>
                        </button>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Card de status de domínio
     */
    private static function render_domain_card($status) {
        ?>
        <div class="card">
            <h2><?php esc_html_e('Binding de Domínio', 'hng-commerce'); ?></h2>
            
            <div style="margin: 20px 0;">
                <?php if ($status['is_valid']): ?>
                    <p style="color: green; font-weight: bold;">
                        ✅ <?php esc_html_e('Domínio válido', 'hng-commerce'); ?>
                    </p>
                <?php else: ?>
                    <p style="color: red; font-weight: bold;">
                        ⚠️ <?php esc_html_e('AVISO: Domínio não corresponde!', 'hng-commerce'); ?>
                    </p>
                <?php endif; ?>
                
                <table>
                    <tr>
                        <td><?php esc_html_e('Domínio Registrado:', 'hng-commerce'); ?></td>
                        <td><code><?php echo esc_html($status['bound_domain']); ?></code></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Domínio Atual:', 'hng-commerce'); ?></td>
                        <td><code><?php echo esc_html($status['current_domain']); ?></code></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Tentativas de Mismatch:', 'hng-commerce'); ?></td>
                        <td><?php echo intval($status['mismatch_count']); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Último Check:', 'hng-commerce'); ?></td>
                        <td><?php echo esc_html($status['last_check']); ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if (!$status['is_valid']): ?>
                <form method="post">
                    <?php wp_nonce_field('hng_connection_nonce'); ?>
                    <p class="description">
                        <?php esc_html_e('Se você moveu o site para um novo domínio intencionalmente, clique em "Re-bind":', 'hng-commerce'); ?>
                    </p>
                    <button 
                        type="submit" 
                        name="action" 
                        value="rebind_domain" 
                        class="button button-primary"
                    >
                        <?php esc_html_e('Re-bind Domínio Atual', 'hng-commerce'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Card de logs de auditoria
     */
    private static function render_audit_logs_card() {
        if (!class_exists('HNG_Audit_Log')) {
            return;
        }
        
        $logs = HNG_Audit_Log::query(['category' => 'security'], 50);
        $stats = HNG_Audit_Log::get_stats(['category' => 'security']);
        
        ?>
        <div class="card">
            <h2><?php esc_html_e('Logs de Auditoria (Últimos 50)', 'hng-commerce'); ?></h2>
            
            <div style="margin: 20px 0;">
                <p>
                    <strong>Total de eventos:</strong> <?php echo intval($stats['total']); ?> |
                    <strong>Avisos:</strong> <?php echo intval($stats['by_severity'][1]); ?> |
                    <strong>Críticos:</strong> <?php echo intval($stats['by_severity'][2]); ?>
                </p>
            </div>
            
            <?php if (empty($logs)): ?>
                <p><?php esc_html_e('Nenhum log disponível', 'hng-commerce'); ?></p>
            <?php else: ?>
                <table class="wp-list-table fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Data', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Evento', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Categoria', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Severidade', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Usuário', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('IP', 'hng-commerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php 
                            $severity_label = ['ℹ️ Info', '⚠️ Warning', '🔴 Critical'][$log->severity] ?? 'Unknown';
                            $user = get_user_by('ID', $log->user_id);
                            ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($log->timestamp))); ?></td>
                                <td><?php echo esc_html($log->event); ?></td>
                                <td><?php echo esc_html($log->category); ?></td>
                                <td><?php echo wp_kses_post($severity_label); ?></td>
                                <td><?php echo $user ? esc_html($user->user_login) : esc_html__('Sistema', 'hng-commerce'); ?></td>
                                <td><code><?php echo esc_html($log->user_ip); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Card de configuração de webhooks
     */
    private static function render_webhook_config_card() {
        ?>
        <div class="card">
            <h2><?php esc_html_e('Configuração de Webhooks', 'hng-commerce'); ?></h2>
            
            <p><?php esc_html_e('Os webhooks devem ser configurados em seu servidor de API para as URLs abaixo:', 'hng-commerce'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Webhook URL Padrão', 'hng-commerce'); ?></th>
                    <td>
                        <code>
                            <?php echo esc_html(site_url('/wp-json/hng-commerce/v1/webhook')); ?>
                        </code>
                        <button class="button" onclick="copyToClipboard(this)">
                            <?php esc_html_e('Copiar', 'hng-commerce'); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Header de Autenticação', 'hng-commerce'); ?></th>
                    <td>
                        <code>X-HNG-Webhook-Secret: <?php echo esc_html(wp_hash('hng-webhook-secret')); ?></code>
                    </td>
                </tr>
            </table>
            
            <p class="description">
                <?php esc_html_e('Veja WEBHOOKS_SETUP.md para instruções detalhadas de configuração por gateway.', 'hng-commerce'); ?>
            </p>
        </div>
        
        <script>
        function copyToClipboard(button) {
            const text = button.previousElementSibling.textContent;
            navigator.clipboard.writeText(text);
            const originalText = button.textContent;
            button.textContent = '<?php esc_html_e('Copiado!', 'hng-commerce'); ?>';
            setTimeout(() => {
                button.textContent = originalText;
            }, 2000);
        }
        </script>
        <?php
    }
    
    /**
     * Processar submissão do formulário
     */
    private static function handle_form_submission($post_data) {
        $action = isset($post_data['action']) ? sanitize_text_field($post_data['action']) : '';
        
        switch ($action) {
            case 'save_connection':
                self::handle_save_connection($post_data);
                break;
            
            case 'test_connection':
                self::handle_test_connection($post_data);
                break;
            
            case 'disconnect':
                self::handle_disconnect();
                break;
            
            case 'rebind_domain':
                self::handle_rebind_domain();
                break;
        }
    }
    
    /**
     * Salvar conexão
     */
    private static function handle_save_connection($post_data) {
        $api_url = isset($post_data['hng_api_url']) 
            ? esc_url_raw($post_data['hng_api_url']) 
            : '';
        $api_key = isset($post_data['hng_api_key']) 
            ? sanitize_text_field($post_data['hng_api_key']) 
            : '';
        
        if (empty($api_url) || empty($api_key)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' 
                    . esc_html__('URL da API e Chave são obrigatórias', 'hng-commerce') 
                    . '</p></div>';
            });
            return;
        }
        
        update_option('hng_api_url', $api_url);
        update_option('hng_api_key', $api_key);
        update_option('hng_connected', 1);
        
        HNG_Audit_Log::log(
            'API connection saved',
            'security',
            ['url' => $api_url],
            0
        );
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' 
                . esc_html__('✅ Conexão salva com sucesso', 'hng-commerce') 
                . '</p></div>';
        });
    }
    
    /**
     * Testar conexão
     */
    private static function handle_test_connection($post_data) {
        $api_url = isset($post_data['hng_api_url']) 
            ? esc_url_raw($post_data['hng_api_url']) 
            : get_option('hng_api_url', '');
        
        if (empty($api_url)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' 
                    . esc_html__('URL da API não configurada', 'hng-commerce') 
                    . '</p></div>';
            });
            return;
        }
        
        $response = wp_remote_get(
            trailingslashit($api_url) . 'status',
            [
                'timeout' => 10,
                'sslverify' => true,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );
        
        if (is_wp_error($response)) {
            HNG_Audit_Log::log(
                'API connection test failed',
                'security',
                ['error' => $response->get_error_message()],
                1
            );
            
            add_action('admin_notices', function() use ($response) {
                echo '<div class="notice notice-error"><p>' 
                    . esc_html__('❌ Falha ao conectar: ', 'hng-commerce') 
                    . esc_html($response->get_error_message())
                    . '</p></div>';
            });
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                update_option('hng_api_heartbeat_last', time());
                
                HNG_Audit_Log::log(
                    'API connection test success',
                    'security',
                    [],
                    0
                );
                
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>' 
                        . esc_html__('✅ Conexão testada com sucesso!', 'hng-commerce') 
                        . '</p></div>';
                });
            } else {
                HNG_Audit_Log::log(
                    'API connection test returned error',
                    'security',
                    ['http_code' => $code],
                    1
                );
                
                add_action('admin_notices', function() use ($code) {
                    printf(
                        '<div class="notice notice-error"><p>%s (HTTP %d)</p></div>',
                        esc_html__('❌ API retornou erro: ', 'hng-commerce'),
                        intval($code)
                    );
                });
            }
        }
    }
    
    /**
     * Desconectar
     */
    private static function handle_disconnect() {
        delete_option('hng_api_key');
        delete_option('hng_connected');
        
        HNG_Audit_Log::log(
            'API disconnected',
            'security',
            [],
            0
        );
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>' 
                . esc_html__('⚠️ Desconectado da API', 'hng-commerce') 
                . '</p></div>';
        });
    }
    
    /**
     * Re-bind domínio
     */
    private static function handle_rebind_domain() {
        if (class_exists('HNG_Domain_Heartbeat')) {
            HNG_Domain_Heartbeat::rebind_domain();
            
            HNG_Audit_Log::log(
                'Domain rebound',
                'security',
                ['domain' => HNG_Domain_Heartbeat::get_current_domain()],
                0
            );
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' 
                    . esc_html__('✅ Domínio re-vinculado com sucesso', 'hng-commerce') 
                    . '</p></div>';
            });
        }
    }
    
    /**
     * Obter status de conexão
     */
    private static function get_connection_status() {
        return [
            'connected' => (bool)get_option('hng_connected', false),
            'api_url' => get_option('hng_api_url', ''),
            'last_heartbeat' => (int)get_option('hng_api_heartbeat_last', 0),
        ];
    }
}

// Nota: O registro do menu é feito através de class-hng-admin.php no método add_admin_menu()
// Isso garante que o menu seja registrado no momento correto do hook admin_menu
