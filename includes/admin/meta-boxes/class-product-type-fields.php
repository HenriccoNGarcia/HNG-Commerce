<?php
/**
 * Product Type Fields Manager
 * 
 * Gerencia campos específicos para cada tipo de produto com UI/UX melhorada
 *
 * @package HNG_Commerce
 * @subpackage Admin/MetaBoxes
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Product_Type_Fields {
    
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

        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

    }

    

    /**

     * Enqueue scripts and styles

     */

    public function enqueue_scripts($hook) {

        global $post_type;

        

        // Verifica se estamos editando um produto HNG

        $is_product_edit = (

            ($hook === 'post.php' || $hook === 'post-new.php') && 

            ($post_type === 'hng_product')

        );

        

        if (!$is_product_edit) {

            return;

        }

        

        // Enqueue CSS

        wp_enqueue_style(

            'hng-product-type-editor',

            HNG_COMMERCE_URL . 'assets/css/product-type-editor.css',

            [],

            HNG_COMMERCE_VERSION

        );

        

        // Enqueue JavaScript

        wp_enqueue_script(

            'hng-product-fields',

            HNG_COMMERCE_URL . 'assets/js/product-type-fields.js',

            ['jquery'],

            HNG_COMMERCE_VERSION,

            true

        );

        

        wp_localize_script('hng-product-fields', 'hngProductFieldsData', [

            'types' => self::get_product_types(),

        ]);

    }

    

    /**

     * Get product types configuration

     */

    public static function get_product_types() {

        return [

            'simple' => [

                'label' => __('Simples', 'hng-commerce'),

                'icon' => '📦',

                'description' => __('Produto padrão', 'hng-commerce'),

                'fields' => [

                    'basic' => [

                        'sku', 'price', 'sale_price', 'cost'

                    ],

                    'shipping' => [

                        'weight', 'length', 'width', 'height'

                    ],

                    'inventory' => [

                        'stock', 'manage_stock', 'low_stock_threshold'

                    ]

                ]

            ],

            'variable' => [

                'label' => __('Variável', 'hng-commerce'),

                'icon' => '🔀',

                'description' => __('Produto com variações', 'hng-commerce'),

                'fields' => [

                    'attributes' => [

                        'product_attributes'

                    ],

                    'variations' => [

                        'product_variations'

                    ],

                    'shipping' => [

                        'weight', 'length', 'width', 'height'

                    ]

                ]

            ],

            'digital' => [

                'label' => __('Digital', 'hng-commerce'),

                'icon' => '💾',

                'description' => __('Produto digital/download', 'hng-commerce'),

                'fields' => [

                    'basic' => [

                        'price', 'sale_price', 'cost'

                    ],

                    'digital' => [

                        'download_file', 'download_url', 'download_limit', 'download_expiry'

                    ]

                ]

            ],

            'subscription' => [

                'label' => __('Assinatura', 'hng-commerce'),

                'icon' => '🔄',

                'description' => __('Produto com pagamento recorrente', 'hng-commerce'),

                'fields' => [

                    'subscription' => [

                        'subscription_price', 'subscription_recurrence'

                    ],

                    'shipping' => [

                        'subscription_requires_shipping', 'weight', 'length', 'width', 'height'

                    ]

                ]

            ],

            'quote' => [

                'label' => __('Orçamento', 'hng-commerce'),

                'icon' => '📋',

                'description' => __('Produto que requer orçamento', 'hng-commerce'),

                'fields' => [

                    'quote' => [

                        'quote_requires_shipping', 'quote_custom_fields'

                    ]

                ]

            ],

            'appointment' => [

                'label' => __('Agendamento', 'hng-commerce'),

                'icon' => '📅',

                'description' => __('Serviço com horário agendado', 'hng-commerce'),

                'fields' => [

                    'basic' => [

                        'price'

                    ],

                    'appointment' => [

                        'booking_start_date', 'service_duration', 'appointment_professionals'

                    ]

                ]

            ]

        ];

    }

    

    /**

     * Get field definitions for all types

     */

    public static function get_field_definitions() {

        return [

            // Basic Fields

            'sku' => [

                'label' => __('SKU:', 'hng-commerce'),

                'type' => 'text',

                'placeholder' => __('Ex: PROD-001', 'hng-commerce'),

                'help' => __('Identificador único do produto', 'hng-commerce'),

            ],

            'price' => [

                'label' => __('Preço (R$):', 'hng-commerce'),

                'type' => 'number',

                'step' => '0.01',

                'min' => '0',

                'placeholder' => '0,00',

                'help' => __('Preço do produto', 'hng-commerce'),

            ],

            'sale_price' => [

                'label' => __('Preço Promocional (R$):', 'hng-commerce'),

                'type' => 'number',

                'step' => '0.01',

                'min' => '0',

                'placeholder' => '0,00',

                'help' => __('Deixe em branco se não houver promoção', 'hng-commerce'),

            ],

            'cost' => [

                'label' => __('Custo (R$):', 'hng-commerce'),

                'type' => 'number',

                'step' => '0.01',

                'min' => '0',

                'placeholder' => '0,00',

                'help' => __('Custo para cálculo de lucro', 'hng-commerce'),

            ],

            

            // Shipping Fields

            'weight' => [

                'label' => __('Peso (kg):', 'hng-commerce'),

                'type' => 'number',

                'step' => '0.001',

                'min' => '0',

                'placeholder' => '0,000',

                'help' => __('Peso para cálculo de frete', 'hng-commerce'),

            ],

            'length' => [

                'label' => __('Comprimento (cm):', 'hng-commerce'),

                'type' => 'number',

                'step' => '0.01',

                'min' => '0',

                'placeholder' => '0,00',

            ],

            'width' => [

                'label' => __('Largura (cm):', 'hng-commerce'),

                'type' => 'number',

                'step' => '0.01',

                'min' => '0',

                'placeholder' => '0,00',

            ],

            'height' => [

                'label' => __('Altura (cm):', 'hng-commerce'),

                'type' => 'number',

                'step' => '0.01',

                'min' => '0',

                'placeholder' => '0,00',

            ],

            

            // Inventory

            'stock' => [

                'label' => __('Estoque:', 'hng-commerce'),

                'type' => 'number',

                'min' => '0',

                'step' => '1',

                'placeholder' => '0',

                'help' => __('Quantidade disponível', 'hng-commerce'),

            ],

            'manage_stock' => [

                'label' => __('Gerenciar Estoque', 'hng-commerce'),

                'type' => 'checkbox',

                'help' => __('Habilitar controle automático', 'hng-commerce'),

            ],

            'low_stock_threshold' => [

                'label' => __('Alerta de Estoque Baixo:', 'hng-commerce'),

                'type' => 'number',

                'min' => '0',

                'step' => '1',

                'placeholder' => '5',

                'help' => __('Quantidade mínima para alerta', 'hng-commerce'),

            ],

            

            // Variable Product - Attributes and Variations

            'product_attributes' => [

                'label' => __('Atributos do Produto', 'hng-commerce'),

                'type' => 'attributes',

                'help' => __('Gerenciar atributos e seus valores', 'hng-commerce'),

            ],

            'product_variations' => [

                'label' => __('Variações do Produto', 'hng-commerce'),

                'type' => 'variations',

                'help' => __('Gerenciar variações por atributo', 'hng-commerce'),

            ],

            

            // Digital Fields

            'download_url' => [

                'label' => __('URL de Download:', 'hng-commerce'),

                'type' => 'url',

                'placeholder' => __('https://exemplo.com/arquivo.zip', 'hng-commerce'),

                'help' => __('Link direto para download', 'hng-commerce'),

            ],

            'download_file' => [

                'label' => __('Arquivo para Download:', 'hng-commerce'),

                'type' => 'file',

                'help' => __('Upload do arquivo para download', 'hng-commerce'),

            ],

            'download_limit' => [

                'label' => __('Limite de Downloads:', 'hng-commerce'),

                'type' => 'number',

                'min' => '0',

                'placeholder' => '0',

                'help' => __('0 = ilimitado', 'hng-commerce'),

            ],

            'download_expiry' => [

                'label' => __('Expiração (dias):', 'hng-commerce'),

                'type' => 'number',

                'min' => '0',

                'placeholder' => '30',

                'help' => __('Dias até expirar o acesso', 'hng-commerce'),

            ],

            

            // Subscription Fields

            'subscription_price' => [

                'label' => __('Preço da Assinatura (R$):', 'hng-commerce'),

                'type' => 'number',

                'step' => '0.01',

                'min' => '0',

                'placeholder' => '0,00',

                'help' => __('Valor cobrado a cada renovação', 'hng-commerce'),

            ],

            'subscription_recurrence' => [

                'label' => __('Recorrência:', 'hng-commerce'),

                'type' => 'select',

                'options' => [

                    'weekly' => __('Semanal', 'hng-commerce'),

                    'monthly' => __('Mensal', 'hng-commerce'),

                    'quarterly' => __('Trimestral', 'hng-commerce'),

                    'semi-annual' => __('Semestral', 'hng-commerce'),

                    'annual' => __('Anual', 'hng-commerce'),

                    'biennial' => __('A cada 2 anos', 'hng-commerce'),

                    'triennial' => __('A cada 3 anos', 'hng-commerce'),

                    'quinquennial' => __('A cada 5 anos', 'hng-commerce'),

                ],

                'help' => __('Frequência de cobrança', 'hng-commerce'),

            ],

            'subscription_requires_shipping' => [

                'label' => __('Requer envio do produto', 'hng-commerce'),

                'type' => 'checkbox',

                'help' => __('Ative para produtos de assinatura que precisam de entrega física e preencha os dados de frete.', 'hng-commerce'),

            ],

            

            // Quote Fields

            'quote_requires_shipping' => [

                'label' => __('Requer Frete', 'hng-commerce'),

                'type' => 'checkbox',

                'help' => __('Marque se o produto requer cálculo de frete', 'hng-commerce'),

            ],

            'quote_custom_fields' => [

                'label' => __('Campos do Formulário', 'hng-commerce'),

                'type' => 'quote_fields',

                'help' => __('Campos que serão solicitados no orçamento', 'hng-commerce'),

            ],

            

            // Appointment Fields

            'booking_start_date' => [

                'label' => __('Data de Início do Agendamento:', 'hng-commerce'),

                'type' => 'date',

                'help' => __('A partir de qual data este serviço pode ser agendado', 'hng-commerce'),

            ],

            'service_duration' => [

                'label' => __('Duração do Serviço (minutos):', 'hng-commerce'),

                'type' => 'number',

                'min' => '15',

                'step' => '15',

                'placeholder' => '60',

                'help' => __('Quanto tempo dura cada agendamento (em minutos)', 'hng-commerce'),

            ],

            'appointment_professionals' => [

                'label' => __('Profissionais Disponíveis', 'hng-commerce'),

                'type' => 'professionals',

                'help' => __('Profissionais que podem realizar este serviço. A capacidade de agendamentos simultâneos é baseada no número de profissionais.', 'hng-commerce'),

            ],

            

            // Legacy fields (kept for backward compatibility)

            'duration' => [

                'label' => __('Duração (minutos):', 'hng-commerce'),

                'type' => 'number',

                'min' => '1',

                'step' => '15',

                'placeholder' => '60',

                'help' => __('Quanto tempo dura cada agendamento', 'hng-commerce'),

            ],

            'buffer_time' => [

                'label' => __('Tempo de Buffer (minutos):', 'hng-commerce'),

                'type' => 'number',

                'min' => '0',

                'step' => '15',

                'placeholder' => '0',

                'help' => __('Tempo para preparação entre agendamentos', 'hng-commerce'),

            ],

            'max_capacity' => [

                'label' => __('Capacidade Máxima:', 'hng-commerce'),

                'type' => 'number',

                'min' => '1',

                'step' => '1',

                'placeholder' => '1',

                'help' => __('Quantas pessoas podem agendar', 'hng-commerce'),

            ],

        ];

    }

    

    /**

     * Render field based on definition

     */

    public static function render_field($field_key, $field_def, $post_id, $product_type) {

        $meta_key = '_' . $field_key;

        $value = get_post_meta($post_id, $meta_key, true);

        $field_id = 'hng_field_' . $field_key;

        $field_name = $meta_key;

        

        ?>

        <div class="hng-field-wrapper" data-field="<?php echo esc_attr($field_key); ?>">

            <?php if (!empty($field_def['label']) && $field_def['type'] !== 'notice' && $field_def['type'] !== 'checkbox'): ?>

                <label for="<?php echo esc_attr($field_id); ?>" class="hng-field-label">

                    <?php echo esc_html($field_def['label']); ?>

                </label>

            <?php endif; ?>

            

            <div class="hng-field-input">

                <?php

                switch ($field_def['type']) {

                    case 'attributes':

                        // Product attributes manager

                        self::render_attributes_field($field_key, $post_id);

                        break;

                    

                    case 'variations':

                        // Product variations manager

                        self::render_variations_field($field_key, $post_id);

                        break;

                    

                    case 'quote_fields':

                        // Quote custom fields builder

                        self::render_quote_fields($field_key, $post_id);

                        break;

                    

                    case 'professionals':

                        // Appointment professionals manager

                        self::render_professionals_field($field_key, $post_id);

                        break;

                    

                    case 'notice':

                        // Info box

                        printf(

                            '<div class="hng-notice hng-notice-info">ℹ️ %s</div>',

                            esc_html($field_def['text'])

                        );

                        break;

                    

                    case 'checkbox':

                        printf(

                            '<label class="hng-checkbox-label"><input type="checkbox" id="%s" name="%s" value="1" %s class="hng-checkbox"> %s</label>',

                            esc_attr($field_id),

                            esc_attr($field_name),

                            checked($value, '1', false),

                            esc_html($field_def['label'])

                        );

                        break;

                    

                    case 'file':

                        // File upload with media library

                        printf(

                            '<div class="hng-file-upload">

                                <input type="hidden" id="%s" name="%s" value="%s" class="hng-file-input">

                                <button type="button" class="button hng-upload-button" data-target="%s">

                                    📁 %s

                                </button>

                                <span class="hng-file-name">%s</span>

                                <button type="button" class="button hng-remove-file" style="display:%s;">❌ Remover</button>

                            </div>',

                            esc_attr($field_id),

                            esc_attr($field_name),

                            esc_attr($value),

                            esc_attr($field_id),

                            esc_html__('Selecionar Arquivo', 'hng-commerce'),

                            $value ? esc_html(basename((string) (get_attached_file($value) ?: ''))) : esc_html__('Nenhum arquivo selecionado', 'hng-commerce'),

                            $value ? 'inline-block' : 'none'

                        );

                        break;

                    

                    case 'text':

                    case 'url':

                    case 'date':

                    case 'number':
                        $extra_attrs = implode(' ', array_filter([
                            !empty($field_def['step']) ? 'step="' . esc_attr($field_def['step']) . '"' : '',
                            !empty($field_def['min']) ? 'min="' . esc_attr($field_def['min']) . '"' : '',
                            !empty($field_def['max']) ? 'max="' . esc_attr($field_def['max']) . '"' : '',
                            !empty($field_def['placeholder']) ? 'placeholder="' . esc_attr($field_def['placeholder']) . '"' : '',
                        ]));
                        $extra_attrs = wp_kses_post($extra_attrs);
                        
                        printf(
                            '<input type="%s" id="%s" name="%s" value="%s" class="hng-input" %s>',
                            esc_attr($field_def['type']),
                            esc_attr($field_id),
                            esc_attr($field_name),
                            esc_attr($value),
                            wp_kses_post($extra_attrs)
                        );
                        break;

                    

                    case 'textarea':

                        printf(

                            '<textarea id="%s" name="%s" class="hng-textarea" rows="5" %s>%s</textarea>',

                            esc_attr($field_id),

                            esc_attr($field_name),

                            !empty($field_def['placeholder']) ? 'placeholder="' . esc_attr($field_def['placeholder']) . '"' : '',

                            esc_textarea($value)

                        );

                        break;

                    

                    case 'select':

                        echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" class="hng-select">';

                        echo '<option value="">' . esc_html__('Selecione...', 'hng-commerce') . '</option>';

                        if (!empty($field_def['options'])) {

                            foreach ($field_def['options'] as $option_value => $option_label) {

                                printf(

                                    '<option value="%s" %s>%s</option>',

                                    esc_attr($option_value),

                                    selected($value, $option_value, false),

                                    esc_html($option_label)

                                );

                            }

                        }

                        echo '</select>';

                        break;

                }

                ?>

            </div>

            

            <?php if (!empty($field_def['help']) && $field_def['type'] !== 'notice'): ?>

                <p class="hng-field-help">

                    <small><?php echo esc_html($field_def['help']); ?></small>

                </p>

            <?php endif; ?>

        </div>

        <?php

    }

    

    /**

     * Render repeater item

     */

    private static function render_repeater_item($field_key, $field_def, $index, $item) {

        ?>

        <div class="hng-repeater-item" data-index="<?php echo esc_attr($index); ?>">

            <div class="hng-repeater-item-header">

                <span class="hng-repeater-handle">⋮⋮</span>

                <span class="hng-repeater-title"><?php echo esc_html__('Campo', 'hng-commerce') . ' #' . esc_html($index + 1); ?></span>

                <button type="button" class="button-link hng-remove-repeater-item" title="<?php echo esc_attr__('Remover', 'hng-commerce'); ?>">❌</button>

            </div>

            <div class="hng-repeater-item-content">

                <?php if (!empty($field_def['fields'])): ?>

                    <?php foreach ($field_def['fields'] as $sub_key => $sub_def): ?>

                        <div class="hng-repeater-field">

                            <label><?php echo esc_html($sub_def['label']); ?></label>

                            <?php

                            $sub_name = '_' . $field_key . '[' . $index . '][' . $sub_key . ']';

                            $sub_value = isset($item[$sub_key]) ? $item[$sub_key] : '';

                            

                            switch ($sub_def['type']) {

                                case 'text':

                                case 'number':

                                    printf(

                                        '<input type="%s" name="%s" value="%s" placeholder="%s" class="hng-input">',

                                        esc_attr($sub_def['type']),

                                        esc_attr($sub_name),

                                        esc_attr($sub_value),

                                        !empty($sub_def['placeholder']) ? esc_attr($sub_def['placeholder']) : ''

                                    );

                                    break;

                                

                                case 'select':

                                    printf('<select name="%s" class="hng-select">', esc_attr($sub_name));

                                    if (!empty($sub_def['options'])) {

                                        foreach ($sub_def['options'] as $opt_val => $opt_label) {

                                            printf(

                                                '<option value="%s" %s>%s</option>',

                                                esc_attr($opt_val),

                                                selected($sub_value, $opt_val, false),

                                                esc_html($opt_label)

                                            );

                                        }

                                    }

                                    echo '</select>';

                                    break;

                                

                                case 'checkbox':

                                    printf(

                                        '<label><input type="checkbox" name="%s" value="1" %s> %s</label>',

                                        esc_attr($sub_name),

                                        checked($sub_value, '1', false),

                                        esc_html($sub_def['label'])

                                    );

                                    break;

                                

                                case 'textarea':

                                    printf(

                                        '<textarea name="%s" rows="2" placeholder="%s" class="hng-textarea">%s</textarea>',

                                        esc_attr($sub_name),

                                        !empty($sub_def['placeholder']) ? esc_attr($sub_def['placeholder']) : '',

                                        esc_textarea($sub_value)

                                    );

                                    break;

                            }

                            ?>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

        <?php

    }

    

    /**

     * Render product attributes field

     */

    private static function render_attributes_field($field_key, $post_id) {

        $attributes = get_post_meta($post_id, '_' . $field_key, true) ?: [];

        ?>

        <div class="hng-attributes-manager">

            <div class="hng-attributes-list" id="attributes-list">

                <?php if (!empty($attributes) && is_array($attributes)): ?>

                    <?php foreach ($attributes as $index => $attr): ?>

                        <div class="hng-attribute-item">

                            <input type="text" 

                                   name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][name]" 

                                   placeholder="Nome do Atributo (ex: Cor, Tamanho)" 

                                   value="<?php echo esc_attr($attr['name'] ?? ''); ?>"

                                   class="hng-attribute-name">

                            <textarea placeholder="Valores separados por vírgula (ex: Vermelho, Azul, Verde)"

                                      name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][values]"

                                      class="hng-attribute-values"><?php echo esc_textarea($attr['values'] ?? ''); ?></textarea>

                            <button type="button" class="button hng-remove-attribute" onclick="this.parentElement.remove();">❌ Remover</button>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <button type="button" class="button hng-add-attribute" onclick="addAttribute();">➕ Adicionar Atributo</button>

            

            <script>

            function addAttribute() {

                const list = document.getElementById('attributes-list');

                const index = list.children.length;

                const html = `

                    <div class="hng-attribute-item">

                        <input type="text" 

                               name="_<?php echo esc_js($field_key); ?>[${index}][name]" 

                               placeholder="Nome do Atributo (ex: Cor, Tamanho)"

                               class="hng-attribute-name">

                        <textarea placeholder="Valores separados por vírgula (ex: Vermelho, Azul, Verde)"

                                  name="_<?php echo esc_js($field_key); ?>[${index}][values]"

                                  class="hng-attribute-values"></textarea>

                        <button type="button" class="button hng-remove-attribute" onclick="this.parentElement.remove();">❌ Remover</button>

                    </div>

                `;

                list.insertAdjacentHTML('beforeend', html);

            }

            </script>

        </div>

        <?php

    }

    

    /**

     * Render product variations field

     */

    private static function render_variations_field($field_key, $post_id) {

        $variations = get_post_meta($post_id, '_' . $field_key, true) ?: [];

        $attributes = get_post_meta($post_id, '_product_attributes', true) ?: [];

        ?>

        <div class="hng-variations-manager">

            <p class="description">Configure preço, imagens e outros dados para cada variação</p>

            <div class="hng-variations-list" id="variations-list">

                <?php if (!empty($variations) && is_array($variations)): ?>

                    <?php foreach ($variations as $index => $var): ?>

                        <div class="hng-variation-item">

                            <h4 class="hng-variation-title" onclick="toggleVariation(this);">

                                <span class="toggle-arrow">▼</span>

                                <?php echo esc_html($var['title'] ?? 'Variação ' . ($index + 1)); ?>

                            </h4>

                            <div class="hng-variation-content" style="display: block;">

                                <input type="text" 

                                       name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][title]" 

                                       placeholder="Título da variação (ex: Vermelho - P)"

                                       value="<?php echo esc_attr($var['title'] ?? ''); ?>"

                                       class="hng-variation-title-input">

                                

                                <label>Preço (R$):</label>

                                <input type="number" 

                                       step="0.01" 

                                       min="0"

                                       name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][price]"

                                       placeholder="0,00"

                                       value="<?php echo esc_attr($var['price'] ?? ''); ?>">

                                

                                <label>Imagens:</label>

                                <button type="button" class="button hng-upload-variation-image" onclick="uploadVariationImage(<?php echo esc_js($index); ?>);">

                                    🖼️ Adicionar Imagem

                                </button>

                                <div class="hng-variation-images" id="variation-images-<?php echo esc_attr($index); ?>">

                                    <?php if (!empty($var['images']) && is_array($var['images'])): ?>

                                        <?php foreach ($var['images'] as $img_id): ?>

                                            <div class="hng-image-preview">

                                                <?php echo wp_get_attachment_image($img_id, 'thumbnail'); ?>

                                                <button type="button" onclick="removeImage(this);">✕</button>

                                                <input type="hidden" name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][images][]" value="<?php echo esc_attr($img_id); ?>">

                                            </div>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </div>

                                

                                <label>Atributo:</label>

                                <select name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][attribute]" class="hng-variation-attribute">

                                    <option value="">Selecione um atributo</option>

                                    <?php foreach ($attributes as $attr): ?>

                                        <option value="<?php echo esc_attr($attr['name'] ?? ''); ?>" 

                                                <?php selected($var['attribute'] ?? '', $attr['name'] ?? ''); ?>>

                                            <?php echo esc_html($attr['name'] ?? ''); ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                

                                <button type="button" class="button hng-remove-variation" onclick="this.closest('.hng-variation-item').remove();">❌ Remover Variação</button>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <button type="button" class="button hng-add-variation" onclick="addVariation();">➕ Adicionar Variação</button>

            

            <script>

            // Função para obter atributos atuais do formulário

            function getAttributeOptions() {

                const attributesInputs = document.querySelectorAll('#attributes-list input[name*="[name]"]');

                const attributes = [];

                attributesInputs.forEach(input => {

                    const name = input.value.trim();

                    if (name) {

                        attributes.push(name);

                    }

                });

                return attributes;

            }

            

            // Função para atualizar os selects de atributos nas variações

            function updateVariationAttributes() {

                const attributes = getAttributeOptions();

                const selects = document.querySelectorAll('.hng-variation-attribute');

                

                selects.forEach(select => {

                    const currentValue = select.value;

                    select.innerHTML = '<option value="">Selecione um atributo</option>';

                    

                    attributes.forEach(attr => {

                        const option = document.createElement('option');

                        option.value = attr;

                        option.textContent = attr;

                        if (attr === currentValue) {

                            option.selected = true;

                        }

                        select.appendChild(option);

                    });

                });

            }

            

            function addVariation() {

                const list = document.getElementById('variations-list');

                const index = list.children.length;

                const attributes = getAttributeOptions();

                

                let attributeOptions = '<option value="">Selecione um atributo</option>';

                attributes.forEach(attr => {

                    attributeOptions += `<option value="${attr}">${attr}</option>`;

                });

                

                const html = `

                    <div class="hng-variation-item">

                        <h4 class="hng-variation-title" onclick="toggleVariation(this);">

                            <span class="toggle-arrow">▼</span>

                            Variação ${index + 1}

                        </h4>

                        <div class="hng-variation-content" style="display: block;">

                            <input type="text" 

                                   name="_<?php echo esc_js($field_key); ?>[${index}][title]" 

                                   placeholder="Título da variação (ex: Vermelho - P)"

                                   class="hng-variation-title-input">

                            

                            <label>Preço (R$):</label>

                            <input type="number" 

                                   step="0.01" 

                                   min="0"

                                   name="_<?php echo esc_js($field_key); ?>[${index}][price]"

                                   placeholder="0,00">

                            

                            <label>Imagens:</label>

                            <button type="button" class="button hng-upload-variation-image" onclick="uploadVariationImage(${index});">

                                🖼️ Adicionar Imagem

                            </button>

                            <div class="hng-variation-images" id="variation-images-${index}"></div>

                            

                            <label>Atributo:</label>

                            <select name="_<?php echo esc_js($field_key); ?>[${index}][attribute]" class="hng-variation-attribute">

                                ${attributeOptions}

                            </select>

                            

                            <button type="button" class="button hng-remove-variation" onclick="this.closest('.hng-variation-item').remove();">❌ Remover Variação</button>

                        </div>

                    </div>

                `;

                list.insertAdjacentHTML('beforeend', html);

            }

            

            function toggleVariation(header) {

                const content = header.nextElementSibling;

                content.style.display = content.style.display === 'none' ? 'block' : 'none';

                header.querySelector('.toggle-arrow').textContent = content.style.display === 'none' ? '▶' : '▼';

            }

            

            // Adicionar listener nos inputs de atributos para atualizar variações automaticamente

            document.addEventListener('DOMContentLoaded', function() {

                const attributesList = document.getElementById('attributes-list');

                if (attributesList) {

                    // Observer para detectar mudanças nos atributos

                    const observer = new MutationObserver(function(mutations) {

                        updateVariationAttributes();

                    });

                    

                    observer.observe(attributesList, {

                        childList: true,

                        subtree: true

                    });

                    

                    // Listener para mudanças nos inputs existentes

                    attributesList.addEventListener('input', function(e) {

                        if (e.target.matches('input[name*="[name]"]')) {

                            updateVariationAttributes();

                        }

                    });

                }

            });

            </script>

        </div>

        <?php

    }

    

    /**

     * Render quote custom fields builder

     */

    private static function render_quote_fields($field_key, $post_id) {

        $fields = get_post_meta($post_id, '_' . $field_key, true) ?: [];

        ?>

        <div class="hng-quote-fields-builder">

            <div class="hng-quote-fields-list" id="quote-fields-list">

                <?php if (!empty($fields) && is_array($fields)): ?>

                    <?php foreach ($fields as $index => $field): ?>

                        <?php self::render_quote_field_row($field_key, $index, $field); ?>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <button type="button" class="button hng-add-quote-field" id="add-quote-field-btn">➕ Adicionar Campo</button>

            

            <script>

            (function() {

                let fieldIndex = <?php echo count($fields); ?>;

                

                document.getElementById('add-quote-field-btn').addEventListener('click', function() {

                    addQuoteField();

                });

                

                function addQuoteField() {

                    const list = document.getElementById('quote-fields-list');

                    const index = fieldIndex++;

                    const fieldKey = '<?php echo esc_js($field_key); ?>';

                    

                    const html = `

                        <div class="hng-quote-field-item" data-index="${index}">

                            <div class="hng-quote-field-row">

                                <input type="text" 

                                       name="_${fieldKey}[${index}][label]" 

                                       placeholder="Título do Campo"

                                       class="hng-quote-field-label">

                                

                                <select name="_${fieldKey}[${index}][type]" class="hng-quote-field-type" onchange="handleFieldTypeChange(this, ${index})">

                                    <option value="text">Texto</option>

                                    <option value="textarea">Texto Longo</option>

                                    <option value="number">Número</option>

                                    <option value="date">Data</option>

                                    <option value="yesno">Sim/Não</option>

                                    <option value="select">Seleção</option>

                                    <option value="file">Upload de Arquivo</option>

                                </select>

                                

                                <label class="hng-quote-field-required-label">

                                    <input type="checkbox" 

                                           name="_${fieldKey}[${index}][required]" 

                                           value="1"> Obrigatório

                                </label>

                                

                                <button type="button" class="button hng-remove-quote-field" onclick="removeQuoteField(this);">❌</button>

                            </div>

                            

                            <div class="hng-quote-field-options" style="display: none;">

                                <input type="text" 

                                       name="_${fieldKey}[${index}][options]" 

                                       placeholder="Opções separadas por vírgula (Ex: Opção 1, Opção 2, Opção 3)"

                                       class="hng-quote-field-options-input">

                            </div>

                            

                            <div class="hng-quote-field-conditional">

                                <label class="hng-quote-field-conditional-toggle">

                                    <input type="checkbox" 

                                           name="_${fieldKey}[${index}][is_conditional]" 

                                           value="1"

                                           onchange="toggleConditionalOptions(this, ${index})"> 

                                    Campo Condicional (aparece apenas se outro campo for marcado)

                                </label>

                                <div class="hng-quote-field-conditional-options" style="display: none;">

                                    <select name="_${fieldKey}[${index}][condition_field]" class="hng-condition-field-select">

                                        <option value="">-- Selecione o campo --</option>

                                    </select>

                                    <select name="_${fieldKey}[${index}][condition_value]" class="hng-condition-value-select">

                                        <option value="yes">Se for SIM</option>

                                        <option value="no">Se for NÃO</option>

                                    </select>

                                </div>

                            </div>

                        </div>

                    `;

                    list.insertAdjacentHTML('beforeend', html);

                    updateConditionalFieldOptions();

                }

                

                window.removeQuoteField = function(btn) {

                    btn.closest('.hng-quote-field-item').remove();

                    updateConditionalFieldOptions();

                };

                

                window.handleFieldTypeChange = function(select, index) {

                    const item = select.closest('.hng-quote-field-item');

                    const optionsDiv = item.querySelector('.hng-quote-field-options');

                    

                    if (select.value === 'select') {

                        optionsDiv.style.display = 'block';

                    } else {

                        optionsDiv.style.display = 'none';

                    }

                    

                    updateConditionalFieldOptions();

                };

                

                window.toggleConditionalOptions = function(checkbox, index) {

                    const item = checkbox.closest('.hng-quote-field-item');

                    const optionsDiv = item.querySelector('.hng-quote-field-conditional-options');

                    optionsDiv.style.display = checkbox.checked ? 'flex' : 'none';

                    

                    if (checkbox.checked) {

                        updateConditionalFieldOptions();

                    }

                };

                

                window.updateConditionalFieldOptions = function() {

                    const list = document.getElementById('quote-fields-list');

                    const items = list.querySelectorAll('.hng-quote-field-item');

                    

                    // Collect all yesno fields

                    const yesnoFields = [];

                    items.forEach((item, idx) => {

                        const typeSelect = item.querySelector('.hng-quote-field-type');

                        const labelInput = item.querySelector('.hng-quote-field-label');

                        if (typeSelect && typeSelect.value === 'yesno' && labelInput && labelInput.value) {

                            yesnoFields.push({

                                index: item.dataset.index,

                                label: labelInput.value

                            });

                        }

                    });

                    

                    // Update all condition field selects

                    items.forEach(item => {

                        const conditionSelect = item.querySelector('.hng-condition-field-select');

                        if (conditionSelect) {

                            const currentValue = conditionSelect.value;

                            conditionSelect.innerHTML = '<option value="">-- Selecione o campo --</option>';

                            yesnoFields.forEach(f => {

                                if (f.index !== item.dataset.index) {

                                    const option = document.createElement('option');

                                    option.value = f.index;

                                    option.textContent = f.label;

                                    if (currentValue === f.index) option.selected = true;

                                    conditionSelect.appendChild(option);

                                }

                            });

                        }

                    });

                };

                

                // Initialize on page load

                document.addEventListener('DOMContentLoaded', function() {

                    // Setup existing fields

                    document.querySelectorAll('.hng-quote-field-type').forEach(function(select) {

                        const item = select.closest('.hng-quote-field-item');

                        const optionsDiv = item.querySelector('.hng-quote-field-options');

                        if (select.value === 'select' && optionsDiv) {

                            optionsDiv.style.display = 'block';

                        }

                    });

                    

                    document.querySelectorAll('.hng-conditional-checkbox').forEach(function(checkbox) {

                        if (checkbox.checked) {

                            const item = checkbox.closest('.hng-quote-field-item');

                            const optionsDiv = item.querySelector('.hng-quote-field-conditional-options');

                            if (optionsDiv) optionsDiv.style.display = 'flex';

                        }

                    });

                    

                    updateConditionalFieldOptions();

                    

                    // Watch for label changes to update conditional options

                    document.getElementById('quote-fields-list').addEventListener('input', function(e) {

                        if (e.target.classList.contains('hng-quote-field-label')) {

                            updateConditionalFieldOptions();

                        }

                    });

                });

            })();

            </script>

        </div>

        

        <style>

        .hng-quote-fields-builder {

            background: #f9fafb;

            border: 1px solid #e5e7eb;

            border-radius: 8px;

            padding: 16px;

        }

        .hng-quote-field-item {

            background: #fff;

            border: 1px solid #e5e7eb;

            border-radius: 6px;

            padding: 12px;

            margin-bottom: 10px;

        }

        .hng-quote-field-row {

            display: flex;

            gap: 10px;

            align-items: center;

            flex-wrap: wrap;

        }

        .hng-quote-field-label {

            flex: 1;

            min-width: 200px;

        }

        .hng-quote-field-type {

            min-width: 140px;

        }

        .hng-quote-field-required-label {

            display: flex;

            align-items: center;

            gap: 4px;

            font-size: 13px;

            white-space: nowrap;

        }

        .hng-quote-field-options {

            margin-top: 10px;

            padding-top: 10px;

            border-top: 1px dashed #e5e7eb;

        }

        .hng-quote-field-options-input {

            width: 100%;

        }

        .hng-quote-field-conditional {

            margin-top: 10px;

            padding-top: 10px;

            border-top: 1px dashed #e5e7eb;

        }

        .hng-quote-field-conditional-toggle {

            display: flex;

            align-items: center;

            gap: 6px;

            font-size: 12px;

            color: #6b7280;

        }

        .hng-quote-field-conditional-options {

            display: flex;

            gap: 10px;

            margin-top: 8px;

            padding: 10px;

            background: #fef3c7;

            border-radius: 4px;

        }

        .hng-condition-field-select,

        .hng-condition-value-select {

            flex: 1;

        }

        .hng-remove-quote-field {

            color: #dc2626 !important;

            border-color: #dc2626 !important;

        }

        </style>

        <?php

    }

    

    /**

     * Render a single quote field row

     */

    private static function render_quote_field_row($field_key, $index, $field) {

        $type = $field['type'] ?? 'text';

        $is_conditional = !empty($field['is_conditional']);

        ?>

        <div class="hng-quote-field-item" data-index="<?php echo esc_attr($index); ?>">

            <div class="hng-quote-field-row">

                <input type="text" 

                       name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][label]" 

                       placeholder="Título do Campo"

                       value="<?php echo esc_attr($field['label'] ?? ''); ?>"

                       class="hng-quote-field-label">

                

                <select name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][type]" 

                        class="hng-quote-field-type" 

                        onchange="handleFieldTypeChange(this, <?php echo esc_attr($index); ?>)">

                    <option value="text" <?php selected($type, 'text'); ?>>Texto</option>

                    <option value="textarea" <?php selected($type, 'textarea'); ?>>Texto Longo</option>

                    <option value="number" <?php selected($type, 'number'); ?>>Número</option>

                    <option value="date" <?php selected($type, 'date'); ?>>Data</option>

                    <option value="yesno" <?php selected($type, 'yesno'); ?>>Sim/Não</option>

                    <option value="select" <?php selected($type, 'select'); ?>>Seleção</option>

                    <option value="file" <?php selected($type, 'file'); ?>>Upload de Arquivo</option>

                </select>

                

                <label class="hng-quote-field-required-label">

                    <input type="checkbox" 

                           name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][required]" 

                           value="1"

                           <?php checked(!empty($field['required'])); ?>> Obrigatório

                </label>

                

                <button type="button" class="button hng-remove-quote-field" onclick="removeQuoteField(this);">❌</button>

            </div>

            

            <div class="hng-quote-field-options" style="display: <?php echo $type === 'select' ? 'block' : 'none'; ?>;">

                <input type="text" 

                       name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][options]" 

                       placeholder="Opções separadas por vírgula (Ex: Opção 1, Opção 2, Opção 3)"

                       value="<?php echo esc_attr($field['options'] ?? ''); ?>"

                       class="hng-quote-field-options-input">

            </div>

            

            <div class="hng-quote-field-conditional">

                <label class="hng-quote-field-conditional-toggle">

                    <input type="checkbox" 

                           name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][is_conditional]" 

                           value="1"

                           class="hng-conditional-checkbox"

                           onchange="toggleConditionalOptions(this, <?php echo esc_attr($index); ?>)"

                           <?php checked($is_conditional); ?>> 

                    Campo Condicional (aparece apenas se outro campo for marcado)

                </label>

                <div class="hng-quote-field-conditional-options" style="display: <?php echo $is_conditional ? 'flex' : 'none'; ?>;">

                    <select name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][condition_field]" class="hng-condition-field-select">

                        <option value="">-- Selecione o campo --</option>

                        <?php // Options will be populated by JavaScript ?>

                    </select>

                    <select name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][condition_value]" class="hng-condition-value-select">

                        <option value="yes" <?php selected($field['condition_value'] ?? '', 'yes'); ?>>Se for SIM</option>

                        <option value="no" <?php selected($field['condition_value'] ?? '', 'no'); ?>>Se for NÃO</option>

                    </select>

                    <input type="hidden" 

                           name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][condition_field_saved]" 

                           value="<?php echo esc_attr($field['condition_field'] ?? ''); ?>"

                           class="hng-condition-field-saved">

                </div>

            </div>

        </div>

        <?php

    }

    

    /**

     * Render appointment professionals field

     */

    private static function render_professionals_field($field_key, $post_id) {

        $professionals = get_post_meta($post_id, '_' . $field_key, true) ?: [];

        ?>

        <div class="hng-professionals-manager">

            <div class="hng-professionals-list" id="professionals-list">

                <?php if (!empty($professionals) && is_array($professionals)): ?>

                    <?php foreach ($professionals as $index => $prof): ?>

                        <div class="hng-professional-item">

                            <input type="text" 

                                   name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][name]" 

                                   placeholder="Nome do Profissional"

                                   value="<?php echo esc_attr($prof['name'] ?? ''); ?>"

                                   class="hng-professional-name">

                            

                            <input type="email" 

                                   name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][email]" 

                                   placeholder="Email"

                                   value="<?php echo esc_attr($prof['email'] ?? ''); ?>"

                                   class="hng-professional-email">

                            

                            <input type="tel" 

                                   name="_<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][phone]" 

                                   placeholder="Telefone"

                                   value="<?php echo esc_attr($prof['phone'] ?? ''); ?>"

                                   class="hng-professional-phone">

                            

                            <button type="button" class="button hng-remove-professional" onclick="this.parentElement.remove(); updateProfessionalCount();">❌ Remover</button>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            

            <div class="hng-professionals-info">

                <p><strong>Total de Profissionais:</strong> <span id="professional-count">0</span></p>

                <p class="description">

                    💡 <strong>Dica:</strong> O número de profissionais disponíveis determina quantos clientes podem agendar o serviço 

                    no mesmo horário. Por exemplo, se o serviço tem duração de 1 hora:

                </p>

                <ul>

                    <li>1 profissional = Apenas 1 cliente por hora</li>

                    <li>2 profissionais = Até 2 clientes no mesmo horário</li>

                    <li>3+ profissionais = Até N clientes simultâneos</li>

                </ul>

            </div>

            

            <button type="button" class="button hng-add-professional" onclick="addProfessional();">➕ Adicionar Profissional</button>

            

            <script>

            function addProfessional() {

                const list = document.getElementById('professionals-list');

                const index = list.children.length;

                const html = `

                    <div class="hng-professional-item">

                        <input type="text" 

                               name="_<?php echo esc_js($field_key); ?>[${index}][name]" 

                               placeholder="Nome do Profissional"

                               class="hng-professional-name">

                        

                        <input type="email" 

                               name="_<?php echo esc_js($field_key); ?>[${index}][email]" 

                               placeholder="Email"

                               class="hng-professional-email">

                        

                        <input type="tel" 

                               name="_<?php echo esc_js($field_key); ?>[${index}][phone]" 

                               placeholder="Telefone"

                               class="hng-professional-phone">

                        

                        <button type="button" class="button hng-remove-professional" onclick="this.parentElement.remove(); updateProfessionalCount();">❌ Remover</button>

                    </div>

                `;

                list.insertAdjacentHTML('beforeend', html);

                updateProfessionalCount();

            }

            

            function updateProfessionalCount() {

                const list = document.getElementById('professionals-list');

                const count = list.children.length;

                document.getElementById('professional-count').textContent = count;

            }

            

            // Initialize count on page load

            document.addEventListener('DOMContentLoaded', function() {

                updateProfessionalCount();

            });

            </script>

        </div>

        <?php

    }

    

    /**

     * Get fields for a specific product type

     */

    public static function get_type_fields($type) {

        $types = self::get_product_types();

        if (!isset($types[$type])) {

            return [];

        }

        

        $fields = [];

        $definitions = self::get_field_definitions();

        

        if (isset($types[$type]['fields'])) {

            foreach ($types[$type]['fields'] as $section => $field_keys) {

                foreach ($field_keys as $field_key) {

                    if (isset($definitions[$field_key])) {

                        $fields[$section][$field_key] = $definitions[$field_key];

                    }

                }

            }

        }

        

        return $fields;

    }

}



// Instantiate

HNG_Product_Type_Fields::instance();

