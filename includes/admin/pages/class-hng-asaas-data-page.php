<?php
/**
 * HNG Commerce - Asaas Data Page
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Asaas_Data_Page {

    /**
     * Render the page
     */
    public static function render() {
        $purge_notice = self::maybe_handle_purge_request();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for tab navigation, no data modification
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'sync';
        ?>
        <div class="wrap hng-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-database" style="font-size: 28px; width: 28px; height: 28px; margin-right: 10px; vertical-align: middle;"></span>
                <?php esc_html_e('Dados do Asaas', 'hng-commerce'); ?>
            </h1>
            <hr class="wp-header-end">

            <nav class="nav-tab-wrapper">
                <a href="?page=hng-asaas-data&tab=sync" class="nav-tab <?php echo esc_attr( $active_tab === 'sync' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Sincronização', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-asaas-data&tab=webhooks" class="nav-tab <?php echo esc_attr( $active_tab === 'webhooks' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Webhooks', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-asaas-data&tab=customers" class="nav-tab <?php echo esc_attr( $active_tab === 'customers' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Clientes', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-asaas-data&tab=reports" class="nav-tab <?php echo esc_attr( $active_tab === 'reports' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Relatórios', 'hng-commerce'); ?>
                </a>
            </nav>

            <div class="hng-tab-content" style="margin-top: 20px;">
                <?php if ($purge_notice) : ?>
                    <div class="notice notice-<?php echo esc_attr($purge_notice['type']); ?> is-dismissible">
                        <p><?php echo esc_html($purge_notice['message']); ?></p>
                    </div>
                <?php endif; ?>
                <?php
                switch ($active_tab) {
                    case 'sync':
                        self::render_sync_tab();
                        break;
                    case 'webhooks':
                        self::render_webhooks_tab();
                        break;
                    case 'customers':
                        self::render_customers_tab();
                        break;
                    case 'reports':
                        self::render_reports_tab();
                        break;
                    default:
                        self::render_sync_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Sync Tab
     */
    private static function render_sync_tab() {
        $last_sync_subs = get_option('hng_asaas_last_sync_subscriptions', __('Nunca', 'hng-commerce'));
        $last_sync_customers = get_option('hng_asaas_last_sync_customers', __('Nunca', 'hng-commerce'));
        $last_sync_payments = get_option('hng_asaas_last_sync_payments', __('Nunca', 'hng-commerce'));
        
        if ($last_sync_subs !== __('Nunca', 'hng-commerce')) {
            $last_sync_subs = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_subs));
        }
        
        if ($last_sync_customers !== __('Nunca', 'hng-commerce')) {
            $last_sync_customers = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_customers));
        }
        
        if ($last_sync_payments !== __('Nunca', 'hng-commerce')) {
            $last_sync_payments = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_payments));
        }
        ?>
        <div class="hng-card">
            <h2><?php esc_html_e('Sincronização Manual', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Force a sincronização de dados entre o Asaas e sua loja. Isso pode levar alguns minutos.', 'hng-commerce'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Assinaturas', 'hng-commerce'); ?></th>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data inicial', 'hng-commerce'); ?></label>
                                <input type="date" id="hng-sync-subscriptions-start" />
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data final', 'hng-commerce'); ?></label>
                                <input type="date" id="hng-sync-subscriptions-end" />
                            </div>
                            <button type="button" class="button button-secondary" id="hng-sync-subscriptions">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Sincronizar Assinaturas', 'hng-commerce'); ?>
                            </button>
                        </div>
                        <p class="description" style="margin-top: 8px;">
                            <?php
                            printf(
                                wp_kses(
                                    /* translators: %s: last sync date */
                                    __('Última sincronização: <strong>%s</strong>. Deixe as datas em branco para sincronizar todas.', 'hng-commerce'),
                                    array('strong' => array())
                                ),
                                esc_html($last_sync_subs)
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Clientes', 'hng-commerce'); ?></th>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data inicial', 'hng-commerce'); ?></label>
                                <input type="date" id="hng-sync-customers-start" />
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data final', 'hng-commerce'); ?></label>
                                <input type="date" id="hng-sync-customers-end" />
                            </div>
                            <button type="button" class="button button-secondary" id="hng-sync-customers">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Sincronizar Clientes', 'hng-commerce'); ?>
                            </button>
                        </div>
                        <p class="description" style="margin-top: 8px;">
                            <?php
                            printf(
                                wp_kses(
                                    /* translators: %s: last sync date */
                                    __('Última sincronização: <strong>%s</strong>. Deixe as datas em branco para sincronizar todos.', 'hng-commerce'),
                                    array('strong' => array())
                                ),
                                esc_html($last_sync_customers)
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Faturamento (Pagamentos)', 'hng-commerce'); ?></th>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data inicial', 'hng-commerce'); ?></label>
                                <input type="date" id="hng-sync-payments-start" />
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data final', 'hng-commerce'); ?></label>
                                <input type="date" id="hng-sync-payments-end" />
                            </div>
                            <button type="button" class="button button-primary" id="hng-sync-payments">
                                <span class="dashicons dashicons-money-alt"></span>
                                <?php esc_html_e('Sincronizar Faturamento', 'hng-commerce'); ?>
                            </button>
                        </div>
                        <p class="description" style="margin-top: 8px;">
                            <?php
                            printf(
                                wp_kses(
                                    /* translators: %s: last sync date */
                                    __('Última sincronização: <strong>%s</strong>. Deixe as datas em branco para usar os últimos 30 dias.', 'hng-commerce'),
                                    array('strong' => array())
                                ),
                                esc_html($last_sync_payments)
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="hng-card" style="margin-top: 20px;">
            <h2><?php esc_html_e('Status da Conexão', 'hng-commerce'); ?></h2>
            <?php
            $api_key = get_option('hng_asaas_api_key');
            $sandbox = get_option('hng_asaas_sandbox');
            ?>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Ambiente', 'hng-commerce'); ?></th>
                    <td>
                        <?php if ($sandbox): ?>
                            <span class="hng-badge hng-badge-warning"><?php esc_html_e('Sandbox (Testes)', 'hng-commerce'); ?></span>
                        <?php else: ?>
                            <span class="hng-badge hng-badge-success"><?php esc_html_e('Produção', 'hng-commerce'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('API Key', 'hng-commerce'); ?></th>
                    <td>
                        <?php if ($api_key): ?>
                            <span class="dashicons dashicons-yes" style="color: green;"></span> <?php esc_html_e('Configurada', 'hng-commerce'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-no" style="color: red;"></span> <?php esc_html_e('Não configurada', 'hng-commerce'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <?php
        // Enqueue Asaas data scripts
        wp_enqueue_script(
            'hng-admin-asaas',
            HNG_COMMERCE_URL . 'assets/js/admin-asaas.js',
            array('jquery'),
            HNG_COMMERCE_VERSION,
            true
        );
        
        wp_localize_script('hng-admin-asaas', 'hngAsaasPage', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hng_asaas_sync_nonce'),
            'i18n' => array(
                'syncing' => __('Sincronizando...', 'hng-commerce'),
                'syncSuccess' => __('Sincronização concluída com sucesso!', 'hng-commerce'),
                'syncError' => __('Erro na sincronização:', 'hng-commerce'),
                'unknownError' => __('Erro desconhecido', 'hng-commerce'),
                'connectionError' => __('Erro de conexão.', 'hng-commerce'),
                'processed' => __('Processados', 'hng-commerce'),
                'created' => __('Criados', 'hng-commerce'),
                'updated' => __('Atualizados', 'hng-commerce'),
            ),
        ));
        ?>
        
        <style>
            .spin { animation: spin 2s infinite linear; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .hng-badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; color: #fff; }
            .hng-badge-warning { background: #f0b849; color: #333; }
            .hng-badge-success { background: #4caf50; }
        </style>
        <?php
        // Data purge card
        ?>
        <div class="hng-card" style="margin-top: 20px; border-color: #d63638;">
            <h2 style="color: #d63638; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Remover dados sincronizados do Asaas', 'hng-commerce'); ?>
            </h2>
            <p><?php esc_html_e('Use esta opção para remover dados que vieram do gateway (clientes, assinaturas e notas relacionadas).', 'hng-commerce'); ?></p>
            <form method="post">
                <?php wp_nonce_field('hng_asaas_purge_action', 'hng_asaas_purge_nonce'); ?>
                <input type="hidden" name="hng_asaas_purge_action" value="1" />
                <label style="display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px;">
                    <input type="checkbox" name="hard_delete" value="1" />
                    <span><?php esc_html_e('Apagar permanentemente (não poderá desfazer)', 'hng-commerce'); ?></span>
                </label>
                <div>
                    <button type="submit" class="button button-secondary" style="background: #d63638; border-color: #d63638; color: #fff;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Purgar dados do Asaas', 'hng-commerce'); ?>
                    </button>
                </div>
                <p class="description" style="margin-top: 10px;">
                    <?php esc_html_e('Se a integração avançada estiver ativa novamente, você poderá sincronizar outra vez. A opção permanente apagará os registros ao invés de apenas escondê-los.', 'hng-commerce'); ?>
                </p>
            </form>
        </div>
        <?php
    }

    private static function render_webhooks_tab() {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_asaas_webhook_log';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Tabela de logs não encontrada.', 'hng-commerce') . '</p></div>';
            return;
        }

        // Pagination
        $per_page = 20;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for pagination, no data modification
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table");
        $total_pages = ceil($total_items / $per_page);

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ));

        echo '<div class="hng-card">';
        echo '<h2>' . esc_html__('Log de Webhooks', 'hng-commerce') . '</h2>';
        
        // Webhook URL Info
        $webhook_url = get_rest_url(null, 'hng-commerce/v1/asaas/webhook');
        echo '<div class="notice notice-info inline"><p><strong>ℹ️ O que são Webhooks?</strong><br>';
        echo 'Webhooks são notificações automáticas que o Asaas envia para sua loja quando algo importante acontece (pagamento confirmado, assinatura cancelada, etc). Isso mantém seus dados sempre atualizados em tempo real.</p></div>';
        echo '<p><strong>' . esc_html__('Como configurar:', 'hng-commerce') . '</strong></p>';
        echo '<ol>';
        echo '<li>Acesse o <a href="https://www.asaas.com" target="_blank">painel do Asaas</a></li>';
        echo '<li>Vá em <strong>Configurações → Webhooks</strong></li>';
        echo '<li>Cole esta URL: <code style="background:#f0f0f0;padding:4px 8px;border-radius:4px;">' . esc_url($webhook_url) . '</code></li>';
        echo '<li>Ative os eventos: <strong>PAYMENT_CONFIRMED</strong>, <strong>PAYMENT_RECEIVED</strong>, <strong>SUBSCRIPTION_CREATED</strong>, <strong>SUBSCRIPTION_UPDATED</strong></li>';
        echo '</ol>';

        if (empty($logs)) {
            echo '<p>' . esc_html__('Nenhum evento recebido ainda.', 'hng-commerce') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('ID', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Evento', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Data', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Payload', 'hng-commerce') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($logs as $log) {
                $payload = json_decode($log->payload, true);
                $payload_preview = $payload ? '<pre style="max-height: 100px; overflow: auto;">' . esc_html(wp_json_encode($payload, JSON_PRETTY_PRINT)) . '</pre>' : esc_html__('Inválido', 'hng-commerce');
                
                echo '<tr>';
                echo '<td>' . esc_html($log->id) . '</td>';
                echo '<td><span class="hng-badge hng-badge-info">' . esc_html($log->event_type) . '</span></td>';
                echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))) . '</td>';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $payload_preview contains pre-escaped HTML with esc_html()
                echo '<td>' . $payload_preview . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            // Pagination Links
            if ($total_pages > 1) {
                echo '<div class="tablenav bottom"><div class="tablenav-pages">';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns safe HTML
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => esc_html__('&laquo;', 'hng-commerce'),
                    'next_text' => esc_html__('&raquo;', 'hng-commerce'),
                    'total' => $total_pages,
                    'current' => $page
                ]);
                echo '</div></div>';
            }
        }
        echo '</div>';
        
        // CSS for badge
        echo '<style>.hng-badge-info { background: #e5f5fa; color: #0086b3; border: 1px solid #b3e0ee; }</style>';
    }

    private static function render_customers_tab() {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_customers';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Tabela de clientes não encontrada.', 'hng-commerce') . '</p></div>';
            return;
        }

        // Pagination
        $per_page = 20;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for pagination, no data modification
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table WHERE asaas_customer_id IS NOT NULL");
        $total_pages = ceil($total_items / $per_page);

        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE asaas_customer_id IS NOT NULL ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ));

        echo '<div class="hng-card">';
        echo '<h2>' . esc_html__('Clientes Sincronizados', 'hng-commerce') . '</h2>';
        
        if (empty($customers)) {
            echo '<p>' . esc_html__('Nenhum cliente sincronizado ainda.', 'hng-commerce') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Nome', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Email', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('CPF/CNPJ', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Asaas ID', 'hng-commerce') . '</th>';
            echo '<th>' . esc_html__('Telefone', 'hng-commerce') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($customers as $customer) {
                echo '<tr>';
                echo '<td>' . esc_html($customer->first_name . ' ' . $customer->last_name) . '</td>';
                echo '<td>' . esc_html($customer->email) . '</td>';
                echo '<td>' . esc_html($customer->cpf_cnpj) . '</td>';
                echo '<td><code>' . esc_html($customer->asaas_customer_id) . '</code></td>';
                echo '<td>' . esc_html($customer->phone) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            // Pagination Links
            if ($total_pages > 1) {
                echo '<div class="tablenav bottom"><div class="tablenav-pages">';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns safe HTML
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => esc_html__('&laquo;', 'hng-commerce'),
                    'next_text' => esc_html__('&raquo;', 'hng-commerce'),
                    'total' => $total_pages,
                    'current' => $page
                ]);
                echo '</div></div>';
            }
        }
        echo '</div>';
    }

    private static function render_reports_tab() {
        if (!class_exists('HNG_Financial_Dashboard')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Classe de Dashboard não encontrada.', 'hng-commerce') . '</p></div>';
            return;
        }

        // Period selection (simple for now: last 30 days)
        $start = gmdate('Y-m-d 00:00:00', strtotime('-30 days'));
        $end = current_time('mysql');
        
        $metrics = HNG_Financial_Dashboard::get_asaas_metrics($start, $end);
        
        echo '<div class="hng-card">';
        echo '<h2>' . esc_html__("Relatório Financeiro Asaas (últimos 30 dias)", 'hng-commerce') . '</h2>';
        
        echo '<div class="hng-grid hng-grid-3" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px;">';
        
        // Revenue
        echo '<div class="hng-stat-box" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">';
        echo '<h3 style="margin-top: 0;">' . esc_html__('Receita Bruta', 'hng-commerce') . '</h3>';
        echo '<div style="font-size: 24px; font-weight: bold; color: #2271b1;">' . esc_html(hng_price($metrics['revenue'])) . '</div>';
        echo '</div>';

        // Net Revenue
        echo '<div class="hng-stat-box" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">';
        echo '<h3 style="margin-top: 0;">' . esc_html__("Receita Líquida (Estimada)", 'hng-commerce') . '</h3>';
        echo '<div style="font-size: 24px; font-weight: bold; color: #4caf50;">' . esc_html(hng_price($metrics['net_revenue'])) . '</div>';
        echo '</div>';

        // Count
        echo '<div class="hng-stat-box" style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">';
        echo '<h3 style="margin-top: 0;">' . esc_html__('Pagamentos Recebidos', 'hng-commerce') . '</h3>';
        echo '<div style="font-size: 24px; font-weight: bold; color: #333;">' . intval($metrics['count']) . '</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<p class="description" style="margin-top: 20px;">' . esc_html__("Nota: Estes dados são obtidos diretamente da API do Asaas e podem incluir transações não originadas nesta loja.", 'hng-commerce') . '</p>';
        echo '</div>';
    }

    /**
     * Handle purge action
     *
     * @return array|null Notice data
     */
    private static function maybe_handle_purge_request() {
        if (!isset($_POST['hng_asaas_purge_action'])) {
            return null;
        }

        if (!current_user_can('manage_options')) {
            return [
                'type' => 'error',
                'message' => __('Você não tem permissão para executar esta ação.', 'hng-commerce'),
            ];
        }

        check_admin_referer('hng_asaas_purge_action', 'hng_asaas_purge_nonce');

        $hard_delete = isset($_POST['hard_delete']) && intval($_POST['hard_delete']) === 1;

        if (!function_exists('hng_hide_gateway_data')) {
            $helper = HNG_COMMERCE_PATH . 'includes/helpers/hng-gateway-data.php';
            if (file_exists($helper)) {
                require_once $helper;
            }
        }

        if (!function_exists('hng_hide_gateway_data')) {
            return [
                'type' => 'error',
                'message' => __('Função de limpeza não encontrada.', 'hng-commerce'),
            ];
        }

        hng_hide_gateway_data('asaas', $hard_delete);

        return [
            'type' => 'success',
            'message' => $hard_delete
                ? __('Dados do Asaas apagados permanentemente.', 'hng-commerce')
                : __('Dados do Asaas ocultados. Reative a integração para sincronizar novamente.', 'hng-commerce'),
        ];
    }
}
