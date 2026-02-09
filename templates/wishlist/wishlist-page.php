<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Página de Lista de Desejos
 */

if (!defined('ABSPATH')) {
    exit;
}

$wishlist = HNG_Wishlist::instance();
$count = $wishlist->get_wishlist_count();
$user_id = get_current_user_id();
?>

<div class="hng-wishlist-page">
    <div class="wishlist-header">
        <h1><?php esc_html_e( 'Minha Lista de Desejos', 'hng-commerce'); ?></h1>
        /* translators: %s: placeholder */
        <span class="wishlist-count"><?php /* translators: %s: number of wishlist items */ printf( esc_html( _n( '%s produto', '%s produtos', $count, 'hng-commerce') ), esc_html( number_format_i18n( $count ) ) ); ?></span>
    </div>
    
    <?php if ($user_id > 0): ?>
    <div class="wishlist-actions">
        <button id="share-wishlist-btn" class="hng-btn hng-btn-secondary">
            <?php esc_html_e( 'Compartilhar Lista', 'hng-commerce'); ?>
        </button>
        
        <div id="share-link-modal" class="hng-modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h3><?php esc_html_e( 'Compartilhar Lista de Desejos', 'hng-commerce'); ?></h3>
                <p><?php esc_html_e( 'Copie o link abaixo para compartilhar sua lista:', 'hng-commerce'); ?></p>
                <div class="share-link-container">
                    <input type="text" id="share-link-input" readonly>
                    <button id="copy-link-btn" class="hng-btn hng-btn-primary">
                        <?php esc_html_e( 'Copiar', 'hng-commerce'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div id="wishlist-items" class="wishlist-items">
            <div class="wishlist-loading">
            <span class="spinner"></span>
            <?php esc_html_e( 'Carregando...', 'hng-commerce'); ?>
        </div>
    </div>
    
    <div id="wishlist-empty" class="wishlist-empty" style="display: none;">
        <div class="empty-icon">♡</div>
        <h2><?php esc_html_e( 'Sua lista de desejos está vazia', 'hng-commerce'); ?></h2>
        <p><?php esc_html_e( 'Adicione produtos que você gosta para salvar para mais tarde.', 'hng-commerce'); ?></p>
        <a href="<?php echo esc_url( get_post_type_archive_link( 'hng_product' ) ); ?>" class="hng-btn hng-btn-primary">
            <?php esc_html_e( 'Explorar Produtos', 'hng-commerce'); ?>
        </a>
    </div>
</div>

<style>
.hng-wishlist-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

.wishlist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.wishlist-header h1 {
    margin: 0;
    font-size: 32px;
    color: #333;
}

.wishlist-count {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
}

.wishlist-actions {
    margin-bottom: 20px;
}

.wishlist-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.wishlist-item {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    position: relative;
}

.wishlist-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.wishlist-item-image {
    position: relative;
    padding-top: 100%;
    background: #f5f5f5;
}

.wishlist-item-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.remove-wishlist-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: white;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    z-index: 10;
    transition: transform 0.2s;
}

.remove-wishlist-btn:hover {
    transform: scale(1.1);
}

.wishlist-item-info {
    padding: 15px;
}

.wishlist-item-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.wishlist-item-price {
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 12px;
}

.wishlist-item-actions {
    display: flex;
    gap: 10px;
}

.wishlist-item-actions .hng-btn {
    flex: 1;
    padding: 10px;
    font-size: 14px;
}

.out-of-stock-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #e74c3c;
    color: white;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.wishlist-added-date {
    font-size: 12px;
    color: #999;
    margin-top: 8px;
}

.wishlist-empty {
    text-align: center;
    padding: 80px 20px;
}

.empty-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.wishlist-empty h2 {
    color: #666;
    margin-bottom: 10px;
}

.wishlist-empty p {
    color: #999;
    margin-bottom: 30px;
}

.hng-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    position: relative;
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    cursor: pointer;
    color: #999;
}

.modal-close:hover {
    color: #333;
}

