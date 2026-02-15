<?php
/**
 * Melhor Envio Shipping Method
 * 
 * Integration with Melhor Envio (Multi-carrier aggregator)
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shipping_MelhorEnvio extends HNG_Shipping_Method {
    
    private $api_url = 'https://melhorenvio.com.br/api/v2';
    private $sandbox_url = 'https://sandbox.melhorenvio.com.br/api/v2';
    
    public function init() {
        $this->id = 'melhorenvio';
        $this->title = 'Melhor Envio';
    }
    
    public function get_settings() {
        return [
            'enabled' => $this->get_option('enabled', 'no'),
            'sandbox' => $this->get_option('sandbox', 'yes'),
            'token' => $this->get_option('token', ''),
            'origin_zipcode' => $this->get_option('origin_zipcode', ''),
            'services' => $this->get_option('services', [1, 2, 3]), // PAC, SEDEX, etc
        ];
    }
    
    public function calculate_shipping($package) {
        $settings = $this->get_settings();
        
        if (empty($settings['token'])) {
            return new WP_Error('no_token', __('Token Melhor Envio nï¿½o configurado.', 'hng-commerce'));
        }
        
        $origin_zip = preg_replace('/\D/', '', $settings['origin_zipcode']);
        $destination_zip = preg_replace('/\D/', '', $package['destination']['postcode'] ?? '');
        
        if (empty($origin_zip) || empty($destination_zip)) {
            return new WP_Error('no_zip', __('CEP invï¿½lido.', 'hng-commerce'));
        }
        
        // Calculate package data
        $package_data = $this->calculate_package_data($package);
        
        $payload = [
            'from' => ['postal_code' => $origin_zip],
            'to' => ['postal_code' => $destination_zip],
            'package' => [
                'height' => $package_data['height'],
                'width' => $package_data['width'],
                'length' => $package_data['length'],
                'weight' => $package_data['weight'],
            ],
            'options' => [
                'insurance_value' => $package['cart_total'] ?? 0,
                'receipt' => false,
                'own_hand' => false,
            ],
            'services' => implode(',', $settings['services']),
        ];
        
        $response = $this->request('POST', '/shipment/calculate', $payload, $settings);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->parse_response($response);
    }
    
    private function calculate_package_data($package) {
        $weight = 0;
        $length = 0;
        $width = 0;
        $height = 0;
        
        foreach ($package['items'] as $item) {
            $item_weight = floatval(get_post_meta($item['product_id'], '_weight', true)) ?: 0.3;
            $item_length = floatval(get_post_meta($item['product_id'], '_length', true)) ?: 16;
            $item_width = floatval(get_post_meta($item['product_id'], '_width', true)) ?: 11;
            $item_height = floatval(get_post_meta($item['product_id'], '_height', true)) ?: 2;
            
            $weight += $item_weight * $item['quantity'];
            $length = max($length, $item_length);
            $width = max($width, $item_width);
            $height += $item_height * $item['quantity'];
        }
        
        return [
            'weight' => max($weight, 0.3),
            'length' => max($length, 16),
            'width' => max($width, 11),
            'height' => max($height, 2),
        ];
    }
    
    private function request($method, $endpoint, $data, $settings) {
        $api_url = ($settings['sandbox'] === 'yes') ? $this->sandbox_url : $this->api_url;
        $url = $api_url . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['token'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($data),
            'timeout' => 15,
        ];
        
        $this->log('Request to Melhor Envio', $data);
        
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
            return new WP_Error('api_error', $data['message'] ?? 'Erro na API Melhor Envio');
        }
        
        return $data;
    }
    
    private function parse_response($response) {
        if (empty($response) || !is_array($response)) {
            return [];
        }
        
        $rates = [];
        
        foreach ($response as $service) {
            if (isset($service['error']) && $service['error']) {
                continue;
            }
            
            $rates[] = [
                'service' => $service['id'],
                'name' => $service['name'] ?? 'Desconhecido',
                'cost' => floatval($service['price'] ?? 0),
                'delivery_time' => absint($service['delivery_time'] ?? 0),
                'company' => $service['company']['name'] ?? '',
            ];
        }
        
        return array_map([$this, 'format_rate'], $rates);
    }
}

// Register method
add_filter('hng_shipping_methods', function($methods) {
    $methods['melhorenvio'] = 'HNG_Shipping_MelhorEnvio';
    return $methods;
});
