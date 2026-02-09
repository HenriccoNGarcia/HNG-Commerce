<?php
/**
 * Analytics Hub Admin Page - Dashboard Principal
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para a página de Analytics Hub
 */
class HNG_Analytics_Hub_Page {
    
    /**
     * Renderizar página (método estático para compatibilidade com HNG_Admin)
     */
    public static function render() {
        global $wpdb;
        
        // Get current month data
        $current_month_start = gmdate('Y-m-01 00:00:00');
        $current_month_end = gmdate('Y-m-t 23:59:59');
        
        // Get orders stats (usando nome correto da tabela com prefix do WordPress)
        $orders_table = $wpdb->prefix . 'hng_orders';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$orders_table} WHERE created_at >= %s AND created_at <= %s",
            $current_month_start,
            $current_month_end
        ));
        
        // Status pode ter prefixo hng- ou não
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $completed_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$orders_table} WHERE (status = 'completed' OR status = 'hng-completed') AND created_at >= %s AND created_at <= %s",
            $current_month_start,
            $current_month_end
        ));
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $canceled_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$orders_table} WHERE status IN ('cancelled', 'failed', 'refunded', 'hng-cancelled', 'hng-failed', 'hng-refunded') AND created_at >= %s AND created_at <= %s",
            $current_month_start,
            $current_month_end
        ));
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pending_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$orders_table} WHERE status IN ('pending', 'processing', 'hng-pending', 'hng-processing') AND created_at >= %s AND created_at <= %s",
            $current_month_start,
            $current_month_end
        ));
        
        // Get revenue stats (coluna é 'total' não 'total_amount')
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM {$orders_table} WHERE (status = 'completed' OR status = 'hng-completed') AND created_at >= %s AND created_at <= %s",
            $current_month_start,
            $current_month_end
        ));
        
        // Get top selling products
        $order_items_table = $wpdb->prefix . 'hng_order_items';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $top_products = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.product_name, oi.product_type, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_revenue
             FROM {$order_items_table} oi
             INNER JOIN {$orders_table} o ON oi.order_id = o.id
             WHERE (o.status = 'completed' OR o.status = 'hng-completed') AND o.created_at >= %s AND o.created_at <= %s
             GROUP BY oi.product_name, oi.product_type
             ORDER BY total_qty DESC
             LIMIT 5",
            $current_month_start,
            $current_month_end
        ), ARRAY_A);
        
        // Get payment methods breakdown
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $payment_methods = $wpdb->get_results($wpdb->prepare(
            "SELECT payment_method, COUNT(*) as count, SUM(total) as revenue
             FROM {$orders_table}
             WHERE (status = 'completed' OR status = 'hng-completed') AND created_at >= %s AND created_at <= %s AND payment_method IS NOT NULL
             GROUP BY payment_method
             ORDER BY count DESC",
            $current_month_start,
            $current_month_end
        ), ARRAY_A);
        
        // Calculate average ticket
        $avg_ticket = $completed_orders > 0 ? $total_revenue / $completed_orders : 0;
        
        // Calculate conversion rate
        $conversion_rate = $total_orders > 0 ? ($completed_orders / $total_orders) * 100 : 0;
        
        ?>
        <div class="wrap hng-analytics-hub">
            <div class="hng-dashboard-header">
                <div class="header-content">
                    <h1>
                        <span class="dashicons dashicons-dashboard"></span>
                        <?php esc_html_e('Dashboard HNG Commerce', 'hng-commerce'); ?>
                    </h1>
                    <p class="subtitle"><?php echo esc_html(sprintf(
                        /* translators: %s = current month and year */
                        __('Visão geral do mês: %s', 'hng-commerce'),
                        date_i18n('F Y')
                    )); ?></p>
                </div>
                <div class="header-actions">
                    <button type="button" class="button" onclick="location.reload()">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Atualizar', 'hng-commerce'); ?>
                    </button>
                </div>
            </div>
            
            <!-- KPI Cards Grid -->
            <div class="hng-kpi-grid">
                <div class="hng-kpi-card revenue">
                    <div class="kpi-icon">💰</div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php esc_html_e('Faturamento do Mês', 'hng-commerce'); ?></span>
                        <span class="kpi-value">R$ <?php echo esc_html(number_format(floatval($total_revenue), 2, ',', '.')); ?></span>
                        <span class="kpi-meta"><?php echo esc_html($completed_orders); ?> pedidos concluídos</span>
                    </div>
                </div>
                
                <div class="hng-kpi-card orders">
                    <div class="kpi-icon">📦</div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php esc_html_e('Total de Pedidos', 'hng-commerce'); ?></span>
                        <span class="kpi-value"><?php echo esc_html(number_format(intval($total_orders))); ?></span>
                        <span class="kpi-meta">
                            <span class="status-badge completed"><?php echo esc_html($completed_orders); ?> concluídos</span>
                            <span class="status-badge pending"><?php echo esc_html($pending_orders); ?> pendentes</span>
                        </span>
                    </div>
                </div>
                
                <div class="hng-kpi-card ticket">
                    <div class="kpi-icon">🎫</div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php esc_html_e('Ticket Médio', 'hng-commerce'); ?></span>
                        <span class="kpi-value">R$ <?php echo esc_html(number_format(floatval($avg_ticket), 2, ',', '.')); ?></span>
                        <span class="kpi-meta"><?php esc_html_e('Por pedido concluído', 'hng-commerce'); ?></span>
                    </div>
                </div>
                
                <div class="hng-kpi-card conversion">
                    <div class="kpi-icon">🎯</div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php esc_html_e('Taxa de Conversão', 'hng-commerce'); ?></span>
                        <span class="kpi-value"><?php echo esc_html(number_format(floatval($conversion_rate), 1)); ?>%</span>
                        <span class="kpi-meta">
                            <span class="status-badge failed"><?php echo esc_html($canceled_orders); ?> cancelados</span>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="hng-dashboard-content">
                <!-- Top Products -->
                <div class="hng-card products-card">
                    <div class="card-header">
                        <h2>
                            <span class="emoji">🏆</span>
                            <?php esc_html_e('Produtos Mais Vendidos', 'hng-commerce'); ?>
                        </h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=hng-reports')); ?>" class="view-all">
                            <?php esc_html_e('Ver todos', 'hng-commerce'); ?> →
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_products)): ?>
                            <div class="products-list">
                                <?php 
                                $product_icons = [
                                    'simple' => '📦',
                                    'variable' => '🔄',
                                    'subscription' => '🔁',
                                    'appointment' => '📅',
                                    'quote' => '📋'
                                ];
                                foreach ($top_products as $i => $product): 
                                    $icon = isset($product_icons[$product['product_type']]) ? $product_icons[$product['product_type']] : '📦';
                                ?>
                                    <div class="product-item">
                                        <span class="product-rank">#<?php echo esc_html($i + 1); ?></span>
                                        <div class="product-info">
                                            <span class="product-name">
                                                <span class="product-icon"><?php echo esc_html($icon); ?></span>
                                                <?php echo esc_html($product['product_name']); ?>
                                            </span>
                                            <span class="product-type"><?php 
                                                $type_labels = [
                                                    'physical' => __('Físico', 'hng-commerce'),
                                                    'simple' => __('Simples', 'hng-commerce'),
                                                    'digital' => __('Digital', 'hng-commerce'),
                                                    'variable' => __('Variável', 'hng-commerce'),
                                                    'subscription' => __('Assinatura', 'hng-commerce'),
                                                    'appointment' => __('Agendamento', 'hng-commerce'),
                                                    'quote' => __('Orçamento', 'hng-commerce'),
                                                ];
                                                $type_key = strtolower($product['product_type']);
                                                echo esc_html(isset($type_labels[$type_key]) ? $type_labels[$type_key] : ucfirst($type_key));
                                            ?></span>
                                        </div>
                                        <div class="product-stats">
                                            <span class="product-qty"><?php echo esc_html(number_format(intval($product['total_qty']))); ?> vendidos</span>
                                            <span class="product-revenue">R$ <?php echo esc_html(number_format(floatval($product['total_revenue']), 2, ',', '.')); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <span class="empty-icon">📦</span>
                                <p><?php esc_html_e('Nenhuma venda neste mês', 'hng-commerce'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Payment Methods -->
                <div class="hng-card payments-card">
                    <div class="card-header">
                        <h2>
                            <span class="emoji">💳</span>
                            <?php esc_html_e('Métodos de Pagamento', 'hng-commerce'); ?>
                        </h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=hng-financial')); ?>" class="view-all">
                            <?php esc_html_e('Ver financeiro', 'hng-commerce'); ?> →
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($payment_methods)): ?>
                            <div class="payment-methods-chart">
                                <?php 
                                $max_count = max(array_column($payment_methods, 'count'));
                                $payment_icons = [
                                    'pix' => '💸',
                                    'boleto' => '🧾',
                                    'credit_card' => '💳',
                                    'debit_card' => '💳',
                                    'pix_installment' => '🔢'
                                ];
                                foreach ($payment_methods as $method): 
                                    $percent = $max_count > 0 ? (intval($method['count']) / $max_count) * 100 : 0;
                                    $icon = isset($payment_icons[$method['payment_method']]) ? $payment_icons[$method['payment_method']] : '💰';
                                    $method_label = str_replace('_', ' ', ucwords($method['payment_method']));
                                ?>
                                    <div class="payment-method-row">
                                        <div class="method-info">
                                            <span class="method-icon"><?php echo esc_html($icon); ?></span>
                                            <span class="method-name"><?php echo esc_html($method_label); ?></span>
                                        </div>
                                        <div class="method-bar-wrapper">
                                            <div class="method-bar" style="width: <?php echo esc_attr($percent); ?>%;">
                                                <span class="method-count"><?php echo esc_html(number_format(intval($method['count']))); ?></span>
                                            </div>
                                        </div>
                                        <span class="method-revenue">R$ <?php echo esc_html(number_format(floatval($method['revenue']), 2, ',', '.')); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <span class="empty-icon">💳</span>
                                <p><?php esc_html_e('Nenhum pagamento registrado', 'hng-commerce'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="hng-card actions-card">
                    <div class="card-header">
                        <h2>
                            <span class="emoji">⚡</span>
                            <?php esc_html_e('Ações Rápidas', 'hng-commerce'); ?>
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions-grid">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-orders')); ?>" class="quick-action-btn">
                                <span class="action-icon">📦</span>
                                <span class="action-label"><?php esc_html_e('Ver Pedidos', 'hng-commerce'); ?></span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-customers')); ?>" class="quick-action-btn">
                                <span class="action-icon">👥</span>
                                <span class="action-label"><?php esc_html_e('Clientes', 'hng-commerce'); ?></span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-products')); ?>" class="quick-action-btn">
                                <span class="action-icon">🛍️</span>
                                <span class="action-label"><?php esc_html_e('Produtos', 'hng-commerce'); ?></span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-gateways')); ?>" class="quick-action-btn">
                                <span class="action-icon">💳</span>
                                <span class="action-label"><?php esc_html_e('Gateways', 'hng-commerce'); ?></span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-reports')); ?>" class="quick-action-btn">
                                <span class="action-icon">📊</span>
                                <span class="action-label"><?php esc_html_e('Relatórios', 'hng-commerce'); ?></span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-settings')); ?>" class="quick-action-btn">
                                <span class="action-icon">⚙️</span>
                                <span class="action-label"><?php esc_html_e('Configurações', 'hng-commerce'); ?></span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="hng-card status-card">
                    <div class="card-header">
                        <h2>
                            <span class="emoji">🔧</span>
                            <?php esc_html_e('Status do Sistema', 'hng-commerce'); ?>
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="status-list">
                            <div class="status-item">
                                <span class="status-label"><?php esc_html_e('Versão do Plugin', 'hng-commerce'); ?></span>
                                <span class="status-value">
                                    <span class="status-badge success"><?php echo esc_html(defined('HNG_COMMERCE_VERSION') ? HNG_COMMERCE_VERSION : '1.2.13'); ?></span>
                                </span>
                            </div>
                            <div class="status-item">
                                <span class="status-label"><?php esc_html_e('WordPress', 'hng-commerce'); ?></span>
                                <span class="status-value">
                                    <span class="status-badge"><?php echo esc_html(get_bloginfo('version')); ?></span>
                                </span>
                            </div>
                            <div class="status-item">
                                <span class="status-label"><?php esc_html_e('PHP', 'hng-commerce'); ?></span>
                                <span class="status-value">
                                    <span class="status-badge <?php echo version_compare(PHP_VERSION, '7.4', '>=') ? 'success' : 'warning'; ?>">
                                        <?php echo esc_html(PHP_VERSION); ?>
                                    </span>
                                </span>
                            </div>
                            <div class="status-item">
                                <span class="status-label"><?php esc_html_e('Gateway Ativo', 'hng-commerce'); ?></span>
                                <span class="status-value">
                                    <?php 
                                    // Verificar gateway ativo (priorizar apenas um)
                                    $active_gateway = get_option('hng_active_gateway');
                                    
                                    // Se não encontrar, verificar gateways reais habilitados (excluir PIX/Boleto que são métodos)
                                    if (empty($active_gateway) || $active_gateway === 'none') {
                                        $real_gateways = ['asaas', 'pagarme', 'mercadopago', 'pagseguro', 'nubank'];
                                        $active_gateway = null;
                                        
                                        foreach ($real_gateways as $gw) {
                                            if (get_option("hng_gateway_{$gw}_enabled") === 'yes') {
                                                $active_gateway = $gw;
                                                break; // Pegar apenas o primeiro habilitado
                                            }
                                        }
                                        
                                        if ($active_gateway) {
                                            $gateway_label = ucfirst($active_gateway);
                                            $gateway_class = 'success';
                                        } else {
                                            // Verificar último gateway usado em pedidos
                                            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                            $last_gateway = $wpdb->get_var("SELECT gateway FROM {$orders_table} WHERE gateway IS NOT NULL AND gateway NOT IN ('pix', 'boleto') ORDER BY created_at DESC LIMIT 1");
                                            
                                            if ($last_gateway) {
                                                $gateway_label = ucfirst(str_replace('_', ' ', $last_gateway)) . ' (último usado)';
                                                $gateway_class = 'info';
                                            } else {
                                                $gateway_label = __('Nenhum configurado', 'hng-commerce');
                                                $gateway_class = 'warning';
                                            }
                                        }
                                    } else {
                                        $gateway_label = ucfirst(str_replace('_', ' ', $active_gateway));
                                        $gateway_class = 'success';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo esc_attr($gateway_class); ?>">
                                        <?php echo esc_html($gateway_label); ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            /* Dashboard Styles */
            .hng-analytics-hub {
                max-width: 1400px;
                margin: 20px auto;
            }
            
            .hng-dashboard-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                padding: 0 5px;
            }
            
            .hng-dashboard-header h1 {
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 0 0 5px 0;
                font-size: 28px;
                color: #1d2327;
            }
            
            .hng-dashboard-header .dashicons {
                font-size: 32px;
                width: 32px;
                height: 32px;
                color: #0073aa;
            }
            
            .hng-dashboard-header .subtitle {
                margin: 0;
                color: #646970;
                font-size: 14px;
            }
            
            .header-actions .button {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            
            /* KPI Grid */
            .hng-kpi-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .hng-kpi-card {
                background: white;
                border-radius: 8px;
                padding: 24px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                display: flex;
                gap: 16px;
                transition: all 0.3s ease;
                border-left: 4px solid #ddd;
            }
            
            .hng-kpi-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            }
            
            .hng-kpi-card.revenue { border-left-color: #10b981; }
            .hng-kpi-card.orders { border-left-color: #3b82f6; }
            .hng-kpi-card.ticket { border-left-color: #f59e0b; }
            .hng-kpi-card.conversion { border-left-color: #8b5cf6; }
            
            .hng-kpi-card .kpi-icon {
                font-size: 48px;
                line-height: 1;
                flex-shrink: 0;
            }
            
            .hng-kpi-card .kpi-content {
                display: flex;
                flex-direction: column;
                gap: 4px;
                flex: 1;
            }
            
            .hng-kpi-card .kpi-label {
                font-size: 13px;
                color: #646970;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .hng-kpi-card .kpi-value {
                font-size: 28px;
                font-weight: 700;
                color: #1d2327;
                line-height: 1.2;
            }
            
            .hng-kpi-card .kpi-meta {
                font-size: 12px;
                color: #646970;
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .status-badge {
                display: inline-flex;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                background: #f0f0f1;
                color: #646970;
            }
            
            .status-badge.completed,
            .status-badge.success {
                background: #d1fae5;
                color: #065f46;
            }
            
            .status-badge.pending,
            .status-badge.warning {
                background: #fef3c7;
                color: #92400e;
            }
            
            .status-badge.failed {
                background: #fee2e2;
                color: #991b1b;
            }
            
            .status-badge.info {
                background: #dbeafe;
                color: #1e40af;
            }
            
            /* Dashboard Content Grid */
            .hng-dashboard-content {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            @media (max-width: 1280px) {
                .hng-dashboard-content {
                    grid-template-columns: 1fr;
                }
            }
            
            .hng-card {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                overflow: hidden;
            }
            
            .hng-card .card-header {
                padding: 20px 24px;
                border-bottom: 1px solid #f0f0f1;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .hng-card .card-header h2 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
                color: #1d2327;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .hng-card .card-header .emoji {
                font-size: 20px;
            }
            
            .hng-card .card-header .view-all {
                font-size: 13px;
                color: #0073aa;
                text-decoration: none;
                font-weight: 500;
            }
            
            .hng-card .card-header .view-all:hover {
                color: #005a87;
            }
            
            .hng-card .card-body {
                padding: 24px;
            }
            
            /* Products List */
            .products-list {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }
            
            .product-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                background: #f9fafb;
                border-radius: 6px;
                transition: all 0.2s;
            }
            
            .product-item:hover {
                background: #f3f4f6;
                transform: translateX(4px);
            }
            
            .product-rank {
                font-size: 16px;
                font-weight: 700;
                color: #0073aa;
                min-width: 30px;
            }
            
            .product-info {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            
            .product-name {
                font-weight: 600;
                color: #1d2327;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .product-icon {
                font-size: 16px;
            }
            
            .product-type {
                font-size: 11px;
                color: #646970;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .product-stats {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 2px;
            }
            
            .product-qty {
                font-size: 12px;
                color: #646970;
            }
            
            .product-revenue {
                font-size: 14px;
                font-weight: 700;
                color: #10b981;
            }
            
            /* Payment Methods Chart */
            .payment-methods-chart {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }
            
            .payment-method-row {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .method-info {
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 140px;
            }
            
            .method-icon {
                font-size: 20px;
            }
            
            .method-name {
                font-size: 13px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .method-bar-wrapper {
                flex: 1;
                height: 32px;
                background: #f0f0f1;
                border-radius: 16px;
                overflow: hidden;
                position: relative;
            }
            
            .method-bar {
                height: 100%;
                background: linear-gradient(90deg, #3b82f6, #8b5cf6);
                border-radius: 16px;
                display: flex;
                align-items: center;
                padding: 0 12px;
                min-width: 60px;
                transition: width 0.5s ease;
            }
            
            .method-count {
                font-size: 11px;
                font-weight: 700;
                color: white;
            }
            
            .method-revenue {
                font-size: 14px;
                font-weight: 700;
                color: #10b981;
                min-width: 100px;
                text-align: right;
            }
            
            /* Quick Actions */
            .quick-actions-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
            }
            
            .quick-action-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                padding: 20px 12px;
                background: #f9fafb;
                border-radius: 8px;
                text-decoration: none;
                transition: all 0.2s;
                border: 2px solid transparent;
            }
            
            .quick-action-btn:hover {
                background: #f3f4f6;
                border-color: #0073aa;
                transform: translateY(-2px);
            }
            
            .quick-action-btn .action-icon {
                font-size: 32px;
            }
            
            .quick-action-btn .action-label {
                font-size: 13px;
                font-weight: 600;
                color: #1d2327;
            }
            
            /* Status List */
            .status-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .status-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px;
                background: #f9fafb;
                border-radius: 6px;
            }
            
            .status-label {
                font-size: 13px;
                font-weight: 600;
                color: #646970;
            }
            
            .status-value .status-badge {
                padding: 4px 12px;
                font-size: 12px;
            }
            
            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 40px 20px;
            }
            
            .empty-state .empty-icon {
                font-size: 48px;
                display: block;
                margin-bottom: 12px;
                opacity: 0.5;
            }
            
            .empty-state p {
                color: #646970;
                margin: 0;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .hng-kpi-grid {
                    grid-template-columns: 1fr;
                }
                
                .quick-actions-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                
                .hng-dashboard-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 16px;
                }
            }
        </style>
        <?php
    }
}

