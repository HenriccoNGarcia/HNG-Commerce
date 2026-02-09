<?php
/**
 * Calculadora de Taxas Escalonadas por GMV
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helpers de DB e arquivos
if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
}
if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-files.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-files.php';
}

class HNG_Fee_Calculator {
    
    /**
     * Taxa má¯Â¿Â½nima por transaá¯Â¿Â½á¯Â¿Â½o (em reais)
     */
    const MINIMUM_FEE = 0.50;
    
    /**
     * Tiers de taxa baseados em GMV mensal
     */
    private $tiers = [];
    
    /**
     * Instá¯Â¿Â½ncia á¯Â¿Â½nica
     */
    private static $instance = null;
    
    /**
     * Obter instá¯Â¿Â½ncia
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        // Inicializa tiers em tempo de execução (permite uso de __() sem erro de expressão constante)
        $this->tiers = [
            1 => [
                'name' => __('Tier 1', 'hng-commerce'),
                'gmv_min' => 0,
                'gmv_max' => 10000,
                'fees' => [
                    'physical' => 1.99,
                    'digital' => 4.79,
                    'subscription' => 1.89,
                    'quote' => 1.49,
                    'appointment' => 1.89,
                ],
                'color' => '#6c757d',
                'icon' => '🌱',
            ],
            2 => [
                'name' => __('Tier 2', 'hng-commerce'),
                'gmv_min' => 10001,
                'gmv_max' => 50000,
                'fees' => [
                    'physical' => 1.49,
                    'digital' => 3.78,
                    'subscription' => 1.47,
                    'quote' => 0.97,
                    'appointment' => 1.47,
                ],
                'color' => '#17a2b8',
                'icon' => '🚀',
            ],
            3 => [
                'name' => __('Tier 3', 'hng-commerce'),
                'gmv_min' => 50001,
                'gmv_max' => PHP_INT_MAX,
                'fees' => [
                    'physical' => 0.97,
                    'digital' => 2.87,
                    'subscription' => 0.97,
                    'quote' => 0.87,
                    'appointment' => 0.97,
                ],
                'color' => '#28a745',
                'icon' => '📈',
            ],
        ];

        // Buscar taxas customizadas da API (se disponível)
        $api_fees = $this->fetch_fees_from_api();
        if ($api_fees !== false) {
            $this->tiers = $this->merge_api_fees($api_fees);
        }

        // Permitir filtrar tiers customizados
        $this->tiers = apply_filters('hng_fee_tiers', $this->tiers);
    }
    
    /**
     * Buscar taxas da API
     * 
     * Busca taxas atualizadas da API central.
     * Usa cache de 5 minutos para evitar requests excessivos.
     * 
     * @return array|false Array de taxas ou false se não disponível
     */
    private function fetch_fees_from_api() {
        // Usar cache de 5 minutos para não sobrecarregar a API
        $cache_key = 'hng_api_fees_data';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $api_url = 'https://api.hngdesenvolvimentos.com.br/admin/fees/get';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 5,
            'sslverify' => true,
            'headers' => [
                'X-Hng-Api-Key' => get_option('hng_api_key', ''),
            ]
        ));
        
        if (is_wp_error($response)) {
            error_log('[HNG] Erro ao buscar taxas da API: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['success']) || empty($data['fees'])) {
            error_log('[HNG] API retornou resposta inválida para taxas');
            return false;
        }

        // Verificar assinatura se bloco 'signed' estiver presente
        if (!empty($data['signed']) && class_exists('HNG_Signature')) {
            $verify = HNG_Signature::verify_signed_block($data['signed'], []);
            if (is_wp_error($verify)) {
                error_log('[HNG] Assinatura de taxas inválida: ' . $verify->get_error_message());
                return false;
            }
        }
        
        // Salvar também a taxa mínima se disponível
        if (isset($data['minimum_fee'])) {
            update_option('hng_minimum_fee', $data['minimum_fee']);
        }
        
        // Cache por 5 minutos
        set_transient($cache_key, $data['fees'], 300);
        
        return $data['fees'];
    }
    
    /**
     * Forçar atualização das taxas da API
     * 
     * @return bool Se conseguiu atualizar
     */
    public function refresh_fees_from_api() {
        delete_transient('hng_api_fees_data');
        self::$instance = null;
        return self::instance() !== null;
    }
    
    /**
     * Mesclar taxas da API com estrutura local
     * 
     * @param array $api_fees Taxas da API
     * @return array Tiers atualizados
     */
    private function merge_api_fees($api_fees) {
        $merged = [];
        
        foreach ($api_fees as $tier_data) {
            $tier_num = (int)$tier_data['tier'];
            
            // Usar dados da API completamente, incluindo name, color, icon
            $merged[$tier_num] = [
                'name' => $tier_data['name'] ?? ('Tier ' . $tier_num),
                'gmv_min' => (int)($tier_data['gmv_min'] ?? 0),
                'gmv_max' => (int)($tier_data['gmv_max'] ?? PHP_INT_MAX),
                'fees' => [
                    'physical' => (float)($tier_data['fees']['physical'] ?? 0),
                    'digital' => (float)($tier_data['fees']['digital'] ?? 0),
                    'subscription' => (float)($tier_data['fees']['subscription'] ?? 0),
                    'quote' => (float)($tier_data['fees']['quote'] ?? 0),
                    'appointment' => (float)($tier_data['fees']['appointment'] ?? 0),
                ],
                'color' => $tier_data['color'] ?? '#6c757d',
                'icon' => $tier_data['icon'] ?? '🎯',
            ];
        }
        
        // Se API retornou dados, usar eles; senão, manter locais
        return !empty($merged) ? $merged : $this->tiers;
    }
    
    /**
     * Calcular GMV (Gross Merchandise Volume) do mês atual
     * 
     * @return float
     */
    public function get_current_month_gmv() {
        global $wpdb;
        
        $first_day = gmdate('Y-m-01 00:00:00');
        $last_day = gmdate('Y-m-t 23:59:59');
        
        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . $wpdb->prefix . 'hng_orders`');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total), 0) as gmv
            FROM {$orders_table_sql}
            WHERE status IN ('hng-processing', 'hng-completed')
            AND created_at BETWEEN %s AND %s",
            $first_day,
            $last_day
        ));
        
        return (float) $result;
    }
    
    /**
     * Obter tier atual baseado no GMV
     * 
     * @param float|null $gmv GMV customizado (se null, calcula do mês)
     * @return int Número do tier (1-3)
     */
    public function get_current_tier($gmv = null) {
        if (is_null($gmv)) {
            $gmv = $this->get_current_month_gmv();
        }
        
        foreach ($this->tiers as $tier_number => $tier_data) {
            if ($gmv >= $tier_data['gmv_min'] && $gmv <= $tier_data['gmv_max']) {
                return $tier_number;
            }
        }
        
        return 1; // Fallback: Tier Iniciante
    }
    
    /**
     * Obter dados completos de um tier
     * 
     * @param int $tier_number
     * @return array|null
     */
    public function get_tier_data($tier_number) {
        return $this->tiers[$tier_number] ?? null;
    }
    
    /**
     * Obter todos os tiers
     * 
     * @return array
     */
    public function get_all_tiers() {
        return $this->tiers;
    }
    
    /**
     * Calcular taxa do plugin (LOCAL - fallback, servidor é authoritative)
     * 
     * IMPORTANTE: Este cálculo é apenas para referência/fallback.
     * O servidor (_api-server) é a fonte de verdade para validação de taxas.
     * 
     * @param float $amount Valor da venda
     * @param string $product_type Tipo do produto (physical, digital, subscription)
     * @param int|null $tier Tier específico (se null, usa o atual)
     * @return float Valor da taxa em reais
     */
    public function calculate_plugin_fee($amount, $product_type = 'physical', $tier = null) {
        // Normalizar e sanitizar pará¡Â¢metros
        $amount = floatval($amount);
        if (function_exists('wp_unslash')) {
            $product_type = wp_unslash($product_type);
        }

        $product_type = sanitize_key(strtolower((string) $product_type));
        $tier = is_null($tier) ? $this->get_current_tier() : absint($tier);

        $tier_data = $this->get_tier_data($tier);
        if (!$tier_data || !is_array($tier_data)) {
            return self::MINIMUM_FEE;
        }

        // Se o tipo ná¯Â¿Â½o existe, usar physical como padrá¯Â¿Â½o
        if (!isset($tier_data['fees'][$product_type])) {
            $product_type = 'physical';
        }

        $percentage = floatval($tier_data['fees'][$product_type] ?? 0);
        $calculated_fee = ($amount * $percentage) / 100.0;

        // Aplicar taxa má¯Â¿Â½nima de R$ 0,50
        return max($calculated_fee, self::MINIMUM_FEE);
    }
    
    /**
     * Calcular taxa do gateway
     * 
     * @param float $amount Valor da venda
     * @param string $gateway Gateway usado (asaas, mercadopago, etc)
     * @param string $method Má¯Â¿Â½todo de pagamento (pix, boleto, credit_card)
     * @return float Valor da taxa em reais
     */
    public function calculate_gateway_fee($amount, $gateway, $method) {
        $amount = floatval($amount);
        if (function_exists('wp_unslash')) {
            $gateway = wp_unslash($gateway);
            $method = wp_unslash($method);
        }

        $gateway = sanitize_key(strtolower((string) $gateway));
        $method = sanitize_key(strtolower((string) $method));
        
        // Taxas dos gateways
        $gateway_fees = [
            'asaas' => [
                'pix' => ['type' => 'percentage', 'value' => 0.99],
                'boleto' => ['type' => 'fixed', 'value' => 3.49],
                'credit_card' => ['type' => 'mixed', 'percentage' => 2.99, 'fixed' => 0.49]
            ],
            'mercadopago' => [
                'pix' => ['type' => 'percentage', 'value' => 0.99],
                'credit_card' => ['type' => 'mixed', 'percentage' => 4.99, 'fixed' => 0.39],
                'debit_card' => ['type' => 'percentage', 'value' => 3.49]
            ],
            'pagseguro' => [
                'pix' => ['type' => 'percentage', 'value' => 0.99],
                'boleto' => ['type' => 'fixed', 'value' => 3.50],
                'credit_card' => ['type' => 'mixed', 'percentage' => 3.79, 'fixed' => 0.60]
            ],
            'stripe' => [
                'credit_card' => ['type' => 'mixed', 'percentage' => 3.99, 'fixed' => 0.39],
                'pix' => ['type' => 'percentage', 'value' => 1.49]
            ],
            'pagarme' => [
                'pix' => ['type' => 'percentage', 'value' => 0.99],
                'boleto' => ['type' => 'fixed', 'value' => 3.49],
                'credit_card' => ['type' => 'mixed', 'percentage' => 2.99, 'fixed' => 0.39]
            ]
        ];
        
        // Permitir filtrar taxas customizadas
        $gateway_fees = apply_filters('hng_gateway_fees', $gateway_fees);
        
        if (!isset($gateway_fees[$gateway][$method])) {
            return 0;
        }
        
        $fee_config = $gateway_fees[$gateway][$method];
        $fee = 0;
        
        switch ($fee_config['type']) {
            case 'percentage':
                $fee = ($amount * $fee_config['value']) / 100;
                break;
                
            case 'fixed':
                $fee = $fee_config['value'];
                break;
                
            case 'mixed':
                $fee = (($amount * $fee_config['percentage']) / 100) + $fee_config['fixed'];
                break;
        }
        
        return $fee;
    }
    
    /**
     * Calcular todas as taxas de uma venda
     * 
     * @param float $amount Valor da venda
     * @param string $product_type Tipo do produto
     * @param string $gateway Gateway usado
     * @param string $method Má¯Â¿Â½todo de pagamento
     * @return array Array com breakdown das taxas
     */
    public function calculate_all_fees($amount, $product_type, $gateway, $method) {
        $amount = floatval($amount);
        if (function_exists('wp_unslash')) {
            $product_type = wp_unslash($product_type);
            $gateway = wp_unslash($gateway);
            $method = wp_unslash($method);
        }

        $product_type = sanitize_key(strtolower((string) $product_type));
        $gateway = sanitize_key(strtolower((string) $gateway));
        $method = sanitize_key(strtolower((string) $method));

        $current_tier = $this->get_current_tier();
        $tier_data = $this->get_tier_data($current_tier);

        $plugin_fee = $this->calculate_plugin_fee($amount, $product_type);
        $gateway_fee = $this->calculate_gateway_fee($amount, $gateway, $method);
        
        $total_fees = $plugin_fee + $gateway_fee;
        $net_amount = $amount - $total_fees;
        
        // Calcular taxa percentual efetiva (considerando o má¯Â¿Â½nimo)
        $effective_percentage = ($plugin_fee / $amount) * 100;
        $is_minimum_fee = ($plugin_fee == self::MINIMUM_FEE);
        
        return [
            'gross_amount' => $amount,
            'plugin_fee' => $plugin_fee,
            'plugin_fee_percentage' => $tier_data['fees'][$product_type] ?? 0,
            'plugin_fee_effective' => $effective_percentage,
            'is_minimum_fee' => $is_minimum_fee,
            'minimum_fee' => self::MINIMUM_FEE,
            'gateway_fee' => $gateway_fee,
            'total_fees' => $total_fees,
            'net_amount' => $net_amount,
            'tier' => $current_tier,
            'tier_name' => $tier_data['name'] ?? '',
            'product_type' => $product_type,
            'gateway' => $gateway,
            'payment_method' => $method
        ];
    }
    
    /**
     * Quanto falta para o prá¯Â¿Â½ximo tier
     * 
     * @return array|null Dados do prá¯Â¿Â½ximo tier ou null se já¯Â¿Â½ está¯Â¿Â½ no má¯Â¿Â½ximo
     */
    public function get_next_tier_info() {
        $current_tier = $this->get_current_tier();
        $current_gmv = $this->get_current_month_gmv();
        
        if ($current_tier >= 3) {
            return null; // Já está no tier máximo
        }
        
        $next_tier = $current_tier + 1;
        $next_tier_data = $this->get_tier_data($next_tier);
        
        $remaining = $next_tier_data['gmv_min'] - $current_gmv;
        
        return [
            'current_tier' => $current_tier,
            'next_tier' => $next_tier,
            'next_tier_name' => $next_tier_data['name'],
            'current_gmv' => $current_gmv,
            'target_gmv' => $next_tier_data['gmv_min'],
            'remaining' => $remaining,
            'progress_percentage' => min(100, ($current_gmv / $next_tier_data['gmv_min']) * 100),
            'savings_physical' => $this->tiers[$current_tier]['fees']['physical'] - $next_tier_data['fees']['physical'],
            'savings_digital' => $this->tiers[$current_tier]['fees']['digital'] - $next_tier_data['fees']['digital']
        ];
    }
    
    /**
     * Obter economia se cliente estivesse em tier superior
     * 
     * @param float $total_sales Total de vendas do perá¯Â¿Â½odo
     * @param string $product_type Tipo predominante de produto
     * @return array Dados de economia
     */
    public function calculate_potential_savings($total_sales, $product_type = 'physical') {
        $total_sales = floatval($total_sales);
        if (function_exists('wp_unslash')) {
            $product_type = wp_unslash($product_type);
        }

        $product_type = sanitize_key(strtolower((string) $product_type));
        $current_tier = $this->get_current_tier();

        if ($current_tier >= 3) {
            /* translators: shown when the user is already at the maximum tier and cannot progress further */
            $msg = __('Você já está no tier máximo!', 'hng-commerce');
            return ['savings' => 0, 'message' => $msg];
        }

        $current_fee = $this->calculate_plugin_fee($total_sales, $product_type, $current_tier);
        $next_tier_fee = $this->calculate_plugin_fee($total_sales, $product_type, $current_tier + 1);

        $savings = $current_fee - $next_tier_fee;

        /* translators: %1$.2f = amount saved (R$), %2$s = next tier name. Use ordered placeholders so translators can reorder if needed. */
        $format = __( 'Vocá¡Âª economizaria R$ %1$.2f por má¡Âªs no tier %2$s!', 'hng-commerce' );
        $message = sprintf( $format, $savings, (string) $this->tiers[$current_tier + 1]['name'] );

        return [
            'savings' => $savings,
            'current_fee' => $current_fee,
            'next_tier_fee' => $next_tier_fee,
            'message' => $message
        ];
    }
    
    /**
     * Registrar transaá¯Â¿Â½á¯Â¿Â½o com taxas
     * 
     * @param int $order_id ID do pedido
     * @param array $fee_data Dados das taxas calculadas
     * @return int|false ID da transaá¯Â¿Â½á¯Â¿Â½o ou false em erro
     */
    public function register_transaction($order_id, $fee_data) {
        global $wpdb;
        
        $table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_transactions') : ($wpdb->prefix . 'hng_transactions');
        // Unslaash e sanitiza entrada
        if (function_exists('wp_unslash')) {
            $fee_data = wp_unslash($fee_data);
        }

        $order_id = intval($order_id);

        $san = $this->sanitize_fee_data($fee_data);

        $data = [
            'order_id' => $order_id,
            'gateway_name' => $san['gateway'] ?? '',
            'payment_method' => $san['payment_method'] ?? '',
            'gross_amount' => floatval($san['gross_amount'] ?? 0),
            'gateway_fee' => floatval($san['gateway_fee'] ?? 0),
            'plugin_fee' => floatval($san['plugin_fee'] ?? 0),
            'plugin_tier' => intval($san['tier'] ?? 0),
            'product_type' => $san['product_type'] ?? '',
            'gmv_month' => $this->get_current_month_gmv(),
            'net_amount' => floatval($san['net_amount'] ?? 0),
            'created_at' => current_time('mysql')
        ];

        $formats = [ '%d', '%s', '%s', '%f', '%f', '%f', '%d', '%s', '%f', '%f', '%s' ];

        $result = $wpdb->insert($table, $data, $formats);

        if ($result === false) {
            $msg = 'HNG: Erro ao registrar transaá§á¡o: ' . $wpdb->last_error;
            if (function_exists('hng_files_log_append')) {
                hng_files_log_append(HNG_COMMERCE_PATH . 'logs/transactions.log', gmdate('c') . ' ' . $msg . "\n");
            }
            return false;
        }

        // Salvar tambá¡Â©m como meta do pedido (backup) com dados sanitizados
        update_post_meta($order_id, '_hng_transaction_data', $san);

        return $wpdb->insert_id;
    }

    /**
     * Sanitiza um array de dados de taxa antes de persistir
     */
    private function sanitize_fee_data($data) {
        if (!is_array($data)) {
            return [];
        }

        $clean = [];
        $clean['gateway'] = isset($data['gateway']) ? sanitize_text_field((string) $data['gateway']) : '';
        $clean['payment_method'] = isset($data['payment_method']) ? sanitize_text_field((string) $data['payment_method']) : '';
        $clean['gross_amount'] = isset($data['gross_amount']) ? floatval($data['gross_amount']) : 0.0;
        $clean['gateway_fee'] = isset($data['gateway_fee']) ? floatval($data['gateway_fee']) : 0.0;
        $clean['plugin_fee'] = isset($data['plugin_fee']) ? floatval($data['plugin_fee']) : 0.0;
        $clean['tier'] = isset($data['tier']) ? intval($data['tier']) : 0;
        $clean['product_type'] = isset($data['product_type']) ? sanitize_key((string) $data['product_type']) : '';
        $clean['net_amount'] = isset($data['net_amount']) ? floatval($data['net_amount']) : 0.0;

        return $clean;
    }
    
    /**
     * Obter transaá¯Â¿Â½á¯Â¿Â½es de um perá¯Â¿Â½odo
     * 
     * @param string $start_date Data iná¯Â¿Â½cio (Y-m-d)
     * @param string $end_date Data fim (Y-m-d)
     * @return array
     */
    public function get_transactions($start_date, $end_date) {
        global $wpdb;
        
        $trans_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_transactions') : ($wpdb->prefix . 'hng_transactions');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $trans_table sanitized via hng_db_full_table_name()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for fee calculation, pricing analysis
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $trans_table sanitized via hng_db_full_table_name()
        return $wpdb->get_results($wpdb->prepare(
            "SELECT *
            FROM {$trans_table}
            WHERE gmdate(created_at) BETWEEN %s AND %s
            ORDER BY created_at DESC",
            $start_date,
            $end_date
        ), ARRAY_A);
    }
}
