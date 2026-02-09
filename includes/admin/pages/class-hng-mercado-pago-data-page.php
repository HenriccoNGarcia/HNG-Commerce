<?php
/**
 * HNG Commerce - Mercado Pago Data Page
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Mercado_Pago_Data_Page {

    /**
     * Render the page
     */
    public static function render() {
        // Handle purge request
        $purge_notice = self::maybe_handle_purge_request();
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for tab navigation, no data modification
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'sync';
        ?>
        <div class="wrap hng-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-database" style="font-size: 28px; width: 28px; height: 28px; margin-right: 10px; vertical-align: middle;"></span>
                <?php esc_html_e('Dados do Mercado Pago', 'hng-commerce'); ?>
            </h1>
            <hr class="wp-header-end">

            <?php if ($purge_notice) : ?>
                <div class="notice notice-<?php echo esc_attr($purge_notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html($purge_notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=hng-mercadopago-data&tab=sync" class="nav-tab <?php echo esc_attr( $active_tab === 'sync' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Sincronização', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-mercadopago-data&tab=subscriptions" class="nav-tab <?php echo esc_attr( $active_tab === 'subscriptions' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Assinaturas', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-mercadopago-data&tab=customers" class="nav-tab <?php echo esc_attr( $active_tab === 'customers' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Clientes', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-mercadopago-data&tab=webhooks" class="nav-tab <?php echo esc_attr( $active_tab === 'webhooks' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Webhooks', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-mercadopago-data&tab=reports" class="nav-tab <?php echo esc_attr( $active_tab === 'reports' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Relatórios', 'hng-commerce'); ?>
                </a>
            </nav>

            <div class="hng-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'sync':
                        self::render_sync_tab();
                        break;
                    case 'subscriptions':
                        self::render_subscriptions_tab();
                        break;
                    case 'customers':
                        self::render_customers_tab();
                        break;
                    case 'webhooks':
                        self::render_webhooks_tab();
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
        $last_sync_subs = get_option('hng_mercadopago_last_sync_subscriptions', __('Nunca', 'hng-commerce'));
        $last_sync_customers = get_option('hng_mercadopago_last_sync_customers', __('Nunca', 'hng-commerce'));
        
        if ($last_sync_subs !== __('Nunca', 'hng-commerce')) {
            $last_sync_subs = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_subs));
        }
        
        if ($last_sync_customers !== __('Nunca', 'hng-commerce')) {
            $last_sync_customers = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_customers));
        }
        ?>
        <div class="hng-card">
            <h2><?php esc_html_e('Sincronização Manual', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Force a sincronização de dados entre o Mercado Pago e sua loja. Isso pode levar alguns minutos.', 'hng-commerce'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Assinaturas', 'hng-commerce'); ?></th>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data inicial', 'hng-commerce'); ?></label>
                                <input type="date" id="mercadopago-sync-subscriptions-start" />
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data final', 'hng-commerce'); ?></label>
                                <input type="date" id="mercadopago-sync-subscriptions-end" />
                            </div>
                            <button type="button" class="button button-secondary" id="hng-sync-mercadopago-subscriptions">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Sincronizar Assinaturas', 'hng-commerce'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php /* translators: %s: last sync date */ printf(esc_html__('Última sincronização: <strong>%s</strong>. Deixe as datas vazias para sincronizar todas/todos.', 'hng-commerce'), esc_html($last_sync_subs)); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Clientes', 'hng-commerce'); ?></th>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data inicial', 'hng-commerce'); ?></label>
                                <input type="date" id="mercadopago-sync-customers-start" />
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data final', 'hng-commerce'); ?></label>
                                <input type="date" id="mercadopago-sync-customers-end" />
                            </div>
                            <button type="button" class="button button-secondary" id="hng-sync-mercadopago-customers">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Sincronizar Clientes', 'hng-commerce'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php /* translators: %s: last sync date */ printf(esc_html__('Última sincronização: <strong>%s</strong>. Deixe as datas vazias para sincronizar todas/todos.', 'hng-commerce'), esc_html($last_sync_customers)); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Data purge card -->
        <div class="hng-card" style="margin-top: 20px; border-color: #d63638;">
            <h2 style="color: #d63638; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Remover dados sincronizados do Mercado Pago', 'hng-commerce'); ?>
            </h2>
            <p><?php esc_html_e('Use esta opção para remover dados que vieram do gateway (clientes, assinaturas e notas relacionadas).', 'hng-commerce'); ?></p>
            <form method="post">
                <?php wp_nonce_field('hng_mercadopago_purge_action', 'hng_mercadopago_purge_nonce'); ?>
                <input type="hidden" name="hng_mercadopago_purge_action" value="1" />
                <label style="display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px;">
                    <input type="checkbox" name="hard_delete" value="1" />
                    <span><?php esc_html_e('Apagar permanentemente (não poderá desfazer)', 'hng-commerce'); ?></span>
                </label>
                <div>
                    <button type="submit" class="button button-secondary" style="background: #d63638; border-color: #d63638; color: #fff;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Purgar dados do Mercado Pago', 'hng-commerce'); ?>
                    </button>
                </div>
                <p class="description" style="margin-top: 10px;">
                    <?php esc_html_e('Se a integração avançada estiver ativa novamente, você poderá sincronizar outra vez. A opção permanente apagará os registros ao invés de apenas escondê-los.', 'hng-commerce'); ?>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render Subscriptions Tab
     */
    private static function render_subscriptions_tab() {
        ?>
        <div class="hng-card">
            <h2><?php esc_html_e('Assinaturas Mercado Pago', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Visualize e gerencie as assinaturas recorrentes sincronizadas do Mercado Pago.', 'hng-commerce'); ?></p>
            
            <div id="mercadopago-subscriptions-list">
                <p><?php esc_html_e('Carregando assinaturas...', 'hng-commerce'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render Customers Tab
     */
    private static function render_customers_tab() {
        ?>
        <div class="hng-card">
            <h2><?php esc_html_e('Clientes Mercado Pago', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Visualize os clientes sincronizados do Mercado Pago para integração com seu sistema de análises financeiras.', 'hng-commerce'); ?></p>
            
            <div id="mercadopago-customers-list">
                <p><?php esc_html_e('Carregando clientes...', 'hng-commerce'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render Webhooks Tab
     */
    private static function render_webhooks_tab() {
        $webhook_url = admin_url('admin-ajax.php') . '?action=hng_mercadopago_webhook';
        ?>
        <div class="hng-card">
            <h2><?php esc_html_e('Configuração de Webhooks', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Configure webhooks no Mercado Pago para receber notificações em tempo real sobre pagamentos e assinaturas.', 'hng-commerce'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('URL do Webhook', 'hng-commerce'); ?></th>
                    <td>
                        <input type="text" value="<?php echo esc_url($webhook_url); ?>" class="large-text" readonly>
                        <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_url($webhook_url); ?>')">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php esc_html_e('Copiar', 'hng-commerce'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Cole esta URL no Mercado Pago (Configurações > Webhooks) para receber notificações.', 'hng-commerce'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Eventos Monitorados', 'hng-commerce'); ?></th>
                    <td>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li>✓ <?php esc_html_e('Pagamento aprovado/pendente/rejeitado', 'hng-commerce'); ?></li>
                            <li>✓ <?php esc_html_e('Assinatura criada/atualizada/cancelada', 'hng-commerce'); ?></li>
                            <li>✓ <?php esc_html_e('Reembolso processado', 'hng-commerce'); ?></li>
                            <li>✓ <?php esc_html_e('Disputa/reclamação recebida', 'hng-commerce'); ?></li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Reports Tab
     */
    private static function render_reports_tab() {
        ?>
        <div class="hng-card">
            <h2><?php esc_html_e('Relatórios Mercado Pago', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Analise dados financeiros e comportamento de clientes do Mercado Pago integrados com seu sistema.', 'hng-commerce'); ?></p>
            
            <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-top: 20px;">
                <h3><?php esc_html_e('Estatísticas', 'hng-commerce'); ?></h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #0073aa;">
                            <span id="mp-total-subscriptions">--</span>
                        </div>
                        <div style="color: #666; margin-top: 5px;">
                            <?php esc_html_e('Assinaturas Ativas', 'hng-commerce'); ?>
                        </div>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #28a745;">
                            <span id="mp-total-customers">--</span>
                        </div>
                        <div style="color: #666; margin-top: 5px;">
                            <?php esc_html_e('Clientes Únicos', 'hng-commerce'); ?>
                        </div>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #ffc107;">
                            <span id="mp-mrr-value">--</span>
                        </div>
                        <div style="color: #666; margin-top: 5px;">
                            <?php esc_html_e('MRR Estimado', 'hng-commerce'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle purge action
     * 
     * @return array|null Purge notice or null
     */
    private static function maybe_handle_purge_request() {
        if (!isset($_POST['hng_mercadopago_purge_action'])) {
            return null;
        }

        // Validate nonce
        if (!isset($_POST['hng_mercadopago_purge_nonce'])) {
            return null;
        }

        check_admin_referer('hng_mercadopago_purge_action', 'hng_mercadopago_purge_nonce');

        if (!current_user_can('manage_options')) {
            return [
                'type' => 'error',
                'message' => __('Você não tem permissão para fazer isso.', 'hng-commerce')
            ];
        }

        $hard_delete = isset($_POST['hard_delete']) && $_POST['hard_delete'] === '1';

        if (!class_exists('HNG_Mercado_Pago_Sync')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/integrations/class-hng-mercado-pago-sync.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/integrations/class-hng-mercado-pago-sync.php';
            }
        }

        if (!class_exists('HNG_Mercado_Pago_Sync')) {
            return [
                'type' => 'error',
                'message' => __('Classe de sincronização não encontrada.', 'hng-commerce')
            ];
        }

        $sync = new HNG_Mercado_Pago_Sync();
        $result = $sync->purge_data($hard_delete);

        if ($result['success']) {
            return [
                'type' => 'success',
                'message' => $result['message'] ?? __('Dados removidos com sucesso!', 'hng-commerce')
            ];
        } else {
            return [
                'type' => 'error',
                'message' => $result['message'] ?? __('Erro ao remover dados.', 'hng-commerce')
            ];
        }
    }
}
