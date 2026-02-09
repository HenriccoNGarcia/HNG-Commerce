<?php
/**
 * AJAX Handlers for Financial and Conversion Analytics Export
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Analytics_Export {
    
    public static function init() {
        // Register AJAX handlers
        add_action('wp_ajax_hng_export_financial_csv', [self::class, 'export_financial_csv']);
        add_action('wp_ajax_hng_export_conversion_csv', [self::class, 'export_conversion_csv']);
    }
    
    /**
     * Export financial analytics to CSV
     */
    public static function export_financial_csv() {
        // Nonce verification for AJAX export
        $nonce = $_POST['nonce'] ?? '';
        $nonce_ok = wp_verify_nonce($nonce, 'hng_reports') || wp_verify_nonce($nonce, 'hng-commerce-admin');
        if (!$nonce_ok) {
            wp_die('Sessão expirada');
        }
        // Check if user can manage plugin
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        
        // Get period
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only export action, no data modification
        $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '30days';
        
        // Get date range
        $dates = self::get_period_dates($period);
        
        // Load analytics class
        if (!class_exists('HNG_Financial_Analytics')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/financial/class-financial-analytics.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/financial/class-financial-analytics.php';
            } else {
                wp_die('Classe não encontrada');
            }
        }
        
        // Get analytics data
        $analysis = HNG_Financial_Analytics::get_detailed_analysis($dates['start'], $dates['end']);
        
        // Generate CSV
        $csv = self::generate_financial_csv($analysis, $dates);
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analise-financeira-' . gmdate('Y-m-d') . '.csv"');
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data is sanitized and not HTML
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data is sanitized and not HTML
        echo $csv;
        exit;
    }
    
    /**
     * Export conversion analytics to CSV
     */
    public static function export_conversion_csv() {
        // Nonce verification for AJAX export
        $nonce = $_POST['nonce'] ?? '';
        $nonce_ok = wp_verify_nonce($nonce, 'hng_reports') || wp_verify_nonce($nonce, 'hng-commerce-admin');
        if (!$nonce_ok) {
            wp_die('Sessão expirada');
        }
        // Check if user can manage plugin
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        
        // Get period
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only export action, no data modification
        $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '30days';
        
        // Get date range
        $dates = self::get_period_dates($period);
        
        // Load analytics class
        if (!class_exists('HNG_Conversion_Analytics')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/financial/class-conversion-analytics.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/financial/class-conversion-analytics.php';
            } else {
                wp_die('Classe não encontrada');
            }
        }
        
        // Get analytics data
        $data = HNG_Conversion_Analytics::get_conversion_analysis($dates['start'], $dates['end']);
        
        // Generate CSV
        $csv = self::generate_conversion_csv($data, $dates);
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio-conversoes-' . gmdate('Y-m-d') . '.csv"');
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data is sanitized and not HTML
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data is sanitized and not HTML
        echo $csv;
        exit;
    }
    
    /**
     * Generate financial CSV content
     */
    private static function generate_financial_csv($analysis, $dates) {
        $csv = '';
        
        // Title
        $csv .= "ANÁLISE FINANCEIRA\n";
        $csv .= "Período: " . self::safe_csv($dates['start']) . " a " . self::safe_csv($dates['end']) . "\n";
        $csv .= "Data de Exportação: " . gmdate('Y-m-d H:i:s') . "\n\n";
        
        // Summary
        $summary = $analysis['summary'];
        $csv .= "RESUMO GERAL\n";
        $csv .= "Faturamento Total;Custo Total;Lucro Total;Margem de Lucro;Número de Pedidos;Ticket Médio\n";
        $csv .= $summary['total_revenue'] . ";" . $summary['total_cost'] . ";" . $summary['total_profit'] . ";" . 
                $summary['profit_margin'] . "%;" . $summary['orders_count'] . ";" . $summary['average_order_value'] . "\n\n";
        
        // By Category
        $csv .= "LUCRO POR CATEGORIA\n";
        $csv .= "Categoria;Faturamento;Custo;Lucro;Margem;Qtd. Vendas\n";
        foreach ($analysis['by_category'] as $cat) {
            $csv .= self::safe_csv($cat['category_name']) . ";" . $cat['revenue'] . ";" . $cat['cost'] . ";" . $cat['profit'] . ";" . 
                    $cat['profit_margin'] . "%;" . $cat['quantity'] . "\n";
        }
        $csv .= "\n";
        
        // By Product Type
        $csv .= "LUCRO POR TIPO DE PRODUTO\n";
        $csv .= "Tipo;Faturamento;Custo;Lucro;Margem;Qtd. Vendas\n";
        foreach ($analysis['by_product_type'] as $type) {
            $csv .= self::safe_csv($type['type_name']) . ";" . $type['revenue'] . ";" . $type['cost'] . ";" . $type['profit'] . ";" . 
                    $type['profit_margin'] . "%;" . $type['quantity'] . "\n";
        }
        $csv .= "\n";
        
        // Top Products
        $csv .= "TOP 10 PRODUTOS POR LUCRO\n";
        $csv .= "Produto;Faturamento;Custo;Lucro;Margem;Qtd. Vendido\n";
        foreach ($analysis['top_products'] as $product) {
            $csv .= self::safe_csv($product['product_name']) . ";" . $product['revenue'] . ";" . $product['cost'] . ";" . $product['profit'] . ";" . 
                    $product['profit_margin'] . "%;" . $product['quantity'] . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Generate conversion CSV content
     */
    private static function generate_conversion_csv($data, $dates) {
        $csv = '';
        
        // Title
        $csv .= "RELATÓRIO DE CONVERSÕES E ABANDONO\n";
        $csv .= "Período: " . self::safe_csv($dates['start']) . " a " . self::safe_csv($dates['end']) . "\n";
        $csv .= "Data de Exportação: " . gmdate('Y-m-d H:i:s') . "\n\n";
        
        // Funnel
        $funnel = $data['conversion_funnel'];
        $csv .= "FUNIL DE CONVERSÁO\n";
        $csv .= "Etapa;Quantidade;Taxa de Conversão\n";
        foreach ($funnel['steps'] as $step) {
            $csv .= self::safe_csv($step['name']) . ";" . $step['count'] . ";" . $step['conversion'] . "%\n";
        }
        $csv .= "Conversão Final;;" . $funnel['final_conversion'] . "%\n\n";
        
        // Top Converting Templates
        $csv .= "TOP PÁGINAS POR CONVERSÕES\n";
        $csv .= "Página;Conversões;Faturamento;Ticket Médio\n";
        foreach ($data['top_converting_templates'] as $template) {
            $csv .= self::safe_csv($template['page_title']) . ";" . $template['conversions'] . ";R$ " . $template['revenue'] . ";R$ " . $template['average_value'] . "\n";
        }
        $csv .= "\n";
        
        // Top Converting Checkouts
        $csv .= "TOP CHECKOUTS POR CONVERSÕES\n";
        $csv .= "Checkout;Conversões;Faturamento;Ticket Médio\n";
        foreach ($data['top_converting_checkouts'] as $checkout) {
            $csv .= self::safe_csv($checkout['checkout_name']) . ";" . $checkout['conversions'] . ";R$ " . $checkout['revenue'] . ";R$ " . $checkout['average_value'] . "\n";
        }
        $csv .= "\n";
        
        // Cart Abandonment by Category
        $csv .= "TAXA DE ABANDONO POR CATEGORIA\n";
        $csv .= "Categoria;Carrinhos Abandonados;Pedidos Completos;Taxa Abandono;Valor Perdido\n";
        foreach ($data['cart_abandonment_by_category'] as $cat) {
            $csv .= self::safe_csv($cat['category_name']) . ";" . $cat['abandoned_carts'] . ";" . $cat['completed_orders'] . ";" . 
                    $cat['abandonment_rate'] . "%;R$ " . $cat['abandoned_value'] . "\n";
        }
        $csv .= "\n";
        
        // Cart Abandonment by Checkout
        $csv .= "TAXA DE ABANDONO POR MÉTODO DE CHECKOUT\n";
        $csv .= "Método;Carrinhos Abandonados;Pedidos Completos;Taxa Abandono;Valor Perdido\n";
        foreach ($data['cart_abandonment_by_checkout'] as $checkout) {
            $csv .= self::safe_csv($checkout['checkout_method']) . ";" . $checkout['abandoned_carts'] . ";" . $checkout['completed_orders'] . ";" . 
                    $checkout['abandonment_rate'] . "%;R$ " . $checkout['abandoned_value'] . "\n";
        }
        
        return $csv;
    }

    /**
     * Sanitize CSV field and mitigate formula injection
     */
    private static function safe_csv($value) {
        $text = sanitize_text_field((string) $value);
        $text = preg_replace("/[\r\n]+/", ' ', $text);
        // Prevent Excel/Sheets formula execution
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'])) {
            $text = "'" . $text;
        }
        return $text;
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

// Initialize on plugins_loaded
add_action('plugins_loaded', [HNG_Analytics_Export::class, 'init']);
