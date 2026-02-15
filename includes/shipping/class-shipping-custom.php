<?php
/**
 * Custom/Own Shipping Method
 * 
 * Permite que o admin configure sua própria transportadora
 * quando não usa serviços integrados (Correios, Melhor Envio, etc)
 * 
 * @package HNG_Commerce
 * @since 1.2.16
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shipping_Custom extends HNG_Shipping_Method {
    
    public function __construct() {
        $this->id = 'custom';
        $this->title = __('Transportadora Própria', 'hng-commerce');
        $this->method_title = __('Transportadora Própria', 'hng-commerce');
        $this->method_description = __('Configure sua própria transportadora ou serviço de entrega', 'hng-commerce');
        
        parent::__construct();
    }
    
    /**
     * Inicializar método (requerido pela classe pai)
     */
    public function init() {
        $this->enabled = $this->is_enabled();
    }
    
    /**
     * Implementação do método abstrato get_settings da classe pai
     */
    public function get_settings() {
        return self::get_custom_settings();
    }
    
    /**
     * Verificar se está habilitado
     */
    public function is_enabled() {
        return get_option('hng_shipping_custom_enabled', 'no') === 'yes';
    }
    
    /**
     * Calcular frete
     */
    public function calculate_shipping($package) {
        if (!$this->is_enabled()) {
            return [];
        }
        
        $rates = [];
        $zones = $this->get_shipping_zones();
        $destination_cep = preg_replace('/\D/', '', $package['destination']['postcode'] ?? '');
        
        if (empty($destination_cep)) {
            return [];
        }
        
        // Verificar cada zona
        foreach ($zones as $zone_id => $zone) {
            if (!$this->is_in_zone($destination_cep, $zone)) {
                continue;
            }
            
            // Calcular custo baseado nas regras da zona
            $cost = $this->calculate_zone_cost($zone, $package);
            
            if ($cost === false) {
                continue;
            }
            
            // Adicionar cada método da zona
            foreach ($zone['methods'] as $method_id => $method) {
                if (empty($method['enabled']) || $method['enabled'] !== 'yes') {
                    continue;
                }
                
                $method_cost = $this->calculate_method_cost($method, $package, $cost);
                
                $rates[] = [
                    'id' => 'custom:' . $zone_id . ':' . $method_id,
                    'label' => $method['name'] ?? __('Entrega', 'hng-commerce'),
                    'cost' => $method_cost,
                    'delivery_time' => $method['delivery_time'] ?? '',
                    'carrier' => 'custom',
                    'zone_id' => $zone_id,
                    'method_id' => $method_id,
                ];
            }
        }
        
        // Se não encontrou zona específica, usar taxa padrão
        if (empty($rates)) {
            $default_rate = $this->get_default_rate($package);
            if ($default_rate) {
                $rates[] = $default_rate;
            }
        }
        
        return $rates;
    }
    
    /**
     * Obter zonas de frete configuradas
     */
    public function get_shipping_zones() {
        return get_option('hng_shipping_custom_zones', []);
    }
    
    /**
     * Verificar se CEP está na zona
     */
    private function is_in_zone($cep, $zone) {
        if (empty($zone['cep_ranges'])) {
            return false;
        }
        
        $cep_num = (int) $cep;
        
        foreach ($zone['cep_ranges'] as $range) {
            $from = (int) preg_replace('/\D/', '', $range['from'] ?? '');
            $to = (int) preg_replace('/\D/', '', $range['to'] ?? '');
            
            if ($cep_num >= $from && $cep_num <= $to) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calcular custo base da zona
     */
    private function calculate_zone_cost($zone, $package) {
        $base_cost = floatval($zone['base_cost'] ?? 0);
        return $base_cost;
    }
    
    /**
     * Calcular custo do método
     */
    private function calculate_method_cost($method, $package, $base_cost) {
        $cost = $base_cost;
        
        // Adicionar custo fixo do método
        $cost += floatval($method['additional_cost'] ?? 0);
        
        // Calcular por peso se configurado
        if (!empty($method['cost_per_kg'])) {
            $total_weight = 0;
            foreach ($package['items'] as $item) {
                $weight = floatval(get_post_meta($item['product_id'], '_weight', true));
                $total_weight += $weight * $item['quantity'];
            }
            $cost += $total_weight * floatval($method['cost_per_kg']);
        }
        
        // Custo por item
        if (!empty($method['cost_per_item'])) {
            $total_items = 0;
            foreach ($package['items'] as $item) {
                $total_items += $item['quantity'];
            }
            $cost += $total_items * floatval($method['cost_per_item']);
        }
        
        // Custo percentual sobre total
        if (!empty($method['percent_cost']) && !empty($package['cart_subtotal'])) {
            $cost += $package['cart_subtotal'] * (floatval($method['percent_cost']) / 100);
        }
        
        return max(0, round($cost, 2));
    }
    
    /**
     * Obter taxa padrão
     */
    private function get_default_rate($package) {
        $default_enabled = get_option('hng_shipping_custom_default_enabled', 'no');
        
        if ($default_enabled !== 'yes') {
            return null;
        }
        
        $default_cost = floatval(get_option('hng_shipping_custom_default_cost', 0));
        $default_name = get_option('hng_shipping_custom_default_name', __('Frete', 'hng-commerce'));
        $default_time = get_option('hng_shipping_custom_default_time', '');
        
        return [
            'id' => 'custom:default',
            'label' => $default_name,
            'cost' => $default_cost,
            'delivery_time' => $default_time,
            'carrier' => 'custom',
        ];
    }
    
    /**
     * Obter configurações da transportadora própria (estático)
     */
    public static function get_custom_settings() {
        return [
            'enabled' => get_option('hng_shipping_custom_enabled', 'no'),
            'company_name' => get_option('hng_shipping_custom_company_name', ''),
            'company_cnpj' => get_option('hng_shipping_custom_company_cnpj', ''),
            'company_phone' => get_option('hng_shipping_custom_company_phone', ''),
            'company_email' => get_option('hng_shipping_custom_company_email', ''),
            'company_address' => get_option('hng_shipping_custom_company_address', ''),
            'company_city' => get_option('hng_shipping_custom_company_city', ''),
            'company_state' => get_option('hng_shipping_custom_company_state', ''),
            'company_cep' => get_option('hng_shipping_custom_company_cep', ''),
            'label_format' => get_option('hng_shipping_custom_label_format', 'a4'),
            'label_copies' => get_option('hng_shipping_custom_label_copies', 1),
            'tracking_prefix' => get_option('hng_shipping_custom_tracking_prefix', 'TR'),
            'tracking_url' => get_option('hng_shipping_custom_tracking_url', ''),
            'default_enabled' => get_option('hng_shipping_custom_default_enabled', 'no'),
            'default_cost' => get_option('hng_shipping_custom_default_cost', ''),
            'default_name' => get_option('hng_shipping_custom_default_name', 'Entrega'),
            'default_time' => get_option('hng_shipping_custom_default_time', ''),
            'zones' => get_option('hng_shipping_custom_zones', []),
        ];
    }
    
    /**
     * Salvar configurações
     */
    public static function save_settings($data) {
        $fields = [
            'enabled', 'company_name', 'company_cnpj', 'company_phone', 
            'company_email', 'company_address', 'company_city', 'company_state',
            'company_cep', 'label_format', 'label_copies', 'tracking_prefix',
            'tracking_url', 'default_enabled', 'default_cost', 'default_name', 'default_time'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                update_option('hng_shipping_custom_' . $field, sanitize_text_field($data[$field]));
            }
        }
        
        // Salvar zonas
        if (isset($data['zones']) && is_array($data['zones'])) {
            update_option('hng_shipping_custom_zones', $data['zones']);
        }
        
        return true;
    }
    
    /**
     * Gerar código de rastreamento
     */
    public static function generate_tracking_code($order_id) {
        $prefix = get_option('hng_shipping_custom_tracking_prefix', 'TR');
        $code = $prefix . gmdate('Ymd') . str_pad($order_id, 6, '0', STR_PAD_LEFT);
        
        // Permitir customização
        return apply_filters('hng_shipping_custom_tracking_code', $code, $order_id);
    }
    
    /**
     * Gerar URL de rastreamento
     */
    public static function get_tracking_url($tracking_code) {
        $base_url = get_option('hng_shipping_custom_tracking_url', '');
        
        if (empty($base_url)) {
            return '';
        }
        
        // Substituir placeholder
        return str_replace('{tracking_code}', $tracking_code, $base_url);
    }
}

// Registrar método de frete
add_filter('hng_shipping_methods', function($methods) {
    $methods['custom'] = 'HNG_Shipping_Custom';
    return $methods;
});
