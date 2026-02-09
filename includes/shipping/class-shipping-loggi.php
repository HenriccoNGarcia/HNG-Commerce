<?php
/**
 * Loggi Shipping Method (stub with hook for API integration)
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shipping_Loggi extends HNG_Shipping_Method {
    public function init() {
        $this->id = 'loggi';
        $this->title = 'Loggi';
    }

    public function get_settings() {
        return [
            'enabled' => $this->get_option('enabled', 'no'),
            'token' => $this->get_option('token', ''),
            'origin_zipcode' => $this->get_option('origin_zipcode', ''),
            'services' => (array) $this->get_option('services', ['express']),
            'extra_days' => $this->get_option('extra_days', 0),
            'handling_fee' => $this->get_option('handling_fee', 0),
        ];
    }

    public function calculate_shipping($package) {
        $settings = $this->get_settings();

        if ($settings['enabled'] !== 'yes') {
            return [];
        }

        if (empty($settings['token'])) {
            return new WP_Error('loggi_no_token', __('Token da Loggi não configurado.', 'hng-commerce'));
        }

        $origin_zip = preg_replace('/\D/', '', $settings['origin_zipcode']);
        $destination_zip = preg_replace('/\D/', '', $package['destination']['postcode'] ?? '');

        if (empty($origin_zip) || empty($destination_zip)) {
            return new WP_Error('loggi_no_zip', __('CEP inválido.', 'hng-commerce'));
        }

        $package_data = $this->calculate_package_data($package);

        // Permite integração real via filtro para chamar a API GraphQL da Loggi
        $hooked_rates = apply_filters('hng_shipping_loggi_request', null, $package, $package_data, $settings);
        if (is_array($hooked_rates)) {
            return array_map([$this, 'format_rate'], $this->normalize_rates($hooked_rates, $settings));
        }

        return new WP_Error('loggi_not_integrated', __('Integração Loggi não configurada. Use o filtro hng_shipping_loggi_request para injetar cotações via API.', 'hng-commerce'));
    }

    private function calculate_package_data($package) {
        $weight = 0;
        $length = 0;
        $width = 0;
        $height = 0;

        foreach ($package['items'] as $item) {
            $item_weight = floatval(get_post_meta($item['product_id'], '_weight', true)) ?: $this->get_default_weight_kg();
            $item_length = floatval(get_post_meta($item['product_id'], '_length', true)) ?: $this->get_default_length_cm();
            $item_width = floatval(get_post_meta($item['product_id'], '_width', true)) ?: $this->get_default_width_cm();
            $item_height = floatval(get_post_meta($item['product_id'], '_height', true)) ?: $this->get_default_height_cm();

            $weight += $item_weight * $item['quantity'];
            $length = max($length, $item_length);
            $width = max($width, $item_width);
            $height += $item_height * $item['quantity'];
        }

        return [
            'weight' => max($weight, $this->get_default_weight_kg()),
            'length' => max($length, $this->get_default_length_cm()),
            'width' => max($width, $this->get_default_width_cm()),
            'height' => max($height, $this->get_default_height_cm()),
        ];
    }

    private function normalize_rates($rates, $settings) {
        $normalized = [];
        foreach ($rates as $rate) {
            $cost = floatval($rate['cost'] ?? 0);
            $delivery = absint($rate['delivery_time'] ?? 0);

            if (!empty($settings['handling_fee'])) {
                $cost += floatval($settings['handling_fee']);
            }
            if (!empty($settings['extra_days'])) {
                $delivery += absint($settings['extra_days']);
            }

            $normalized[] = [
                'service' => $rate['service'] ?? 'express',
                'name' => $rate['name'] ?? 'Loggi',
                'cost' => $cost,
                'delivery_time' => $delivery,
            ];
        }
        return $normalized;
    }
}

add_filter('hng_shipping_methods', function($methods) {
    $methods['loggi'] = 'HNG_Shipping_Loggi';
    return $methods;
});
 
/**
 * Label creation and tracking scaffolding via filters
 */
add_filter('hng_shipping_loggi_create_label', function($result, $order, $selected_rate, $settings) {
    // Implementador deve chamar API da Loggi e retornar array com dados da etiqueta
    // Exemplo de estrutura: ['label_id' => '...', 'tracking_code' => '...', 'url' => '...']
    return $result; // null por padrão
}, 10, 4);

add_filter('hng_shipping_loggi_track', function($result, $tracking_code, $settings) {
    // Implementador deve chamar API de rastreio da Loggi e retornar eventos/status
    // Exemplo: ['status' => 'Em trânsito', 'events' => [...]]
    return $result; // null por padrão
}, 10, 3);
