<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Produto Único
 * 
 * Este template pode ser sobrescrito pelo tema criando:
 * - tema/single-hng_product.php
 * - tema/hng-commerce/single-product.php
 * 
 * Suporta todos os tipos de produtos:
 * - physical: Produtos físicos
 * - digital: Produtos digitais/downloads
 * - subscription: Assinaturas
 * - appointment: Agendamentos
 * - service: Serviços
 * - variable: Produtos variáveis
 * 
 * @package HNG_Commerce
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $post;
$product = function_exists('hng_get_product') ? hng_get_product($post->ID) : null;
$product_id = get_the_ID();

// Fallback se não houver classe de produto
if (!$product || !method_exists($product, 'get_id')) {
    // Usar meta dados diretamente
    $product_data = [
        'id' => $product_id,
        'name' => get_the_title(),
        'price' => floatval(get_post_meta($product_id, '_price', true)),
        'regular_price' => floatval(get_post_meta($product_id, '_regular_price', true)),
        'sale_price' => floatval(get_post_meta($product_id, '_sale_price', true)),
        'sku' => get_post_meta($product_id, '_sku', true),
        'stock_status' => get_post_meta($product_id, '_stock_status', true) ?: 'instock',
        'stock_quantity' => intval(get_post_meta($product_id, '_stock_quantity', true)),
        'manage_stock' => get_post_meta($product_id, '_manage_stock', true) === 'yes',
        'product_type' => get_post_meta($product_id, '_hng_product_type', true) ?: 'physical',
        'sold_individually' => get_post_meta($product_id, '_sold_individually', true) === 'yes',
        'description' => get_the_content(),
        'short_description' => get_the_excerpt(),
    ];
} else {
    $product_data = [
        'id' => $product->get_id(),
        'name' => $product->get_name(),
        'price' => floatval($product->get_price()),
        'regular_price' => floatval($product->get_regular_price()),
        'sale_price' => floatval($product->get_sale_price()),
        'sku' => $product->get_sku(),
        'stock_status' => $product->get_stock_status(),
        'stock_quantity' => $product->get_stock_quantity(),
        'manage_stock' => method_exists($product, 'manages_stock') ? $product->manages_stock() : false,
        'product_type' => method_exists($product, 'get_product_type') ? $product->get_product_type() : (isset($product->product_type) ? $product->product_type : 'simple'),
        'sold_individually' => method_exists($product, 'is_sold_individually') ? $product->is_sold_individually() : false,
        'description' => $product->get_description(),
        'short_description' => $product->get_short_description(),
    ];
}

// Calcular preços e desconto
$has_sale = $product_data['sale_price'] > 0 && $product_data['regular_price'] > 0 && $product_data['sale_price'] < $product_data['regular_price'];
$display_price = $has_sale ? $product_data['sale_price'] : $product_data['price'];
$discount_percent = $has_sale ? round((($product_data['regular_price'] - $product_data['sale_price']) / $product_data['regular_price']) * 100) : 0;

// Verificar estoque
$is_in_stock = $product_data['stock_status'] === 'instock';
if ($product_data['manage_stock'] && $product_data['stock_quantity'] <= 0) {
    $is_in_stock = false;
}

// Verificar se pode comprar
$is_purchasable = $is_in_stock && $display_price > 0;

// Dados específicos do tipo de produto
$product_type = $product_data['product_type'];
$gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
$categories = get_the_terms($product_id, 'hng_product_cat');
$tags = get_the_terms($product_id, 'hng_product_tag');

// Custom fields - check both locations for compatibility
$custom_fields = get_post_meta($product_id, '_hng_custom_fields', true);
if (empty($custom_fields) && $product_type === 'quote') {
    $custom_fields = get_post_meta($product_id, '_quote_custom_fields', true);
}

// Dados de assinatura
$subscription_price = get_post_meta($product_id, '_subscription_price', true);
$subscription_period = get_post_meta($product_id, '_subscription_period', true);
$subscription_length = get_post_meta($product_id, '_subscription_length', true);

// Dados de agendamento
$appointment_duration = get_post_meta($product_id, '_appointment_duration', true);
$appointment_capacity = get_post_meta($product_id, '_appointment_capacity', true);

// Dados de download
$downloadable_files = get_post_meta($product_id, '_downloadable_files', true);

// Dimensões
$weight = get_post_meta($product_id, '_weight', true);
$length = get_post_meta($product_id, '_length', true);
$width = get_post_meta($product_id, '_width', true);
$height = get_post_meta($product_id, '_height', true);
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('hng-product-template'); ?>>
<?php wp_body_open(); ?>

