<?php
/**
 * PagSeguro Split Payment Integration
 * Integração completa de split payment com cálculo de taxas via API
 * 
 * @package HNG_Commerce
 * @since 1.2.16
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_PagSeguro_Split {
    
    /**
     * Calcular e preparar dados de split payment
     * 
     * ARQUITETURA CORRETA:
     * - Cliente coloca suas credenciais do PagBank
     * - Sistema calcula a taxa automaticamente via API Manager
     * - Split é enviado para o PagBank distribuir
     * 
     * @param WC_Order $order
     * @param string $payment_method (pix, credit_card, boleto)
     * @return array|WP_Error com split_rules preparadas para PagBank
     */
    public static function calculate_split_rules($order, $payment_method) {
        
        // 1. Obter dados do pedido
        $order_id = $order->get_id();
        $total_amount = floatval($order->get_total());
        
        // Detectar tipo de produto (physical, digital, subscription, etc)
        $product_type = self::detect_product_type($order);
        
        // 2. Chamar API para calcular taxas (autoridade central)
        $api_client = HNG_API_Client::instance();
        
        $fee_response = $api_client->calculate_fee([
            'amount' => $total_amount,
            'product_type' => $product_type,
            'gateway' => 'pagseguro',
            'payment_method' => $payment_method,
            'order_id' => $order_id
        ]);
        
        // Validar resposta
        if (is_wp_error($fee_response)) {
            return new WP_Error(
                'split_calc_error',
                'Erro ao calcular taxas de split: ' . $fee_response->get_error_message()
            );
        }
        
        // 3. Extrair valores de taxa
        $plugin_fee = floatval($fee_response['plugin_fee'] ?? 0);
        $gateway_fee = floatval($fee_response['gateway_fee'] ?? 0);
        $net_amount = floatval($fee_response['net_amount'] ?? ($total_amount - $plugin_fee - $gateway_fee));
        $tier = intval($fee_response['tier'] ?? 1);
        $is_fallback = $fee_response['is_fallback'] ?? false;
        
        // 4. Salvar dados de taxa no pedido (auditoria)
        update_post_meta($order_id, '_hng_split_api_response', $fee_response);
        update_post_meta($order_id, '_hng_split_plugin_fee', $plugin_fee);
        update_post_meta($order_id, '_hng_split_gateway_fee', $gateway_fee);
        update_post_meta($order_id, '_hng_split_net_amount', $net_amount);
        update_post_meta($order_id, '_hng_split_tier', $tier);
        update_post_meta($order_id, '_hng_split_payment_method', $payment_method);
        update_post_meta($order_id, '_hng_split_product_type', $product_type);
        update_post_meta($order_id, '_hng_split_is_fallback', $is_fallback ? '1' : '0');
        
        // 5. Preparar split_rules para PagBank (NOVA ARQUITETURA)
        // 
        // IMPORTANTE: O split agora funciona assim:
        // - Account ID do Cliente: Recebe o valor líquido (97,52)
        // - Account ID de HNG: Recebe a comissão (1,49) - CONFIGURADO NA API MANAGER
        // - Account ID do PagBank: Recebe a taxa (0,99) - CONFIGURADO NA API MANAGER
        //
        // Os Account IDs e percentuais são definidos na API Manager, não aqui.
        // Aqui apenas informamos ao PagBank como dividir.
        
        $split_rules = self::build_split_rules_from_api($total_amount, $fee_response);
        
        // Log de auditoria
        self::log_split_calculation($order_id, [
            'total_amount' => $total_amount,
            'plugin_fee' => $plugin_fee,
            'gateway_fee' => $gateway_fee,
            'net_amount' => $net_amount,
            'tier' => $tier,
            'payment_method' => $payment_method,
            'split_rules' => $split_rules,
            'is_fallback' => $is_fallback
        ]);
        
        return [
            'success' => true,
            'split_rules' => $split_rules,
            'total_amount' => $total_amount,
            'plugin_fee' => $plugin_fee,
            'gateway_fee' => $gateway_fee,
            'net_amount' => $net_amount,
            'tier' => $tier,
            'is_fallback' => $is_fallback,
            'product_type' => $product_type,
            'payment_method' => $payment_method
        ];
    }
    
    /**
     * Construir split_rules a partir da resposta da API
     * 
     * Isso usa a resposta da API Manager que já sabe quem recebe o quê
     * 
     * @param float $total_amount Valor total do pedido
     * @param array $fee_response Resposta da API com informações de split
     * @return array Split rules para PagBank
     */
    private static function build_split_rules_from_api($total_amount, $fee_response) {
        // A resposta da API já contém as informações de split
        // Se a API retornar os Account IDs, usamos. Senão, retornamos vazio
        // e deixamos o split ser feito manual no API Manager
        
        $split_rules = [];
        
        // Verificar se a API retornou informações de split
        if (!empty($fee_response['split_rules'])) {
            return $fee_response['split_rules'];
        }
        
        // Se não retornou, o split será feito manualmente no API Manager
        return $split_rules;
    }
    
    /**
     * Detectar tipo de produto do pedido
     * 
     * @param WC_Order $order
     * @return string (physical|digital|subscription|appointment|quote)
     */
    public static function detect_product_type($order) {
        // Tentar detectar pelo meta do pedido (se já foi definido)
        $product_type = get_post_meta($order->get_id(), '_hng_product_type', true);
        if ($product_type) {
            return $product_type;
        }
        
        // Analisar items do pedido
        $items = $order->get_items();
        
        if (empty($items)) {
            return 'physical'; // default
        }
        
        // Verificar tipos através dos produtos
        foreach ($items as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            // Verificar meta personalizado
            $product_type_meta = $product->get_meta('_hng_product_type');
            if ($product_type_meta) {
                return $product_type_meta;
            }
            
            // Fallback para produto virtual
            if ($product->is_virtual()) {
                return 'digital';
            }
        }
        
        return 'physical'; // default
    }
    
    /**
     * Log de auditoria para cálculos de split
     * 
     * @param int $order_id
     * @param array $data
     */
    private static function log_split_calculation($order_id, $data) {
        if (!function_exists('hng_files_log_append')) {
            return;
        }
        
        // Verificar se logging está habilitado
        if (!get_option('hng_split_logging_enabled', true)) {
            return;
        }
        
        $log_entry = sprintf(
            "[%s] Order #%d | Amount: R$ %.2f | Plugin Fee: R$ %.2f | Gateway Fee: R$ %.2f | Net: R$ %.2f | Tier: %d | Method: %s | Fallback: %s\n",
            gmdate('Y-m-d H:i:s'),
            $order_id,
            $data['total_amount'],
            $data['plugin_fee'],
            $data['gateway_fee'],
            $data['net_amount'],
            $data['tier'],
            $data['payment_method'],
            $data['is_fallback'] ? 'Yes' : 'No'
        );
        
        $log_file = WP_CONTENT_DIR . '/hng-split-payments.log';
        hng_files_log_append($log_file, $log_entry);
    }
    
    /**
     * Registrar transação confirmada no servidor central
     * Para fins de auditoria e relatórios financeiros
     * 
     * @param WC_Order $order
     * @param array $pag_seguro_response Resposta do PagSeguro
     */
    public static function register_confirmed_transaction($order, $pag_seguro_response) {
        $api_client = HNG_API_Client::instance();
        
        // Obter dados que foram salvos durante calculate_split_rules
        $plugin_fee = floatval(get_post_meta($order->get_id(), '_hng_split_plugin_fee', true));
        $gateway_fee = floatval(get_post_meta($order->get_id(), '_hng_split_gateway_fee', true));
        $net_amount = floatval(get_post_meta($order->get_id(), '_hng_split_net_amount', true));
        $tier = intval(get_post_meta($order->get_id(), '_hng_split_tier', true));
        $payment_method = get_post_meta($order->get_id(), '_hng_split_payment_method', true);
        
        // Registrar no servidor central
        $result = $api_client->register_transaction($order->get_id(), [
            'amount' => floatval($order->get_total()),
            'gateway' => 'pagseguro',
            'payment_method' => $payment_method,
            'plugin_fee' => $plugin_fee,
            'gateway_fee' => $gateway_fee,
            'net_amount' => $net_amount,
            'tier' => $tier,
            'pag_seguro_order_id' => $pag_seguro_response['id'] ?? null,
            'external_transaction_id' => $pag_seguro_response['id'] ?? null
        ]);
        
        if (!is_wp_error($result)) {
            update_post_meta($order->get_id(), '_hng_transaction_registered', '1');
        }
        
        return $result;
    }
    
    /**
     * Obter dados de split para relatório
     * 
     * @param int $order_id
     * @return array
     */
    public static function get_split_data($order_id) {
        return [
            'plugin_fee' => floatval(get_post_meta($order_id, '_hng_split_plugin_fee', true)),
            'gateway_fee' => floatval(get_post_meta($order_id, '_hng_split_gateway_fee', true)),
            'net_amount' => floatval(get_post_meta($order_id, '_hng_split_net_amount', true)),
            'tier' => intval(get_post_meta($order_id, '_hng_split_tier', true)),
            'payment_method' => get_post_meta($order_id, '_hng_split_payment_method', true),
            'product_type' => get_post_meta($order_id, '_hng_split_product_type', true),
            'is_fallback' => get_post_meta($order_id, '_hng_split_is_fallback', true) === '1',
            'registered' => get_post_meta($order_id, '_hng_transaction_registered', true) === '1'
        ];
    }
}
