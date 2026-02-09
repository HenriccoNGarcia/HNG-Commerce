<?php
/**
 * Diagnóstico de Frete HNG Commerce
 * Acesse: /wp-json/hng/v1/shipping-diagnostic?cep=01310100
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function() {
    register_rest_route('hng/v1', '/shipping-diagnostic', [
        'methods' => 'GET',
        'callback' => 'hng_shipping_diagnostic',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
});

function hng_shipping_diagnostic($request) {
    $cep = preg_replace('/\D/', '', $request->get_param('cep') ?: '01310100');
    
    $result = [
        'cep_testado' => $cep,
        'metodos_habilitados' => [],
        'resultados' => [],
        'configuracoes' => [],
    ];
    
    $manager = HNG_Shipping_Manager::instance();
    $enabled = $manager->get_enabled_methods();
    
    foreach ($enabled as $id => $method) {
        $settings = $method->get_settings();
        
        $config_status = [];
        switch ($id) {
            case 'correios':
                $config_status = [
                    'cep_origem' => $settings['origin_zipcode'] ?: 'NÁO CONFIGURADO',
                    'servicos' => $settings['services'],
                    'tem_sigep' => !empty($settings['usuario_sigep']) && !empty($settings['senha']),
                ];
                break;
            case 'jadlog':
                $config_status = [
                    'cep_origem' => $settings['origin_zipcode'] ?: 'NÁO CONFIGURADO',
                    'token' => !empty($settings['token']) ? 'CONFIGURADO' : 'FALTANDO',
                    'cnpj' => !empty($settings['cnpj']) ? 'CONFIGURADO' : 'FALTANDO',
                ];
                break;
            case 'melhorenvio':
                $config_status = [
                    'cep_origem' => $settings['origin_zipcode'] ?: 'NÁO CONFIGURADO',
                    'token' => !empty($settings['token']) ? 'CONFIGURADO' : 'FALTANDO',
                    'sandbox' => $settings['sandbox'] ?? 'yes',
                ];
                break;
            case 'total_express':
                $config_status = [
                    'cep_origem' => $settings['origin_zipcode'] ?: 'NÁO CONFIGURADO',
                    'client_code' => !empty($settings['client_code']) ? 'CONFIGURADO' : 'FALTANDO',
                    'api_key' => !empty($settings['api_key']) ? 'CONFIGURADO' : 'FALTANDO',
                ];
                break;
        }
        
        $result['metodos_habilitados'][] = $id;
        $result['configuracoes'][$id] = $config_status;
    }
    
    // Testar cotação
    $package = [
        'destination' => ['postcode' => $cep],
        'items' => [
            ['product_id' => 0, 'quantity' => 1]
        ],
        'cart_total' => 100,
    ];
    
    // Desabilitar cache
    add_filter('hng_shipping_use_cache', '__return_false');
    
    foreach ($enabled as $id => $method) {
        $start = microtime(true);
        $rates = $method->calculate_shipping($package);
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        
        if (is_wp_error($rates)) {
            $result['resultados'][$id] = [
                'status' => 'ERRO',
                'mensagem' => $rates->get_error_message(),
                'tempo_ms' => $elapsed,
            ];
        } elseif (empty($rates)) {
            $result['resultados'][$id] = [
                'status' => 'VAZIO',
                'mensagem' => 'Nenhuma opção retornada',
                'tempo_ms' => $elapsed,
            ];
        } else {
            $result['resultados'][$id] = [
                'status' => 'OK',
                'opcoes' => count($rates),
                'tempo_ms' => $elapsed,
                'rates' => array_map(function($r) {
                    return [
                        'nome' => $r['service_name'] ?? $r['label'] ?? 'N/A',
                        'valor' => 'R$ ' . number_format($r['cost'] ?? 0, 2, ',', '.'),
                        'prazo' => ($r['delivery_time'] ?? '?') . ' dias',
                    ];
                }, $rates),
            ];
        }
    }
    
    return new WP_REST_Response($result, 200);
}
