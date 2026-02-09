<?php
/**
 * Admin Page: Dashboard Analytics Avançado
 *
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Dados para análise
$analytics = HNG_Analytics::instance();
$conversion_rate = $analytics->get_conversion_rate();
$low_stock_products = $analytics->get_low_stock_products(5);
$vip_customers = $analytics->get_vip_customers(10);
$sales_forecast = $analytics->get_sales_forecast();
?>
                <h2><?php esc_html_e('Top 5 Produtos', 'hng-commerce'); ?></h2>
                <canvas id="top-products-chart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Tabelas -->
    <div class="hng-tables-section">
        <div class="hng-table-row">
            <!-- Produtos com Estoque Baixo -->
            <div class="hng-table-container">
                <h2>📦 <?php esc_html_e('Produtos com Estoque Baixo', 'hng-commerce'); ?></h2>
                <table class="hng-analytics-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Produto', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Estoque', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Vendas/Mês', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Dias Restantes', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Ação', 'hng-commerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($low_stock_products)): ?>
                        <tr>
                            <td colspan="5" class="no-data"><?php esc_html_e('Nenhum produto com estoque baixo', 'hng-commerce'); ?></td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($low_stock_products as $product): ?>
                        <tr class="<?php echo esc_attr( $product['days_remaining'] < 7 ? 'critical' : 'warning' ); ?>">
                            <td>
                                <strong><?php echo esc_html($product['name']); ?></strong>
                                <br><small>#<?php echo esc_html($product['id']); ?></small>
                            </td>
                            <td>
                                <span class="stock-badge <?php echo esc_attr( $product['stock'] <= 5 ? 'critical' : 'low' ); ?>">
                                    <?php echo esc_html(intval($product['stock'])); ?> unidades
                                </span>
                            </td>
                            <td><?php echo esc_html(intval($product['monthly_sales'])); ?> unidades</td>
                            <td>
                                <span class="days-badge">
                                    <?php echo esc_html(intval($product['days_remaining'])); ?> dias
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($product['id'])); ?>" class="button button-small">
                                    <?php esc_html_e('Editar', 'hng-commerce'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Clientes VIP -->
            <div class="hng-table-container">
                <h2>? <?php esc_html_e('Clientes VIP', 'hng-commerce'); ?></h2>
                <table class="hng-analytics-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Cliente', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Total Gasto', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Pedidos', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Ticket Médio', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Ação', 'hng-commerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vip_customers)): ?>
                        <tr>
                            <td colspan="5" class="no-data"><?php esc_html_e('Nenhum cliente VIP ainda', 'hng-commerce'); ?></td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($vip_customers as $customer): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($customer['name']); ?></strong>
                                <br><small><?php echo esc_html($customer['email']); ?></small>
                            </td>
                            <td>
                                <strong class="vip-amount">R$ <?php echo esc_html(number_format($customer['total_spent'], 2, ',', '.')); ?></strong>
                            </td>
                            <td><?php echo esc_html(intval($customer['order_count'])); ?> pedidos</td>
                            <td>R$ <?php echo esc_html(number_format($customer['avg_order'], 2, ',', '.')); ?></td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($customer['email']); ?>" class="button button-small">
                                    ✉️ <?php esc_html_e('Contatar', 'hng-commerce'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Insights Automáticos -->
    <div class="hng-insights-section">
        <h2>💡 <?php esc_html_e('Insights Automáticos', 'hng-commerce'); ?></h2>
        <div class="insights-grid">
            <?php 
            $insights = $analytics->get_automated_insights();
            foreach ($insights as $insight): 
            ?>
            <div class="insight-card <?php echo esc_attr($insight['type']); ?>">
                <div class="insight-icon"><?php echo esc_html($insight['icon']); ?></div>
                <div class="insight-content">
                    <h4><?php echo esc_html($insight['title']); ?></h4>
                    <p><?php echo esc_html($insight['message']); ?></p>
                    <?php if (!empty($insight['action'])): ?>
                    <a href="<?php echo esc_url($insight['action']['url']); ?>" class="insight-action">
                        <?php echo esc_html($insight['action']['text']); ?> ?
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.hng-analytics-dashboard {
    max-width: 1400px;
}

.hng-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0 40px 0;
}

.hng-kpi-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    gap: 15px;
    transition: transform 0.3s;
}

.hng-kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.kpi-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.kpi-icon.conversion { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.kpi-icon.revenue { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.kpi-icon.forecast { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.kpi-icon.customers { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

.kpi-content h3 {
    margin: 0 0 5px 0;
    font-size: 13px;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
}

.kpi-value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.kpi-subtitle, .kpi-change {
    font-size: 12px;
    color: #999;
}

.kpi-change {
    font-weight: 600;
}

.kpi-change.positive { color: #4caf50; }
.kpi-change.negative { color: #e74c3c; }

.hng-charts-section {
    margin: 40px 0;
}

.hng-chart-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.hng-chart-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.hng-chart-container h2 {
    margin: 0 0 20px 0;
    font-size: 18px;
    color: #333;
}

.hng-table-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.hng-table-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.hng-analytics-table {
    width: 100%;
    border-collapse: collapse;
}

.hng-analytics-table th {
    text-align: left;
    padding: 12px;
    border-bottom: 2px solid #e0e0e0;
    font-weight: 600;
    color: #555;
}

.hng-analytics-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
}

.hng-analytics-table tr.critical {
    background: #ffebee;
}

.hng-analytics-table tr.warning {
    background: #fff3e0;
}

.stock-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.stock-badge.critical {
    background: #e74c3c;
    color: white;
}

.stock-badge.low {
    background: #ff9800;
    color: white;
}

.days-badge {
    font-weight: 600;
    color: #666;
}

.vip-amount {
    color: #667eea;
    font-size: 16px;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.insight-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #667eea;
    display: flex;
    gap: 15px;
}

.insight-card.success { border-left-color: #4caf50; }
.insight-card.warning { border-left-color: #ff9800; }
.insight-card.danger { border-left-color: #e74c3c; }

.insight-icon {
    font-size: 32px;
}

.insight-content h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
}

.insight-content p {
    margin: 0 0 12px 0;
    color: #666;
    font-size: 14px;
}

.insight-action {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
}

.insight-action:hover {
    text-decoration: underline;
}

@media (max-width: 1200px) {
    .hng-chart-row,
    .hng-table-row {
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    gap: 15px;
    transition: transform 0.3s;
}

.hng-kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.kpi-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.kpi-icon.conversion { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.kpi-icon.revenue { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.kpi-icon.forecast { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.kpi-icon.customers { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

.kpi-content h3 {
    margin: 0 0 5px 0;
    font-size: 13px;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
}

.kpi-value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.kpi-subtitle, .kpi-change {
    font-size: 12px;
    color: #999;
}

.kpi-change {
    font-weight: 600;
}

.kpi-change.positive { color: #4caf50; }
.kpi-change.negative { color: #e74c3c; }

.hng-charts-section {
    margin: 40px 0;
}

.hng-chart-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.hng-chart-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.hng-chart-container h2 {
    margin: 0 0 20px 0;
    font-size: 18px;
    color: #333;
}

.hng-table-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.hng-table-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.hng-analytics-table {
    width: 100%;
    border-collapse: collapse;
}

.hng-analytics-table th {
    text-align: left;
    padding: 12px;
    border-bottom: 2px solid #e0e0e0;
    font-weight: 600;
    color: #555;
}

.hng-analytics-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
}

.hng-analytics-table tr.critical {
    background: #ffebee;
}

.hng-analytics-table tr.warning {
    background: #fff3e0;
}

.stock-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.stock-badge.critical {
    background: #e74c3c;
    color: white;
}

.stock-badge.low {
    background: #ff9800;
    color: white;
}

.days-badge {
    font-weight: 600;
    color: #666;
}

.vip-amount {
    color: #667eea;
    font-size: 16px;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.insight-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #667eea;
    display: flex;
    gap: 15px;
}

.insight-card.success { border-left-color: #4caf50; }
.insight-card.warning { border-left-color: #ff9800; }
.insight-card.danger { border-left-color: #e74c3c; }

.insight-icon {
    font-size: 32px;
}

.insight-content h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
}

.insight-content p {
    margin: 0 0 12px 0;
    color: #666;
    font-size: 14px;
}

.insight-action {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
}

.insight-action:hover {
    text-decoration: underline;
}

@media (max-width: 1200px) {
    .hng-chart-row,
    .hng-table-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
// En queue Chart placeholder script (should be enqueued in functions or admin class)
wp_enqueue_script('hng-chart-placeholder', plugin_dir_url(__FILE__) . 'assets/js/vendor/chart-placeholder.js', [], '1.0', true);
// For now, add inline script data
$chart_data = [
    'monthlyComparison' => [
        'labels' => $monthly_comparison['labels'],
        'data' => $monthly_comparison['data'],
    ],
    'categoryRevenue' => [
        'labels' => array_column($revenue_by_category, 'name'),
        'data' => array_column($revenue_by_category, 'revenue'),
    ],
    'conversionFunnel' => $analytics->get_conversion_funnel_data(),
            'topProducts' => [
                'labels' => array_column($top_products, 'name'),
                'data' => wp_json_encode(array_column($top_products, 'sales')),
    ],
];
wp_localize_script('hng-commerce-admin', 'hngAnalyticsData', $chart_data);
?>
<script>
// Grfico de Comparao Mensal
const monthlyComparisonCtx = document.getElementById('monthly-comparison-chart');
new Chart(monthlyComparisonCtx, {
    type: 'bar',
    data: {
        labels: <?php echo wp_json_encode($monthly_comparison['labels']); ?>,
        datasets: [{
            label: 'Receita (R$)',
            data: <?php echo wp_json_encode($monthly_comparison['data']); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => 'R$ ' + value.toLocaleString('pt-BR')
                }
            }
        }
    }
});

// Grfico de Receita por Categoria
const categoryRevenueCtx = document.getElementById('category-revenue-chart');
new Chart(categoryRevenueCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo wp_json_encode(array_column($revenue_by_category, 'name')); ?>,
        datasets: [{
            data: <?php echo wp_json_encode(array_column($revenue_by_category, 'revenue')); ?>,
            backgroundColor: [
                'rgba(102, 126, 234, 0.8)',
                'rgba(118, 75, 162, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(255, 99, 132, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});

// Grfico de Funil de Converso
const conversionFunnelCtx = document.getElementById('conversion-funnel-chart');
new Chart(conversionFunnelCtx, {
    type: 'bar',
    data: {
        labels: ['Visitantes', 'Visualizaes', 'Carrinho', 'Checkout', 'Compra'],
        datasets: [{
            label: 'Usurios',
                data: <?php echo wp_json_encode($analytics->get_conversion_funnel_data()); ?>,
            backgroundColor: [
                'rgba(102, 126, 234, 0.9)',
                'rgba(102, 126, 234, 0.7)',
                'rgba(102, 126, 234, 0.5)',
                'rgba(102, 126, 234, 0.3)',
                'rgba(102, 126, 234, 0.1)'
            ],
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false
    }
});

// Grfico de Top Produtos
const topProductsCtx = document.getElementById('top-products-chart');
new Chart(topProductsCtx, {
    type: 'horizontalBar',
    data: {
        labels: <?php echo wp_json_encode(array_column($top_products, 'name')); ?>,
        datasets: [{
            label: 'Vendas',
            data: <?php echo wp_json_encode(array_column($top_products, 'sales')); ?>,
            backgroundColor: 'rgba(76, 175, 80, 0.8)',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                beginAtZero: true
            }
        }
    }
});
</script>


