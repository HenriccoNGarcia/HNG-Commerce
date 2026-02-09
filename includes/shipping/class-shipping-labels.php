<?php
/**
 * Shipping Labels Manager
 * 
 * Gerencia a criação e download de etiquetas de envio
 * 
 * @package HNG_Commerce
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Shipping_Labels {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook para gerar etiqueta quando pedido é pago
        add_action('hng_order_status_changed', [$this, 'maybe_generate_label'], 10, 3);
        
        // AJAX para gerar etiqueta manualmente
        add_action('wp_ajax_hng_generate_shipping_label', [$this, 'ajax_generate_label']);
        
        // AJAX para download de etiqueta
        add_action('wp_ajax_hng_download_shipping_label', [$this, 'ajax_download_label']);
        
        // AJAX para salvar código de rastreamento
        add_action('wp_ajax_hng_save_tracking_code', [$this, 'ajax_save_tracking_code']);
    }
    
    /**
     * Gera etiqueta automaticamente quando pedido muda para "processing"
     */
    public function maybe_generate_label($order_id, $old_status, $new_status) {
        // Gerar etiqueta quando pedido vai para processando ou concluído
        if (!in_array($new_status, ['processing', 'completed', 'preparing', 'shipped'])) {
            return;
        }
        
        // Verificar se já tem etiqueta
        $existing_label = get_post_meta($order_id, '_hng_shipping_label', true);
        if (!empty($existing_label)) {
            return;
        }
        
        // Verificar se pedido precisa de envio
        $order = new HNG_Order($order_id);
        if (!$order->needs_shipping()) {
            return;
        }
        
        // Tentar gerar etiqueta
        $this->generate_label_for_order($order_id);
    }
    
    /**
     * Gera etiqueta para um pedido específico
     */
    public function generate_label_for_order($order_id) {
        $order = new HNG_Order($order_id);
        $order_data = $order->get_data();
        
        // Obter método de frete selecionado
        $shipping_method = get_post_meta($order_id, '_shipping_method', true);
        $shipping_data = get_post_meta($order_id, '_shipping_data', true);
        
        if (empty($shipping_method)) {
            // Tentar obter do order_meta
            $shipping_method = $order_data['shipping_method'] ?? '';
        }
        
        // Identificar qual transportadora foi usada
        $carrier = $this->get_carrier_from_method($shipping_method);
        
        if (empty($carrier)) {
            return new WP_Error('no_carrier', __('Método de envio não identificado.', 'hng-commerce'));
        }
        
        // Preparar dados do pacote
        $package_data = $this->prepare_package_data($order);
        
        // Chamar método específico da transportadora
        $result = null;
        
        switch ($carrier) {
            case 'correios':
                $result = $this->generate_correios_label($order, $package_data);
                break;
                
            case 'melhorenvio':
                $result = $this->generate_melhorenvio_label($order, $package_data);
                break;
                
            case 'jadlog':
                $result = $this->generate_jadlog_label($order, $package_data);
                break;
                
            case 'loggi':
                $result = apply_filters('hng_shipping_loggi_create_label', null, $order, $shipping_data, $this->get_carrier_settings('loggi'));
                break;
                
            case 'total_express':
                $result = apply_filters('hng_shipping_total_express_create_label', null, $order, $shipping_data, $this->get_carrier_settings('total_express'));
                break;
                
            case 'custom':
            case 'proprio':
            case 'propria':
                // Transportadora própria - usar gerador de etiqueta customizada
                if (class_exists('HNG_Custom_Shipping_Label')) {
                    $custom_label = HNG_Custom_Shipping_Label::instance();
                    $result = $custom_label->generate_label_for_order($order);
                }
                break;
        }
        
        // Permitir override via filtro
        $result = apply_filters('hng_shipping_generate_label', $result, $order, $carrier, $package_data);
        
        if ($result && !is_wp_error($result)) {
            // Salvar dados da etiqueta
            update_post_meta($order_id, '_hng_shipping_label', $result);
            update_post_meta($order_id, '_hng_shipping_carrier', $carrier);
            
            if (!empty($result['tracking_code'])) {
                update_post_meta($order_id, '_hng_tracking_code', $result['tracking_code']);
            }
            
            // Disparar ação
            do_action('hng_shipping_label_generated', $order_id, $result, $carrier);
            
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Identifica transportadora pelo método de frete
     */
    private function get_carrier_from_method($method) {
        if (empty($method)) {
            return '';
        }
        
        // Formato esperado: "carrier:service" ou apenas "carrier"
        $parts = explode(':', $method);
        $carrier = strtolower($parts[0]);
        
        // Mapear aliases
        $aliases = [
            'pac' => 'correios',
            'sedex' => 'correios',
            '04014' => 'correios',
            '04510' => 'correios',
            'melhor_envio' => 'melhorenvio',
            'proprio' => 'custom',
            'propria' => 'custom',
            'transportadora_propria' => 'custom',
            'frete_proprio' => 'custom',
        ];
        
        return $aliases[$carrier] ?? $carrier;
    }
    
    /**
     * Obtém configurações de uma transportadora
     */
    private function get_carrier_settings($carrier) {
        $opt = function($key, $default = '') use ($carrier) {
            return get_option("hng_shipping_{$carrier}_{$key}", $default);
        };
        
        switch ($carrier) {
            case 'correios':
                return [
                    'codigo_empresa' => $opt('codigo_empresa', ''),
                    'cartao_postagem' => $opt('cartao_postagem', ''),
                    'cnpj' => $opt('cnpj', ''),
                    'usuario_sigep' => $opt('usuario_sigep', ''),
                    'senha' => $opt('senha', ''),
                    'homologacao' => $opt('homologacao', 'yes'),
                    'origin_zipcode' => $opt('origin_zipcode', ''),
                    // Dados do remetente
                    'remetente_nome' => $opt('remetente_nome', get_bloginfo('name')),
                    'remetente_logradouro' => $opt('remetente_logradouro', ''),
                    'remetente_numero' => $opt('remetente_numero', ''),
                    'remetente_complemento' => $opt('remetente_complemento', ''),
                    'remetente_bairro' => $opt('remetente_bairro', ''),
                    'remetente_cidade' => $opt('remetente_cidade', ''),
                    'remetente_uf' => $opt('remetente_uf', ''),
                    'remetente_telefone' => $opt('remetente_telefone', ''),
                ];
                
            case 'melhorenvio':
                return [
                    'token' => $opt('token', ''),
                    'sandbox' => $opt('sandbox', 'yes'),
                    'origin_zipcode' => $opt('origin_zipcode', ''),
                ];
                
            case 'jadlog':
                return [
                    'token' => $opt('token', ''),
                    'cnpj' => $opt('cnpj', ''),
                    'contract_number' => $opt('contract_number', ''),
                    'origin_zipcode' => $opt('origin_zipcode', ''),
                ];
                
            case 'loggi':
                return [
                    'token' => $opt('token', ''),
                    'origin_zipcode' => $opt('origin_zipcode', ''),
                ];
                
            case 'total_express':
                return [
                    'client_code' => $opt('client_code', ''),
                    'api_key' => $opt('api_key', ''),
                    'origin_zipcode' => $opt('origin_zipcode', ''),
                ];
        }
        
        return [];
    }
    
    /**
     * Prepara dados do pacote para geração de etiqueta
     */
    private function prepare_package_data($order) {
        $order_data = $order->get_data();
        $items = $order->get_items();
        
        $weight = 0;
        $length = 0;
        $width = 0;
        $height = 0;
        
        foreach ($items as $item) {
            $product_id = $item['product_id'];
            $qty = $item['quantity'];
            
            $item_weight = floatval(get_post_meta($product_id, '_weight', true)) ?: 0.3;
            $item_length = floatval(get_post_meta($product_id, '_length', true)) ?: 16;
            $item_width = floatval(get_post_meta($product_id, '_width', true)) ?: 11;
            $item_height = floatval(get_post_meta($product_id, '_height', true)) ?: 2;
            
            $weight += $item_weight * $qty;
            $length = max($length, $item_length);
            $width = max($width, $item_width);
            $height += $item_height * $qty;
        }
        
        // Dados do destinatário
        $billing = maybe_unserialize($order_data['billing_address'] ?? '');
        
        return [
            'weight' => max($weight, 0.3),
            'length' => max($length, 16),
            'width' => max($width, 11),
            'height' => max($height, 2),
            'value' => floatval($order_data['total'] ?? 0),
            'recipient' => [
                'name' => $order_data['customer_name'] ?? '',
                'email' => $order_data['customer_email'] ?? '',
                'phone' => $billing['phone'] ?? '',
                'document' => $billing['cpf'] ?? $billing['document'] ?? '',
                'address' => $billing['street'] ?? $billing['address_1'] ?? '',
                'number' => $billing['number'] ?? '',
                'complement' => $billing['complement'] ?? $billing['address_2'] ?? '',
                'district' => $billing['district'] ?? $billing['neighborhood'] ?? '',
                'city' => $billing['city'] ?? '',
                'state' => $billing['state'] ?? '',
                'postcode' => preg_replace('/\D/', '', $billing['postcode'] ?? ''),
            ],
        ];
    }
    
    /**
     * Gera etiqueta via Correios (SIGEP Web)
     * 
     * API SIGEP Web usa SOAP para:
     * 1. Solicitar etiquetas (buscaCliente + solicitaEtiquetas)
     * 2. Gerar dígito verificador
     * 3. Fechar PLP (Pré-Lista de Postagem)
     * 
     * Limites importantes:
     * - Máximo 10 etiquetas por requisição
     * - Etiquetas devem ser usadas em até 30 dias
     * - PLP deve ser fechada para validar etiquetas
     */
    private function generate_correios_label($order, $package) {
        $settings = $this->get_carrier_settings('correios');
        
        // Validar configurações obrigatórias
        $required = ['codigo_empresa', 'cartao_postagem', 'usuario_sigep', 'senha'];
        foreach ($required as $field) {
            if (empty($settings[$field])) {
                return [
                    'status' => 'manual',
                    'message' => sprintf(
                        /* translators: %s: field name that is not configured */
                        __('Campo "%s" não configurado. Configure o contrato SIGEP completo para gerar etiquetas automaticamente.', 'hng-commerce'),
                        $field
                    ),
                    'manual_url' => 'https://cas.correios.com.br/login',
                    'help' => __('Acesse Frete > Correios e preencha todos os campos do contrato SIGEP.', 'hng-commerce'),
                ];
            }
        }
        
        // Determinar ambiente (homologação ou produção)
        $is_sandbox = ($settings['homologacao'] ?? 'yes') === 'yes';
        $wsdl_url = $is_sandbox 
            ? 'https://apphom.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl'
            : 'https://apps.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl';
        
        try {
            // Criar cliente SOAP
            $soap_options = [
                'trace' => true,
                'exceptions' => true,
                'connection_timeout' => 30,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ];
            
            $client = new SoapClient($wsdl_url, $soap_options);
            
            // 1. Buscar cliente para validar contrato
            $cliente_params = [
                'idContrato' => $settings['codigo_empresa'],
                'idCartaoPostagem' => $settings['cartao_postagem'],
                'usuario' => $settings['usuario_sigep'],
                'senha' => $settings['senha'],
            ];
            
            $cliente_result = $client->buscaCliente($cliente_params);
            
            if (empty($cliente_result->return)) {
                return new WP_Error('sigep_client', __('Não foi possível validar o contrato com os Correios.', 'hng-commerce'));
            }
            
            // Obter código do serviço (PAC, SEDEX, etc)
            $service_code = get_post_meta($order->get_id(), '_shipping_service_code', true);
            if (empty($service_code)) {
                // Tentar extrair do método de envio
                $shipping_method = get_post_meta($order->get_id(), '_shipping_method', true);
                if (preg_match('/correios:(\d+)/', $shipping_method, $matches)) {
                    $service_code = $matches[1];
                } else {
                    $service_code = '04510'; // PAC padrão
                }
            }
            
            // 2. Solicitar faixa de etiquetas (1 etiqueta)
            $etiqueta_params = [
                'tipoDestinatario' => 'C', // Cliente
                'identificador' => preg_replace('/\D/', '', $settings['cnpj']),
                'idServico' => $this->get_sigep_service_id($service_code, $cliente_result->return),
                'qtdEtiquetas' => 1,
                'usuario' => $settings['usuario_sigep'],
                'senha' => $settings['senha'],
            ];
            
            $etiqueta_result = $client->solicitaEtiquetas($etiqueta_params);
            
            if (empty($etiqueta_result->return)) {
                return new WP_Error('sigep_etiqueta', __('Não foi possível solicitar etiqueta dos Correios.', 'hng-commerce'));
            }
            
            // Etiqueta vem no formato "SS123456789BR" ou range "SS123456789BR,SS123456790BR"
            $etiquetas = explode(',', $etiqueta_result->return);
            $etiqueta_sem_dv = trim($etiquetas[0]);
            
            // 3. Gerar dígito verificador
            $dv_params = [
                'etiquetas' => $etiqueta_sem_dv,
                'usuario' => $settings['usuario_sigep'],
                'senha' => $settings['senha'],
            ];
            
            $dv_result = $client->geraDigitoVerificadorEtiquetas($dv_params);
            $digito = $dv_result->return[0] ?? 0;
            
            // Montar código de rastreamento completo
            $prefixo = substr($etiqueta_sem_dv, 0, 2);
            $numero = substr($etiqueta_sem_dv, 2, 8);
            $sufixo = substr($etiqueta_sem_dv, -2);
            $tracking_code = $prefixo . $numero . $digito . $sufixo;
            
            // 4. Criar objeto de postagem para PLP
            $objeto_postal = $this->build_correios_objeto_postal($order, $package, $settings, $tracking_code, $service_code);
            
            // 5. Fechar PLP (Pré-Lista de Postagem)
            $plp_params = [
                'xml' => $this->build_correios_plp_xml($objeto_postal, $settings, $tracking_code),
                'idPlpCliente' => $order->get_id(),
                'cartaoPostagem' => $settings['cartao_postagem'],
                'listaEtiquetas' => [$etiqueta_sem_dv],
                'usuario' => $settings['usuario_sigep'],
                'senha' => $settings['senha'],
            ];
            
            $plp_result = $client->fechaPlpVariosServicos($plp_params);
            $plp_id = $plp_result->return ?? '';
            
            // 6. Obter URL da etiqueta (opcional - nem sempre disponível)
            $label_url = '';
            if (!empty($plp_id)) {
                // URL para download do PDF da PLP
                $label_url = $is_sandbox
                    ? "https://apphom.correios.com.br/SigepMasterJPA/etiqueta/EtiquetaVisualizaServlet?idPlp={$plp_id}&usuario={$settings['usuario_sigep']}&senha={$settings['senha']}"
                    : "https://apps.correios.com.br/SigepMasterJPA/etiqueta/EtiquetaVisualizaServlet?idPlp={$plp_id}&usuario={$settings['usuario_sigep']}&senha={$settings['senha']}";
            }
            
            // Log de sucesso
            $this->log_correios('Etiqueta gerada com sucesso', [
                'order_id' => $order->get_id(),
                'tracking' => $tracking_code,
                'plp_id' => $plp_id,
                'ambiente' => $is_sandbox ? 'homologacao' : 'producao',
            ]);
            
            return [
                'status' => 'generated',
                'tracking_code' => $tracking_code,
                'plp_id' => $plp_id,
                'label_url' => $label_url,
                'carrier' => 'correios',
                'service_code' => $service_code,
                'created_at' => current_time('mysql'),
                'environment' => $is_sandbox ? 'sandbox' : 'production',
                'message' => sprintf(
                    /* translators: 1: tracking code, 2: PLP ID */
                    __('Etiqueta gerada! Código: %1$s | PLP: %2$s', 'hng-commerce'),
                    $tracking_code,
                    $plp_id
                ),
            ];
            
        } catch (SoapFault $e) {
            $this->log_correios('Erro SOAP SIGEP', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'order_id' => $order->get_id(),
            ]);
            
            return new WP_Error(
                'sigep_soap_error',
                /* translators: %s: error message from SIGEP service */
                sprintf(__('Erro na comunicação com SIGEP: %s', 'hng-commerce'), $e->getMessage())
            );
            
        } catch (Exception $e) {
            $this->log_correios('Erro geral SIGEP', [
                'message' => $e->getMessage(),
                'order_id' => $order->get_id(),
            ]);
            
            return new WP_Error(
                'sigep_error',
                /* translators: %s: error message */
                sprintf(__('Erro ao gerar etiqueta: %s', 'hng-commerce'), $e->getMessage())
            );
        }
    }
    
    /**
     * Obtém ID do serviço SIGEP a partir do código do serviço
     */
    private function get_sigep_service_id($service_code, $cliente_data) {
        // Tentar encontrar o ID do serviço nos contratos do cliente
        if (!empty($cliente_data->contratos)) {
            foreach ($cliente_data->contratos as $contrato) {
                if (!empty($contrato->cartoesPostagem)) {
                    foreach ($contrato->cartoesPostagem as $cartao) {
                        if (!empty($cartao->servicos)) {
                            foreach ($cartao->servicos as $servico) {
                                if ($servico->codigo == $service_code) {
                                    return $servico->id;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Mapeamento padrão de códigos para IDs
        $map = [
            '04014' => 124849, // SEDEX
            '04510' => 124848, // PAC
            '04782' => 124850, // SEDEX 10
            '04804' => 124851, // SEDEX Hoje
            '41106' => 109819, // PAC sem contrato
            '40010' => 109817, // SEDEX sem contrato
        ];
        
        return $map[$service_code] ?? $map['04510'];
    }
    
    /**
     * Constrói objeto postal para PLP dos Correios
     */
    private function build_correios_objeto_postal($order, $package, $settings, $tracking, $service_code) {
        $order_data = $order->get_data();
        
        return [
            'numero_etiqueta' => $tracking,
            'codigo_servico_postagem' => $service_code,
            'peso' => (int) ($package['weight'] * 1000), // em gramas
            'dimensao' => [
                'tipo_objeto' => '002', // Pacote/Caixa
                'altura' => (int) $package['height'],
                'largura' => (int) $package['width'],
                'comprimento' => (int) $package['length'],
            ],
            'valor_declarado' => number_format($package['value'], 2, '.', ''),
            'destinatario' => [
                'nome' => $package['recipient']['name'],
                'telefone' => preg_replace('/\D/', '', $package['recipient']['phone']),
                'email' => $package['recipient']['email'],
                'logradouro' => $package['recipient']['address'],
                'numero' => $package['recipient']['number'],
                'complemento' => $package['recipient']['complement'],
                'bairro' => $package['recipient']['district'],
                'cidade' => $package['recipient']['city'],
                'uf' => $package['recipient']['state'],
                'cep' => $package['recipient']['postcode'],
            ],
            'remetente' => [
                'nome' => $settings['remetente_nome'],
                'telefone' => preg_replace('/\D/', '', $settings['remetente_telefone']),
                'logradouro' => $settings['remetente_logradouro'],
                'numero' => $settings['remetente_numero'],
                'complemento' => $settings['remetente_complemento'],
                'bairro' => $settings['remetente_bairro'],
                'cidade' => $settings['remetente_cidade'],
                'uf' => $settings['remetente_uf'],
                'cep' => preg_replace('/\D/', '', $settings['origin_zipcode']),
            ],
        ];
    }
    
    /**
     * Constrói XML da PLP para fechamento
     */
    private function build_correios_plp_xml($objeto, $settings, $tracking) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="ISO-8859-1"?><correioslog/>');
        
        $xml->addChild('tipo_arquivo', 'Postagem');
        $xml->addChild('versao_arquivo', '2.3');
        
        $plp = $xml->addChild('plp');
        $plp->addChild('id_plp', '');
        $plp->addChild('valor_global', '');
        $plp->addChild('mcu_unidade_postagem', '');
        $plp->addChild('nome_unidade_postagem', '');
        $plp->addChild('cartao_postagem', $settings['cartao_postagem']);
        
        $rem = $xml->addChild('remetente');
        $rem->addChild('numero_contrato', $settings['codigo_empresa']);
        $rem->addChild('numero_diretoria', '36');
        $rem->addChild('codigo_administrativo', $settings['codigo_empresa']);
        $rem->addChild('nome_remetente', $this->sanitize_xml($settings['remetente_nome']));
        $rem->addChild('logradouro_remetente', $this->sanitize_xml($settings['remetente_logradouro']));
        $rem->addChild('numero_remetente', $settings['remetente_numero']);
        $rem->addChild('complemento_remetente', $this->sanitize_xml($settings['remetente_complemento']));
        $rem->addChild('bairro_remetente', $this->sanitize_xml($settings['remetente_bairro']));
        $rem->addChild('cep_remetente', preg_replace('/\D/', '', $settings['origin_zipcode']));
        $rem->addChild('cidade_remetente', $this->sanitize_xml($settings['remetente_cidade']));
        $rem->addChild('uf_remetente', strtoupper($settings['remetente_uf']));
        $rem->addChild('telefone_remetente', preg_replace('/\D/', '', $settings['remetente_telefone']));
        $rem->addChild('email_remetente', get_option('admin_email'));
        
        // Forma de pagamento
        $forma = $xml->addChild('forma_pagamento');
        $forma->addChild('codigo_forma_pagamento', '0'); // Já pago
        
        // Objeto postal
        $obj = $xml->addChild('objeto_postal');
        $obj->addChild('numero_etiqueta', $tracking);
        $obj->addChild('codigo_objeto_cliente', '');
        $obj->addChild('codigo_servico_postagem', $objeto['codigo_servico_postagem']);
        $obj->addChild('cubagem', '0,0000');
        $obj->addChild('peso', $objeto['peso']);
        $obj->addChild('rt1', '');
        $obj->addChild('rt2', '');
        
        $dest = $obj->addChild('destinatario');
        $dest->addChild('nome_destinatario', $this->sanitize_xml($objeto['destinatario']['nome']));
        $dest->addChild('telefone_destinatario', $objeto['destinatario']['telefone']);
        $dest->addChild('celular_destinatario', $objeto['destinatario']['telefone']);
        $dest->addChild('email_destinatario', $objeto['destinatario']['email']);
        $dest->addChild('logradouro_destinatario', $this->sanitize_xml($objeto['destinatario']['logradouro']));
        $dest->addChild('numero_end_destinatario', $objeto['destinatario']['numero']);
        $dest->addChild('complemento_destinatario', $this->sanitize_xml($objeto['destinatario']['complemento']));
        $dest->addChild('bairro_destinatario', $this->sanitize_xml($objeto['destinatario']['bairro']));
        $dest->addChild('cidade_destinatario', $this->sanitize_xml($objeto['destinatario']['cidade']));
        $dest->addChild('uf_destinatario', strtoupper($objeto['destinatario']['uf']));
        $dest->addChild('cep_destinatario', $objeto['destinatario']['cep']);
        
        $nac = $obj->addChild('nacional');
        $nac->addChild('bairro_destinatario', $this->sanitize_xml($objeto['destinatario']['bairro']));
        $nac->addChild('cidade_destinatario', $this->sanitize_xml($objeto['destinatario']['cidade']));
        $nac->addChild('uf_destinatario', strtoupper($objeto['destinatario']['uf']));
        $nac->addChild('cep_destinatario', $objeto['destinatario']['cep']);
        $nac->addChild('codigo_usuario_postal', '');
        $nac->addChild('centro_custo_cliente', '');
        $nac->addChild('numero_nota_fiscal', '');
        $nac->addChild('serie_nota_fiscal', '');
        $nac->addChild('valor_nota_fiscal', $objeto['valor_declarado']);
        $nac->addChild('natureza_nota_fiscal', 'Venda');
        $nac->addChild('descricao_objeto', 'Mercadoria');
        $nac->addChild('valor_a_cobrar', '0,00');
        
        $serv = $obj->addChild('servico_adicional');
        $serv->addChild('codigo_servico_adicional', '025'); // Registro Nacional
        
        $dim = $obj->addChild('dimensao_objeto');
        $dim->addChild('tipo_objeto', $objeto['dimensao']['tipo_objeto']);
        $dim->addChild('dimensao_altura', $objeto['dimensao']['altura']);
        $dim->addChild('dimensao_largura', $objeto['dimensao']['largura']);
        $dim->addChild('dimensao_comprimento', $objeto['dimensao']['comprimento']);
        $dim->addChild('dimensao_diametro', '0');
        
        $obj->addChild('data_postagem_sara', '');
        $obj->addChild('status_processamento', '0');
        $obj->addChild('numero_comprovante_postagem', '');
        $obj->addChild('valor_cobrado', '');
        
        return $xml->asXML();
    }
    
    /**
     * Sanitiza string para uso em XML
     */
    private function sanitize_xml($string) {
        $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
        $string = preg_replace('/[^\p{L}\p{N}\s\-.,]/u', '', $string);
        return mb_substr($string, 0, 50);
    }
    
    /**
     * Log específico para Correios
     */
    private function log_correios($message, $data = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HNG Commerce - Correios SIGEP] ' . $message . ' | ' . wp_json_encode($data));
        }
        
        // Também salvar em option para debug via admin
        $logs = get_option('hng_correios_sigep_logs', []);
        array_unshift($logs, [
            'time' => current_time('mysql'),
            'message' => $message,
            'data' => $data,
        ]);
        // Manter apenas últimos 50 logs
        $logs = array_slice($logs, 0, 50);
        update_option('hng_correios_sigep_logs', $logs);
    }
    
    /**
     * Gera etiqueta via Melhor Envio
     */
    private function generate_melhorenvio_label($order, $package) {
        $settings = $this->get_carrier_settings('melhorenvio');
        
        if (empty($settings['token'])) {
            return new WP_Error('no_token', __('Token Melhor Envio não configurado.', 'hng-commerce'));
        }
        
        $api_url = ($settings['sandbox'] === 'yes') 
            ? 'https://sandbox.melhorenvio.com.br/api/v2'
            : 'https://melhorenvio.com.br/api/v2';
        
        $origin_zip = preg_replace('/\D/', '', $settings['origin_zipcode']);
        
        // 1. Criar carrinho de frete
        $cart_payload = [
            'service' => get_post_meta($order->get_id(), '_shipping_service_code', true) ?: 1,
            'from' => [
                'postal_code' => $origin_zip,
            ],
            'to' => [
                'name' => $package['recipient']['name'],
                'phone' => $package['recipient']['phone'],
                'email' => $package['recipient']['email'],
                'document' => $package['recipient']['document'],
                'address' => $package['recipient']['address'],
                'number' => $package['recipient']['number'],
                'complement' => $package['recipient']['complement'],
                'district' => $package['recipient']['district'],
                'city' => $package['recipient']['city'],
                'state_abbr' => $package['recipient']['state'],
                'postal_code' => $package['recipient']['postcode'],
            ],
            'products' => [
                [
                    'name' => 'Pedido #' . $order->get_id(),
                    'quantity' => 1,
                    'unitary_value' => $package['value'],
                ],
            ],
            'package' => [
                'height' => $package['height'],
                'width' => $package['width'],
                'length' => $package['length'],
                'weight' => $package['weight'],
            ],
            'options' => [
                'insurance_value' => $package['value'],
                'receipt' => false,
                'own_hand' => false,
            ],
        ];
        
        // Requisição para criar no carrinho
        $cart_response = wp_remote_post($api_url . '/cart', [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['token'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($cart_payload),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($cart_response)) {
            return $cart_response;
        }
        
        $cart_data = json_decode(wp_remote_retrieve_body($cart_response), true);
        
        if (empty($cart_data['id'])) {
            return new WP_Error('cart_failed', $cart_data['message'] ?? __('Erro ao criar envio no Melhor Envio.', 'hng-commerce'));
        }
        
        $shipment_id = $cart_data['id'];
        
        // 2. Checkout (pagar o frete) - em sandbox é gratuito
        $checkout_response = wp_remote_post($api_url . '/shipment/checkout', [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['token'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode(['orders' => [$shipment_id]]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($checkout_response)) {
            return $checkout_response;
        }
        
        $checkout_data = json_decode(wp_remote_retrieve_body($checkout_response), true);
        
        // 3. Gerar etiqueta
        $label_response = wp_remote_post($api_url . '/shipment/generate', [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['token'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode(['orders' => [$shipment_id]]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($label_response)) {
            return $label_response;
        }
        
        // 4. Obter URL da etiqueta
        $print_response = wp_remote_post($api_url . '/shipment/print', [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['token'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode(['orders' => [$shipment_id]]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($print_response)) {
            return $print_response;
        }
        
        $print_data = json_decode(wp_remote_retrieve_body($print_response), true);
        
        return [
            'status' => 'generated',
            'shipment_id' => $shipment_id,
            'tracking_code' => $checkout_data['purchase']['orders'][0]['tracking'] ?? '',
            'label_url' => $print_data['url'] ?? '',
            'carrier' => 'melhorenvio',
            'created_at' => current_time('mysql'),
        ];
    }
    
    /**
     * Gera etiqueta via Jadlog
     */
    private function generate_jadlog_label($order, $package) {
        $settings = $this->get_carrier_settings('jadlog');
        
        if (empty($settings['token'])) {
            return new WP_Error('no_token', __('Token Jadlog não configurado.', 'hng-commerce'));
        }
        
        $api_url = 'https://www.jadlog.com.br/embarcador/api';
        $origin_zip = preg_replace('/\D/', '', $settings['origin_zipcode']);
        
        // Criar pedido de coleta/envio
        $payload = [
            'pedido' => [
                [
                    'conteudo' => 'Pedido #' . $order->get_id(),
                    'totPeso' => $package['weight'],
                    'totValor' => $package['value'],
                    'rem' => [
                        'nome' => get_bloginfo('name'),
                        'cnpjCpf' => preg_replace('/\D/', '', $settings['cnpj']),
                        'endereco' => get_option('hng_store_address', ''),
                        'numero' => get_option('hng_store_number', ''),
                        'compl' => '',
                        'bairro' => get_option('hng_store_district', ''),
                        'cidade' => get_option('hng_store_city', ''),
                        'uf' => get_option('hng_store_state', ''),
                        'cep' => $origin_zip,
                        'fone' => get_option('hng_store_phone', ''),
                    ],
                    'des' => [
                        'nome' => $package['recipient']['name'],
                        'cnpjCpf' => preg_replace('/\D/', '', $package['recipient']['document']),
                        'endereco' => $package['recipient']['address'],
                        'numero' => $package['recipient']['number'],
                        'compl' => $package['recipient']['complement'],
                        'bairro' => $package['recipient']['district'],
                        'cidade' => $package['recipient']['city'],
                        'uf' => $package['recipient']['state'],
                        'cep' => $package['recipient']['postcode'],
                        'fone' => $package['recipient']['phone'],
                    ],
                    'dfe' => [
                        'cfop' => '5102',
                    ],
                    'volume' => [
                        'qtde' => 1,
                        'peso' => $package['weight'],
                    ],
                ],
            ],
        ];
        
        $response = wp_remote_post($api_url . '/pedido/incluir', [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['token'],
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($data['pedido'][0]['codigo'])) {
            return new WP_Error('jadlog_failed', $data['mensagem'] ?? __('Erro ao criar envio na Jadlog.', 'hng-commerce'));
        }
        
        $tracking = $data['pedido'][0]['shipmentId'] ?? $data['pedido'][0]['codigo'] ?? '';
        
        return [
            'status' => 'generated',
            'shipment_id' => $data['pedido'][0]['codigo'],
            'tracking_code' => $tracking,
            'label_url' => '', // Jadlog usa portal web para etiquetas
            'carrier' => 'jadlog',
            'created_at' => current_time('mysql'),
            'message' => __('Etiqueta disponível no portal Jadlog.', 'hng-commerce'),
            'portal_url' => 'https://www.jadlog.com.br/embarcador/',
        ];
    }
    
    /**
     * AJAX: Gerar etiqueta manualmente
     */
    public function ajax_generate_label() {
        check_ajax_referer('hng_shipping_label', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        $order_id = absint($_POST['order_id'] ?? 0);
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('ID do pedido inválido.', 'hng-commerce')]);
        }
        
        $result = $this->generate_label_for_order($order_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Download de etiqueta
     */
    public function ajax_download_label() {
        check_ajax_referer('hng_shipping_label', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão.', 'hng-commerce'));
        }
        
        $order_id = absint($_GET['order_id'] ?? 0);
        $carrier = get_post_meta($order_id, '_hng_shipping_carrier', true);
        $label_data = get_post_meta($order_id, '_hng_shipping_label', true);
        
        // Etiqueta custom - usar gerador interno
        if ($carrier === 'custom' && class_exists('HNG_Custom_Shipping_Label')) {
            $custom_label = HNG_Custom_Shipping_Label::instance();
            $custom_label->render_label_html($label_data);
            exit;
        }
        
        if (empty($label_data['label_url'])) {
            wp_die(esc_html__('Etiqueta não disponível para download.', 'hng-commerce'));
        }
        
        // Redirecionar para URL da etiqueta
        wp_safe_redirect(esc_url_raw($label_data['label_url']));
        exit;
    }
    
    /**
     * AJAX: Salvar código de rastreamento manualmente
     */
    public function ajax_save_tracking_code() {
        check_ajax_referer('hng_shipping_label', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        $order_id = absint($_POST['order_id'] ?? 0);
        $tracking_code = sanitize_text_field($_POST['tracking_code'] ?? '');
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('ID do pedido inválido.', 'hng-commerce')]);
        }
        
        if (empty($tracking_code)) {
            wp_send_json_error(['message' => __('Código de rastreamento não pode ser vazio.', 'hng-commerce')]);
        }
        
        // Salvar código de rastreamento
        update_post_meta($order_id, '_hng_tracking_code', $tracking_code);
        
        // Se já existe dados de etiqueta, atualizar o tracking nela também
        $label_data = get_post_meta($order_id, '_hng_shipping_label', true);
        if (!empty($label_data) && is_array($label_data)) {
            $label_data['tracking_code'] = $tracking_code;
            update_post_meta($order_id, '_hng_shipping_label', $label_data);
        }
        
        // Disparar ação para possíveis integrações
        do_action('hng_tracking_code_saved', $order_id, $tracking_code);
        
        wp_send_json_success([
            'message' => __('Código de rastreamento salvo com sucesso.', 'hng-commerce'),
            'tracking_code' => $tracking_code,
        ]);
    }
    
    /**
     * Obtém dados da etiqueta de um pedido
     */
    public static function get_label_data($order_id) {
        return get_post_meta($order_id, '_hng_shipping_label', true);
    }
    
    /**
     * Verifica se pedido tem etiqueta
     */
    public static function has_label($order_id) {
        $label = get_post_meta($order_id, '_hng_shipping_label', true);
        return !empty($label);
    }
}

// Inicializar
add_action('plugins_loaded', function() {
    HNG_Shipping_Labels::instance();
});
