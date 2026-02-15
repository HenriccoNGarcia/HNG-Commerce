<?php
/**
 * Correios Shipping Method
 * 
 * Integration with Brazilian Post Office (Correios)
 * Atualizado para nova API (2024+)
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shipping_Correios extends HNG_Shipping_Method {
    
    /**
     * Nova API dos Correios (token obrigatório para produção)
     * Para cotação sem contrato, usa API pública via proxy ou ViaCEP
     */
    private $api_url = 'https://api.correios.com.br';
    
    // Fallback: API do Melhor Envio para cotação Correios (gratuita, sem token)
    private $fallback_url = 'https://www.melhorenvio.com.br/api/v2/me/shipment/calculate';
    
    public function init() {
        $this->id = 'correios';
        $this->title = 'Correios';
    }
    
    public function get_settings() {
        return [
            'enabled' => $this->get_option('enabled', 'no'),
            'codigo_empresa' => $this->get_option('codigo_empresa', ''),
            'cartao_postagem' => $this->get_option('cartao_postagem', ''),
            'cnpj' => $this->get_option('cnpj', ''),
            'usuario_sigep' => $this->get_option('usuario_sigep', ''),
            'senha' => $this->get_option('senha', ''),
            'homologacao' => $this->get_option('homologacao', 'yes'),
            'services' => $this->get_option('services', ['04014', '04510']), // SEDEX, PAC
            'origin_zipcode' => $this->get_option('origin_zipcode', ''),
            'extra_days' => $this->get_option('extra_days', 0),
            'handling_fee' => $this->get_option('handling_fee', 0),
            // Dados do remetente
            'remetente_nome' => $this->get_option('remetente_nome', get_bloginfo('name')),
            'remetente_logradouro' => $this->get_option('remetente_logradouro', ''),
            'remetente_numero' => $this->get_option('remetente_numero', ''),
            'remetente_complemento' => $this->get_option('remetente_complemento', ''),
            'remetente_bairro' => $this->get_option('remetente_bairro', ''),
            'remetente_cidade' => $this->get_option('remetente_cidade', ''),
            'remetente_uf' => $this->get_option('remetente_uf', ''),
            'remetente_telefone' => $this->get_option('remetente_telefone', ''),
        ];
    }
    
    /**
     * Verifica se o contrato SIGEP está configurado
     */
    public function has_sigep_contract() {
        $settings = $this->get_settings();
        return !empty($settings['codigo_empresa']) 
            && !empty($settings['cartao_postagem']) 
            && !empty($settings['usuario_sigep']) 
            && !empty($settings['senha']);
    }
    
    public function calculate_shipping($package) {
        $settings = $this->get_settings();
        
        if (empty($settings['origin_zipcode'])) {
            return new WP_Error('no_origin', __('CEP de origem não configurado.', 'hng-commerce'));
        }
        
        $origin_zip = preg_replace('/\D/', '', $settings['origin_zipcode']);
        $destination_zip = preg_replace('/\D/', '', $package['destination']['postcode'] ?? '');
        
        if (empty($destination_zip) || strlen($destination_zip) !== 8) {
            return new WP_Error('no_destination', __('CEP de destino inválido.', 'hng-commerce'));
        }
        
        // Calculate package dimensions and weight
        $package_data = $this->calculate_package_data($package);
        
        // Normalizar códigos de serviço (aceitar nomes ou códigos)
        $services = $this->normalize_service_codes($settings['services']);
        
        $this->log('Iniciando cálculo Correios', [
            'origin' => $origin_zip,
            'destination' => $destination_zip,
            'services_raw' => $settings['services'],
            'services_normalized' => $services,
            'package' => $package_data,
        ]);
        
        $rates = [];
        
        // Tentar API oficial dos Correios (requer token)
        if (!empty($settings['usuario_sigep']) && !empty($settings['senha'])) {
            $rates = $this->calculate_with_official_api($origin_zip, $destination_zip, $package_data, $services, $settings);
            if (!empty($rates) && !is_wp_error($rates)) {
                $this->log('Correios: API oficial retornou ' . count($rates) . ' opções');
                return $rates;
            }
        }
        
        // Fallback: Calculadora via consulta direta (sem autenticação)
        $rates = $this->calculate_with_fallback($origin_zip, $destination_zip, $package_data, $services, $settings);
        
        // Se não encontrou com o CEP específico, tentar com CEP base da cidade (-000)
        if (empty($rates) && substr($destination_zip, -3) !== '000') {
            $base_cep = substr($destination_zip, 0, 5) . '000';
            $this->log('CEP específico sem resultado, tentando CEP base da cidade', $base_cep);
            $rates = $this->calculate_with_fallback($origin_zip, $base_cep, $package_data, $services, $settings);
        }
        
        $this->log('Correios: Fallback retornou ' . (is_array($rates) ? count($rates) : 'erro'), $rates);
        
        return $rates;
    }
    
    /**
     * Normaliza códigos de serviço (aceita nomes como "sedex", "pac" ou códigos como "04014")
     */
    private function normalize_service_codes($services) {
        if (!is_array($services)) {
            $services = array_map('trim', explode(',', (string) $services));
        }
        
        $code_map = [
            'sedex' => '04014',
            'pac' => '04510',
            'sedex10' => '04782',
            'sedex 10' => '04782',
            'sedexhoje' => '04804',
            'sedex hoje' => '04804',
            'sedex_10' => '04782',
            'sedex_hoje' => '04804',
        ];
        
        $normalized = [];
        foreach ($services as $service) {
            $service = strtolower(trim($service));
            
            // Se já é um código numérico, usar diretamente
            if (is_numeric($service) || preg_match('/^\d{5}$/', $service)) {
                $normalized[] = $service;
            } elseif (isset($code_map[$service])) {
                $normalized[] = $code_map[$service];
            }
        }
        
        // Se não conseguiu normalizar nenhum, usar PAC e SEDEX padrão
        if (empty($normalized)) {
            $normalized = ['04014', '04510'];
            $this->log('Serviços não reconhecidos, usando padrão PAC/SEDEX', $services);
        }
        
        return array_unique($normalized);
    }
    
    /**
     * Calcula frete usando API oficial dos Correios (requer contrato)
     */
    private function calculate_with_official_api($origin, $dest, $package_data, $services, $settings) {
        // Nova API requer autenticação OAuth2
        $token = $this->get_correios_token($settings);
        
        if (is_wp_error($token) || empty($token)) {
            $this->log('Token Correios não obtido, usando fallback');
            return [];
        }
        
        $rates = [];
        
        foreach ($services as $service_code) {
            $payload = [
                'cepOrigem' => $origin,
                'cepDestino' => $dest,
                'psObjeto' => $package_data['weight'] * 1000, // em gramas
                'tpObjeto' => 2, // Pacote
                'comprimento' => $package_data['length'],
                'largura' => $package_data['width'],
                'altura' => $package_data['height'],
                'servicosAdicionais' => [],
            ];
            
            $response = wp_remote_post($this->api_url . '/preco/v1/nacional', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
                'timeout' => 15,
            ]);
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($body['pcFinal'])) {
                    $rates[] = $this->format_rate([
                        'service' => $service_code,
                        'name' => $this->get_service_name($service_code),
                        'cost' => floatval($body['pcFinal']),
                        'delivery_time' => intval($body['prazoEntrega'] ?? 5),
                    ]);
                }
            }
        }
        
        return $rates;
    }
    
    /**
     * Obtém token OAuth2 dos Correios
     */
    private function get_correios_token($settings) {
        $cache_key = 'hng_correios_token';
        $cached = get_transient($cache_key);
        
        if ($cached) {
            return $cached;
        }
        
        $is_sandbox = ($settings['homologacao'] ?? 'yes') === 'yes';
        $auth_url = $is_sandbox 
            ? 'https://apihom.correios.com.br/token/v1/autentica/cartaopostagem'
            : 'https://api.correios.com.br/token/v1/autentica/cartaopostagem';
        
        $response = wp_remote_post($auth_url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($settings['usuario_sigep'] . ':' . $settings['senha']),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'numero' => $settings['cartao_postagem'] ?? '',
            ]),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['token'])) {
            set_transient($cache_key, $body['token'], HOUR_IN_SECONDS);
            return $body['token'];
        }
        
        return new WP_Error('token_error', 'Não foi possível obter token dos Correios');
    }
    
    /**
     * Calcula frete usando calculadora alternativa (sem autenticação)
     * Usa valores estimados baseados em tabelas públicas
     */
    private function calculate_with_fallback($origin, $dest, $package_data, $services, $settings) {
        $rates = [];
        
        $this->log('Tentando fallback CepCerto', ['origin' => $origin, 'dest' => $dest, 'services' => $services]);
        
        // Usar API pública de consulta de frete (várias opções disponíveis)
        // Opção 1: Consulta via proxy público
        $url = 'https://www.cepcerto.com/ws/json-frete/' . $origin . '/' . $dest . '/' . 
               intval($package_data['weight'] * 1000) . '/' . // peso em gramas
               intval($package_data['length']) . '/' . 
               intval($package_data['height']) . '/' . 
               intval($package_data['width']);
        
        $this->log('URL CepCerto', $url);
        
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        
        if (is_wp_error($response)) {
            $this->log('CepCerto ERRO', $response->get_error_message());
        } else {
            $http_code = wp_remote_retrieve_response_code($response);
            $body_raw = wp_remote_retrieve_body($response);
            $body = json_decode($body_raw, true);
            
            $this->log('CepCerto resposta', [
                'http_code' => $http_code,
                'body_raw' => substr($body_raw, 0, 500),
                'body_parsed' => $body,
            ]);
            
            if (!empty($body) && is_array($body)) {
                // PAC
                if (in_array('04510', $services) && !empty($body['valorpac'])) {
                    $rates[] = $this->format_rate([
                        'service' => '04510',
                        'name' => 'PAC',
                        'cost' => $this->parse_currency($body['valorpac']),
                        'delivery_time' => intval($body['prazopac'] ?? 8),
                    ], $settings);
                }
                
                // SEDEX
                if (in_array('04014', $services) && !empty($body['valorsedex'])) {
                    $rates[] = $this->format_rate([
                        'service' => '04014',
                        'name' => 'SEDEX',
                        'cost' => $this->parse_currency($body['valorsedex']),
                        'delivery_time' => intval($body['prazosedex'] ?? 3),
                    ], $settings);
                }
            }
        }
        
        // Se falhou, tentar outra API
        if (empty($rates)) {
            $this->log('CepCerto falhou, tentando BrasilAPI');
            $rates = $this->calculate_with_brcep($origin, $dest, $package_data, $services, $settings);
        }
        
        // Último recurso: valores estimados por região
        if (empty($rates)) {
            $this->log('Todas APIs falharam, usando estimativa');
            $rates = $this->calculate_estimated($origin, $dest, $package_data, $services, $settings);
        }
        
        return $rates;
    }
    
    /**
     * Tenta calcular via API BrCep
     */
    private function calculate_with_brcep($origin, $dest, $package_data, $services, $settings) {
        $rates = [];
        
        // Formato: peso em kg, dimensões em cm
        $weight = $package_data['weight'];
        $volume = $package_data['length'] * $package_data['width'] * $package_data['height'];
        
        foreach ($services as $service_code) {
            $service_id = ($service_code === '04014') ? 'sedex' : 'pac';
            
            $url = "https://brasilapi.com.br/api/correios/v1/shipping";
            $params = [
                'cepOrigem' => $origin,
                'cepDestino' => $dest,
                'peso' => $weight,
                'formato' => 1,
                'comprimento' => $package_data['length'],
                'altura' => $package_data['height'],
                'largura' => $package_data['width'],
                'servico' => $service_code,
            ];
            
            $response = wp_remote_get(add_query_arg($params, $url), [
                'timeout' => 10,
            ]);
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                
                if (!empty($body['valor']) && $body['valor'] > 0) {
                    $rates[] = $this->format_rate([
                        'service' => $service_code,
                        'name' => $this->get_service_name($service_code),
                        'cost' => floatval($body['valor']),
                        'delivery_time' => intval($body['prazo'] ?? 5),
                    ], $settings);
                }
            }
        }
        
        return $rates;
    }
    
    /**
     * Calcula valor estimado baseado em região (último recurso)
     */
    private function calculate_estimated($origin, $dest, $package_data, $services, $settings) {
        $rates = [];
        
        // Determinar região de destino pelo CEP
        $origin_region = $this->get_region_from_cep($origin);
        $dest_region = $this->get_region_from_cep($dest);
        
        // Tabela base de preços estimados (atualizar periodicamente)
        $base_prices = [
            'same_state' => ['pac' => 18.50, 'sedex' => 28.00],
            'same_region' => ['pac' => 25.00, 'sedex' => 42.00],
            'neighbor_region' => ['pac' => 35.00, 'sedex' => 58.00],
            'far_region' => ['pac' => 48.00, 'sedex' => 78.00],
        ];
        
        // Determinar distância
        $distance_type = $this->calculate_distance_type($origin_region, $dest_region);
        $prices = $base_prices[$distance_type];
        
        // Ajustar por peso (adicional por kg acima de 0.3kg)
        $extra_weight = max(0, $package_data['weight'] - 0.3);
        $weight_factor = 1 + ($extra_weight * 0.8);
        
        // Ajustar por volume (cubagem)
        $volume = ($package_data['length'] * $package_data['width'] * $package_data['height']) / 6000;
        $volume_factor = max(1, $volume / $package_data['weight']);
        
        $factor = max($weight_factor, $volume_factor);
        
        foreach ($services as $service_code) {
            $service_type = ($service_code === '04014') ? 'sedex' : 'pac';
            $base_price = $prices[$service_type] ?? 30;
            $final_price = $base_price * $factor;
            
            $delivery_days = ($service_type === 'sedex') 
                ? $this->estimate_delivery_days($distance_type, 'express')
                : $this->estimate_delivery_days($distance_type, 'normal');
            
            $rates[] = $this->format_rate([
                'service' => $service_code,
                'name' => $this->get_service_name($service_code) . ' (estimado)',
                'cost' => round($final_price, 2),
                'delivery_time' => $delivery_days,
            ], $settings);
        }
        
        $this->log('Usando valores estimados para ' . $dest, [
            'distance_type' => $distance_type,
            'weight_factor' => $weight_factor,
            'volume_factor' => $volume_factor,
        ]);
        
        return $rates;
    }
    
    /**
     * Obtém região pelo CEP (primeiros 2 dígitos)
     */
    private function get_region_from_cep($cep) {
        $prefix = intval(substr($cep, 0, 2));
        
        // Regiões por faixa de CEP
        if ($prefix >= 1 && $prefix <= 19) return ['region' => 'SP', 'zone' => 'sudeste'];
        if ($prefix >= 20 && $prefix <= 28) return ['region' => 'RJ', 'zone' => 'sudeste'];
        if ($prefix >= 29 && $prefix <= 29) return ['region' => 'ES', 'zone' => 'sudeste'];
        if ($prefix >= 30 && $prefix <= 39) return ['region' => 'MG', 'zone' => 'sudeste'];
        if ($prefix >= 40 && $prefix <= 48) return ['region' => 'BA', 'zone' => 'nordeste'];
        if ($prefix >= 49 && $prefix <= 49) return ['region' => 'SE', 'zone' => 'nordeste'];
        if ($prefix >= 50 && $prefix <= 56) return ['region' => 'PE', 'zone' => 'nordeste'];
        if ($prefix >= 57 && $prefix <= 57) return ['region' => 'AL', 'zone' => 'nordeste'];
        if ($prefix >= 58 && $prefix <= 58) return ['region' => 'PB', 'zone' => 'nordeste'];
        if ($prefix >= 59 && $prefix <= 59) return ['region' => 'RN', 'zone' => 'nordeste'];
        if ($prefix >= 60 && $prefix <= 63) return ['region' => 'CE', 'zone' => 'nordeste'];
        if ($prefix >= 64 && $prefix <= 64) return ['region' => 'PI', 'zone' => 'nordeste'];
        if ($prefix >= 65 && $prefix <= 65) return ['region' => 'MA', 'zone' => 'nordeste'];
        if ($prefix >= 66 && $prefix <= 68) return ['region' => 'PA', 'zone' => 'norte'];
        if ($prefix >= 69 && $prefix <= 69) return ['region' => 'AM', 'zone' => 'norte'];
        if ($prefix >= 70 && $prefix <= 73) return ['region' => 'DF', 'zone' => 'centro-oeste'];
        if ($prefix >= 74 && $prefix <= 76) return ['region' => 'GO', 'zone' => 'centro-oeste'];
        if ($prefix >= 77 && $prefix <= 77) return ['region' => 'TO', 'zone' => 'norte'];
        if ($prefix >= 78 && $prefix <= 78) return ['region' => 'MT', 'zone' => 'centro-oeste'];
        if ($prefix >= 79 && $prefix <= 79) return ['region' => 'MS', 'zone' => 'centro-oeste'];
        if ($prefix >= 80 && $prefix <= 87) return ['region' => 'PR', 'zone' => 'sul'];
        if ($prefix >= 88 && $prefix <= 89) return ['region' => 'SC', 'zone' => 'sul'];
        if ($prefix >= 90 && $prefix <= 99) return ['region' => 'RS', 'zone' => 'sul'];
        
        return ['region' => 'BR', 'zone' => 'outro'];
    }
    
    /**
     * Calcula tipo de distância entre origens
     */
    private function calculate_distance_type($origin, $dest) {
        if ($origin['region'] === $dest['region']) {
            return 'same_state';
        }
        
        if ($origin['zone'] === $dest['zone']) {
            return 'same_region';
        }
        
        $neighbor_zones = [
            'sudeste' => ['sul', 'centro-oeste'],
            'sul' => ['sudeste'],
            'centro-oeste' => ['sudeste', 'norte', 'nordeste'],
            'nordeste' => ['centro-oeste', 'norte'],
            'norte' => ['centro-oeste', 'nordeste'],
        ];
        
        if (in_array($dest['zone'], $neighbor_zones[$origin['zone']] ?? [])) {
            return 'neighbor_region';
        }
        
        return 'far_region';
    }
    
    /**
     * Estima dias de entrega
     */
    private function estimate_delivery_days($distance_type, $service) {
        $days = [
            'same_state' => ['normal' => 4, 'express' => 1],
            'same_region' => ['normal' => 6, 'express' => 2],
            'neighbor_region' => ['normal' => 9, 'express' => 4],
            'far_region' => ['normal' => 14, 'express' => 6],
        ];
        
        return $days[$distance_type][$service] ?? 7;
    }
    
    /**
     * Converte string de moeda para float
     */
    private function parse_currency($value) {
        if (is_numeric($value)) {
            return floatval($value);
        }
        $value = preg_replace('/[^\d,.]/', '', $value);
        $value = str_replace(',', '.', $value);
        return floatval($value);
    }
    
    /**
     * Retorna nome do serviço
     */
    private function get_service_name($code) {
        $names = [
            '04014' => 'SEDEX',
            '04510' => 'PAC',
            '04782' => 'SEDEX 10',
            '04804' => 'SEDEX Hoje',
            '41106' => 'PAC',
            '40010' => 'SEDEX',
        ];
        return $names[$code] ?? 'Correios';
    }
    
    /**
     * Formata rate para padrão esperado
     */
    protected function format_rate($rate, $settings = null) {
        if ($settings === null) {
            $settings = $this->get_settings();
        }
        
        $cost = floatval($rate['cost'] ?? 0);
        $delivery_time = intval($rate['delivery_time'] ?? 5);
        
        // Apply handling fee
        $handling_fee = floatval($settings['handling_fee'] ?? 0);
        if ($handling_fee > 0) {
            $cost += $handling_fee;
        }
        
        // Apply extra days
        $extra_days = intval($settings['extra_days'] ?? 0);
        if ($extra_days > 0) {
            $delivery_time += $extra_days;
        }
        
        $service_code = $rate['service'] ?? '';
        $method_id = 'correios:' . $service_code;
        
        return [
            'id' => $method_id,
            'method_id' => $method_id,
            'service' => $service_code,
            'service_name' => $rate['name'] ?? 'Correios',
            'method_title' => $rate['name'] ?? 'Correios',
            'label' => $rate['name'] ?? 'Correios',
            'cost' => round($cost, 2),
            'delivery_time' => $delivery_time,
            'delivery_time_label' => sprintf(
                /* translators: %d: number of business days for delivery */
                _n('%d dia útil', '%d dias úteis', $delivery_time, 'hng-commerce'),
                $delivery_time
            ),
        ];
    }
    
    private function calculate_package_data($package) {
        $weight = 0;
        $volumes = [];
        
        foreach ($package['items'] as $item) {
            $product = new HNG_Product($item['product_id']);
            
            // Weight in kg
            $item_weight = floatval(get_post_meta($item['product_id'], '_weight', true)) ?: 0.3;
            $weight += $item_weight * $item['quantity'];
            
            // Dimensions in cm
            $length = floatval(get_post_meta($item['product_id'], '_length', true)) ?: 16;
            $width = floatval(get_post_meta($item['product_id'], '_width', true)) ?: 11;
            $height = floatval(get_post_meta($item['product_id'], '_height', true)) ?: 2;
            
            for ($i = 0; $i < $item['quantity']; $i++) {
                $volumes[] = ['length' => $length, 'width' => $width, 'height' => $height];
            }
        }
        
        // Simple packing algorithm (can be improved)
        $dimensions = $this->pack_volumes($volumes);
        
        return [
            'weight' => max($weight, 0.3), // Minimum 300g
            'length' => max($dimensions['length'], 16),
            'width' => max($dimensions['width'], 11),
            'height' => max($dimensions['height'], 2),
        ];
    }
    
    private function pack_volumes($volumes) {
        if (empty($volumes)) {
            return ['length' => 16, 'width' => 11, 'height' => 2];
        }
        
        // Simple: sum all heights, use max length and width
        $length = 0;
        $width = 0;
        $height = 0;
        
        foreach ($volumes as $vol) {
            $length = max($length, $vol['length']);
            $width = max($width, $vol['width']);
            $height += $vol['height'];
        }
        
        return compact('length', 'width', 'height');
    }
}

// Register method
add_filter('hng_shipping_methods', function($methods) {
    $methods['correios'] = 'HNG_Shipping_Correios';
    return $methods;
});
