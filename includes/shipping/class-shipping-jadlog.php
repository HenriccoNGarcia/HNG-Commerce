<?php
/**
 * Jadlog Shipping Method
 * 
 * Integration with Jadlog Express
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shipping_Jadlog extends HNG_Shipping_Method {
    
    private $api_url = 'https://www.jadlog.com.br/embarcador/api';
    
    public function init() {
        $this->id = 'jadlog';
        $this->title = 'Jadlog';
    }
    
    public function get_settings() {
        return [
            'enabled' => $this->get_option('enabled', 'no'),
            'cnpj' => $this->get_option('cnpj', ''),
            'token' => $this->get_option('token', ''),
            'contract_number' => $this->get_option('contract_number', ''),
            'origin_zipcode' => $this->get_option('origin_zipcode', ''),
            'services' => $this->get_option('services', ['0', '3', '4']), // Package, .COM, DOC
        ];
    }
    
    public function calculate_shipping($package) {
        $settings = $this->get_settings();
        
        if (empty($settings['token']) || empty($settings['cnpj'])) {
            return new WP_Error('no_credentials', __('Credenciais Jadlog nï¿½o configuradas.', 'hng-commerce'));
        }
        
        $origin_zip = preg_replace('/\D/', '', $settings['origin_zipcode']);
        $destination_zip = preg_replace('/\D/', '', $package['destination']['postcode'] ?? '');
        
        if (empty($origin_zip) || empty($destination_zip)) {
            return new WP_Error('no_zip', __('CEP invï¿½lido.', 'hng-commerce'));
        }
        
        $package_data = $this->calculate_package_data($package);
        
        $rates = [];
        
        foreach ($settings['services'] as $service_code) {
            $payload = [
                'frete' => [
                    [
                        'cepori' => $origin_zip,
                        'cepdes' => $destination_zip,
                        'peso' => $package_data['weight'],
                        'cnpj' => preg_replace('/\D/', '', $settings['cnpj']),
                        'conta' => $settings['contract_number'],
                        'modalidade' => $service_code,
                        'tpentrega' => 'D',
                        'tpseguro' => 'N',
                        'vldeclarado' => $package['cart_total'] ?? 0,
                    ]
                ]
            ];
            
            $response = $this->request('POST', '/frete/valor', $payload, $settings);
            
            if (!is_wp_error($response) && isset($response['frete'][0])) {
                $rate = $this->parse_service_response($response['frete'][0], $service_code);
                if ($rate) {
                    $rates[] = $this->format_rate($rate);
                }
            }
        }
        
        return $rates;
    }
    
    private function calculate_package_data($package) {
        $weight = 0;
        
        foreach ($package['items'] as $item) {
            $item_weight = floatval(get_post_meta($item['product_id'], '_weight', true)) ?: 0.3;
            $weight += $item_weight * $item['quantity'];
        }
        
        return [
            'weight' => max($weight, 0.5), // Minimum 500g for Jadlog
        ];
    }
    
    private function request($method, $endpoint, $data, $settings) {
        $url = $this->api_url . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['token'],
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($data),
            'timeout' => 15,
        ];
        
        $this->log('Request to Jadlog', $data);
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log('Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode($body, true);
        
        $this->log('Response: ' . $code, $data);
        
        if ($code >= 400) {
            return new WP_Error('api_error', $data['mensagem'] ?? 'Erro na API Jadlog');
        }
        
        return $data;
    }
    
    private function parse_service_response($service, $service_code) {
        if (!isset($service['vltotal']) || floatval($service['vltotal']) == 0) {
            return null;
        }
        
        $service_names = [
            '0' => 'Jadlog Package',
            '3' => 'Jadlog .COM',
            '4' => 'Jadlog DOC',
            '5' => 'Jadlog Corporate',
            '7' => 'Jadlog Rodoviï¿½rio',
        ];
        
        return [
            'service' => $service_code,
            'name' => $service_names[$service_code] ?? 'Jadlog',
            'cost' => floatval($service['vltotal'] ?? 0),
            'delivery_time' => absint($service['prazo'] ?? 0),
        ];
    }
}

// Register method
add_filter('hng_shipping_methods', function($methods) {
    $methods['jadlog'] = 'HNG_Shipping_Jadlog';
    return $methods;
});