.share-link-container {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.share-link-container input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

@media (max-width: 768px) {
    .wishlist-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .wishlist-items {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Carregar wishlist
    loadWishlist();
    
    function loadWishlist() {
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            method: 'POST',
            data: {
                action: 'hng_get_wishlist',
                nonce: '<?php echo esc_attr(wp_create_nonce('hng_wishlist')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const products = response.data.products;
                    const $container = $('#wishlist-items');
                    
                    $container.find('.wishlist-loading').remove();
                    
                    if (products.length === 0) {
                        $container.hide();
                        $('#wishlist-empty').show();
                    } else {
                        $container.empty().show();
                        $('#wishlist-empty').hide();
                        
                        products.forEach(function(product) {
                            const html = buildProductHtml(product);
                            $container.append(html);
                        });
                    }
                    
                    updateCount(products.length);
                }
            }
        });
    }
    
    function buildProductHtml(product) {
        const stockBadge = !product.in_stock ? '<span class="out-of-stock-badge">Esgotado</span>' : '';
        const addToCartBtn = product.in_stock 
            ? `<button class="hng-btn hng-btn-primary add-to-cart-btn" data-id="${product.id}">Adicionar ao Carrinho</button>`
            : `<button class="hng-btn hng-btn-secondary" disabled>Indisponível</button>`;
        
        return `
            <div class="wishlist-item" data-product-id="${product.id}">
                <div class="wishlist-item-image">
                    ${stockBadge}
                    <button class="remove-wishlist-btn" data-id="${product.id}" title="Remover">♡</button>
                    <img src="${product.image || '<?php echo esc_url( HNG_COMMERCE_URL ); ?>assets/images/placeholder.svg'}" alt="${product.name}">
                </div>
                <div class="wishlist-item-info">
                    <h3 class="wishlist-item-name">
                        <a href="${product.url}">${product.name}</a>
                    </h3>
                    <div class="wishlist-item-price">${product.price_formatted}</div>
                    <div class="wishlist-item-actions">
                        ${addToCartBtn}
                        <a href="${product.url}" class="hng-btn hng-btn-secondary">Ver Detalhes</a>
                    </div>
                    <div class="wishlist-added-date">Adicionado em ${product.added_at}</div>
                </div>
            </div>
        `;
    }
    
    // Remover da wishlist
    $(document).on('click', '.remove-wishlist-btn', function() {
        const $btn = $(this);
        const productId = $btn.data('id');
        const $item = $btn.closest('.wishlist-item');
        
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            method: 'POST',
            data: {
                action: 'hng_remove_from_wishlist',
                product_id: productId,
                nonce: '<?php echo esc_attr(wp_create_nonce('hng_wishlist')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        
                        if ($('.wishlist-item').length === 0) {
                            $('#wishlist-items').hide();
                            $('#wishlist-empty').fadeIn();
                        }
                    });
                    
                    updateCount(response.data.count);
                }
            }
        });
    });
    
    // Adicionar ao carrinho
    $(document).on('click', '.add-to-cart-btn', function() {
        const $btn = $(this);
        const productId = $btn.data('id');
        
        $btn.prop('disabled', true).text('Adicionando...');
        
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            method: 'POST',
            data: {
                action: 'hng_add_to_cart',
                product_id: productId,
                quantity: 1,
                nonce: '<?php echo esc_attr(wp_create_nonce('hng_add_to_cart')); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $btn.text('? Adicionado!');
                    setTimeout(function() {
                        $btn.prop('disabled', false).text('Adicionar ao Carrinho');
                    }, 2000);
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false).text('Adicionar ao Carrinho');
                }
            }
        });
    });
    
    // Compartilhar wishlist
    $('#share-wishlist-btn').on('click', function() {
        // TODO: Implementar geraï¿½ï¿½o de link
        $('#share-link-modal').fadeIn();
        $('#share-link-input').val(window.location.href + '?share=token123');
    });
    
    $('.modal-close').on('click', function() {
        $('#share-link-modal').fadeOut();
    });
    
    $('#copy-link-btn').on('click', function() {
        const input = document.getElementById('share-link-input');
        input.select();
        document.execCommand('copy');
        $(this).text('? Copiado!');
        setTimeout(function() {
            $('#copy-link-btn').text('Copiar');
        }, 2000);
    });
    
    function updateCount(count) {
        $('.wishlist-count').text(count + (count === 1 ? ' produto' : ' produtos'));
    }
});
</script>
