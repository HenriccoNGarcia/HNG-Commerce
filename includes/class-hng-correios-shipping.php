<?php
/**
 * Integraï¿½ï¿½o com API dos Correios
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Correios_Shipping {
    
    /**
     * Instï¿½ncia ï¿½nica
     */
    private static $instance = null;
    
    /**
     * URL da API dos Correios (Sandbox/Produï¿½ï¿½o)
     */
    private $api_url = 'https://api.correios.com.br/';
    
    /**
     * Token de autenticaï¿½ï¿½o
     */
    private $token = '';
    
    /**
     * Cache transient prefix
     */
    private $cache_prefix = 'hng_correios_';
    
    /**
     * Obter instï¿½ncia
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
        $this->token = get_option('hng_correios_token', '');
    }
    
    /**
     * Calcular frete
     * 
     * @param array $params Parï¿½metros de cï¿½lculo
     * @return array|WP_Error
     */
    public function calculate_shipping($params) {
        // Validar parï¿½metros obrigatï¿½rios
        $required = ['cep_origem', 'cep_destino', 'peso', 'formato', 'comprimento', 'altura', 'largura'];
        
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return new WP_Error('missing_param', "Parï¿½metro obrigatï¿½rio ausente: {$field}");
            }
        }
        
        // Verificar cache
        $cache_key = $this->get_cache_key($params);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Preparar dados para API
        $request_data = [
            'cepOrigem' => $this->clean_cep($params['cep_origem']),
            'cepDestino' => $this->clean_cep($params['cep_destino']),
            'peso' => $params['peso'], // em gramas
            'formato' => $params['formato'], // 1=caixa, 2=rolo, 3=envelope
            'comprimento' => $params['comprimento'], // em cm
            'altura' => $params['altura'], // em cm
            'largura' => $params['largura'], // em cm
            'diametro' => $params['diametro'] ?? 0,
            'maoPropria' => $params['mao_propria'] ?? false,
            'valorDeclarado' => $params['valor_declarado'] ?? 0,
            'avisoRecebimento' => $params['aviso_recebimento'] ?? false,
        ];
        
        // Fazer requisiï¿½ï¿½o ï¿½ API
        $response = $this->make_request('preco/v1/nacional', $request_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Processar resposta
        $result = $this->process_shipping_response($response);
        
        // Cachear por 24 horas
        set_transient($cache_key, $result, DAY_IN_SECONDS);
        
        return $result;
    }
    
    /**
     * Calcular frete para carrinho
     * 
     * @param string $cep_destino CEP de destino
     * @param array $items Itens do carrinho
     * @return array
     */
    public function calculate_cart_shipping($cep_destino, $items) {
        // Obter CEP de origem das configuraï¿½ï¿½es
        $cep_origem = get_option('hng_shipping_origin_cep', '');
        
        if (empty($cep_origem)) {
            return new WP_Error('no_origin_cep', 'CEP de origem nï¿½o configurado');
        }
        
        // Calcular peso e dimensï¿½es totais do carrinho
        $dimensions = $this->calculate_cart_dimensions($items);
        
        if (is_wp_error($dimensions)) {
            return $dimensions;
        }
        
        // Calcular frete
        $params = [
            'cep_origem' => $cep_origem,
            'cep_destino' => $cep_destino,
            'peso' => $dimensions['peso'],
            'formato' => 1, // Caixa/pacote
            'comprimento' => $dimensions['comprimento'],
            'altura' => $dimensions['altura'],
            'largura' => $dimensions['largura'],
            'valor_declarado' => $dimensions['valor_total'],
        ];
        
        return $this->calculate_shipping($params);
    }
    
    /**
     * Calcular dimensï¿½es do carrinho
     * 
     * @param array $items Itens do carrinho
     * @return array|WP_Error
     */
    private function calculate_cart_dimensions($items) {
        $peso_total = 0;
        $comprimento_max = 0;
        $altura_max = 0;
        $largura_max = 0;
        $valor_total = 0;
        
        foreach ($items as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            
            // Peso (converter para gramas)
            $peso = (float) get_post_meta($product_id, '_weight', true) ?: 300; // Padrï¿½o 300g
            $peso_total += ($peso * $quantity);
            
            // Dimensï¿½es (em cm)
            $comprimento = (float) get_post_meta($product_id, '_length', true) ?: 16;
            $altura = (float) get_post_meta($product_id, '_height', true) ?: 5;
            $largura = (float) get_post_meta($product_id, '_width', true) ?: 11;
            
            // Usar dimensï¿½es mï¿½ximas
            $comprimento_max = max($comprimento_max, $comprimento);
            $altura_max = max($altura_max, $altura);
            $largura_max = max($largura_max, $largura);
            
            // Valor total
            $valor_total += ($item['price'] * $quantity);
        }
        
        // Validar limites dos Correios
        if ($peso_total > 30000) { // Mï¿½ximo 30kg
            return new WP_Error('weight_exceeded', 'Peso total excede o limite de 30kg dos Correios');
        }
        
        // Dimensï¿½es mï¿½nimas
        $comprimento_max = max(16, $comprimento_max);
        $altura_max = max(2, $altura_max);
        $largura_max = max(11, $largura_max);
        
        return [
            'peso' => $peso_total,
            'comprimento' => $comprimento_max,
            'altura' => $altura_max,
            'largura' => $largura_max,
            'valor_total' => $valor_total,
        ];
    }
    
    /**
     * Processar resposta da API
     */
    private function process_shipping_response($response) {
        $methods = [];
        
        if (empty($response) || !is_array($response)) {
            return $methods;
        }
        
        foreach ($response as $servico) {
            // Cï¿½digo do serviï¿½o: 04014 (SEDEX), 04510 (PAC)
            $codigo = $servico['codigo'] ?? '';
            $nome = $servico['nome'] ?? '';
            $valor = (float) ($servico['valor'] ?? 0);
            $prazo = (int) ($servico['prazoEntrega'] ?? 0);
            $erro = $servico['erro'] ?? 0;
            
            // Ignorar se houver erro
            if ($erro != 0) {
                continue;
            }
            
            // Adicionar margem de lucro configurável
            $margem = (float) get_option('hng_shipping_margin', 0);
            if ($margem > 0) {
                $valor = $valor * (1 + ($margem / 100));
            }
            
            $methods[] = [
                'id' => "correios_{$codigo}",
                'label' => $nome,
                'cost' => $valor,
                'delivery_time' => $prazo,
                /* translators: %1$d: número de dias úteis estimados para entrega */
                'delivery_time_text' => sprintf(_n('%1$d dia útil', '%1$d dias úteis', $prazo, 'hng-commerce'), $prazo),
            ];
        }
        
        return $methods;
    }
    
    /**
     * Rastrear encomenda
     * 
    * @param string $tracking_code Código de rastreamento
     * @return array|WP_Error
     */
    public function track_package($tracking_code) {
        // Verificar cache
        $cache_key = $this->cache_prefix . 'track_' . $tracking_code;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Fazer requisição à API
        $response = $this->make_request("srorastro/v1/objetos/{$tracking_code}", [], 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Processar eventos de rastreamento
        $result = $this->process_tracking_response($response);
        
        // Cachear por 2 horas
        set_transient($cache_key, $result, 2 * HOUR_IN_SECONDS);
        
        return $result;
    }
    
    /**
     * Processar resposta de rastreamento
     */
    private function process_tracking_response($response) {
        $events = [];
        
        if (empty($response['objetos'][0]['eventos'])) {
            return $events;
        }
        
        foreach ($response['objetos'][0]['eventos'] as $evento) {
            $events[] = [
                'date' => $evento['dtHrCriado'] ?? '',
                'location' => $evento['unidade']['endereco']['cidade'] ?? '',
                'status' => $evento['descricao'] ?? '',
                'details' => $evento['detalhe'] ?? '',
            ];
        }
        
        return [
            'code' => $response['objetos'][0]['codObjeto'] ?? '',
            'events' => $events,
            'last_status' => $events[0]['status'] ?? '',
            'delivered' => $this->is_delivered($events[0]['status'] ?? ''),
        ];
    }
    
    /**
     * Verificar se foi entregue
     */
    private function is_delivered($status) {
        $delivered_keywords = ['entregue', 'entrega', 'delivered'];
        
        foreach ($delivered_keywords as $keyword) {
            if (stripos($status, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Fazer requisiï¿½ï¿½o ï¿½ API
     */
    private function make_request($endpoint, $data = [], $method = 'POST') {
        $url = $this->api_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ];
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return new WP_Error('api_error', "Erro na API dos Correios: {$status_code}");
        }
        
        return json_decode($body, true);
    }
    
    /**
     * Limpar CEP
     */
    private function clean_cep($cep) {
        return preg_replace('/[^0-9]/', '', $cep);
    }
    
    /**
     * Gerar chave de cache
     */
    private function get_cache_key($params) {
        return $this->cache_prefix . md5(serialize($params));
    }
    
    /**
     * Limpar cache
     */
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_' . $this->cache_prefix) . '%'
            )
        );
    }
}
