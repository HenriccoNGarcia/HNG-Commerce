<?php
/**
 * Custom Shipping Label Generator
 * 
 * Gera etiquetas de envio para transportadora própria
 * 
 * @package HNG_Commerce
 * @since 1.2.16
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Custom_Shipping_Label {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX para gerar etiqueta customizada
        add_action('wp_ajax_hng_generate_custom_label', [$this, 'ajax_generate_label']);
        
        // AJAX para download da etiqueta
        add_action('wp_ajax_hng_download_custom_label', [$this, 'ajax_download_label']);
        
        // Hook no sistema de etiquetas principal
        add_filter('hng_shipping_generate_label', [$this, 'generate_label'], 10, 4);
        
        // Adicionar "custom" ao mapeamento de transportadoras
        add_filter('hng_shipping_carrier_aliases', [$this, 'add_carrier_alias']);
    }
    
    /**
     * Adicionar alias para transportadora custom
     */
    public function add_carrier_alias($aliases) {
        $aliases['custom'] = 'custom';
        $aliases['proprio'] = 'custom';
        $aliases['propria'] = 'custom';
        return $aliases;
    }
    
    /**
     * Hook para gerar etiqueta customizada
     */
    public function generate_label($result, $order, $carrier, $package_data) {
        if ($carrier !== 'custom') {
            return $result;
        }
        
        return $this->generate_label_for_order($order);
    }
    
    /**
     * Gerar etiqueta para pedido
     */
    public function generate_label_for_order($order) {
        if (is_numeric($order)) {
            $order = new HNG_Order($order);
        }
        
        $order_id = $order->get_id();
        $order_data = $order->get_data();
        $settings = HNG_Shipping_Custom::get_custom_settings();
        
        // Gerar código de rastreamento
        $tracking_code = HNG_Shipping_Custom::generate_tracking_code($order_id);
        
        // Dados do remetente (da loja)
        $sender = [
            'name' => $settings['company_name'] ?: get_bloginfo('name'),
            'company' => get_bloginfo('name'),
            'address' => $settings['company_address'] ?: get_option('hng_store_address', ''),
            'city' => $settings['company_city'] ?: get_option('hng_store_city', ''),
            'state' => $settings['company_state'] ?: get_option('hng_store_state', ''),
            'postcode' => $settings['company_cep'] ?: get_option('hng_store_cep', ''),
            'country' => 'BR',
            'phone' => $settings['company_phone'] ?: get_option('hng_store_phone', ''),
            'email' => $settings['company_email'] ?: get_option('hng_store_email', ''),
            'cnpj' => $settings['company_cnpj'] ?: get_option('hng_store_cnpj', ''),
        ];
        
        // Dados do destinatário
        $recipient = [
            'name' => $order_data['customer_name'] ?? '',
            'address' => ($order_data['shipping_address'] ?? '') . ' ' . ($order_data['shipping_number'] ?? ''),
            'complement' => $order_data['shipping_complement'] ?? '',
            'district' => $order_data['shipping_district'] ?? '',
            'city' => $order_data['shipping_city'] ?? '',
            'state' => $order_data['shipping_state'] ?? '',
            'postcode' => $order_data['shipping_postcode'] ?? '',
            'country' => 'BR',
            'phone' => $order_data['customer_phone'] ?? '',
            'email' => $order_data['customer_email'] ?? '',
            'cpf' => $order_data['customer_cpf'] ?? '',
        ];
        
        // Dados do pacote
        $package = $this->get_package_data($order);
        
        // Dados do frete selecionado
        $shipping_method = get_post_meta($order_id, '_shipping_method', true);
        $shipping_cost = get_post_meta($order_id, '_shipping_cost', true) ?: $order_data['shipping_total'] ?? 0;
        $shipping_label = get_post_meta($order_id, '_shipping_label_name', true) ?: 'Entrega';
        
        // Montar dados da etiqueta
        $label_data = [
            'tracking_code' => $tracking_code,
            'order_id' => $order_id,
            'order_number' => $order_data['order_number'] ?? $order_id,
            'date' => current_time('Y-m-d H:i:s'),
            'carrier' => 'custom',
            'service' => $shipping_label,
            'cost' => $shipping_cost,
            'sender' => $sender,
            'recipient' => $recipient,
            'package' => $package,
            'items' => $this->get_order_items($order),
            'notes' => get_post_meta($order_id, '_customer_notes', true) ?: '',
            'label_format' => $settings['label_format'] ?: 'a4',
            'generated_at' => current_time('mysql'),
            'status' => 'generated',
        ];
        
        // Salvar dados da etiqueta
        update_post_meta($order_id, '_hng_shipping_label', $label_data);
        update_post_meta($order_id, '_hng_tracking_code', $tracking_code);
        update_post_meta($order_id, '_hng_shipping_carrier', 'custom');
        
        return $label_data;
    }
    
    /**
     * Obter dados do pacote
     */
    private function get_package_data($order) {
        $order_id = $order->get_id();
        $items = $order->get_items();
        
        $total_weight = 0;
        $total_volume = 0;
        $max_length = 0;
        $max_width = 0;
        $max_height = 0;
        
        foreach ($items as $item) {
            $product_id = $item['product_id'] ?? 0;
            $qty = $item['quantity'] ?? 1;
            
            $weight = floatval(get_post_meta($product_id, '_weight', true));
            $length = floatval(get_post_meta($product_id, '_length', true));
            $width = floatval(get_post_meta($product_id, '_width', true));
            $height = floatval(get_post_meta($product_id, '_height', true));
            
            $total_weight += $weight * $qty;
            $total_volume += ($length * $width * $height) * $qty;
            
            $max_length = max($max_length, $length);
            $max_width = max($max_width, $width);
            $max_height += $height * $qty; // Empilhar
        }
        
        return [
            'weight' => $total_weight ?: 0.5, // mínimo 500g
            'length' => $max_length ?: 20,
            'width' => $max_width ?: 15,
            'height' => $max_height ?: 10,
            'volume' => $total_volume,
            'quantity' => count($items),
        ];
    }
    
    /**
     * Obter itens do pedido
     */
    private function get_order_items($order) {
        $items = [];
        
        foreach ($order->get_items() as $item) {
            $items[] = [
                'name' => $item['name'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'sku' => $item['sku'] ?? '',
            ];
        }
        
        return $items;
    }
    
    /**
     * AJAX: Gerar etiqueta
     */
    public function ajax_generate_label() {
        check_ajax_referer('hng_shipping_label', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'hng-commerce')]);
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Pedido não encontrado.', 'hng-commerce')]);
        }
        
        $order = new HNG_Order($order_id);
        
        $result = $this->generate_label_for_order($order);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'message' => __('Etiqueta gerada com sucesso!', 'hng-commerce'),
            'label' => $result,
            'tracking_code' => $result['tracking_code'],
        ]);
    }
    
    /**
     * AJAX: Download da etiqueta (HTML para impressão)
     */
    public function ajax_download_label() {
        check_ajax_referer('hng_shipping_label', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(__('Sem permissão.', 'hng-commerce')));
        }
        
        $order_id = intval($_GET['order_id'] ?? 0);
        
        if (!$order_id) {
            wp_die(esc_html(__('Pedido não encontrado.', 'hng-commerce')));
        }
        
        $label_data = get_post_meta($order_id, '_hng_shipping_label', true);
        
        if (empty($label_data)) {
            wp_die(esc_html(__('Etiqueta não encontrada. Gere a etiqueta primeiro.', 'hng-commerce')));
        }
        
        $this->render_label_html($label_data);
        exit;
    }
    
    /**
     * Renderizar HTML da etiqueta para impressão
     */
    public function render_label_html($label_data) {
        $format = $label_data['label_format'] ?? 'a4';
        $copies = intval(get_option('hng_shipping_custom_label_copies', 1));
        
        // Definir tamanho baseado no formato
        $sizes = [
            'a4' => ['width' => '210mm', 'height' => '297mm', 'label_height' => '148mm'],
            'a5' => ['width' => '148mm', 'height' => '210mm', 'label_height' => '200mm'],
            '10x15' => ['width' => '100mm', 'height' => '150mm', 'label_height' => '145mm'],
            'zebra' => ['width' => '101.6mm', 'height' => '152.4mm', 'label_height' => '147mm'],
        ];
        
        $size = $sizes[$format] ?? $sizes['a4'];
        
        $sender = $label_data['sender'] ?? [];
        $recipient = $label_data['recipient'] ?? [];
        $package = $label_data['package'] ?? [];
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Etiqueta de Envio - #<?php echo esc_html($label_data['order_number'] ?? $label_data['order_id']); ?></title>
            <style>
                @page {
                    size: <?php echo esc_attr($size['width']); ?> <?php echo esc_attr($size['height']); ?>;
                    margin: 5mm;
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 11px;
                    line-height: 1.4;
                    color: #000;
                }
                
                .label-container {
                    width: 100%;
                    height: <?php echo esc_attr($size['label_height']); ?>;
                    border: 2px solid #000;
                    padding: 8px;
                    page-break-after: always;
                    display: flex;
                    flex-direction: column;
                }
                
                .label-container:last-child {
                    page-break-after: avoid;
                }
                
                .label-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    padding-bottom: 8px;
                    border-bottom: 2px solid #000;
                    margin-bottom: 8px;
                }
                
                .company-logo {
                    font-size: 16px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                
                .tracking-box {
                    text-align: right;
                }
                
                .tracking-code {
                    font-size: 14px;
                    font-weight: bold;
                    font-family: monospace;
                    letter-spacing: 1px;
                }
                
                .barcode {
                    margin-top: 5px;
                }
                
                .barcode svg {
                    height: 40px;
                }
                
                .addresses {
                    display: flex;
                    gap: 10px;
                    flex: 1;
                }
                
                .address-box {
                    flex: 1;
                    padding: 8px;
                    border: 1px solid #000;
                }
                
                .address-box.recipient {
                    border-width: 2px;
                    background: #f9f9f9;
                }
                
                .address-title {
                    font-size: 10px;
                    font-weight: bold;
                    text-transform: uppercase;
                    margin-bottom: 5px;
                    color: #666;
                }
                
                .address-name {
                    font-size: 14px;
                    font-weight: bold;
                    margin-bottom: 3px;
                }
                
                .address-line {
                    margin-bottom: 2px;
                }
                
                .postcode {
                    font-size: 18px;
                    font-weight: bold;
                    font-family: monospace;
                    margin-top: 5px;
                }
                
                .label-footer {
                    display: flex;
                    justify-content: space-between;
                    padding-top: 8px;
                    border-top: 1px dashed #000;
                    margin-top: 8px;
                    font-size: 10px;
                }
                
                .package-info {
                    display: flex;
                    gap: 15px;
                }
                
                .package-item {
                    text-align: center;
                }
                
                .package-item strong {
                    display: block;
                    font-size: 12px;
                }
                
                .order-info {
                    text-align: right;
                }
                
                .service-badge {
                    background: #000;
                    color: #fff;
                    padding: 3px 8px;
                    font-weight: bold;
                    display: inline-block;
                    margin-bottom: 5px;
                }
                
                .notes {
                    margin-top: 8px;
                    padding: 5px;
                    background: #fffde7;
                    border: 1px solid #fdd835;
                    font-size: 10px;
                }
                
                @media print {
                    body { -webkit-print-color-adjust: exact; }
                    .no-print { display: none; }
                }
                
                .print-controls {
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    padding: 15px;
                    background: #fff;
                    border: 1px solid #ddd;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    z-index: 1000;
                    border-radius: 8px;
                }
                
                .print-controls button {
                    padding: 10px 20px;
                    font-size: 14px;
                    cursor: pointer;
                    background: #2271b1;
                    color: #fff;
                    border: none;
                    border-radius: 4px;
                    margin-right: 10px;
                }
                
                .print-controls button:hover {
                    background: #135e96;
                }
            </style>
        </head>
        <body>
            <div class="print-controls no-print">
                <button onclick="window.print()">🖨️ Imprimir Etiqueta</button>
                <button onclick="window.close()">✕ Fechar</button>
            </div>
            
            <?php for ($i = 0; $i < $copies; $i++): ?>
            <div class="label-container">
                <div class="label-header">
                    <div class="company-info">
                        <div class="company-logo"><?php echo esc_html($sender['company'] ?? 'Loja'); ?></div>
                        <div style="font-size: 9px; color: #666; margin-top: 3px;">
                            <?php if (!empty($sender['cnpj'])): ?>
                                CNPJ: <?php echo esc_html($sender['cnpj']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tracking-box">
                        <div class="service-badge"><?php echo esc_html(strtoupper($label_data['service'] ?? 'ENTREGA')); ?></div>
                        <div class="tracking-code"><?php echo esc_html($label_data['tracking_code']); ?></div>
                        <div class="barcode">
                            <?php echo wp_kses_post($this->generate_barcode_svg($label_data['tracking_code'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="addresses">
                    <div class="address-box sender">
                        <div class="address-title">📤 Remetente</div>
                        <div class="address-name"><?php echo esc_html($sender['name']); ?></div>
                        <div class="address-line"><?php echo esc_html($sender['address']); ?></div>
                        <div class="address-line"><?php echo esc_html($sender['city']); ?> - <?php echo esc_html($sender['state']); ?></div>
                        <div class="postcode">CEP: <?php echo esc_html($this->format_cep($sender['postcode'])); ?></div>
                        <?php if (!empty($sender['phone'])): ?>
                            <div class="address-line" style="margin-top: 5px;">📞 <?php echo esc_html($sender['phone']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="address-box recipient">
                        <div class="address-title">📥 Destinatário</div>
                        <div class="address-name"><?php echo esc_html($recipient['name']); ?></div>
                        <div class="address-line"><?php echo esc_html($recipient['address']); ?></div>
                        <?php if (!empty($recipient['complement'])): ?>
                            <div class="address-line"><?php echo esc_html($recipient['complement']); ?></div>
                        <?php endif; ?>
                        <div class="address-line"><?php echo esc_html($recipient['district']); ?></div>
                        <div class="address-line"><?php echo esc_html($recipient['city']); ?> - <?php echo esc_html($recipient['state']); ?></div>
                        <div class="postcode">CEP: <?php echo esc_html($this->format_cep($recipient['postcode'])); ?></div>
                        <?php if (!empty($recipient['phone'])): ?>
                            <div class="address-line" style="margin-top: 5px;">📞 <?php echo esc_html($recipient['phone']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($label_data['notes'])): ?>
                    <div class="notes">
                        <strong>📝 Observações:</strong> <?php echo esc_html($label_data['notes']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="label-footer">
                    <div class="package-info">
                        <div class="package-item">
                            <span>Peso</span>
                            <strong><?php echo number_format($package['weight'] ?? 0, 2, ',', '.'); ?> kg</strong>
                        </div>
                        <div class="package-item">
                            <span>Dimensões</span>
                            <strong><?php echo intval($package['length']); ?>x<?php echo intval($package['width']); ?>x<?php echo intval($package['height']); ?> cm</strong>
                        </div>
                        <div class="package-item">
                            <span>Volumes</span>
                            <strong><?php echo esc_html($package['quantity'] ?? 1); ?></strong>
                        </div>
                        <div class="package-item">
                            <span>Frete</span>
                            <strong>R$ <?php echo number_format(floatval($label_data['cost'] ?? 0), 2, ',', '.'); ?></strong>
                        </div>
                    </div>
                    <div class="order-info">
                        <div><strong>Pedido #<?php echo esc_html($label_data['order_number'] ?? $label_data['order_id']); ?></strong></div>
                        <div><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($label_data['date']))); ?></div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
            
            <script>
                // Auto-print se não houver interação em 2s
                // setTimeout(function() { window.print(); }, 2000);
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Formatar CEP
     */
    private function format_cep($cep) {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) === 8) {
            return substr($cep, 0, 5) . '-' . substr($cep, 5);
        }
        return $cep;
    }
    
    /**
     * Gerar código de barras simples em SVG (Code 128)
     */
    private function generate_barcode_svg($code) {
        // Simplificação: usar apenas caracteres do código em representação visual
        $svg = '<svg viewBox="0 0 200 40" xmlns="http://www.w3.org/2000/svg">';
        
        $x = 0;
        $bar_width = 2;
        
        // Padrão simples para demonstração
        foreach (str_split($code) as $char) {
            $ascii = ord($char);
            
            // Barras baseadas no ASCII do caractere
            for ($i = 0; $i < 4; $i++) {
                $is_bar = ($ascii >> $i) & 1;
                if ($is_bar) {
                    $svg .= '<rect x="' . $x . '" y="0" width="' . $bar_width . '" height="40" fill="#000"/>';
                }
                $x += $bar_width + 1;
            }
        }
        
        $svg .= '</svg>';
        
        return $svg;
    }
    
    /**
     * Gerar etiqueta PDF (requer biblioteca externa)
     * Por enquanto retorna HTML para impressão
     */
    public function generate_pdf($label_data) {
        // TODO: Implementar com TCPDF ou DOMPDF se necessário
        // Por enquanto, usamos HTML que funciona perfeitamente para impressão
        return $this->render_label_html($label_data);
    }
}

// Inicializar
HNG_Custom_Shipping_Label::instance();
