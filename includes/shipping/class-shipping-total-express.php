<?php
/**
 * Total Express Shipping Method (stub with hook for API integration)
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shipping_Total_Express extends HNG_Shipping_Method {
    public function init() {
        $this->id = 'total_express';
        $this->title = 'Total Express';
    }

    public function get_settings() {
        return [
            'enabled' => $this->get_option('enabled', 'no'),
            'client_code' => $this->get_option('client_code', ''),
            'api_key' => $this->get_option('api_key', ''),
            'origin_zipcode' => $this->get_option('origin_zipcode', ''),
            'services' => (array) $this->get_option('services', ['ecommerce']),
            'extra_days' => $this->get_option('extra_days', 0),
            'handling_fee' => $this->get_option('handling_fee', 0),
        ];
    }

    public function calculate_shipping($package) {
        $settings = $this->get_settings();

        if ($settings['enabled'] !== 'yes') {
            return [];
        }

        if (empty($settings['client_code']) || empty($settings['api_key'])) {
            return new WP_Error('total_express_no_credentials', __('Credenciais da Total Express não configuradas.', 'hng-commerce'));
        }

        $origin_zip = preg_replace('/\D/', '', $settings['origin_zipcode']);
        $destination_zip = preg_replace('/\D/', '', $package['destination']['postcode'] ?? '');

        if (empty($origin_zip) || empty($destination_zip)) {
            return new WP_Error('total_express_no_zip', __('CEP inválido.', 'hng-commerce'));
        }

        $package_data = $this->calculate_package_data($package);

        // Permite integração real via filtro para chamar a API da Total Express
        $hooked_rates = apply_filters('hng_shipping_total_express_request', null, $package, $package_data, $settings);
        if (is_array($hooked_rates)) {
            return array_map([$this, 'format_rate'], $this->normalize_rates($hooked_rates, $settings));
        }

        return new WP_Error('total_express_not_integrated', __('Integração Total Express não configurada. Use o filtro hng_shipping_total_express_request para injetar cotações via API.', 'hng-commerce'));
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
                'service' => $rate['service'] ?? 'standard',
                'name' => $rate['name'] ?? 'Total Express',
                'cost' => $cost,
                'delivery_time' => $delivery,
            ];
        }
        return $normalized;
    }
}

add_filter('hng_shipping_methods', function($methods) {
    $methods['total_express'] = 'HNG_Shipping_Total_Express';
    return $methods;
});

/**
 * Label creation and tracking scaffolding via filters
 */
add_filter('hng_shipping_total_express_create_label', function($result, $order, $selected_rate, $settings) {
    // Implementador deve chamar API da Total Express e retornar dados da etiqueta
    return $result; // null por padrão
}, 10, 4);

add_filter('hng_shipping_total_express_track', function($result, $tracking_code, $settings) {
    // Implementador deve chamar API de rastreio da Total Express e retornar eventos/status
    return $result; // null por padrão
}, 10, 3);
