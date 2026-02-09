<?php
/**
 * Página de configurações de frete
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shipping_Page {
    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão.', 'hng-commerce'));
        }
        
        // Verificar se está na aba custom
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
        
        if ($tab === 'custom') {
            // Renderizar página de configurações de frete customizado
            if (class_exists('HNG_Custom_Shipping_Settings')) {
                return HNG_Custom_Shipping_Settings::render();
            }
            echo '<div class="notice notice-error"><p>' . esc_html__('Configurações de frete customizado indisponíveis.', 'hng-commerce') . '</p></div>';
            return;
        }

        $saved = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shipping_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['shipping_nonce'])), 'hng_shipping_settings')) {
            self::save_settings();
            $saved = true;
        }

        $data = self::load_settings($saved);
        $breadcrumbs = [
            ['label' => __('HNG Commerce', 'hng-commerce'), 'url' => admin_url('admin.php?page=hng-commerce')],
            ['label' => __('Frete', 'hng-commerce'), 'url' => admin_url('admin.php?page=hng-shipping')],
        ];

        $admin_instance = HNG_Admin::instance();
        include HNG_COMMERCE_PATH . 'templates/admin-shipping-page.php';
    }

    private static function load_settings($saved) {
        $opt = function($id, $key, $default = '') {
            return get_option("hng_shipping_{$id}_{$key}", $default);
        };

        return [
            'general' => [
                'default_weight' => get_option('hng_shipping_default_weight', 0.3),
                'default_length' => get_option('hng_shipping_default_length', 16),
                'default_width' => get_option('hng_shipping_default_width', 11),
                'default_height' => get_option('hng_shipping_default_height', 2),
                'free_shipping_min' => get_option('hng_shipping_free_shipping_min', ''),
            ],
            'correios' => [
                'enabled' => $opt('correios', 'enabled', 'no'),
                'codigo_empresa' => $opt('correios', 'codigo_empresa', ''),
                'cartao_postagem' => $opt('correios', 'cartao_postagem', ''),
                'cnpj' => $opt('correios', 'cnpj', ''),
                'usuario_sigep' => $opt('correios', 'usuario_sigep', ''),
                'senha' => $opt('correios', 'senha', ''),
                'homologacao' => $opt('correios', 'homologacao', 'yes'),
                'origin_zipcode' => $opt('correios', 'origin_zipcode', ''),
                'services' => (array) $opt('correios', 'services', ['04014', '04510']),
                'extra_days' => $opt('correios', 'extra_days', 0),
                'handling_fee' => $opt('correios', 'handling_fee', 0),
                // Dados do remetente
                'remetente_nome' => $opt('correios', 'remetente_nome', get_bloginfo('name')),
                'remetente_logradouro' => $opt('correios', 'remetente_logradouro', ''),
                'remetente_numero' => $opt('correios', 'remetente_numero', ''),
                'remetente_complemento' => $opt('correios', 'remetente_complemento', ''),
                'remetente_bairro' => $opt('correios', 'remetente_bairro', ''),
                'remetente_cidade' => $opt('correios', 'remetente_cidade', ''),
                'remetente_uf' => $opt('correios', 'remetente_uf', ''),
                'remetente_telefone' => $opt('correios', 'remetente_telefone', ''),
            ],
            'jadlog' => [
                'enabled' => $opt('jadlog', 'enabled', 'no'),
                'cnpj' => $opt('jadlog', 'cnpj', ''),
                'token' => $opt('jadlog', 'token', ''),
                'contract_number' => $opt('jadlog', 'contract_number', ''),
                'origin_zipcode' => $opt('jadlog', 'origin_zipcode', ''),
                'services' => (array) $opt('jadlog', 'services', ['0', '3', '4']),
            ],
            'melhorenvio' => [
                'enabled' => $opt('melhorenvio', 'enabled', 'no'),
                'sandbox' => $opt('melhorenvio', 'sandbox', 'yes'),
                'token' => $opt('melhorenvio', 'token', ''),
                'origin_zipcode' => $opt('melhorenvio', 'origin_zipcode', ''),
                'services' => (array) $opt('melhorenvio', 'services', [1, 2, 3]),
            ],
            'loggi' => [
                'enabled' => $opt('loggi', 'enabled', 'no'),
                'token' => $opt('loggi', 'token', ''),
                'origin_zipcode' => $opt('loggi', 'origin_zipcode', ''),
                'services' => (array) $opt('loggi', 'services', ['express']),
                'extra_days' => $opt('loggi', 'extra_days', 0),
                'handling_fee' => $opt('loggi', 'handling_fee', 0),
            ],
            'total_express' => [
                'enabled' => $opt('total_express', 'enabled', 'no'),
                'client_code' => $opt('total_express', 'client_code', ''),
                'api_key' => $opt('total_express', 'api_key', ''),
                'origin_zipcode' => $opt('total_express', 'origin_zipcode', ''),
                'services' => (array) $opt('total_express', 'services', ['ecommerce']),
                'extra_days' => $opt('total_express', 'extra_days', 0),
                'handling_fee' => $opt('total_express', 'handling_fee', 0),
            ],
            'saved' => (bool) $saved,
        ];
    }

    private static function save_settings() {
        $fields = [
            'correios' => [
                'enabled', 'codigo_empresa', 'cartao_postagem', 'cnpj', 'usuario_sigep', 'senha', 'homologacao',
                'origin_zipcode', 'services', 'extra_days', 'handling_fee',
                'remetente_nome', 'remetente_logradouro', 'remetente_numero', 'remetente_complemento',
                'remetente_bairro', 'remetente_cidade', 'remetente_uf', 'remetente_telefone'
            ],
            'jadlog' => ['enabled', 'cnpj', 'token', 'contract_number', 'origin_zipcode', 'services'],
            'melhorenvio' => ['enabled', 'sandbox', 'token', 'origin_zipcode', 'services'],
            'loggi' => ['enabled', 'token', 'origin_zipcode', 'services', 'extra_days', 'handling_fee'],
            'total_express' => ['enabled', 'client_code', 'api_key', 'origin_zipcode', 'services', 'extra_days', 'handling_fee'],
        ];

        foreach ($fields as $method => $keys) {
            foreach ($keys as $key) {
                $opt_name = "hng_shipping_{$method}_{$key}";
                $post_data = isset($_POST[$method]) && is_array($_POST[$method]) ? wp_unslash($_POST[$method]) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $value = $post_data[$key] ?? '';

                if ($key === 'enabled' || $key === 'sandbox' || $key === 'homologacao') {
                    $value = $value === 'yes' ? 'yes' : 'no';
                } elseif ($key === 'services') {
                    $value = array_filter(array_map('sanitize_text_field', array_map('trim', is_array($value) ? $value : explode(',', (string) $value))));
                } elseif (in_array($key, ['extra_days', 'handling_fee'], true)) {
                    $value = is_numeric($value) ? $value : 0;
                } else {
                    $value = sanitize_text_field(wp_unslash($value));
                }

                update_option($opt_name, $value);
            }
        }

        // General settings
        if (isset($_POST['general'])) {
            $g = isset($_POST['general']) && is_array($_POST['general']) ? wp_unslash($_POST['general']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            update_option('hng_shipping_default_weight', is_numeric($g['default_weight'] ?? null) ? floatval($g['default_weight']) : 0.3);
            update_option('hng_shipping_default_length', is_numeric($g['default_length'] ?? null) ? floatval($g['default_length']) : 16);
            update_option('hng_shipping_default_width', is_numeric($g['default_width'] ?? null) ? floatval($g['default_width']) : 11);
            update_option('hng_shipping_default_height', is_numeric($g['default_height'] ?? null) ? floatval($g['default_height']) : 2);
            update_option('hng_shipping_free_shipping_min', is_numeric($g['free_shipping_min'] ?? null) ? floatval($g['free_shipping_min']) : '');
        }
        
        // Custom shipping enabled toggle
        if (isset($_POST['custom_shipping_enabled'])) {
            update_option('hng_shipping_custom_enabled', 'yes');
        } else {
            update_option('hng_shipping_custom_enabled', 'no');
        }
    }
}
