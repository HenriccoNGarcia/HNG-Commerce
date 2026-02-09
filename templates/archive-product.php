<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Archive de Produtos
 * 
 * Este template pode ser sobrescrito pelo tema criando:
 * - tema/archive-hng_product.php
 * - tema/hng-commerce/archive-product.php
 * 
 * @package HNG_Commerce
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Verificar se é taxonomia
$is_taxonomy = is_tax(['hng_product_cat', 'hng_product_tag']);
$term = $is_taxonomy ? get_queried_object() : null;

// Título e descrição
$page_title = $is_taxonomy ? $term->name : __('Produtos', 'hng-commerce');
$page_description = $is_taxonomy ? $term->description : '';

// Parâmetros de ordenação
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for sorting, no data modification
$orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'date';
$order_options = [
    'date' => __('Mais recentes', 'hng-commerce'),
    'price_asc' => __('Menor preço', 'hng-commerce'),
    'price_desc' => __('Maior preço', 'hng-commerce'),
    'title' => __('Nome A-Z', 'hng-commerce'),
    'popularity' => __('Mais vendidos', 'hng-commerce'),
];
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('hng-archive-template'); ?>>
<?php wp_body_open(); ?>

<main class="hng-archive-page">
    <div class="hng-container">
        
        <!-- Header -->
        <header class="hng-archive-header">
            <h1 class="hng-archive-title"><?php echo esc_html($page_title); ?></h1>
            <?php if ($page_description) : ?>
                <p class="hng-archive-description"><?php echo esc_html($page_description); ?></p>
            <?php endif; ?>
        </header>
        
        <!-- Filtros -->
        <div class="hng-filters">
            <div class="hng-filter-group">
                <label for="orderby"><?php esc_html_e('Ordenar por:', 'hng-commerce'); ?></label>
                <select id="orderby" name="orderby" onchange="window.location.href=this.value">
                    <?php foreach ($order_options as $key => $label) : 
                        $url = add_query_arg('orderby', $key);
                    ?>
                        <option value="<?php echo esc_url($url); ?>" <?php selected($orderby, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php 
            // Categorias
            $categories = get_terms([
                'taxonomy' => 'hng_product_cat',
                'hide_empty' => true,
            ]);
            
            if ($categories && !is_wp_error($categories)) : ?>
                <div class="hng-filter-group">
                    <label for="category"><?php esc_html_e('Categoria:', 'hng-commerce'); ?></label>
                    <select id="category" name="category" onchange="if(this.value) window.location.href=this.value">
                        <option value="<?php echo esc_url(get_post_type_archive_link('hng_product')); ?>"><?php esc_html_e('Todas', 'hng-commerce'); ?></option>
                        <?php foreach ($categories as $cat) : ?>
                            <option value="<?php echo esc_url(get_term_link($cat)); ?>" <?php selected($is_taxonomy && $term && $term->term_id === $cat->term_id); ?>>
                                <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="hng-filter-results">
                <?php 
                global $wp_query;
                printf(
                    /* translators: %d: number of products found */
                    esc_html__('%d produto(s) encontrado(s)', 'hng-commerce'),
                    absint($wp_query->found_posts)
                );
                ?>
            </div>
        </div>
        
        <!-- Produtos -->
        <?php if (have_posts()) : ?>
            <div class="hng-products-grid hng-columns-4">
                <?php while (have_posts()) : the_post(); 
                    $product_id = get_the_ID();
                    $price = floatval(get_post_meta($product_id, '_price', true));
                    $regular_price = floatval(get_post_meta($product_id, '_regular_price', true));
                    $sale_price = floatval(get_post_meta($product_id, '_sale_price', true));
                    $stock_status = get_post_meta($product_id, '_stock_status', true) ?: 'instock';
                    $product_type = get_post_meta($product_id, '_hng_product_type', true) ?: 'physical';
                    
                    $has_sale = $sale_price > 0 && $regular_price > 0 && $sale_price < $regular_price;
                    $display_price = $has_sale ? $sale_price : $price;
                    $is_in_stock = $stock_status === 'instock';
                ?>
                    <article class="hng-product-card <?php echo !$is_in_stock ? 'hng-out-of-stock' : ''; ?>">
                        <a href="<?php the_permalink(); ?>" class="hng-card-link">
                            <?php if ($has_sale) : ?>
                                <span class="hng-card-badge hng-badge-sale"><?php esc_html_e('Oferta', 'hng-commerce'); ?></span>
                            <?php endif; ?>
                            
                            <?php if (!$is_in_stock) : ?>
                                <span class="hng-card-badge hng-badge-out"><?php esc_html_e('Esgotado', 'hng-commerce'); ?></span>
                            <?php endif; ?>
                            
                            <?php if ($product_type === 'digital') : ?>
                                <span class="hng-card-badge hng-badge-digital"><?php esc_html_e('Digital', 'hng-commerce'); ?></span>
                            <?php endif; ?>
                            
                            <div class="hng-card-image">
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail('medium'); ?>
                                <?php else : ?>
                                    <div class="hng-placeholder">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="hng-card-content">
                                <h3 class="hng-card-title"><?php the_title(); ?></h3>
                                
                                <div class="hng-card-price">
                                    <?php if ($has_sale) : ?>
                                        <span class="hng-old-price">R$ <?php echo esc_html(number_format($regular_price, 2, ',', '.')); ?></span>
                                    <?php endif; ?>
                                    <span class="hng-current-price">R$ <?php echo esc_html(number_format($display_price, 2, ',', '.')); ?></span>
                                </div>
                            </div>
                        </a>
                        
                        <div class="hng-card-actions">
                            <?php if ($is_in_stock && $display_price > 0) : ?>
                                <button class="hng-btn hng-btn-small hng-quick-add" 
                                        data-product-id="<?php echo esc_attr($product_id); ?>"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('hng_add_to_cart')); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                    </svg>
                                    <span><?php esc_html_e('Adicionar', 'hng-commerce'); ?></span>
                                </button>
                            <?php endif; ?>

                            <a class="hng-btn hng-btn-ghost hng-btn-small hng-view-details" href="<?php the_permalink(); ?>">
                                <?php esc_html_e('Ver detalhes', 'hng-commerce'); ?>
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
            
            <!-- Paginação -->
            <?php 
            $pagination = paginate_links([
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'type' => 'array',
            ]);
            
            if ($pagination) : ?>
                <nav class="hng-pagination" aria-label="<?php esc_attr_e('Paginação', 'hng-commerce'); ?>">
                    <?php foreach ($pagination as $link) : ?>
                        <?php echo wp_kses_post($link); ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            
        <?php else : ?>
            <div class="hng-no-products">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <p><?php esc_html_e('Nenhum produto encontrado.', 'hng-commerce'); ?></p>
                <a href="<?php echo esc_url(get_post_type_archive_link('hng_product')); ?>" class="hng-btn hng-btn-primary">
                    <?php esc_html_e('Ver todos os produtos', 'hng-commerce'); ?>
                </a>
            </div>
        <?php endif; ?>
        
    </div>
