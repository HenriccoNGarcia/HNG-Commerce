<?php
/**
 * Reports Page - Relatórios e Análises de Vendas
 * 
 * Dashboard completo de métricas de e-commerce com UI/UX moderno
 * 
 * @package HNG_Commerce
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Reports_Page {
    
    /**
     * Render reports page
     */
    public static function render() {
        // Get period from request
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '30days';
        
        // Get date range
        $dates = self::get_period_dates($period);
        $start_date = $dates['start'];
        $end_date = $dates['end'];
        
        // Load analytics classes
        if (!class_exists('HNG_Conversion_Analytics')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/financial/class-conversion-analytics.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/financial/class-conversion-analytics.php';
            }
        }
        if (!class_exists('HNG_Financial_Analytics')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/financial/class-financial-analytics.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/financial/class-financial-analytics.php';
            }
        }
        
        // Get data
        $conversion_data = [];
        if (class_exists('HNG_Conversion_Analytics')) {
            $conversion_data = HNG_Conversion_Analytics::get_conversion_analysis($start_date, $end_date);
        }
        
        $funnel = $conversion_data['conversion_funnel'] ?? ['steps' => [], 'final_conversion' => 0];
        $top_templates = $conversion_data['top_converting_templates'] ?? [];
        $top_checkouts = $conversion_data['top_converting_checkouts'] ?? [];
        $abandonment_category = $conversion_data['cart_abandonment_by_category'] ?? [];
        $abandonment_checkout = $conversion_data['cart_abandonment_by_checkout'] ?? [];
        
        // Get additional metrics
        $sales_metrics = self::get_sales_metrics($start_date, $end_date);
        $sales_split = class_exists('HNG_Financial_Analytics') ? HNG_Financial_Analytics::get_summary_split($start_date, $end_date) : ['site' => ['revenue'=>0,'orders'=>0,'aov'=>0], 'gateway' => ['revenue'=>0,'orders'=>0,'aov'=>0], 'combined' => ['revenue'=>0,'orders'=>0,'aov'=>0]];
        $top_products = self::get_top_products($start_date, $end_date);
        $top_categories = self::get_top_categories($start_date, $end_date);
        
        self::render_styles();
        ?>
        <div class="hng-reports-dashboard">
            <!-- Header -->
            <header class="reports-header">
                <div class="header-left">
                    <h1>
                        <span class="icon">📊</span>
                        <?php esc_html_e('Relatórios de Vendas', 'hng-commerce'); ?>
                    </h1>
                    <p class="subtitle"><?php esc_html_e('Acompanhe o desempenho da sua loja em tempo real', 'hng-commerce'); ?></p>
                </div>
                <div class="header-right">
                    <div class="period-selector">
                        <select id="period-select">
                            <option value="today" <?php selected($period, 'today'); ?>><?php esc_html_e('Hoje', 'hng-commerce'); ?></option>
                            <option value="7days" <?php selected($period, '7days'); ?>><?php esc_html_e('Últimos 7 dias', 'hng-commerce'); ?></option>
                            <option value="30days" <?php selected($period, '30days'); ?>><?php esc_html_e('Últimos 30 dias', 'hng-commerce'); ?></option>
                            <option value="90days" <?php selected($period, '90days'); ?>><?php esc_html_e('Últimos 90 dias', 'hng-commerce'); ?></option>
                            <option value="year" <?php selected($period, 'year'); ?>><?php esc_html_e('Este ano', 'hng-commerce'); ?></option>
                        </select>
                    </div>
                    <button type="button" class="btn-export" id="export-csv">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Exportar', 'hng-commerce'); ?>
                    </button>
                </div>
            </header>
            
            <!-- KPI Cards -->
            <section class="kpi-grid">
                <div class="kpi-card revenue">
                    <div class="kpi-icon">💰</div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php esc_html_e('Faturamento', 'hng-commerce'); ?></span>
                        <span class="kpi-value">R$ <?php echo esc_html(number_format($sales_metrics['revenue'], 2, ',', '.')); ?></span>
                        <span class="kpi-change <?php echo esc_attr( $sales_metrics['revenue_change'] >= 0 ? 'positive' : 'negative' ); ?>">
                            <?php echo esc_html($sales_metrics['revenue_change'] >= 0 ? '↑' : '↓'); ?> 
                            <?php echo esc_html(abs($sales_metrics['revenue_change'])); ?>%
                        </span>
                    </div>
                </div>
                
                <div class="kpi-card orders">
                    <div class="kpi-icon">📦</div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php esc_html_e('Pedidos', 'hng-commerce'); ?></span>
                        <span class="kpi-value"><?php echo esc_html(number_format($sales_metrics['orders'])); ?></span>
                        <span class="kpi-change <?php echo esc_attr( $sales_metrics['orders_change'] >= 0 ? 'positive' : 'negative' ); ?>">
                            <?php echo esc_html($sales_metrics['orders_change'] >= 0 ? '↑' : '↓'); ?> 
                            <?php echo esc_html(abs($sales_metrics['orders_change'])); ?>%
                        </span>
                    </div>
                </div>
                
                <div class="kpi-card ticket">
                    <div class="kpi-icon">🎫</div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php esc_html_e('Ticket Médio', 'hng-commerce'); ?></span>
                        <span class="kpi-value">R$ <?php echo esc_html(number_format($sales_metrics['avg_ticket'], 2, ',', '.')); ?></span>
                    </div>
                </div>
                
                <div class="kpi-card conversion">
                    <div class="kpi-icon">🎯</div>
                    <div class="kpi-content">
                        <span class="kpi-label"><?php esc_html_e('Taxa de Conversão', 'hng-commerce'); ?></span>
                        <span class="kpi-value"><?php echo esc_html($funnel['final_conversion']); ?>%</span>
                    </div>
                </div>
            </section>

            <!-- Data Source Split -->
            <section class="reports-grid">
                <div class="report-card full-width">
                    <div class="card-header">
                        <h2><span class="emoji">🔀</span> <?php esc_html_e('Origem dos Dados (Site vs Gateway)', 'hng-commerce'); ?></h2>
                        <p class="card-subtitle"><?php esc_html_e('Separação dos pedidos rastreados pelo site e os provenientes dos gateways. Exibimos também o total combinado.', 'hng-commerce'); ?></p>
                    </div>
                    <div class="card-body">
                        <div class="kpi-grid">
                            <div class="kpi-card">
                                <div class="kpi-icon">🌐</div>
                                <div class="kpi-content">
                                    <span class="kpi-label"><?php esc_html_e('Site (rastreamento)', 'hng-commerce'); ?></span>
                                    <span class="kpi-value">R$ <?php echo esc_html(number_format($sales_split['site']['revenue'], 2, ',', '.')); ?></span>
                                    <span class="kpi-change">📦 <?php echo esc_html(intval($sales_split['site']['orders'])); ?> · 🎫 R$ <?php echo esc_html(number_format($sales_split['site']['aov'], 2, ',', '.')); ?></span>
                                </div>
                            </div>
                            <div class="kpi-card">
                                <div class="kpi-icon">💳</div>
                                <div class="kpi-content">
                                    <span class="kpi-label"><?php esc_html_e('Gateways (integração)', 'hng-commerce'); ?></span>
                                    <span class="kpi-value">R$ <?php echo esc_html(number_format($sales_split['gateway']['revenue'], 2, ',', '.')); ?></span>
                                    <span class="kpi-change">📦 <?php echo esc_html(intval($sales_split['gateway']['orders'])); ?> · 🎫 R$ <?php echo esc_html(number_format($sales_split['gateway']['aov'], 2, ',', '.')); ?></span>
                                </div>
                            </div>
                            <div class="kpi-card">
                                <div class="kpi-icon">➕</div>
                                <div class="kpi-content">
                                    <span class="kpi-label"><?php esc_html_e('Total Combinado', 'hng-commerce'); ?></span>
                                    <span class="kpi-value">R$ <?php echo esc_html(number_format($sales_split['combined']['revenue'], 2, ',', '.')); ?></span>
                                    <span class="kpi-change">📦 <?php echo esc_html(intval($sales_split['combined']['orders'])); ?> · 🎫 R$ <?php echo esc_html(number_format($sales_split['combined']['aov'], 2, ',', '.')); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Main Content Grid -->
            <div class="reports-grid">
                <!-- Conversion Funnel -->
                <section class="report-card funnel-card">
                    <div class="card-header">
                        <h2><span class="emoji">🔀</span> <?php esc_html_e('Funil de Conversão', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="funnel-visualization">
                            <?php 
                            $max_count = !empty($funnel['steps']) ? max(array_column($funnel['steps'], 'count')) : 1;
                            $colors = ['#6366f1', '#8b5cf6', '#a855f7', '#d946ef'];
                            foreach ($funnel['steps'] as $i => $step): 
                                $width = $max_count > 0 ? ($step['count'] / $max_count) * 100 : 0;
                            ?>
                            <div class="funnel-step">
                                <div class="step-label">
                                    <span class="step-name"><?php echo esc_html($step['name']); ?></span>
                                    <span class="step-count"><?php echo esc_html(number_format($step['count'])); ?></span>
                                </div>
                                <div class="step-bar-container">
                                    <div class="step-bar" style="width: <?php echo esc_attr(max($width, 5)); ?>%; background: <?php echo esc_attr($colors[$i % 4]); ?>;">
                                        <?php if ($i > 0): ?>
                                            <span class="step-rate"><?php echo esc_html(isset($step['conversion']) ? floatval($step['conversion']) : 0); ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($i < count($funnel['steps']) - 1): ?>
                                    <div class="funnel-arrow">
                                        <span class="drop-rate">
                                            <?php 
                                            $next_conversion = isset($funnel['steps'][$i + 1]['conversion']) ? floatval($funnel['steps'][$i + 1]['conversion']) : 0;
                                            $drop = 100 - $next_conversion;
                                            echo esc_html('-' . number_format($drop, 1) . '%');
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="funnel-summary">
                            <div class="summary-stat">
                                <span class="stat-value"><?php echo esc_html($funnel['final_conversion']); ?>%</span>
                                <span class="stat-label"><?php esc_html_e('Conversão Final', 'hng-commerce'); ?></span>
                            </div>
                            <div class="summary-text">
                                <?php 
                                $visitors = $funnel['steps'][0]['count'] ?? 0;
                                $buyers = !empty($funnel['steps']) ? end($funnel['steps'])['count'] : 0;
                                printf(
                                    /* translators: 1: number of visitors, 2: number of buyers */
                                    esc_html__('De %1$s visitantes, %2$s finalizaram a compra', 'hng-commerce'),
                                    '<strong>' . esc_html(number_format($visitors)) . '</strong>',
                                    '<strong>' . esc_html(number_format($buyers)) . '</strong>'
                                );
                                ?>
                            </div>
                        </div>
                    </div>
                </section>
                
                <!-- Top Products -->
                <section class="report-card products-card">
                    <div class="card-header">
                        <h2><span class="emoji">🏆</span> <?php esc_html_e('Produtos Mais Vendidos', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_products)): ?>
                            <div class="products-list">
                                <?php foreach ($top_products as $i => $product): ?>
                                    <div class="product-item">
                                        <span class="product-rank">#<?php echo esc_html($i + 1); ?></span>
                                        <div class="product-info">
                                            <span class="product-name"><?php echo esc_html($product['name']); ?></span>
                                            <span class="product-stats">
                                                <?php echo esc_html(number_format($product['quantity'])); ?> vendidos
                                            </span>
                                        </div>
                                        <div class="product-revenue">
                                            R$ <?php echo esc_html(number_format($product['revenue'], 2, ',', '.')); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <span class="empty-icon">📦</span>
                                <p><?php esc_html_e('Nenhuma venda no período', 'hng-commerce'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                <!-- Top Categories -->
                <section class="report-card categories-card">
                    <div class="card-header">
                        <h2><span class="emoji">📁</span> <?php esc_html_e('Categorias com Mais Vendas', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_categories)): ?>
                            <div class="categories-chart">
                                <?php 
                                $max_revenue = max(array_column($top_categories, 'revenue'));
                                $cat_colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'];
                                foreach ($top_categories as $i => $category): 
                                    $percent = $max_revenue > 0 ? ($category['revenue'] / $max_revenue) * 100 : 0;
                                ?>
                                    <div class="category-row">
                                        <div class="category-info">
                                            <span class="category-name"><?php echo esc_html($category['name']); ?></span>
                                            <span class="category-count"><?php echo esc_html(number_format($category['orders'])); ?> pedidos</span>
                                        </div>
                                        <div class="category-bar-wrapper">
                                            <div class="category-bar" style="width: <?php echo esc_attr($percent); ?>%; background: <?php echo esc_attr($cat_colors[$i % 5]); ?>;"></div>
                                        </div>
                                        <span class="category-value">R$ <?php echo esc_html(number_format($category['revenue'], 2, ',', '.')); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <span class="empty-icon">📁</span>
                                <p><?php esc_html_e('Nenhum dado disponível', 'hng-commerce'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                <!-- Cart Abandonment -->
                <section class="report-card abandonment-card">
                    <div class="card-header">
                        <h2><span class="emoji">🛒</span> <?php esc_html_e('Abandono de Carrinho', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($abandonment_category)): ?>
                            <div class="abandonment-stats">
                                <?php 
                                $total_abandoned = array_sum(array_column($abandonment_category, 'abandoned_value'));
                                $avg_rate = count($abandonment_category) > 0 ? array_sum(array_column($abandonment_category, 'abandonment_rate')) / count($abandonment_category) : 0;
                                ?>
                                <div class="abandon-metric danger">
                                    <span class="metric-value">R$ <?php echo esc_html(number_format($total_abandoned, 2, ',', '.')); ?></span>
                                    <span class="metric-label"><?php esc_html_e('Valor Perdido', 'hng-commerce'); ?></span>
                                </div>
                                <div class="abandon-metric <?php echo esc_attr( $avg_rate > 50 ? 'danger' : 'warning' ); ?>">
                                    <span class="metric-value"><?php echo esc_html(number_format($avg_rate, 1)); ?>%</span>
                                    <span class="metric-label"><?php esc_html_e('Taxa Média', 'hng-commerce'); ?></span>
                                </div>
                            </div>
                            
                            <div class="abandonment-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Categoria', 'hng-commerce'); ?></th>
                                            <th><?php esc_html_e('Abandonados', 'hng-commerce'); ?></th>
                                            <th><?php esc_html_e('Taxa', 'hng-commerce'); ?></th>
                                            <th><?php esc_html_e('Valor Perdido', 'hng-commerce'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($abandonment_category, 0, 5) as $cat): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($cat['category_name']); ?></strong></td>
                                                <td><?php echo esc_html(number_format($cat['abandoned_carts'])); ?></td>
                                                <td>
                                                    <span class="rate-badge <?php echo esc_attr( $cat['abandonment_rate'] > 50 ? 'high' : 'low' ); ?>">
                                                        <?php echo esc_html($cat['abandonment_rate']); ?>%
                                                    </span>
                                                </td>
                                                <td>R$ <?php echo esc_html(number_format($cat['abandoned_value'], 2, ',', '.')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state success">
                                <span class="empty-icon">✅</span>
                                <p><?php esc_html_e('Sem abandonos registrados!', 'hng-commerce'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            
            <!-- Full Width Tables -->
            <div class="reports-tables">
                <!-- Top Converting Pages -->
                <section class="report-card full-width">
                    <div class="card-header">
                        <h2><span class="emoji">📄</span> <?php esc_html_e('Performance de Páginas', 'hng-commerce'); ?></h2>
                        <p class="card-subtitle"><?php esc_html_e('Páginas que mais geram vendas no seu site', 'hng-commerce'); ?></p>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_templates)): ?>
                            <div class="data-table-wrapper">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th class="col-page"><?php esc_html_e('Página', 'hng-commerce'); ?></th>
                                            <th class="col-type"><?php esc_html_e('Tipo', 'hng-commerce'); ?></th>
                                            <th class="col-conversions"><?php esc_html_e('Conversões', 'hng-commerce'); ?></th>
                                            <th class="col-rate"><?php esc_html_e('Taxa Conv.', 'hng-commerce'); ?></th>
                                            <th class="col-revenue"><?php esc_html_e('Faturamento', 'hng-commerce'); ?></th>
                                            <th class="col-ticket"><?php esc_html_e('Ticket Médio', 'hng-commerce'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($top_templates, 0, 10) as $template): ?>
                                            <tr>
                                                <td class="col-page">
                                                    <a href="<?php echo esc_url($template['page_url']); ?>" target="_blank" class="page-link">
                                                        <?php echo esc_html(mb_substr($template['page_title'], 0, 45)); ?>
                                                        <?php if (strlen($template['page_title']) > 45) echo '...'; ?>
                                                        <span class="dashicons dashicons-external"></span>
                                                    </a>
                                                </td>
                                                <td class="col-type">
                                                    <?php 
                                                    $type_config = [
                                                        'page' => ['label' => 'Página', 'class' => 'type-page'],
                                                        'post' => ['label' => 'Post', 'class' => 'type-post'],
                                                        'hng_product' => ['label' => 'Produto', 'class' => 'type-product']
                                                    ];
                                                    $type = $type_config[$template['page_type']] ?? ['label' => $template['page_type'], 'class' => 'type-other'];
                                                    ?>
                                                    <span class="type-badge <?php echo esc_attr($type['class']); ?>">
                                                        <?php echo esc_html($type['label']); ?>
                                                    </span>
                                                </td>
                                                <td class="col-conversions">
                                                    <strong><?php echo esc_html(number_format($template['conversions'])); ?></strong>
                                                </td>
                                                <td class="col-rate">
                                                    <div class="rate-visual">
                                                        <div class="rate-bar">
                                                            <div class="rate-fill" style="width: <?php echo esc_attr(min(100, $template['conversion_rate'])); ?>%;"></div>
                                                        </div>
                                                        <span><?php echo esc_html(number_format($template['conversion_rate'], 2)); ?>%</span>
                                                    </div>
                                                </td>
                                                <td class="col-revenue">
                                                    <strong>R$ <?php echo esc_html(number_format($template['revenue'], 2, ',', '.')); ?></strong>
                                                </td>
                                                <td class="col-ticket">
                                                    <?php if ($template['conversions'] > 0): ?>
                                                        R$ <?php echo esc_html(number_format($template['average_value'], 2, ',', '.')); ?>
                                                    <?php else: ?>
                                                        <span class="no-data">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <span class="empty-icon">📄</span>
                                <p><?php esc_html_e('Nenhum dado de conversão disponível', 'hng-commerce'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                <!-- Checkout Performance -->
                <section class="report-card full-width">
                    <div class="card-header">
                        <h2><span class="emoji">💳</span> <?php esc_html_e('Performance de Checkouts', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="checkout-grid">
                            <!-- Checkouts com mais conversões -->
                            <div class="checkout-section">
                                <h3><?php esc_html_e('Top Conversões', 'hng-commerce'); ?></h3>
                                <?php if (!empty($top_checkouts)): ?>
                                    <div class="checkout-list">
                                        <?php foreach ($top_checkouts as $checkout): ?>
                                            <div class="checkout-item success">
                                                <div class="checkout-name"><?php echo esc_html($checkout['checkout_name']); ?></div>
                                                <div class="checkout-stats">
                                                    <span class="stat"><?php echo esc_html(number_format($checkout['conversions'])); ?> conversões</span>
                                                    <span class="revenue">R$ <?php echo esc_html(number_format($checkout['revenue'], 2, ',', '.')); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state mini">
                                        <p><?php esc_html_e('Sem dados', 'hng-commerce'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Abandono por checkout -->
                            <div class="checkout-section">
                                <h3><?php esc_html_e('Abandono por Método', 'hng-commerce'); ?></h3>
                                <?php if (!empty($abandonment_checkout)): ?>
                                    <div class="checkout-list">
                                        <?php foreach ($abandonment_checkout as $checkout): ?>
                                            <div class="checkout-item <?php echo esc_attr( $checkout['abandonment_rate'] > 50 ? 'danger' : 'warning' ); ?>">
                                                <div class="checkout-name"><?php echo esc_html($checkout['checkout_method']); ?></div>
                                                <div class="checkout-stats">
                                                    <span class="rate-badge <?php echo esc_attr( $checkout['abandonment_rate'] > 50 ? 'high' : 'low' ); ?>">
                                                        <?php echo esc_html($checkout['abandonment_rate']); ?>% abandono
                                                    </span>
                                                    <span class="lost">R$ <?php echo esc_html(number_format($checkout['abandoned_value'], 2, ',', '.')); ?> perdido</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state mini success">
                                        <p><?php esc_html_e('Sem abandonos!', 'hng-commerce'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#period-select').change(function() {
                window.location.href = '<?php echo esc_url(admin_url('admin.php?page=hng-reports')); ?>&period=' + $(this).val();
            });
            
            $('#export-csv').click(function() {
                window.location.href = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=hng_export_conversion_csv&period=' + $('#period-select').val();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render CSS styles
     */
    private static function render_styles() {
        ?>
        <style>
        .hng-reports-dashboard {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        /* Header */
        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .reports-header h1 {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }
        
        .reports-header .icon {
            font-size: 32px;
        }
        
        .reports-header .subtitle {
            color: #64748b;
            font-size: 14px;
            margin: 8px 0 0 44px;
        }
        
        .header-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .period-selector select {
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
            cursor: pointer;
            min-width: 180px;
        }
        
        .btn-export {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-export:hover {
            background: #4f46e5;
        }
        
        /* KPI Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .kpi-icon {
            font-size: 36px;
            line-height: 1;
        }
        
        .kpi-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .kpi-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
        
        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .kpi-change {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
            width: fit-content;
        }
        
        .kpi-change.positive {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .kpi-change.negative {
            background: #fee2e2;
            color: #dc2626;
        }
        
        /* Report Cards */
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .report-card.full-width {
            grid-column: 1 / -1;
        }
        
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header .emoji {
            font-size: 22px;
        }
        
        .card-subtitle {
            font-size: 13px;
            color: #64748b;
            margin: 6px 0 0 32px;
        }
        
        .card-body {
            padding: 24px;
        }
        
        /* Funnel Visualization */
        .funnel-step {
            margin-bottom: 20px;
        }
        
        .step-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .step-name {
            font-weight: 500;
            color: #334155;
        }
        
        .step-count {
            font-size: 13px;
            color: #64748b;
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 20px;
        }
        
        .step-bar-container {
            background: #f1f5f9;
            border-radius: 8px;
            height: 36px;
            overflow: hidden;
        }
        
        .step-bar {
            height: 100%;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 12px;
            transition: width 0.5s ease;
        }
        
        .step-rate {
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }
        
        .funnel-arrow {
            text-align: center;
            padding: 8px 0;
        }
        
        .drop-rate {
            font-size: 11px;
            color: #ef4444;
            background: #fef2f2;
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .funnel-summary {
            margin-top: 24px;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 24px;
        }
        
        .summary-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 100px;
        }
        
        .summary-stat .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #6366f1;
        }
        
        .summary-stat .stat-label {
            font-size: 12px;
            color: #64748b;
        }
        
        .summary-text {
            font-size: 14px;
            color: #475569;
            line-height: 1.5;
        }
        
        /* Products List */
        .products-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            background: #f8fafc;
            border-radius: 10px;
            transition: background 0.2s;
        }
        
        .product-item:hover {
            background: #f1f5f9;
        }
        
        .product-rank {
            font-size: 14px;
            font-weight: 700;
            color: #6366f1;
            min-width: 30px;
        }
        
        .product-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .product-name {
            font-weight: 500;
            color: #1e293b;
        }
        
        .product-stats {
            font-size: 12px;
            color: #64748b;
        }
        
        .product-revenue {
            font-weight: 600;
            color: #10b981;
        }
        
        /* Categories Chart */
        .categories-chart {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        
        .category-row {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .category-info {
            min-width: 140px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .category-name {
            font-weight: 500;
            color: #1e293b;
            font-size: 13px;
        }
        
        .category-count {
            font-size: 11px;
            color: #64748b;
        }
        
        .category-bar-wrapper {
            flex: 1;
            background: #f1f5f9;
            border-radius: 6px;
            height: 24px;
            overflow: hidden;
        }
        
        .category-bar {
            height: 100%;
            border-radius: 6px;
            transition: width 0.5s ease;
        }
        
        .category-value {
            font-weight: 600;
            font-size: 13px;
            color: #1e293b;
            min-width: 100px;
            text-align: right;
        }
        
        /* Abandonment Stats */
        .abandonment-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .abandon-metric {
            flex: 1;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .abandon-metric.danger {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
        }
        
        .abandon-metric.warning {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
        }
        
        .abandon-metric .metric-value {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            display: block;
        }
        
        .abandon-metric .metric-label {
            font-size: 12px;
            color: #64748b;
        }
        
        .abandonment-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .abandonment-table th {
            text-align: left;
            padding: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .abandonment-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .rate-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .rate-badge.high {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .rate-badge.low {
            background: #dcfce7;
            color: #16a34a;
        }
        
        /* Data Table */
        .data-table-wrapper {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: #f8fafc;
        }
        
        .data-table th {
            text-align: left;
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .data-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .page-link {
            color: #3b82f6;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .page-link:hover {
            color: #1d4ed8;
        }
        
        .page-link .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }
        
        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .type-badge.type-page {
            background: #e0e7ff;
            color: #4338ca;
        }
        
        .type-badge.type-post {
            background: #fef3c7;
            color: #b45309;
        }
        
        .type-badge.type-product {
            background: #d1fae5;
            color: #047857;
        }
        
        .rate-visual {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .rate-bar {
            width: 60px;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rate-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            border-radius: 4px;
        }
        
        .no-data {
            color: #94a3b8;
        }
        
        /* Checkout Grid */
        .checkout-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }
        
        .checkout-section h3 {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            margin: 0 0 16px 0;
        }
        
        .checkout-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .checkout-item {
            padding: 14px;
            border-radius: 10px;
            border-left: 4px solid;
        }
        
        .checkout-item.success {
            background: #f0fdf4;
            border-color: #10b981;
        }
        
        .checkout-item.warning {
            background: #fffbeb;
            border-color: #f59e0b;
        }
        
        .checkout-item.danger {
            background: #fef2f2;
            border-color: #ef4444;
        }
        
        .checkout-name {
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 6px;
        }
        
        .checkout-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }
        
        .checkout-stats .stat {
            color: #64748b;
        }
        
        .checkout-stats .revenue {
            font-weight: 600;
            color: #10b981;
        }
        
        .checkout-stats .lost {
            color: #ef4444;
        }
        
        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-state.mini {
            padding: 20px;
        }
        
        .empty-state .empty-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.6;
        }
        
        .empty-state.success {
            background: #f0fdf4;
            border-radius: 10px;
        }
        
        /* Reports Tables Section */
        .reports-tables {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .reports-header {
                flex-direction: column;
            }
            
            .header-right {
                width: 100%;
                flex-wrap: wrap;
            }
            
            .period-selector {
                flex: 1;
            }
            
            .period-selector select {
                width: 100%;
            }
            
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .kpi-value {
                font-size: 22px;
            }
            
            .funnel-summary {
                flex-direction: column;
                text-align: center;
            }
            
            .data-table th,
            .data-table td {
                padding: 10px 8px;
                font-size: 12px;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Get sales metrics
     */
    private static function get_sales_metrics($start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_orders';
        
        // Current period - incluir status com prefixo hng-
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as orders,
                COALESCE(SUM(total), 0) as revenue
             FROM {$table}
             WHERE status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND created_at BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));
        
        // Calculate days in period
        $days = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400);
        
        // Previous period
        $prev_start = gmdate('Y-m-d H:i:s', strtotime($start_date) - ($days * 86400));
        $prev_end = $start_date;
        
        $previous = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as orders,
                COALESCE(SUM(total), 0) as revenue
             FROM {$table}
             WHERE status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND created_at BETWEEN %s AND %s",
            $prev_start,
            $prev_end
        ));
        
        // Calculate changes
        $revenue_change = $previous->revenue > 0 
            ? round((($current->revenue - $previous->revenue) / $previous->revenue) * 100, 1) 
            : 0;
        
        $orders_change = $previous->orders > 0 
            ? round((($current->orders - $previous->orders) / $previous->orders) * 100, 1) 
            : 0;
        
        return [
            'revenue' => floatval($current->revenue),
            'orders' => intval($current->orders),
            'avg_ticket' => $current->orders > 0 ? $current->revenue / $current->orders : 0,
            'revenue_change' => $revenue_change,
            'orders_change' => $orders_change,
        ];
    }
    
    /**
     * Get top selling products
     */
    private static function get_top_products($start_date, $end_date) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';
        $items_table = $wpdb->prefix . 'hng_order_items';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names use safe prefix
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.product_id,
                oi.product_name as name,
                SUM(oi.quantity) as quantity,
                SUM(oi.subtotal) as revenue
             FROM {$items_table} oi
             INNER JOIN {$orders_table} o ON o.id = oi.order_id
             WHERE o.status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND o.created_at BETWEEN %s AND %s
             GROUP BY oi.product_id, oi.product_name
             ORDER BY revenue DESC
             LIMIT 5",
            $start_date,
            $end_date
        ), ARRAY_A);
        
        return $results ?: [];
    }
    
    /**
     * Get top categories by revenue
     */
    private static function get_top_categories($start_date, $end_date) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hng_orders';
        $items_table = $wpdb->prefix . 'hng_order_items';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names use safe prefix
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.product_id,
                SUM(oi.subtotal) as revenue,
                COUNT(DISTINCT o.id) as orders
             FROM {$items_table} oi
             INNER JOIN {$orders_table} o ON o.id = oi.order_id
             WHERE o.status IN ('processing', 'completed', 'hng-processing', 'hng-completed')
             AND o.created_at BETWEEN %s AND %s
             GROUP BY oi.product_id
             ORDER BY revenue DESC",
            $start_date,
            $end_date
        ), ARRAY_A);
        
        // Group by category
        $categories = [];
        foreach ($results as $item) {
            $terms = wp_get_post_terms($item['product_id'], 'hng_product_cat');
            $cat_name = !empty($terms) && !is_wp_error($terms) ? $terms[0]->name : __('Sem categoria', 'hng-commerce');
            
            if (!isset($categories[$cat_name])) {
                $categories[$cat_name] = ['name' => $cat_name, 'revenue' => 0, 'orders' => 0];
            }
            $categories[$cat_name]['revenue'] += floatval($item['revenue']);
            $categories[$cat_name]['orders'] += intval($item['orders']);
        }
        
        // Sort and return top 5
        usort($categories, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        
        return array_slice($categories, 0, 5);
    }
    
    /**
     * Get date range based on period
     */
    private static function get_period_dates($period) {
        $end = current_time('mysql');
        
        switch ($period) {
            case 'today':
                $start = gmdate('Y-m-d 00:00:00');
                break;
            case '7days':
                $start = gmdate('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case '30days':
                $start = gmdate('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            case '90days':
                $start = gmdate('Y-m-d 00:00:00', strtotime('-90 days'));
                break;
            case 'year':
                $start = gmdate('Y-m-d 00:00:00', strtotime('-365 days'));
                break;
            default:
                $start = gmdate('Y-m-d 00:00:00', strtotime('-30 days'));
        }
        
        return ['start' => $start, 'end' => $end];
    }
}
