<?php
/**
 * HNG Commerce - PagSeguro Data Page
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_PagSeguro_Data_Page {

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
                <?php esc_html_e('Dados do PagSeguro', 'hng-commerce'); ?>
            </h1>
            <hr class="wp-header-end">

            <?php if ($purge_notice) : ?>
                <div class="notice notice-<?php echo esc_attr($purge_notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html($purge_notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=hng-pagseguro-data&tab=sync" class="nav-tab <?php echo esc_attr( $active_tab === 'sync' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Sincronização', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-pagseguro-data&tab=subscriptions" class="nav-tab <?php echo esc_attr( $active_tab === 'subscriptions' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Assinaturas', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-pagseguro-data&tab=customers" class="nav-tab <?php echo esc_attr( $active_tab === 'customers' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Clientes', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-pagseguro-data&tab=webhooks" class="nav-tab <?php echo esc_attr( $active_tab === 'webhooks' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e('Webhooks', 'hng-commerce'); ?>
                </a>
                <a href="?page=hng-pagseguro-data&tab=reports" class="nav-tab <?php echo esc_attr( $active_tab === 'reports' ? 'nav-tab-active' : '' ); ?>">
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
        $last_sync_subs = get_option('hng_pagseguro_last_sync_subscriptions', __('Nunca', 'hng-commerce'));
        $last_sync_customers = get_option('hng_pagseguro_last_sync_customers', __('Nunca', 'hng-commerce'));
        $last_sync_payments = get_option('hng_pagseguro_last_sync_payments', __('Nunca', 'hng-commerce'));
        
        if ($last_sync_subs !== __('Nunca', 'hng-commerce')) {
            $last_sync_subs = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_subs));
        }
        
        if ($last_sync_customers !== __('Nunca', 'hng-commerce')) {
            $last_sync_customers = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_customers));
        }
        
        if ($last_sync_payments !== __('Nunca', 'hng-commerce')) {
            $last_sync_payments = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_payments));
        }
        
        $advanced_enabled = get_option('hng_pagseguro_advanced_integration', 'no') === 'yes';
        ?>
        <?php if (!$advanced_enabled): ?>
        <div class="notice notice-warning" style="margin-bottom: 20px;">
            <p>
                <strong><?php esc_html_e('Integração Avançada Desativada', 'hng-commerce'); ?></strong><br>
                <?php esc_html_e('Para sincronizar dados do PagSeguro, ative a "Integração Avançada" nas configurações do gateway.', 'hng-commerce'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hng-gateways')); ?>"><?php esc_html_e('Ir para Configurações', 'hng-commerce'); ?></a>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="hng-card">
            <h2><?php esc_html_e('Sincronização Manual', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Force a sincronização de dados entre o PagSeguro/PagBank e sua loja. Isso pode levar alguns minutos.', 'hng-commerce'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Pagamentos/Faturamento', 'hng-commerce'); ?></th>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data inicial', 'hng-commerce'); ?></label>
                                <input type="date" id="pagseguro-sync-start" <?php echo !$advanced_enabled ? 'disabled' : ''; ?> />
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data final', 'hng-commerce'); ?></label>
                                <input type="date" id="pagseguro-sync-end" <?php echo !$advanced_enabled ? 'disabled' : ''; ?> />
                            </div>
                            <button type="button" class="button button-primary" id="hng-sync-pagseguro-payments" <?php echo !$advanced_enabled ? 'disabled' : ''; ?>>
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Sincronizar Pagamentos', 'hng-commerce'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php /* translators: %s: last sync date */ printf(esc_html__('Última sincronização: <strong>%s</strong>. Deixe as datas vazias para usar os últimos 30 dias.', 'hng-commerce'), esc_html($last_sync_payments)); ?>
                        </p>
                        <p class="description"><?php esc_html_e('Importa cobranças e pagamentos para a página Financeiro.', 'hng-commerce'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Assinaturas', 'hng-commerce'); ?></th>
                    <td>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data inicial', 'hng-commerce'); ?></label>
                                <input type="date" id="pagseguro-sync-subscriptions-start" <?php echo !$advanced_enabled ? 'disabled' : ''; ?> />
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data final', 'hng-commerce'); ?></label>
                                <input type="date" id="pagseguro-sync-subscriptions-end" <?php echo !$advanced_enabled ? 'disabled' : ''; ?> />
                            </div>
                            <button type="button" class="button button-secondary" id="hng-sync-pagseguro-subscriptions" <?php echo !$advanced_enabled ? 'disabled' : ''; ?>>
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
                                <input type="date" id="pagseguro-sync-customers-start" <?php echo !$advanced_enabled ? 'disabled' : ''; ?> />
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:4px;"><?php esc_html_e('Data final', 'hng-commerce'); ?></label>
                                <input type="date" id="pagseguro-sync-customers-end" <?php echo !$advanced_enabled ? 'disabled' : ''; ?> />
                            </div>
                            <button type="button" class="button button-secondary" id="hng-sync-pagseguro-customers" <?php echo !$advanced_enabled ? 'disabled' : ''; ?>>
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Sincronizar Clientes', 'hng-commerce'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php /* translators: %s: last sync date */ printf(esc_html__('Última sincronização: <strong>%s</strong>. Deixe as datas vazias para sincronizar todas/todos.', 'hng-commerce'), esc_html($last_sync_customers)); ?>
                        </p>
                        <p class="description"><?php esc_html_e('Os clientes podem ser vinculados a usuários WordPress para acesso ao painel.', 'hng-commerce'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Data purge card -->
        <div class="hng-card" style="margin-top: 20px; border-color: #d63638;">
            <h2 style="color: #d63638; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Remover dados sincronizados do PagSeguro', 'hng-commerce'); ?>
            </h2>
            <p><?php esc_html_e('Use esta opção para remover dados que vieram do gateway (clientes, assinaturas e notas relacionadas).', 'hng-commerce'); ?></p>
            <form method="post">
                <?php wp_nonce_field('hng_pagseguro_purge_action', 'hng_pagseguro_purge_nonce'); ?>
                <input type="hidden" name="hng_pagseguro_purge_action" value="1" />
                <label style="display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px;">
                    <input type="checkbox" name="hard_delete" value="1" />
                    <span><?php esc_html_e('Apagar permanentemente (não poderá desfazer)', 'hng-commerce'); ?></span>
                </label>
                <div>
                    <button type="submit" class="button button-secondary" style="background: #d63638; border-color: #d63638; color: #fff;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Purgar dados do PagSeguro', 'hng-commerce'); ?>
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
            <h2><?php esc_html_e('Assinaturas PagSeguro', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Visualize e gerencie as assinaturas recorrentes sincronizadas do PagSeguro.', 'hng-commerce'); ?></p>
            
            <div id="pagseguro-subscriptions-list">
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
            <h2><?php esc_html_e('Clientes PagSeguro', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Visualize os clientes e assinantes sincronizados do PagSeguro para integração com seu sistema de análises financeiras.', 'hng-commerce'); ?></p>
            
            <div id="pagseguro-customers-list">
                <p><?php esc_html_e('Carregando clientes...', 'hng-commerce'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render Webhooks Tab
     */
    private static function render_webhooks_tab() {
        $webhook_url = admin_url('admin-ajax.php') . '?action=hng_pagseguro_webhook';
        ?>
        <div class="hng-card">
            <h2><?php esc_html_e('Configuração de Webhooks', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Configure webhooks no PagSeguro para receber notificações em tempo real sobre transações e assinaturas.', 'hng-commerce'); ?></p>
            
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
                            <?php esc_html_e('Cole esta URL no portal do PagSeguro para receber notificações de webhooks.', 'hng-commerce'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Eventos Monitorados', 'hng-commerce'); ?></th>
                    <td>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li>✓ <?php esc_html_e('Assinatura criada/cancelada', 'hng-commerce'); ?></li>
                            <li>✓ <?php esc_html_e('Pagamento recebido/falhado', 'hng-commerce'); ?></li>
                            <li>✓ <?php esc_html_e('Reembolso processado', 'hng-commerce'); ?></li>
                            <li>✓ <?php esc_html_e('Mudança de plano', 'hng-commerce'); ?></li>
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
            <h2><?php esc_html_e('Relatórios PagSeguro', 'hng-commerce'); ?></h2>
            <p><?php esc_html_e('Analise dados financeiros e comportamento de assinantes do PagSeguro integrados com seu sistema.', 'hng-commerce'); ?></p>
            
            <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin-top: 20px;">
                <h3><?php esc_html_e('Estatísticas', 'hng-commerce'); ?></h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #0073aa;">
                            <span id="ps-total-subscriptions">--</span>
                        </div>
                        <div style="color: #666; margin-top: 5px;">
                            <?php esc_html_e('Assinaturas Ativas', 'hng-commerce'); ?>
                        </div>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #28a745;">
                            <span id="ps-total-customers">--</span>
                        </div>
                        <div style="color: #666; margin-top: 5px;">
                            <?php esc_html_e('Clientes Únicos', 'hng-commerce'); ?>
                        </div>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #ffc107;">
                            <span id="ps-total-payments">--</span>
                        </div>
                        <div style="color: #666; margin-top: 5px;">
                            <?php esc_html_e('Pagamentos Sincronizados', 'hng-commerce'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Load stats
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'hng_pagseguro_get_sync_stats'
                },
                success: function(response) {
                    if (response.success) {
                        $('#ps-total-subscriptions').text(response.data.total_subscriptions || 0);
                        $('#ps-total-customers').text(response.data.total_customers || 0);
                        $('#ps-total-payments').text(response.data.total_payments || 0);
                    }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Enqueue page scripts
     */
    public static function enqueue_scripts() {
        $nonce = wp_create_nonce('hng_pagseguro_sync_nonce');
        ?>
        <script>
        jQuery(document).ready(function($) {
            var syncNonce = '<?php echo esc_js($nonce); ?>';
            
            // Sync Subscriptions
            $('#hng-sync-pagseguro-subscriptions').on('click', function() {
                var $btn = $(this);
                var startDate = $('#pagseguro-sync-subscriptions-start').val();
                var endDate = $('#pagseguro-sync-subscriptions-end').val();
                $btn.prop('disabled', true).find('.dashicons').addClass('spin');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hng_pagseguro_sync_subscriptions',
                        nonce: syncNonce,
                        start_date: startDate,
                        end_date: endDate
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message || 'Sincronização concluída!');
                            location.reload();
                        } else {
                            alert('Erro: ' + (response.data.error || 'Erro desconhecido'));
                        }
                    },
                    error: function(xhr) {
                        alert('Erro de conexão. Tente novamente.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                    }
                });
            });
            
            // Sync Customers
            $('#hng-sync-pagseguro-customers').on('click', function() {
                var $btn = $(this);
                var startDate = $('#pagseguro-sync-customers-start').val();
                var endDate = $('#pagseguro-sync-customers-end').val();
                $btn.prop('disabled', true).find('.dashicons').addClass('spin');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hng_pagseguro_sync_customers',
                        nonce: syncNonce,
                        start_date: startDate,
                        end_date: endDate
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message || 'Sincronização concluída!');
                            location.reload();
                        } else {
                            alert('Erro: ' + (response.data.error || 'Erro desconhecido'));
                        }
                    },
                    error: function(xhr) {
                        alert('Erro de conexão. Tente novamente.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                    }
                });
            });
            
            // Sync Payments
            $('#hng-sync-pagseguro-payments').on('click', function() {
                var $btn = $(this);
                var startDate = $('#pagseguro-sync-start').val();
                var endDate = $('#pagseguro-sync-end').val();
                $btn.prop('disabled', true).find('.dashicons').addClass('spin');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hng_pagseguro_sync_payments',
                        nonce: syncNonce,
                        start_date: startDate,
                        end_date: endDate
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message || 'Sincronização concluída!');
                            location.reload();
                        } else {
                            alert('Erro: ' + (response.data.error || 'Erro desconhecido'));
                        }
                    },
                    error: function(xhr) {
                        alert('Erro de conexão. Tente novamente.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                    }
                });
            });
            
            // Load subscriptions list
            function loadSubscriptions() {
                var $container = $('#pagseguro-subscriptions-list');
                if ($container.length === 0) return;
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hng_pagseguro_list_subscriptions',
                        page: 1
                    },
                    success: function(response) {
                        if (response.success && response.data.subscriptions.length > 0) {
                            var html = '<table class="wp-list-table widefat fixed striped">';
                            html += '<thead><tr><th>ID</th><th>Cliente</th><th>Valor</th><th>Status</th><th>Próx. Cobrança</th></tr></thead>';
                            html += '<tbody>';
                            response.data.subscriptions.forEach(function(sub) {
                                var customerName = sub.customer ? sub.customer.name : '-';
                                var statusClass = sub.status === 'active' ? 'color: green;' : (sub.status === 'cancelled' ? 'color: red;' : '');
                                html += '<tr>';
                                html += '<td>' + (sub.pagseguro_subscription_id || sub.id) + '</td>';
                                html += '<td>' + customerName + '</td>';
                                html += '<td>R$ ' + parseFloat(sub.amount).toFixed(2) + '</td>';
                                html += '<td style="' + statusClass + '">' + sub.status + '</td>';
                                html += '<td>' + (sub.next_billing_date || '-') + '</td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                            html += '<p class="description">Total: ' + response.data.total + ' assinaturas</p>';
                            $container.html(html);
                        } else {
                            $container.html('<p>Nenhuma assinatura sincronizada. Clique em "Sincronizar Assinaturas" para importar.</p>');
                        }
                    },
                    error: function() {
                        $container.html('<p>Erro ao carregar assinaturas.</p>');
                    }
                });
            }
            
            // Load customers list
            function loadCustomers() {
                var $container = $('#pagseguro-customers-list');
                if ($container.length === 0) return;
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hng_pagseguro_list_customers',
                        page: 1
                    },
                    success: function(response) {
                        if (response.success && response.data.customers.length > 0) {
                            var html = '<table class="wp-list-table widefat fixed striped">';
                            html += '<thead><tr><th>Nome</th><th>Email</th><th>Documento</th><th>Usuário WP</th><th>Ações</th></tr></thead>';
                            html += '<tbody>';
                            response.data.customers.forEach(function(cust) {
                                var wpUser = cust.wp_user ? cust.wp_user.display_name : '<em style="color:#999;">Não vinculado</em>';
                                html += '<tr>';
                                html += '<td>' + (cust.name || '-') + '</td>';
                                html += '<td>' + (cust.email || '-') + '</td>';
                                html += '<td>' + (cust.document || '-') + '</td>';
                                html += '<td>' + wpUser + '</td>';
                                html += '<td>';
                                if (!cust.wp_user) {
                                    html += '<button type="button" class="button button-small link-customer-btn" data-id="' + cust.id + '" data-email="' + cust.email + '">Vincular</button>';
                                }
                                html += '</td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                            html += '<p class="description">Total: ' + response.data.total + ' clientes</p>';
                            $container.html(html);
                        } else {
                            $container.html('<p>Nenhum cliente sincronizado. Clique em "Sincronizar Clientes" para importar.</p>');
                        }
                    },
                    error: function() {
                        $container.html('<p>Erro ao carregar clientes.</p>');
                    }
                });
            }
            
            // Link customer to user
            $(document).on('click', '.link-customer-btn', function() {
                var $btn = $(this);
                var customerId = $btn.data('id');
                var customerEmail = $btn.data('email');
                var userId = prompt('Digite o ID do usuário WordPress para vincular (ou deixe em branco para buscar por email):');
                
                if (userId === null) return;
                
                if (!userId && customerEmail) {
                    // Search by email
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'hng_search_user_by_email',
                            email: customerEmail
                        },
                        success: function(response) {
                            if (response.success && response.data.user_id) {
                                linkCustomer(customerId, response.data.user_id);
                            } else {
                                alert('Nenhum usuário encontrado com este email.');
                            }
                        }
                    });
                } else if (userId) {
                    linkCustomer(customerId, parseInt(userId));
                }
            });
            
            function linkCustomer(customerId, userId) {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hng_pagseguro_link_customer_to_user',
                        nonce: syncNonce,
                        customer_id: customerId,
                        user_id: userId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            loadCustomers();
                        } else {
                            alert('Erro: ' + response.data.error);
                        }
                    }
                });
            }
            
            // Auto-load lists based on current tab
            var urlParams = new URLSearchParams(window.location.search);
            var tab = urlParams.get('tab') || 'sync';
            
            if (tab === 'subscriptions') {
                loadSubscriptions();
            } else if (tab === 'customers') {
                loadCustomers();
            }
        });
        </script>
        <style>
        .dashicons.spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        </style>
        <?php
    }

    /**
     * Handle purge action
     * 
     * @return array|null Purge notice or null
     */
    private static function maybe_handle_purge_request() {
        if (!isset($_POST['hng_pagseguro_purge_action'])) {
            return null;
        }

        // Validate nonce
        if (!isset($_POST['hng_pagseguro_purge_nonce'])) {
            return null;
        }

        check_admin_referer('hng_pagseguro_purge_action', 'hng_pagseguro_purge_nonce');

        if (!current_user_can('manage_options')) {
            return [
                'type' => 'error',
                'message' => __('Você não tem permissão para fazer isso.', 'hng-commerce')
            ];
        }

        $hard_delete = isset($_POST['hard_delete']) && $_POST['hard_delete'] === '1';

        if (!class_exists('HNG_PagSeguro_Sync')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/integrations/class-hng-pagseguro-sync.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/integrations/class-hng-pagseguro-sync.php';
            }
        }

        if (!class_exists('HNG_PagSeguro_Sync')) {
            return [
                'type' => 'error',
                'message' => __('Classe de sincronização não encontrada.', 'hng-commerce')
            ];
        }

        $sync = new HNG_PagSeguro_Sync();
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


// Enqueue scripts on page load
add_action('admin_footer', function() {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'hng-pagseguro-data') !== false) {
        HNG_PagSeguro_Data_Page::enqueue_scripts();
    }
});