</main>

<script>
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        // Quick Add to Cart
        var quickAddBtns = document.querySelectorAll('.hng-quick-add');
        
        quickAddBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var productId = this.dataset.productId;
                var nonce = this.dataset.nonce;
                var button = this;
                var originalHtml = button.innerHTML;
                
                button.disabled = true;
                button.innerHTML = '<span class="hng-spinner"></span>';
                
                var formData = new FormData();
                formData.append('action', 'hng_add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', 1);
                formData.append('hng_cart_nonce', nonce);
                
                fetch(typeof hng_ajax !== 'undefined' ? hng_ajax.ajax_url : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><span><?php esc_html_e('Adicionado!', 'hng-commerce'); ?></span>';
                        button.classList.add('hng-added');
                        
                        setTimeout(function() {
                            button.innerHTML = originalHtml;
                            button.classList.remove('hng-added');
                            button.disabled = false;
                        }, 2000);
                    } else {
                        button.innerHTML = originalHtml;
                        button.disabled = false;
                        alert(data.data && data.data.message ? data.data.message : '<?php esc_html_e('Erro ao adicionar', 'hng-commerce'); ?>');
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                });
            });
        });
    });
})();
</script>

<style>
.hng-filter-results {
    margin-left: auto;
    color: var(--hng-text-light, #64748b);
    font-size: 0.875rem;
}

.hng-card-actions {
    padding: 0 1rem 1rem;
}

.hng-btn-small {
    width: 100%;
    padding: 0.625rem 1rem;
    font-size: 0.875rem;
}

.hng-quick-add {
    background: var(--hng-primary, #0066cc);
    color: white;
    border: none;
    border-radius: var(--hng-radius, 8px);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.hng-quick-add:hover {
    background: var(--hng-primary-hover, #0052a3);
}

.hng-quick-add.hng-added {
    background: var(--hng-success, #16a34a);
}

.hng-product-card.hng-out-of-stock .hng-card-image {
    opacity: 0.6;
}

.hng-no-products {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--hng-white, white);
    border-radius: var(--hng-radius-lg, 12px);
    box-shadow: var(--hng-shadow, 0 1px 3px rgba(0,0,0,0.1));
}

.hng-no-products svg {
    color: var(--hng-text-light, #64748b);
    margin-bottom: 1rem;
}

.hng-no-products p {
    margin-bottom: 1.5rem;
    color: var(--hng-text-light, #64748b);
}

.hng-badge-out {
    background: var(--hng-secondary, #64748b) !important;
    top: auto !important;
    bottom: 0.75rem;
}

.hng-badge-digital {
    background: var(--hng-primary, #0066cc) !important;
    left: auto !important;
    right: 0.75rem;
}
</style>

<?php wp_footer(); ?>
</body>
</html>
