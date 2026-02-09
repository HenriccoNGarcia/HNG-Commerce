<?php
/**
 * Financial Dashboard Page
 * Displays detailed financial metrics, charts, and analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Financial_Dashboard_Page {
    
    public static function render() {
        // Get period from request
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for period filter, no data modification
        $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '30days';
        
        // Get date range based on period
        $dates = self::get_period_dates($period);
        $start_date = $dates['start'];
        $end_date = $dates['end'];
        
        // Load analytics classes
        if (!class_exists('HNG_Financial_Analytics')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/financial/class-financial-analytics.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/financial/class-financial-analytics.php';
            }
        }
        
        if (!class_exists('HNG_Financial_Analytics')) {
            echo '<div class="notice notice-error"><p>Classe HNG_Financial_Analytics não encontrada.</p></div>';
            return;
        }
        
        // Get analytics data
        $analysis = HNG_Financial_Analytics::get_detailed_analysis($start_date, $end_date);
        $summary = $analysis['summary'];
        $split = $analysis['summary_split'];
        
        ?>
        <div class="wrap hng-wrap hng-financial-dashboard">
            <style>
                .hng-financial-dashboard {
                    max-width: 1400px;
                    margin: 0 auto;
                }
                
                .hng-financial-dashboard h1 {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    color: #1d2327;
                    margin-bottom: 30px;
                }
                
                .hng-financial-dashboard .dashicons {
                    font-size: 32px;
                    width: 32px;
                    height: 32px;
                    color: #0073aa;
                }
                
                .hng-period-selector {
                    background: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    display: flex;
                    gap: 12px;
                    align-items: center;
                    flex-wrap: wrap;
                }
                
                .hng-period-selector select,
                .hng-period-selector .button {
                    padding: 8px 12px;
                    border-radius: 4px;
                    border: 1px solid #ddd;
                    font-size: 14px;
                }
                
                .hng-period-selector select {
                    background: white;
                    cursor: pointer;
                    min-width: 150px;
                }
                
                .hng-period-selector .button {
                    cursor: pointer;
                    background: #0073aa;
                    color: white;
                    border: none;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                
                .hng-period-selector .button:hover {
                    background: #005a87;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 8px rgba(0,115,170,0.3);
                }
                
                .hng-card {
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
                    padding: 25px;
                    margin-bottom: 25px;
                    border-left: 4px solid #0073aa;
                }
                
                .hng-card h2 {
                    color: #1d2327;
                    margin: 0 0 15px 0;
                    font-size: 18px;
                    font-weight: 600;
                    padding-bottom: 12px;
                    border-bottom: 1px solid #eee;
                }
                
                .hng-card h4 {
                    color: #1d2327;
                    margin: 15px 0 10px 0;
                    font-size: 14px;
                    font-weight: 600;
                }
                
                .hng-stat-box {
                    transition: all 0.3s ease;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                    cursor: pointer;
                    position: relative;
                    overflow: hidden;
                }
                
                .hng-stat-box::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: rgba(255,255,255,0.1);
                    transition: left 0.3s ease;
                }
                
                .hng-stat-box:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
                }
                
                .hng-stat-box:hover::before {
                    left: 100%;
                }
                
                .hng-stat-box > * {
                    position: relative;
                    z-index: 1;
                }
                
                .wp-list-table thead {
                    background: #f6f6f6;
                }
                
                .wp-list-table th {
                    padding: 12px;
                    font-weight: 600;
                    color: #1d2327;
                    border-bottom: 2px solid #0073aa;
                }
                
                .wp-list-table td {
                    padding: 12px;
                    vertical-align: middle;
                }
                
                .wp-list-table tbody tr:hover {
                    background: #f9f9f9;
                }
                
                .description {
                    color: #666;
                    font-size: 12px;
                    font-style: italic;
                    margin-top: 5px;
                    display: block;
                }
                
                @media (max-width: 768px) {
                    .hng-period-selector {
                        flex-direction: column;
                    }
                    
                    .hng-period-selector select,
                    .hng-period-selector .button {
                        width: 100%;
                    }
                    
                    .hng-card {
                        padding: 15px;
                    }
                    
                    .hng-stat-box {
                        padding: 15px !important;
                    }
                    
                    .wp-list-table {
                        font-size: 12px;
                    }
                    
                    .wp-list-table th,
                    .wp-list-table td {
                        padding: 8px;
                    }
                }
            </style>
            
            <h1>
                <span class="dashicons dashicons-chart-line"></span>
                <?php esc_html_e('Análise Financeira', 'hng-commerce'); ?>
            </h1>
            
            <div class="hng-period-selector">
                <select id="hng-period-select">
                    <option value="today" <?php selected($period, 'today'); ?>>📅 Hoje</option>
                    <option value="7days" <?php selected($period, '7days'); ?>>📊 Últimos 7 dias</option>
                    <option value="30days" <?php selected($period, '30days'); ?>>📈 Últimos 30 dias</option>
                    <option value="90days" <?php selected($period, '90days'); ?>>📉 Últimos 90 dias</option>
                    <option value="year" <?php selected($period, 'year'); ?>>📆 Este ano</option>
                </select>
                <button type="button" class="button" id="hng-export-csv">
                    📥 <?php esc_html_e('Exportar CSV', 'hng-commerce'); ?>
                </button>
            </div>
            
            <!-- RESUMO GERAL -->
            <div class="hng-card">
                <h2>💰 <?php esc_html_e('Resumo Geral', 'hng-commerce'); ?></h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                    
                    <div class="hng-stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 12px;">
                        <div style="font-size: 11px; opacity: 0.85; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">
                            💵 <?php esc_html_e('Faturamento Total', 'hng-commerce'); ?>
                        </div>
                        <div style="font-size: 32px; font-weight: 700; margin-top: 12px; line-height: 1;">
                            R$ <?php echo esc_html(number_format($summary['total_revenue'], 2, ',', '.')); ?>
                        </div>
                        <div style="font-size: 13px; margin-top: 8px; opacity: 0.9;">
                            📦 <?php echo esc_html(intval($summary['orders_count'])); ?> <?php esc_html_e('pedidos', 'hng-commerce'); ?>
                        </div>
                    </div>
                    
                    <div class="hng-stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 24px; border-radius: 12px;">
                        <div style="font-size: 11px; opacity: 0.85; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">
                            💸 <?php esc_html_e('Custo Total', 'hng-commerce'); ?>
                        </div>
                        <div style="font-size: 32px; font-weight: 700; margin-top: 12px; line-height: 1;">
                            R$ <?php echo esc_html(number_format($summary['total_cost'], 2, ',', '.')); ?>
                        </div>
                        <div style="font-size: 13px; margin-top: 8px; opacity: 0.9;">
                            &nbsp;
                        </div>
                    </div>
                    
                    <div class="hng-stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 24px; border-radius: 12px;">
                        <div style="font-size: 11px; opacity: 0.85; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">
                            ✨ <?php esc_html_e('Lucro Total', 'hng-commerce'); ?>
                        </div>
                        <div style="font-size: 32px; font-weight: 700; margin-top: 12px; line-height: 1;">
                            R$ <?php echo esc_html(number_format($summary['total_profit'], 2, ',', '.')); ?>
                        </div>
                        <div style="font-size: 13px; margin-top: 8px; opacity: 0.9;">
                            📊 <?php echo esc_html(number_format($summary['profit_margin'], 2, ',', '.')); ?>% <?php esc_html_e('margem', 'hng-commerce'); ?>
                        </div>
                    </div>
                    
                    <div class="hng-stat-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: #333; padding: 24px; border-radius: 12px;">
                        <div style="font-size: 11px; opacity: 0.75; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">
                            🎯 <?php esc_html_e('Ticket Médio', 'hng-commerce'); ?>
                        </div>
                        <div style="font-size: 32px; font-weight: 700; margin-top: 12px; line-height: 1;">
                            R$ <?php echo esc_html(number_format($summary['average_order_value'], 2, ',', '.')); ?>
                        </div>
                        <div style="font-size: 13px; margin-top: 8px; opacity: 0.8;">
                            &nbsp;
                        </div>
                    </div>
                </div>
            </div>

            <!-- ORIGEM DOS DADOS: SITE vs GATEWAY -->
            <div class="hng-card" style="margin-top: 20px;">
                <h2>🔀 <?php esc_html_e('Origem dos Dados (Site vs Gateway)', 'hng-commerce'); ?></h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div class="hng-stat-box" style="background: linear-gradient(135deg, #34d399 0%, #10b981 100%); color: white; padding: 20px; border-radius: 12px;">
                        <div style="font-size: 11px; opacity: 0.85; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">🌐 <?php esc_html_e('Site (rastreamento)', 'hng-commerce'); ?></div>
                        <div style="font-size: 18px; font-weight: 700; margin-top: 8px;">R$ <?php echo esc_html(number_format($split['site']['revenue'], 2, ',', '.')); ?></div>
                        <div style="font-size: 12px; margin-top: 6px; opacity: 0.9;">📦 <?php echo esc_html(intval($split['site']['orders'])); ?> <?php esc_html_e('pedidos', 'hng-commerce'); ?> • 🎫 R$ <?php echo esc_html(number_format($split['site']['aov'], 2, ',', '.')); ?></div>
                    </div>
                    <div class="hng-stat-box" style="background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%); color: white; padding: 20px; border-radius: 12px;">
                        <div style="font-size: 11px; opacity: 0.85; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">💳 <?php esc_html_e('Gateways (integração)', 'hng-commerce'); ?></div>
                        <div style="font-size: 18px; font-weight: 700; margin-top: 8px;">R$ <?php echo esc_html(number_format($split['gateway']['revenue'], 2, ',', '.')); ?></div>
                        <div style="font-size: 12px; margin-top: 6px; opacity: 0.9;">📦 <?php echo esc_html(intval($split['gateway']['orders'])); ?> <?php esc_html_e('pedidos', 'hng-commerce'); ?> • 🎫 R$ <?php echo esc_html(number_format($split['gateway']['aov'], 2, ',', '.')); ?></div>
                    </div>
                    <div class="hng-stat-box" style="background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%); color: white; padding: 20px; border-radius: 12px;">
                        <div style="font-size: 11px; opacity: 0.85; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;">➕ <?php esc_html_e('Total Combinado', 'hng-commerce'); ?></div>
                        <div style="font-size: 18px; font-weight: 700; margin-top: 8px;">R$ <?php echo esc_html(number_format($split['combined']['revenue'], 2, ',', '.')); ?></div>
                        <div style="font-size: 12px; margin-top: 6px; opacity: 0.9;">📦 <?php echo esc_html(intval($split['combined']['orders'])); ?> <?php esc_html_e('pedidos', 'hng-commerce'); ?> • 🎫 R$ <?php echo esc_html(number_format($split['combined']['aov'], 2, ',', '.')); ?></div>
                    </div>
                </div>
                <p class="description"><?php esc_html_e('Site: pedidos com origem rastreada (source_page_id). Gateway: pedidos sem origem rastreada, via integrações.', 'hng-commerce'); ?></p>
            </div>
            
            <!-- LUCRO POR CATEGORIA -->
            <div class="hng-card" style="margin-top: 20px;">
                <h2>💎 <?php esc_html_e('Lucro por Categoria', 'hng-commerce'); ?></h2>
                <?php self::render_table($analysis['by_category'], [
                    'category_name' => esc_html__('Categoria', 'hng-commerce'),
                    'revenue' => esc_html__('Faturamento', 'hng-commerce'),
                    'cost' => __('Custo', 'hng-commerce'),
                    'profit' => __('Lucro', 'hng-commerce'),
                    'profit_margin' => __('Margem', 'hng-commerce'),
                    'quantity' => __('Qtd. Vendas', 'hng-commerce'),
                ], ['revenue', 'cost', 'profit']); ?>
            </div>
            
            <!-- FATURAMENTO POR CATEGORIA -->
            <div class="hng-card" style="margin-top: 20px;">
                <h2>📊 <?php esc_html_e('Faturamento por Categoria', 'hng-commerce'); ?></h2>
                <div style="margin-top: 25px;">
                    <?php 
                    $revenues = array_column($analysis['by_category'], 'revenue');
                    $max_revenue = !empty($revenues) ? max($revenues) : 1;
                    foreach ($analysis['by_category'] as $cat): 
                        $percentage = $max_revenue > 0 ? round(($cat['revenue'] / $max_revenue) * 100) : 0;
                    ?>
                        <div style="display: flex; align-items: center; margin-bottom: 20px; justify-content: space-between;">
                            <div style="flex-shrink: 0; width: 160px;">
                                <span style="font-weight: 600; display: block; color: #1d2327; margin-bottom: 4px;"><?php echo esc_html($cat['category_name']); ?></span>
                            </div>
                            <div style="flex: 1; background: #e8e8e8; height: 28px; margin: 0 15px; border-radius: 4px; overflow: hidden; position: relative; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                                <div style="background: linear-gradient(90deg, #667eea, #764ba2); height: 100%; width: <?php echo esc_attr($percentage); ?>%; transition: width 0.3s ease; position: relative;">
                                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(180deg, rgba(255,255,255,0.3) 0%, transparent 100%);"></div>
                                </div>
                            </div>
                            <div style="text-align: right; flex-shrink: 0; width: 140px;">
                                <strong style="display: block; color: #0073aa; font-size: 16px;">R$ <?php echo esc_html(number_format($cat['revenue'], 2, ',', '.')); ?></strong>
                                <small style="color: #666;"><?php echo esc_html($percentage); ?>%</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- LUCRO POR TIPO DE PRODUTO -->
            <div class="hng-card" style="margin-top: 20px;">
                <h2>🏆 <?php esc_html_e('Lucro por Tipo de Produto', 'hng-commerce'); ?></h2>
                <?php self::render_table($analysis['by_product_type'], [
                    'type_name' => esc_html__('Tipo', 'hng-commerce'),
                    'revenue' => esc_html__('Faturamento', 'hng-commerce'),
                    'cost' => esc_html__('Custo', 'hng-commerce'),
                    'profit' => esc_html__('Lucro', 'hng-commerce'),
                    'profit_margin' => esc_html__('Margem', 'hng-commerce'),
                    'quantity' => esc_html__('Qtd. Vendas', 'hng-commerce'),
                ], ['revenue', 'cost', 'profit']); ?>
            </div>
            
            <!-- FATURAMENTO POR TIPO -->
            <div class="hng-card" style="margin-top: 20px;">
                <h2>📈 <?php esc_html_e('Faturamento por Tipo de Produto', 'hng-commerce'); ?></h2>
                <div style="margin-top: 25px;">
                    <?php 
                    $type_revenues = array_column($analysis['by_product_type'], 'revenue');
                    $max_type_revenue = !empty($type_revenues) ? max($type_revenues) : 1;
                    foreach ($analysis['by_product_type'] as $type): 
                        $type_percentage = $max_type_revenue > 0 ? round(($type['revenue'] / $max_type_revenue) * 100) : 0;
                    ?>
                        <div style="display: flex; align-items: center; margin-bottom: 20px; justify-content: space-between;">
                            <div style="flex-shrink: 0; width: 160px;">
                                <span style="font-weight: 600; display: block; color: #1d2327; margin-bottom: 4px;"><?php echo esc_html($type['type_name']); ?></span>
                            </div>
                            <div style="flex: 1; background: #e8e8e8; height: 28px; margin: 0 15px; border-radius: 4px; overflow: hidden; position: relative; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                                <div style="background: linear-gradient(90deg, #f093fb, #f5576c); height: 100%; width: <?php echo esc_attr($type_percentage); ?>%; transition: width 0.3s ease; position: relative;">
                                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(180deg, rgba(255,255,255,0.3) 0%, transparent 100%);"></div>
                                </div>
                            </div>
                            <div style="text-align: right; flex-shrink: 0; width: 140px;">
                                <strong style="display: block; color: #0073aa; font-size: 16px;">R$ <?php echo esc_html(number_format($type['revenue'], 2, ',', '.')); ?></strong>
                                <small style="color: #666;"><?php echo esc_html($type_percentage); ?>%</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- TOP PRODUTOS -->
            <div class="hng-card" style="margin-top: 20px;">
                <h2>⭐ <?php esc_html_e('Top 10 Produtos por Lucro', 'hng-commerce'); ?></h2>
                <?php self::render_table($analysis['top_products'], [
                    'product_name' => esc_html__('Produto', 'hng-commerce'),
                    'revenue' => esc_html__('Faturamento', 'hng-commerce'),
                    'cost' => esc_html__('Custo', 'hng-commerce'),
                    'profit' => esc_html__('Lucro', 'hng-commerce'),
                    'profit_margin' => esc_html__('Margem', 'hng-commerce'),
                    'quantity' => __('Vendido', 'hng-commerce'),
                ], ['revenue', 'cost', 'profit']); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#hng-period-select').change(function() {
                const period = $(this).val();
                window.location.href = '<?php echo esc_url(admin_url('admin.php?page=hng-financial')); ?>&period=' + period;
            });
            
            $('#hng-export-csv').click(function() {
                const period = $('#hng-period-select').val();
                window.location.href = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=hng_export_financial_csv&period=' + period;
            });
        });
        </script>
        <?php
    }
    
    /**
     * Helper to render table with currency formatting
     */
    private static function render_table($data, $headers, $currency_cols = []) {
        if (empty($data)) {
            echo '<p>' . esc_html__('Nenhum dado disponível', 'hng-commerce') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php foreach ($headers as $key => $label): ?>
                        <th><?php echo esc_html($label); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <?php foreach ($headers as $key => $label): ?>
                            <td>
                                <?php 
                                $value = $row[$key] ?? '';
                                if (in_array($key, $currency_cols)) {
                                    echo 'R$ ' . esc_html(number_format(floatval($value), 2, ',', '.'));
                                } elseif (strpos($key, 'margin') !== false) {
                                    echo esc_html($value) . '%';
                                } else {
                                    echo esc_html($value);
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
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

