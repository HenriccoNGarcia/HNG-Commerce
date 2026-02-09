<?php
/**
 * HNG Admin Meta Boxes Manager
 *
 * Gerencia registro e callbacks de meta boxes para produtos, cupons e pedidos.
 *
 * @package HNG_Commerce
 * @subpackage Admin/MetaBoxes
 * @since 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load product type fields manager
if (file_exists(__DIR__ . '/class-product-type-fields.php')) {
    require_once __DIR__ . '/class-product-type-fields.php';
}

class HNG_Admin_Meta_Boxes {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('add_meta_boxes_hng_product', [$this, 'register_product_meta_boxes']);
        add_action('add_meta_boxes_hng_coupon', [$this, 'register_coupon_meta_boxes']);
        
        // Save hooks
        add_action('save_post_hng_product', [$this, 'save_product_meta'], 10, 2);
        add_action('save_post_hng_coupon', [$this, 'save_coupon_meta'], 10, 2);
    }
    
    /**
     * Obter apenas os tipos de produto habilitados nas configurações
     */
    private function get_enabled_product_types() {
        // Carregar classe de settings se necessário
        $settings_file = dirname(__DIR__) . '/settings/class-admin-settings.php';
        if (!class_exists('HNG_Admin_Settings') && file_exists($settings_file)) {
            require_once $settings_file;
        }
        
        if (class_exists('HNG_Admin_Settings')) {
            return HNG_Admin_Settings::get_enabled_product_types();
        }
        
        // Fallback: retornar todos os tipos
        return HNG_Product_Type_Fields::get_product_types();
    }
    
    /**
     * Registrar meta boxes gerais
     */
    public function register_meta_boxes() {
        // Meta box para pedidos (se houver post type hng_order)
        $post_types = get_post_types();
        if (in_array('hng_order', $post_types, true)) {
            add_meta_box(
                'hng_order_details',
                __('Detalhes do Pedido', 'hng-commerce'),
                [$this, 'render_order_details_meta_box'],
                'hng_order',
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Registrar meta boxes de produtos
     */
    public function register_product_meta_boxes() {
        add_meta_box(
            'hng_product_data',
            __('Dados do Produto', 'hng-commerce'),
            [$this, 'render_product_data_meta_box'],
            'hng_product',
            'normal',
            'high'
        );
        
        add_meta_box(
            'hng_product_oneoff',
            __('Link de Pagamento Avulso', 'hng-commerce'),
            [$this, 'render_product_oneoff_meta_box'],
            'hng_product',
            'side',
            'default'
        );
    }
    
    /**
     * Registrar meta boxes de cupons
     */
    public function register_coupon_meta_boxes() {
        add_meta_box(
            'hng_coupon_data',
            __('Dados do Cupom', 'hng-commerce'),
            [$this, 'render_coupon_data_meta_box'],
            'hng_coupon',
            'normal',
            'high'
        );
    }
    
    /**
     * Renderizar meta box de dados do produto
     */
    public function render_product_data_meta_box($post) {
        wp_nonce_field('hng_product_meta', 'hng_product_meta_nonce');
        
        $product_type   = get_post_meta($post->ID, '_product_type', true) ?: 'simple';
        
        // Obter apenas tipos habilitados nas configurações
        $product_types = $this->get_enabled_product_types();

        // Monta um mapa de seções -> campos contendo todos os campos de todos os tipos,
        // garantindo que a troca de tipo no JS tenha todos os wrappers renderizados.
        $field_definitions   = HNG_Product_Type_Fields::get_field_definitions();
        $all_sections_fields = [];

        foreach ($product_types as $type_info) {
            if (empty($type_info['fields'])) {
                continue;
            }

            foreach ($type_info['fields'] as $section => $fields) {
                foreach ($fields as $field_key) {
                    if (!isset($field_definitions[$field_key])) {
                        continue;
                    }

                    // Evita duplicados mantendo a primeira definição encontrada.
                    if (!isset($all_sections_fields[$section][$field_key])) {
                        $all_sections_fields[$section][$field_key] = $field_definitions[$field_key];
                    }
                }
            }
        }
        
        ?>
        <div class="hng-product-data hng-product-type-editor">
            <!-- Type Selector with Visual Cards -->
            <div class="hng-type-selector-container">
                <h3><?php esc_html_e('Selecione o Tipo de Produto', 'hng-commerce'); ?></h3>
                <div class="hng-type-cards">
                    <?php foreach ($product_types as $type_key => $type_info): ?>
                        <label class="hng-type-card <?php echo esc_attr( $product_type === $type_key ? 'active' : '' ); ?>" data-type="<?php echo esc_attr($type_key); ?>">
                            <input type="radio" 
                                   name="_product_type" 
                                   value="<?php echo esc_attr($type_key); ?>" 
                                   <?php checked($product_type, $type_key); ?>
                                   class="hng-type-radio">
                            <span class="hng-type-icon"><?php echo esc_html($type_info['icon']); ?></span>
                            <span class="hng-type-label"><?php echo esc_html($type_info['label']); ?></span>
                            <span class="hng-type-description"><?php echo esc_html($type_info['description']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Dynamic Fields Based on Type -->
            <div class="hng-type-fields-container">
                <?php
                if (!empty($all_sections_fields)):
                    $section_labels = [
                        'basic' => __('Informações Básicas', 'hng-commerce'),
                        'shipping' => __('Frete e Dimensões', 'hng-commerce'),
                        'inventory' => __('Estoque', 'hng-commerce'),
                        'attributes' => __('Atributos do Produto', 'hng-commerce'),
                        'variations' => __('Variações', 'hng-commerce'),
                        'digital' => __('Arquivo para Download', 'hng-commerce'),
                        'subscription' => __('Configurações de Assinatura', 'hng-commerce'),
                        'quote' => __('Configurações de Orçamento', 'hng-commerce'),
                        'appointment' => __('Configurações de Agendamento', 'hng-commerce'),
                    ];

                    // Ordem: labels conhecidas primeiro, depois quaisquer seções extras
                    $ordered_sections = array_merge(
                        array_keys($section_labels),
                        array_diff(array_keys($all_sections_fields), array_keys($section_labels))
                    );

                    foreach ($ordered_sections as $section):
                        if (empty($all_sections_fields[$section])) {
                            continue;
                        }

                        $section_label = $section_labels[$section] ?? ucfirst($section);
                        ?>
                        <div class="hng-fields-section" data-section="<?php echo esc_attr($section); ?>">
                            <h4 class="hng-section-title"><?php echo esc_html($section_label); ?></h4>
                            <div class="hng-fields-group">
                                <?php
                                foreach ($all_sections_fields[$section] as $field_key => $field_def):
                                    HNG_Product_Type_Fields::render_field($field_key, $field_def, $post->ID, $product_type);
                                endforeach;
                                ?>
                            </div>
                        </div>
                        <?php
                    endforeach;
                else:
                    ?>
                    <p class="hng-notice-info">
                        <?php esc_html_e('Nenhum campo disponível para este tipo de produto.', 'hng-commerce'); ?>
                    </p>
                    <?php
                endif;
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar meta box de link avulso
     */
    public function render_product_oneoff_meta_box($post) {
        $price = get_post_meta($post->ID, '_price', true);
        
        ?>
        <div class="hng-oneoff-link-box">
            <p><?php esc_html_e('Gere um link de pagamento direto para este produto:', 'hng-commerce'); ?></p>
            
            <div class="hng-oneoff-controls">
                <input type="number" 
                       id="hng-oneoff-price" 
                       class="hng-oneoff-price-input" 
                       value="<?php echo esc_attr($price); ?>" 
                       step="0.01" 
                       min="0"
                       placeholder="<?php esc_attr_e('Valor (R$)', 'hng-commerce'); ?>">
                
                <button type="button" 
                        class="button button-secondary hng-generate-oneoff-link" 
                        data-product-id="<?php echo esc_attr($post->ID); ?>">
                    <?php esc_html_e('Gerar Link', 'hng-commerce'); ?>
                </button>
            </div>
            
            <div class="hng-oneoff-result" style="display: none; margin-top: 10px;">
                <input type="text" class="hng-oneoff-link-input" readonly style="width: 100%; margin-bottom: 5px;">
                <button type="button" class="button hng-copy-oneoff-link"><?php esc_html_e('Copiar', 'hng-commerce'); ?></button>
                <a href="#" class="button hng-open-oneoff-link" target="_blank"><?php esc_html_e('Abrir', 'hng-commerce'); ?></a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar meta box de dados do cupom
     */
    public function render_coupon_data_meta_box($post) {
        wp_nonce_field('hng_coupon_meta', 'hng_coupon_meta_nonce');
        
        $discount_type = get_post_meta($post->ID, '_discount_type', true) ?: 'percent';
        $discount_value = get_post_meta($post->ID, '_discount_value', true);
        $usage_limit = get_post_meta($post->ID, '_usage_limit', true);
        $expiry_date = get_post_meta($post->ID, '_expiry_date', true);
        $min_purchase = get_post_meta($post->ID, '_minimum_purchase', true);
        
        ?>
        <div class="hng-coupon-data">
            <p>
                <label for="discount_type"><?php esc_html_e('Tipo de Desconto:', 'hng-commerce'); ?></label>
                <select name="_discount_type" id="discount_type">
                    <option value="percent" <?php selected($discount_type, 'percent'); ?>><?php esc_html_e('Porcentagem', 'hng-commerce'); ?></option>
                    <option value="fixed" <?php selected($discount_type, 'fixed'); ?>><?php esc_html_e('Valor Fixo', 'hng-commerce'); ?></option>
                </select>
            </p>
            
            <p>
                <label for="discount_value"><?php esc_html_e('Valor do Desconto:', 'hng-commerce'); ?></label>
                <input type="number" name="_discount_value" id="discount_value" value="<?php echo esc_attr($discount_value); ?>" step="0.01" min="0">
                <span class="description"><?php esc_html_e('Porcentagem (%) ou Valor (R$)', 'hng-commerce'); ?></span>
            </p>
            
            <p>
                <label for="usage_limit"><?php esc_html_e('Limite de Uso:', 'hng-commerce'); ?></label>
                <input type="number" name="_usage_limit" id="usage_limit" value="<?php echo esc_attr($usage_limit); ?>" min="0">
                <span class="description"><?php esc_html_e('Deixe em branco para ilimitado', 'hng-commerce'); ?></span>
            </p>
            
            <p>
                <label for="expiry_date"><?php esc_html_e('Data de Expiração:', 'hng-commerce'); ?></label>
                <input type="date" name="_expiry_date" id="expiry_date" value="<?php echo esc_attr($expiry_date); ?>">
            </p>
            
            <p>
                <label for="minimum_purchase"><?php esc_html_e('Compra Mínima (R$):', 'hng-commerce'); ?></label>
                <input type="number" name="_minimum_purchase" id="minimum_purchase" value="<?php echo esc_attr($min_purchase); ?>" step="0.01" min="0">
            </p>
        </div>
        <?php
    }
    
    /**
     * Renderizar meta box de detalhes do pedido
     */
    public function render_order_details_meta_box($post) {
        echo '<p>' . esc_html__('Meta box de pedidos (a ser implementada)', 'hng-commerce') . '</p>';
    }
    
    /**
     * Salvar meta do produto
     */
    public function save_product_meta($post_id, $post) {
        // Verificar nonce
        $product_meta_nonce = isset($_POST['hng_product_meta_nonce']) ? sanitize_text_field(wp_unslash($_POST['hng_product_meta_nonce'])) : '';
        if (!$product_meta_nonce || !wp_verify_nonce($product_meta_nonce, 'hng_product_meta')) {
            return;
        }
        
        // Verificar se não é autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permissões
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Get product type
        $product_type = isset($_POST['_product_type']) ? sanitize_text_field(wp_unslash($_POST['_product_type'])) : 'simple';
        update_post_meta($post_id, '_product_type', $product_type);
        
        // Get all possible fields and save those that are present
        $field_definitions = HNG_Product_Type_Fields::get_field_definitions();
        
        foreach ($field_definitions as $field_key => $field_def) {
            $field_name = '_' . $field_key;
            
            if (isset($_POST[$field_name])) {
                $value = wp_unslash($_POST[$field_name]);
                
                // Sanitize based on field type
                switch ($field_def['type']) {
                    case 'textarea':
                        $value = sanitize_textarea_field($value);
                        break;
                    case 'url':
                        $value = esc_url_raw($value);
                        break;
                    case 'number':
                    case 'select':
                        $value = sanitize_text_field($value);
                        break;
                    case 'attributes':
                        // Handle product attributes - array of {name, values}
                        if (is_array($value)) {
                            $sanitized = [];
                            foreach ($value as $attr) {
                                if (is_array($attr) && !empty($attr['name'])) {
                                    $sanitized[] = [
                                        'name' => sanitize_text_field($attr['name']),
                                        'values' => isset($attr['values']) ? sanitize_textarea_field($attr['values']) : ''
                                    ];
                                }
                            }
                            $value = $sanitized;
                        } else {
                            $value = [];
                        }
                        break;
                    case 'variations':
                        // Handle product variations - array of variation data
                        if (is_array($value)) {
                            $sanitized = [];
                            foreach ($value as $idx => $variation) {
                                if (is_array($variation) && (!empty($variation['title']) || !empty($variation['price']))) {
                                    $sanitized_var = [];
                                    $sanitized_var['title'] = sanitize_text_field($variation['title'] ?? '');
                                    $sanitized_var['price'] = floatval($variation['price'] ?? 0);
                                    $sanitized_var['attribute'] = sanitize_text_field($variation['attribute'] ?? '');
                                    
                                    // Save images for this variation
                                    if (isset($variation['images']) && is_array($variation['images'])) {
                                        $sanitized_var['images'] = array_map('absint', $variation['images']);
                                    } else {
                                        $sanitized_var['images'] = [];
                                    }
                                    
                                    $sanitized[] = $sanitized_var;
                                }
                            }
                            $value = $sanitized;
                        } else {
                            $value = [];
                        }
                        break;
                    case 'quote_fields':
                    case 'professionals':
                        // Handle array fields specially
                        if (is_array($value)) {
                            $sanitized = [];
                            foreach ($value as $idx => $item) {
                                if (is_array($item)) {
                                    $sanitized_item = [];
                                    foreach ($item as $key => $val) {
                                        $sanitized_item[sanitize_key($key)] = is_array($val) 
                                            ? array_map('sanitize_text_field', $val) 
                                            : sanitize_text_field($val);
                                    }
                                    // Only add if has a label or meaningful data
                                    if (!empty($sanitized_item['label']) || !empty($sanitized_item['name'])) {
                                        $sanitized[] = $sanitized_item;
                                    }
                                }
                            }
                            $value = $sanitized;
                        }
                        break;
                    default:
                        $value = sanitize_text_field($value);
                }
                
                // Handle array values
                if (is_array($value)) {
                    if (!empty($value)) {
                        update_post_meta($post_id, $field_name, $value);
                    } else {
                        delete_post_meta($post_id, $field_name);
                    }
                } elseif (!empty($value)) {
                    update_post_meta($post_id, $field_name, $value);
                } else {
                    delete_post_meta($post_id, $field_name);
                }
            }
        }
    }
    
    /**
     * Salvar meta do cupom
     */
    public function save_coupon_meta($post_id, $post) {
        // Verificar nonce
        $coupon_meta_nonce = isset($_POST['hng_coupon_meta_nonce']) ? sanitize_text_field(wp_unslash($_POST['hng_coupon_meta_nonce'])) : '';
        if (!$coupon_meta_nonce || !wp_verify_nonce($coupon_meta_nonce, 'hng_coupon_meta')) {
            return;
        }
        
        // Verificar se não é autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permissões
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Campos para salvar
        $fields = ['_discount_type', '_discount_value', '_usage_limit', '_expiry_date', '_minimum_purchase'];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field(wp_unslash($_POST[$field]));
                update_post_meta($post_id, $field, $value);
            }
        }
    }
}