<main class="hng-product-page">
    <div class="hng-container">
        
        <!-- Breadcrumb -->
        <nav class="hng-breadcrumb" aria-label="<?php esc_attr_e('Navegação', 'hng-commerce'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Início', 'hng-commerce'); ?></a>
            <span class="hng-sep">/</span>
            <a href="<?php echo esc_url(get_post_type_archive_link('hng_product')); ?>"><?php esc_html_e('Produtos', 'hng-commerce'); ?></a>
            <?php if ($categories && !is_wp_error($categories)) : ?>
                <span class="hng-sep">/</span>
                <a href="<?php echo esc_url(get_term_link($categories[0])); ?>"><?php echo esc_html($categories[0]->name); ?></a>
            <?php endif; ?>
            <span class="hng-sep">/</span>
            <span class="hng-current"><?php echo esc_html($product_data['name']); ?></span>
        </nav>
        
        <?php while (have_posts()) : the_post(); ?>
        
        <div class="hng-product-main">
            
            <!-- Galeria -->
            <div class="hng-product-gallery">
                <div class="hng-gallery-main">
                    <?php if ($has_sale) : ?>
                        <span class="hng-badge hng-badge-sale">-<?php echo esc_html($discount_percent); ?>%</span>
                    <?php endif; ?>
                    
                    <?php if (!$is_in_stock) : ?>
                        <span class="hng-badge hng-badge-out"><?php esc_html_e('Esgotado', 'hng-commerce'); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($product_type === 'digital') : ?>
                        <span class="hng-badge hng-badge-digital"><?php esc_html_e('Digital', 'hng-commerce'); ?></span>
                    <?php endif; ?>
                    
                    <?php if (has_post_thumbnail()) : ?>
                        <img id="hng-main-image" src="<?php echo esc_url(get_the_post_thumbnail_url($product_id, 'large')); ?>" alt="<?php echo esc_attr($product_data['name']); ?>">
                    <?php else : ?>
                        <div class="hng-no-image">
                            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($gallery_ids || has_post_thumbnail()) : ?>
                    <div class="hng-gallery-thumbs">
                        <?php if (has_post_thumbnail()) : ?>
                            <button class="hng-thumb active" data-src="<?php echo esc_url(get_the_post_thumbnail_url($product_id, 'large')); ?>">
                                <?php the_post_thumbnail('thumbnail'); ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($gallery_ids) : 
                            foreach (explode(',', $gallery_ids) as $img_id) : ?>
                                <button class="hng-thumb" data-src="<?php echo esc_url(wp_get_attachment_image_url($img_id, 'large')); ?>">
                                    <?php echo wp_get_attachment_image($img_id, 'thumbnail'); ?>
                                </button>
                            <?php endforeach; 
                        endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Informações do Produto -->
            <div class="hng-product-summary">
                
                <h1 class="hng-product-title"><?php echo esc_html($product_data['name']); ?></h1>
                
                <?php if ($product_data['sku']) : ?>
                    <span class="hng-product-sku"><?php esc_html_e('SKU:', 'hng-commerce'); ?> <?php echo esc_html($product_data['sku']); ?></span>
                <?php endif; ?>
                
                <!-- Preço -->
                <div class="hng-product-price">
                    <?php if ($product_type === 'subscription' && $subscription_price) : ?>
                        <!-- Preço de Assinatura -->
                        <?php 
                        $period_labels = [
                            'day' => esc_html__('dia', 'hng-commerce'),
                            'week' => esc_html__('semana', 'hng-commerce'),
                            'month' => esc_html__('mês', 'hng-commerce'),
                            'year' => esc_html__('ano', 'hng-commerce'),
                        ];
                        $period_label = $period_labels[$subscription_period] ?? $subscription_period;
                        ?>
                        <span class="hng-price-current">R$ <?php echo esc_html(number_format(floatval($subscription_price), 2, ',', '.')); ?></span>
                        <span class="hng-price-period">/ <?php echo esc_html($period_label); ?></span>
                    <?php else : ?>
                        <?php if ($has_sale) : ?>
                            <span class="hng-price-original">R$ <?php echo esc_html(number_format($product_data['regular_price'], 2, ',', '.')); ?></span>
                        <?php endif; ?>
                        <span class="hng-price-current">R$ <?php echo esc_html(number_format($display_price, 2, ',', '.')); ?></span>
                        <?php if ($has_sale) : ?>
                            <span class="hng-price-savings"><?php
                        /* translators: %s: savings amount */
                        printf(esc_html__('Economia de R$ %s', 'hng-commerce'), esc_html(number_format($product_data['regular_price'] - $product_data['sale_price'], 2, ',', '.')));
                        ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Status de Estoque -->
                <div class="hng-stock-status <?php echo esc_attr( $is_in_stock ? 'hng-in-stock' : 'hng-out-of-stock' ); ?>">
                    <?php if ($is_in_stock) : ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><?php esc_html_e('Em estoque', 'hng-commerce'); ?></span>
                        <?php if ($product_data['manage_stock'] && $product_data['stock_quantity'] <= 10) : ?>
                            <span class="hng-low-stock"><?php
                            /* translators: %d: stock quantity */
                            printf(esc_html__('(Apenas %d restantes)', 'hng-commerce'), absint($product_data['stock_quantity']));
                            ?></span>
                        <?php endif; ?>
                    <?php else : ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <span><?php esc_html_e('Produto indisponível', 'hng-commerce'); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Descrição Curta -->
                <?php if (!empty($product_data['short_description'])) : ?>
                    <div class="hng-short-description">
                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post already sanitizes content ?>
                        <?php echo wpautop(wp_kses_post($product_data['short_description'])); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Informações do Tipo de Produto -->
                <?php if ($product_type === 'digital') : ?>
                    <div class="hng-product-type-info hng-digital-info">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span><?php esc_html_e('Produto digital - Download imediato após o pagamento', 'hng-commerce'); ?></span>
                    </div>
                <?php elseif ($product_type === 'appointment') : ?>
                    <div class="hng-product-type-info hng-appointment-info">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span>
                            <?php esc_html_e('Serviço com agendamento', 'hng-commerce'); ?>
                            <?php if ($appointment_duration) : ?>
                                - <?php
                                /* translators: %d: duration in minutes */
                                printf(esc_html__('Duração: %d minutos', 'hng-commerce'), intval($appointment_duration));
                                ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php elseif ($product_type === 'subscription') : ?>
                    <div class="hng-product-type-info hng-subscription-info">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                        <span><?php esc_html_e('Cobrança recorrente - Cancele quando quiser', 'hng-commerce'); ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Formulário de Compra -->
                <?php if ($is_purchasable) : ?>
                    <form class="hng-add-to-cart-form" id="hng-product-form" method="post">
                        <?php wp_nonce_field('hng_add_to_cart', 'hng_cart_nonce'); ?>
                        <input type="hidden" name="action" value="hng_add_to_cart">
                        <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
                        
                        <!-- Campos Personalizados -->
                        <?php if (is_array($custom_fields) && !empty($custom_fields)) : 
                            // Prepare fields with slugs if not set
                            $prepared_fields = [];
                            foreach ($custom_fields as $idx => $field) {
                                if (!isset($field['slug'])) {
                                    $field['slug'] = sanitize_key($field['label'] ?? 'field_' . $idx);
                                }
                                $field['index'] = $idx;
                                $prepared_fields[$idx] = $field;
                            }
                        ?>
                            <div class="hng-custom-fields" id="hng-custom-fields-container">
                                <?php foreach ($prepared_fields as $idx => $field) : 
                                    $slug = sanitize_key($field['slug'] ?? $field['label'] ?? 'field_' . $idx);
                                    $label = $field['label'] ?? '';
                                    $type = $field['type'] ?? 'text';
                                    $required = !empty($field['required']);
                                    $options = isset($field['options']) ? array_map('trim', explode(',', $field['options'])) : [];
                                    
                                    // Conditional field data
                                    $is_conditional = !empty($field['is_conditional']);
                                    $condition_field = $field['condition_field'] ?? '';
                                    $condition_value = $field['condition_value'] ?? 'yes';
                                    
                                    // Get condition field slug if it's a conditional field
                                    $condition_slug = '';
                                    if ($is_conditional && isset($prepared_fields[$condition_field])) {
                                        $condition_slug = sanitize_key($prepared_fields[$condition_field]['slug'] ?? $prepared_fields[$condition_field]['label'] ?? '');
                                    }
                                ?>
                                    <div class="hng-field hng-field-<?php echo esc_attr($type); ?><?php echo $is_conditional ? ' hng-conditional-field' : ''; ?>" 
                                         data-field-slug="<?php echo esc_attr($slug); ?>"
                                         <?php if ($is_conditional) : ?>
                                         data-condition-field="<?php echo esc_attr($condition_slug); ?>"
                                         data-condition-value="<?php echo esc_attr($condition_value); ?>"
                                         style="display: none;"
                                         <?php endif; ?>>
                                        
                                        <?php if ($type !== 'yesno') : ?>
                                        <label for="hng_cf_<?php echo esc_attr($slug); ?>">
                                            <?php echo esc_html($label); ?>
                                            <?php if ($required && !$is_conditional) : ?><span class="hng-required">*</span><?php endif; ?>
                                        </label>
                                        <?php endif; ?>
                                        
                                        <?php switch ($type) : 
                                            case 'textarea': ?>
                                                <textarea name="hng_cf[<?php echo esc_attr($slug); ?>]" id="hng_cf_<?php echo esc_attr($slug); ?>" rows="3" <?php echo ($required && !$is_conditional) ? 'required' : ''; ?>></textarea>
                                                <?php break; 
                                            
                                            case 'select': ?>
                                                <select name="hng_cf[<?php echo esc_attr($slug); ?>]" id="hng_cf_<?php echo esc_attr($slug); ?>" <?php echo ($required && !$is_conditional) ? 'required' : ''; ?>>
                                                    <option value=""><?php esc_html_e('Selecione...', 'hng-commerce'); ?></option>
                                                    <?php foreach ($options as $opt) : ?>
                                                        <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php break;
                                            
                                            case 'radio': ?>
                                                <div class="hng-radio-group">
                                                    <?php foreach ($options as $opt) : ?>
                                                        <label><input type="radio" name="hng_cf[<?php echo esc_attr($slug); ?>]" value="<?php echo esc_attr($opt); ?>" <?php echo ($required && !$is_conditional) ? 'required' : ''; ?>> <?php echo esc_html($opt); ?></label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php break;
                                            
                                            case 'checkbox': ?>
                                                <div class="hng-checkbox-group">
                                                    <?php foreach ($options as $opt) : ?>
                                                        <label><input type="checkbox" name="hng_cf[<?php echo esc_attr($slug); ?>][]" value="<?php echo esc_attr($opt); ?>"> <?php echo esc_html($opt); ?></label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php break;
                                            
                                            case 'number': ?>
                                                <input type="number" name="hng_cf[<?php echo esc_attr($slug); ?>]" id="hng_cf_<?php echo esc_attr($slug); ?>" <?php echo ($required && !$is_conditional) ? 'required' : ''; ?>>
                                                <?php break;
                                            
                                            case 'date': ?>
                                                <input type="date" name="hng_cf[<?php echo esc_attr($slug); ?>]" id="hng_cf_<?php echo esc_attr($slug); ?>" <?php echo ($required && !$is_conditional) ? 'required' : ''; ?>>
                                                <?php break;
                                            
                                            case 'yesno': ?>
                                                <div class="hng-yesno-field">
                                                    <label class="hng-yesno-label">
                                                        <span class="hng-yesno-text">
                                                            <?php echo esc_html($label); ?>
                                                            <?php if ($required && !$is_conditional) : ?><span class="hng-required">*</span><?php endif; ?>
                                                        </span>
                                                        <div class="hng-toggle-switch">
                                                            <input type="hidden" name="hng_cf[<?php echo esc_attr($slug); ?>]" value="no">
                                                            <input type="checkbox" 
                                                                   name="hng_cf[<?php echo esc_attr($slug); ?>]" 
                                                                   id="hng_cf_<?php echo esc_attr($slug); ?>" 
                                                                   value="yes"
                                                                   class="hng-yesno-input"
                                                                   data-field-slug="<?php echo esc_attr($slug); ?>">
                                                            <span class="hng-toggle-slider"></span>
                                                        </div>
                                                        <span class="hng-yesno-status" id="hng_cf_<?php echo esc_attr($slug); ?>_status">Não</span>
                                                    </label>
                                                </div>
                                                <?php break;
                                            
                                            case 'file': ?>
                                                <input type="file" name="hng_cf[<?php echo esc_attr($slug); ?>]" id="hng_cf_<?php echo esc_attr($slug); ?>" <?php echo ($required && !$is_conditional) ? 'required' : ''; ?>>
                                                <?php break;
                                            
                                            default: ?>
                                                <input type="text" name="hng_cf[<?php echo esc_attr($slug); ?>]" id="hng_cf_<?php echo esc_attr($slug); ?>" <?php echo ($required && !$is_conditional) ? 'required' : ''; ?>>
                                        <?php endswitch; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <script>
                            (function() {
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Handle yes/no toggle display
                                    document.querySelectorAll('.hng-yesno-input').forEach(function(input) {
                                        const statusEl = document.getElementById(input.id + '_status');
                                        
                                        function updateStatus() {
                                            if (statusEl) {
                                                statusEl.textContent = input.checked ? 'Sim' : 'Não';
                                            }
                                            // Trigger conditional fields update
                                            updateConditionalFields();
                                        }
                                        
                                        input.addEventListener('change', updateStatus);
                                        updateStatus();
                                    });
                                    
                                    // Handle conditional fields visibility
                                    function updateConditionalFields() {
                                        document.querySelectorAll('.hng-conditional-field').forEach(function(field) {
                                            const conditionFieldSlug = field.dataset.conditionField;
                                            const conditionValue = field.dataset.conditionValue;
                                            
                                            if (!conditionFieldSlug) return;
                                            
                                            // Find the condition field
                                            const conditionInput = document.querySelector('[data-field-slug="' + conditionFieldSlug + '"]');
                                            if (!conditionInput) {
                                                // Try to find by field container
                                                const conditionContainer = document.querySelector('.hng-field[data-field-slug="' + conditionFieldSlug + '"]');
                                                if (conditionContainer) {
                                                    const checkbox = conditionContainer.querySelector('.hng-yesno-input');
                                                    if (checkbox) {
                                                        const isYes = checkbox.checked;
                                                        const shouldShow = (conditionValue === 'yes' && isYes) || (conditionValue === 'no' && !isYes);
                                                        
                                                        field.style.display = shouldShow ? '' : 'none';
                                                        
                                                        // Toggle required attribute
                                                        const inputs = field.querySelectorAll('input, select, textarea');
                                                        inputs.forEach(function(inp) {
                                                            if (shouldShow && field.dataset.wasRequired === 'true') {
                                                                inp.setAttribute('required', '');
                                                            } else {
                                                                if (inp.hasAttribute('required')) {
                                                                    field.dataset.wasRequired = 'true';
                                                                }
                                                                inp.removeAttribute('required');
                                                            }
                                                        });
                                                    }
                                                }
                                                return;
                                            }
                                            
                                            const isYes = conditionInput.checked;
                                            const shouldShow = (conditionValue === 'yes' && isYes) || (conditionValue === 'no' && !isYes);
                                            
                                            field.style.display = shouldShow ? '' : 'none';
                                        });
                                    }
                                    
                                    updateConditionalFields();
                                });
                            })();
                            </script>
                            
                            <style>
                            .hng-yesno-field {
                                margin-bottom: 1rem;
                            }
                            .hng-yesno-label {
                                display: flex;
                                align-items: center;
                                gap: 12px;
                                cursor: pointer;
                            }
                            .hng-yesno-text {
                                flex: 1;
                                font-weight: 500;
                            }
                            .hng-toggle-switch {
                                position: relative;
                                width: 50px;
                                height: 26px;
                            }
                            .hng-toggle-switch input {
                                opacity: 0;
                                width: 0;
                                height: 0;
                            }
                            .hng-toggle-slider {
                                position: absolute;
                                cursor: pointer;
                                top: 0;
                                left: 0;
                                right: 0;
                                bottom: 0;
                                background-color: #cbd5e1;
                                transition: 0.3s;
                                border-radius: 26px;
                            }
                            .hng-toggle-slider:before {
                                position: absolute;
                                content: "";
                                height: 20px;
                                width: 20px;
                                left: 3px;
                                bottom: 3px;
                                background-color: white;
                                transition: 0.3s;
                                border-radius: 50%;
                                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                            }
                            .hng-toggle-switch input:checked + .hng-toggle-slider {
                                background-color: #10b981;
                            }
                            .hng-toggle-switch input:checked + .hng-toggle-slider:before {
                                transform: translateX(24px);
                            }
                            .hng-yesno-status {
                                min-width: 30px;
                                font-size: 0.875rem;
                                color: #64748b;
                            }
                            .hng-conditional-field {
                                animation: fadeIn 0.3s ease;
                            }
                            @keyframes fadeIn {
                                from { opacity: 0; transform: translateY(-10px); }
                                to { opacity: 1; transform: translateY(0); }
                            }
                            </style>
                        <?php endif; ?>
                        
                        <!-- Seletor de Data para Agendamentos -->
                        <?php if ($product_type === 'appointment') : ?>
                            <div class="hng-appointment-picker">
                                <div class="hng-field">
                                    <label for="appointment_date"><?php esc_html_e('Data do Agendamento', 'hng-commerce'); ?> <span class="hng-required">*</span></label>
                                    <input type="date" name="appointment_date" id="appointment_date" required min="<?php echo esc_attr(gmdate('Y-m-d', strtotime('+1 day'))); ?>">
                                </div>
                                <div class="hng-field">
                                    <label for="appointment_time"><?php esc_html_e('Horário', 'hng-commerce'); ?> <span class="hng-required">*</span></label>
                                    <select name="appointment_time" id="appointment_time" required>
                                        <option value=""><?php esc_html_e('Selecione a data primeiro', 'hng-commerce'); ?></option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Quantidade -->
                        <?php if (!$product_data['sold_individually'] && $product_type !== 'appointment') : ?>
                            <div class="hng-quantity-wrapper">
                                <label for="quantity"><?php esc_html_e('Quantidade', 'hng-commerce'); ?></label>
                                <div class="hng-quantity-controls">
                                    <button type="button" class="hng-qty-btn hng-qty-minus">−</button>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1" <?php if ($product_data['manage_stock']) echo 'max="' . esc_attr($product_data['stock_quantity']) . '"'; ?>>
                                    <button type="button" class="hng-qty-btn hng-qty-plus">+</button>
                                </div>
                            </div>
                        <?php else : ?>
                            <input type="hidden" name="quantity" value="1">
                        <?php endif; ?>
                        
                        <!-- Botões -->
                        <div class="hng-actions">
                            <button type="submit" class="hng-btn hng-btn-primary hng-add-to-cart-btn" id="hng-add-cart">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                <span>
                                    <?php 
                                    if ($product_type === 'subscription') {
                                        esc_html_e('Assinar Agora', 'hng-commerce');
                                    } elseif ($product_type === 'appointment') {
                                        esc_html_e('Agendar', 'hng-commerce');
                                    } else {
                                        esc_html_e('Adicionar ao Carrinho', 'hng-commerce');
                                    }
                                    ?>
                                </span>
                            </button>
                            
                            <a href="<?php echo esc_url(function_exists('hng_get_checkout_url') ? hng_get_checkout_url() : home_url('/finalizar-compra/')); ?>" class="hng-btn hng-btn-secondary hng-buy-now-btn" id="hng-buy-now">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                <span><?php esc_html_e('Comprar Agora', 'hng-commerce'); ?></span>
                            </a>
                        </div>
                        
                        <div class="hng-cart-message" id="hng-cart-message"></div>
                    </form>
                <?php else : ?>
                    <div class="hng-unavailable">
                        <p><?php esc_html_e('Este produto não está disponível para compra no momento.', 'hng-commerce'); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Meta Informações -->
                <div class="hng-product-meta">
                    <?php if ($categories && !is_wp_error($categories)) : ?>
                        <div class="hng-meta-row">
                            <span class="hng-meta-label"><?php esc_html_e('Categoria:', 'hng-commerce'); ?></span>
                            <span class="hng-meta-value">
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link is properly escaped inside array_map
                                echo wp_kses_post(implode(', ', array_map(function($cat) {
                                    return '<a href="' . esc_url(get_term_link($cat)) . '">' . esc_html($cat->name) . '</a>';
                                }, $categories)));
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($tags && !is_wp_error($tags)) : ?>
                        <div class="hng-meta-row">
                            <span class="hng-meta-label"><?php esc_html_e('Tags:', 'hng-commerce'); ?></span>
                            <span class="hng-meta-value">
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link is properly escaped inside array_map
                                echo wp_kses_post(implode(', ', array_map(function($tag) {
                                    return '<a href="' . esc_url(get_term_link($tag)) . '">' . esc_html($tag->name) . '</a>';
                                }, $tags)));
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Trust Badges -->
                <div class="hng-trust-badges">
                    <div class="hng-trust-badge">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span><?php esc_html_e('Pagamento Seguro', 'hng-commerce'); ?></span>
                    </div>
                    <div class="hng-trust-badge">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span><?php esc_html_e('Compra Garantida', 'hng-commerce'); ?></span>
                    </div>
                    <?php if ($product_type !== 'digital') : ?>
                        <div class="hng-trust-badge">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <span><?php esc_html_e('Entrega Rápida', 'hng-commerce'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php do_action('hng_after_product_summary', $product_id); ?>
            </div>
            
        </div>
        
        <!-- Tabs -->
        <div class="hng-product-tabs">
            <div class="hng-tabs-nav">
                <button class="hng-tab-btn active" data-tab="description"><?php esc_html_e('Descrição', 'hng-commerce'); ?></button>
                <button class="hng-tab-btn" data-tab="info"><?php esc_html_e('Informações', 'hng-commerce'); ?></button>
                <?php if (comments_open()) : ?>
                    <button class="hng-tab-btn" data-tab="reviews"><?php
                    /* translators: %d: number of comments */
                    printf(esc_html__('Comentários (%d)', 'hng-commerce'), absint(get_comments_number()));
                    ?></button>
                <?php endif; ?>
            </div>
            
            <div class="hng-tabs-content">
                <div class="hng-tab-panel active" id="hng-tab-description">
                    <div class="hng-description-content">
                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post already sanitizes content ?>
                        <?php echo wpautop(wp_kses_post($product_data['description'])); ?>
                    </div>
                </div>
                
                <div class="hng-tab-panel" id="hng-tab-info">
                    <table class="hng-info-table">
                        <?php if ($product_data['sku']) : ?>
                            <tr><th><?php esc_html_e('SKU', 'hng-commerce'); ?></th><td><?php echo esc_html($product_data['sku']); ?></td></tr>
                        <?php endif; ?>
                        <?php if ($weight) : ?>
                            <tr><th><?php esc_html_e('Peso', 'hng-commerce'); ?></th><td><?php echo esc_html($weight); ?> kg</td></tr>
                        <?php endif; ?>
                        <?php if ($length && $width && $height) : ?>
                            <tr><th><?php esc_html_e('Dimensões', 'hng-commerce'); ?></th><td><?php echo esc_html("$length × $width × $height cm"); ?></td></tr>
                        <?php endif; ?>
                        <tr><th><?php esc_html_e('Tipo', 'hng-commerce'); ?></th><td>
                            <?php
                            $type_labels = [
                                'physical' => esc_html__('Produto Físico', 'hng-commerce'),
                                'digital' => esc_html__('Produto Digital', 'hng-commerce'),
                                'subscription' => esc_html__('Assinatura', 'hng-commerce'),
                                'appointment' => esc_html__('Agendamento', 'hng-commerce'),
                                'service' => esc_html__('Serviço', 'hng-commerce'),
                            ];
                            echo esc_html($type_labels[$product_type] ?? ucfirst($product_type));
                            ?>
                        </td></tr>
                    </table>
                </div>
                
                <?php if (comments_open()) : ?>
                    <div class="hng-tab-panel" id="hng-tab-reviews">
                        <?php comments_template(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endwhile; ?>
        
        <!-- Produtos Relacionados -->
        <?php
        $related_args = [
            'post_type' => 'hng_product',
            'posts_per_page' => 4,
            'post_status' => 'publish',
            'post__not_in' => [$product_id],
            'orderby' => 'rand',
        ];
        
        if ($categories && !is_wp_error($categories)) {
            $related_args['tax_query'] = [[
                'taxonomy' => 'hng_product_cat',
                'field' => 'term_id',
                'terms' => wp_list_pluck($categories, 'term_id'),
            ]];
        }
        
        $related = new WP_Query($related_args);
        
        if ($related->have_posts()) :
        ?>
        <section class="hng-related-products">
            <h2 class="hng-section-title"><?php esc_html_e('Produtos Relacionados', 'hng-commerce'); ?></h2>
            <div class="hng-products-grid hng-columns-4">
                <?php while ($related->have_posts()) : $related->the_post(); 
                    $rel_price = floatval(get_post_meta(get_the_ID(), '_price', true));
                    $rel_sale = floatval(get_post_meta(get_the_ID(), '_sale_price', true));
                    $rel_regular = floatval(get_post_meta(get_the_ID(), '_regular_price', true));
                    $rel_has_sale = $rel_sale > 0 && $rel_regular > 0 && $rel_sale < $rel_regular;
                ?>
                    <article class="hng-product-card">
                        <a href="<?php the_permalink(); ?>" class="hng-card-link">
                            <?php if ($rel_has_sale) : ?>
                                <span class="hng-card-badge"><?php esc_html_e('Oferta', 'hng-commerce'); ?></span>
                            <?php endif; ?>
                            <div class="hng-card-image">
                                <?php if (has_post_thumbnail()) : the_post_thumbnail('medium'); else : ?>
                                    <div class="hng-placeholder"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
                                <?php endif; ?>
                            </div>
                            <div class="hng-card-content">
                                <h3 class="hng-card-title"><?php the_title(); ?></h3>
                                <div class="hng-card-price">
                                    <?php if ($rel_has_sale) : ?>
                                        <span class="hng-old-price">R$ <?php echo esc_html(number_format($rel_regular, 2, ',', '.')); ?></span>
                                    <?php endif; ?>
                                    <span class="hng-current-price">R$ <?php echo esc_html(number_format($rel_has_sale ? $rel_sale : $rel_price, 2, ',', '.')); ?></span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>
        </section>
        <?php wp_reset_postdata(); endif; ?>
        
    </div>
</main>

<script>
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        // Galeria de imagens
        var thumbs = document.querySelectorAll('.hng-thumb');
        var mainImg = document.getElementById('hng-main-image');
        
        thumbs.forEach(function(thumb) {
            thumb.addEventListener('click', function() {
                thumbs.forEach(function(t) { t.classList.remove('active'); });
                this.classList.add('active');
                if (mainImg && this.dataset.src) {
                    mainImg.src = this.dataset.src;
                }
            });
        });
        
        // Controles de quantidade
        var qtyInput = document.getElementById('quantity');
        var minusBtn = document.querySelector('.hng-qty-minus');
        var plusBtn = document.querySelector('.hng-qty-plus');
        
        if (minusBtn && plusBtn && qtyInput) {
            minusBtn.addEventListener('click', function() {
                var val = parseInt(qtyInput.value) || 1;
                if (val > 1) qtyInput.value = val - 1;
            });
            
            plusBtn.addEventListener('click', function() {
                var val = parseInt(qtyInput.value) || 1;
                var max = parseInt(qtyInput.max) || 999;
                if (val < max) qtyInput.value = val + 1;
            });
        }
        
        // Tabs
        var tabBtns = document.querySelectorAll('.hng-tab-btn');
        var tabPanels = document.querySelectorAll('.hng-tab-panel');
        
        tabBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabId = this.dataset.tab;
                
                tabBtns.forEach(function(b) { b.classList.remove('active'); });
                tabPanels.forEach(function(p) { p.classList.remove('active'); });
                
                this.classList.add('active');
                var panel = document.getElementById('hng-tab-' + tabId);
                if (panel) panel.classList.add('active');
            });
        });
        
        // Formulário de adicionar ao carrinho
        var form = document.getElementById('hng-product-form');
        var addBtn = document.getElementById('hng-add-cart');
        var msgDiv = document.getElementById('hng-cart-message');
        
        if (form && addBtn) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(form);
                formData.append('action', 'hng_add_to_cart');
                
                // Garantir que campos essenciais sejam enviados
                var productIdField = form.querySelector('[name="product_id"]');
                if (productIdField && productIdField.value) {
                    formData.set('product_id', productIdField.value);
                }
                
                // Garantir nonce
                var nonce = '<?php echo esc_js(wp_create_nonce('hng_add_to_cart')); ?>';
                formData.set('nonce', nonce);
                
                var nonceField = form.querySelector('[name="hng_cart_nonce"]');
                if (nonceField && nonceField.value) {
                    formData.set('nonce', nonceField.value);
                }
                
                // Debug: log formData
                console.log('FormData entries:');
                for (var pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                
                addBtn.disabled = true;
                addBtn.innerHTML = '<span class="hng-spinner"></span><span><?php esc_html_e('Adicionando...', 'hng-commerce'); ?></span>';
                
                fetch(typeof hng_ajax !== 'undefined' ? hng_ajax.ajax_url : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (msgDiv) {
                        msgDiv.style.display = 'block';
                        if (data.success) {
                            msgDiv.className = 'hng-cart-message hng-success';
                            msgDiv.innerHTML = '<?php esc_html_e('Produto adicionado!', 'hng-commerce'); ?> <a href="<?php echo esc_url(function_exists('hng_get_cart_url') ? hng_get_cart_url() : home_url('/carrinho/')); ?>"><?php esc_html_e('Ver carrinho', 'hng-commerce'); ?></a>';
                        } else {
                            msgDiv.className = 'hng-cart-message hng-error';
                            msgDiv.textContent = data.data && data.data.message ? data.data.message : '<?php esc_html_e('Erro ao adicionar ao carrinho', 'hng-commerce'); ?>';
                        }
                    }
                    
                    addBtn.disabled = false;
                    addBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span><?php esc_html_e('Adicionar ao Carrinho', 'hng-commerce'); ?></span>';
                })
                .catch(function(err) {
                    console.error(err);
                    addBtn.disabled = false;
                    addBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span><?php esc_html_e('Adicionar ao Carrinho', 'hng-commerce'); ?></span>';
                });
            });
        }
        
        // Comprar Agora
        var buyNowBtn = document.getElementById('hng-buy-now');
        if (buyNowBtn && form) {
            buyNowBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var formData = new FormData(form);
                formData.append('action', 'hng_add_to_cart');
                
                // Garantir que campos essenciais sejam enviados
                var productIdField = form.querySelector('[name="product_id"]');
                if (productIdField && productIdField.value) {
                    formData.set('product_id', productIdField.value);
                }
                
                var nonceField = form.querySelector('[name="hng_cart_nonce"]');
                if (nonceField && nonceField.value) {
                    formData.set('nonce', nonceField.value);
                }
                
                fetch(typeof hng_ajax !== 'undefined' ? hng_ajax.ajax_url : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function() {
                    window.location.href = buyNowBtn.href;
                });
            });
        }
    });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
