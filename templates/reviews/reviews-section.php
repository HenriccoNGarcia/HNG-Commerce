<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Seção de Avaliações do Produto
 * 
 * @var int $product_id
 */

if (!defined('ABSPATH')) {
    exit;
}

$reviews_instance = HNG_Reviews::instance();
$stats = $reviews_instance->get_product_stats($product_id);
$user_id = get_current_user_id();
?>

<div class="hng-reviews-section" id="hng-reviews">
    <!-- Header com Estatísticas -->
    <div class="hng-reviews-header">
        <h2><?php esc_html_e( 'Avaliações de Clientes', 'hng-commerce'); ?></h2>

        <?php if ( $stats['count'] > 0 ): ?>
        <div class="hng-reviews-summary">
            <div class="hng-reviews-summary-left">
                <div class="hng-average-rating">
                    <span class="rating-number"><?php echo esc_html( number_format_i18n( (float) $stats['average'], 1 ) ); ?></span>
                    <div class="rating-stars">
                        <?php echo wp_kses_post( hng_get_star_rating( $stats['average'] ) ); ?>
                    </div>
                    <?php /* translators: %1$s: number of reviews */ ?>
                    <?php $hng_reviews_count_format = _n( '%1$s avaliação', '%1$s avaliações', $stats['count'], 'hng-commerce' ); ?>
                    <span class="rating-count"><?php printf( esc_html( $hng_reviews_count_format ), esc_html( number_format_i18n( $stats['count'] ) ) ); ?></span>
                </div>
            </div>
            
            <div class="hng-reviews-summary-right">
                <div class="hng-rating-breakdown">
                    <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                        <?php 
                        $count = $stats['breakdown'][$star] ?? 0;
                        $percentage = $stats['count'] > 0 ? ($count / $stats['count']) * 100 : 0;
                        ?>
                        <div class="rating-bar-row">
                            <span class="star-label"><?php echo esc_html( intval( $star ) ); ?> <span class="star-icon">★</span></span>
                            <div class="rating-bar">
                                <div class="rating-bar-fill" style="width: <?php echo esc_attr( round( (float) $percentage, 2 ) ); ?>%"></div>
                            </div>
                            <span class="rating-count"><?php echo esc_html( intval( $count ) ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <p class="no-reviews-message"><?php esc_html_e( 'Seja o primeiro a avaliar este produto!', 'hng-commerce'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Formulário de Nova Avaliação -->
    <div class="hng-review-form-section">
        <h3><?php esc_html_e( 'Escrever uma Avaliação', 'hng-commerce'); ?></h3>
        
        <?php if ($user_id > 0 || get_option('hng_reviews_allow_guests', 'yes') === 'yes'): ?>
        <form id="hng-review-form" class="hng-review-form">
            <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
            
            <!-- Rating -->
            <div class="form-group">
                <label><?php esc_html_e( 'Sua Avaliação', 'hng-commerce'); ?> <span class="required">*</span></label>
                <div class="star-rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="star-<?php echo esc_attr( $i ); ?>" value="<?php echo esc_attr( $i ); ?>" required>
                        <?php /* translators: %1$s: number of stars */ ?>
                        <label for="star-<?php echo esc_attr( $i ); ?>" title="<?php echo esc_attr( sprintf( esc_html__( '%1$s estrelas', 'hng-commerce' ), $i ) ); ?>">★</label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Título -->
            <div class="form-group">
                <label for="review-title"><?php esc_html_e( 'Título da Avaliação', 'hng-commerce'); ?></label>
                <input type="text" id="review-title" name="title" maxlength="255" placeholder="<?php echo esc_attr__( 'Ex: Produto excelente!', 'hng-commerce'); ?>">
            </div>
            
            <?php if ($user_id === 0): ?>
            <!-- Nome (apenas para não logados) -->
            <div class="form-group">
                <label for="author-name"><?php esc_html_e( 'Seu Nome', 'hng-commerce'); ?> <span class="required">*</span></label>
                <input type="text" id="author-name" name="author_name" required>
            </div>
            
            <!-- Email (apenas para não logados) -->
            <div class="form-group">
                <label for="author-email"><?php esc_html_e( 'Seu Email', 'hng-commerce'); ?> <span class="required">*</span></label>
                <input type="email" id="author-email" name="author_email" required>
            </div>
            <?php endif; ?>
            
            <!-- Comentário -->
            <div class="form-group">
                <label for="review-comment"><?php esc_html_e( 'Seu Comentário', 'hng-commerce'); ?> <span class="required">*</span></label>
                <textarea id="review-comment" name="comment" rows="5" required minlength="10" placeholder="<?php echo esc_attr__( 'Compartilhe sua experiência com este produto...', 'hng-commerce'); ?>"></textarea>
                <small class="char-counter"><span id="char-count">0</span> / 1000 caracteres</small>
            </div>
            
            <!-- Mensagem de Resposta -->
            <div id="review-message" class="hng-alert" style="display: none;"></div>
            
            <!-- Botão Submit -->
            <button type="submit" class="hng-btn hng-btn-primary" id="submit-review-btn">
                <span class="btn-text"><?php esc_html_e( 'Publicar Avaliação', 'hng-commerce'); ?></span>
                <span class="btn-loading" style="display: none;">
                    <span class="spinner"></span> <?php esc_html_e( 'Enviando...', 'hng-commerce'); ?>
                </span>
            </button>
        </form>
        <?php else: ?>
        <p class="login-required">
            <?php /* translators: %1$s: login URL */ ?>
            <?php echo wp_kses_post( sprintf( __( 'Você precisa <a href="%1$s">fazer login</a> para avaliar este produto.', 'hng-commerce' ), esc_url( wp_login_url( get_permalink() ) ) ) ); ?>
        </p>
        <?php endif; ?>
    </div>
    
    <!-- Lista de Avaliações -->
    <div class="hng-reviews-list-section">
        <h3><?php esc_html_e( 'Todas as Avaliações', 'hng-commerce'); ?></h3>
        
        <!-- Filtros -->
        <div class="hng-reviews-filters">
            <select id="reviews-filter" class="reviews-filter">
                <option value="recent"><?php esc_html_e( 'Mais Recentes', 'hng-commerce'); ?></option>
                <option value="highest"><?php esc_html_e( 'Maior Avaliação', 'hng-commerce'); ?></option>
                <option value="lowest"><?php esc_html_e( 'Menor Avaliação', 'hng-commerce'); ?></option>
                <option value="helpful"><?php esc_html_e( 'Mais úteis', 'hng-commerce'); ?></option>
            </select>
        </div>
        
        <div id="hng-reviews-list" class="hng-reviews-list">
            <!-- Reviews serão carregadas via AJAX -->
            <div class="reviews-loading">
                <span class="spinner"></span>
                <?php esc_html_e( 'Carregando avaliações...', 'hng-commerce'); ?>
            </div>
        </div>
        
        <!-- Paginação -->
        <div id="reviews-pagination" class="reviews-pagination" style="display: none;">
            <button class="hng-btn hng-btn-secondary" id="load-more-reviews">
                <?php esc_html_e( 'Carregar Mais Avaliações', 'hng-commerce'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.hng-reviews-section {
    margin: 40px 0;
    padding: 30px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.hng-reviews-header h2 {
    margin: 0 0 20px 0;
    font-size: 28px;
    color: #333;
}

.hng-reviews-summary {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 40px;
    margin: 30px 0;
}

.hng-average-rating {
    text-align: center;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
}

.rating-number {
    display: block;
    font-size: 48px;
    font-weight: bold;
    margin-bottom: 10px;
}

.rating-stars {
    margin: 10px 0;
    font-size: 24px;
}

.rating-count {
    font-size: 14px;
    opacity: 0.9;
}

.hng-rating-breakdown {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.rating-bar-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.star-label {
    min-width: 50px;
    font-size: 14px;
    font-weight: 600;
}

.rating-bar {
    flex: 1;
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
}

.rating-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #ffd700 0%, #ffb300 100%);
    transition: width 0.3s;
}

/* Formulário */
.hng-review-form {
    max-width: 600px;
    margin: 20px 0;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.required {
    color: #e74c3c;
}

.star-rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
    font-size: 32px;
}

.star-rating-input input {
    display: none;
}

.star-rating-input label {
    cursor: pointer;
    color: #ddd;
    transition: color 0.2s;
}

.star-rating-input label:hover,
.star-rating-input label:hover ~ label,
.star-rating-input input:checked ~ label {
    color: #ffd700;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
}

.char-counter {
    display: block;
    text-align: right;
    color: #666;
    font-size: 12px;
    margin-top: 5px;
}

/* Lista de Reviews */
.hng-reviews-list {
    margin-top: 20px;
}

.review-item {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    animation: fadeIn 0.3s;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
}

.review-author {
    display: flex;
    align-items: center;
    gap: 10px;
}

.author-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.author-info {
    display: flex;
    flex-direction: column;
}

.author-name {
    font-weight: 600;
    color: #333;
}

.verified-badge {
    display: inline-block;
    background: #4caf50;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    margin-left: 8px;
}

.review-date {
    font-size: 12px;
    color: #999;
}

.review-rating {
    font-size: 16px;
    color: #ffd700;
}

.review-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.review-comment {
    color: #666;
    line-height: 1.6;
    margin-bottom: 12px;
}

.review-actions {
    display: flex;
    gap: 15px;
    font-size: 13px;
}

.review-helpful {
    color: #667eea;
    cursor: pointer;
    user-select: none;
}

.review-helpful:hover {
    text-decoration: underline;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .hng-reviews-summary {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const productId = <?php echo (int) $product_id; ?>;
    let currentPage = 1;
    
    // Contador de caracteres
    $('#review-comment').on('input', function() {
        const count = $(this).val().length;
        $('#char-count').text(count);
        
        if (count > 1000) {
            $(this).val($(this).val().substring(0, 1000));
            $('#char-count').text(1000);
        }
    });
    
    // Submeter avaliação
    $('#hng-review-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $btn = $('#submit-review-btn');
        const $message = $('#review-message');
        
        // Validar rating
        const rating = $('input[name="rating"]:checked').val();
        if (!rating) {
            showMessage('error', <?php echo wp_json_encode( __( 'Por favor, selecione uma avaliação em estrelas.', 'hng-commerce') ); ?>);
            return;
        }
        
        $btn.prop('disabled', true);
        $btn.find('.btn-text').hide();
        $btn.find('.btn-loading').show();
        $message.hide();
        
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            method: 'POST',
            data: $form.serialize() + '&action=hng_submit_review&nonce=<?php echo esc_js(wp_create_nonce('hng_submit_review')); ?>',
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    $form[0].reset();
                    
                    // Recarregar lista se aprovado
                    if (response.data.status === 'approved') {
                        loadReviews(1, true);
                    }
                } else {
                    showMessage('error', response.data.message);
                }
            },
            error: function() {
                showMessage('error', <?php echo wp_json_encode( __( 'Erro ao enviar avaliação. Tente novamente.', 'hng-commerce') ); ?>);
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.btn-text').show();
                $btn.find('.btn-loading').hide();
            }
        });
    });
    
    function showMessage(type, message) {
        const $message = $('#review-message');
        $message
            .removeClass('hng-alert-success hng-alert-error')
            .addClass('hng-alert-' + type)
            .text(message)
            .fadeIn();
    }
    
    // Carregar avaliações
    loadReviews(1);
    
    function loadReviews(page, prepend = false) {
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            method: 'POST',
            data: {
                action: 'hng_load_reviews',
                product_id: productId,
                page: page
            },
            success: function(response) {
                if (response.success) {
                    const reviews = response.data.reviews;
                    const $list = $('#hng-reviews-list');
                    
                    if (page === 1 && !prepend) {
                        $list.empty();
                    }
                    
                    if (reviews.length === 0 && page === 1) {
                        $list.html( '<p class="no-reviews">' + <?php echo wp_json_encode( __( 'Nenhuma avaliação ainda.', 'hng-commerce') ); ?> + '</p>' );
                    } else {
                        reviews.forEach(function(review) {
                            const html = buildReviewHtml(review);
                            if (prepend && page === 1) {
                                $list.prepend(html);
                            } else {
                                $list.append(html);
                            }
                        });
                    }
                    
                    // Mostrar/ocultar botão "Carregar Mais"
                    if (response.data.has_more) {
                        $('#reviews-pagination').show();
                        currentPage = page;
                    } else {
                        $('#reviews-pagination').hide();
                    }
                }
            }
        });
    }
    
    function buildReviewHtml(review) {
        const initials = review.author_name.substring(0, 2).toUpperCase();
        const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
        const verifiedBadge = review.verified_purchase ? '<span class="verified-badge">✓ Compra Verificada</span>' : '';
        
        return `
            <div class="review-item">
                <div class="review-header">
                    <div class="review-author">
                        <div class="author-avatar">${initials}</div>
                        <div class="author-info">
                            <div class="author-name">${review.author_name}${verifiedBadge}</div>
                            <div class="review-date">${review.date_relative}</div>
                        </div>
                    </div>
                    <div class="review-rating">${stars}</div>
                </div>
                ${review.title ? `<div class="review-title">${review.title}</div>` : ''}
                <div class="review-comment">${review.comment}</div>
                <div class="review-actions">
                    <span class="review-helpful" data-id="${review.id}">👍 Útil (${review.helpful_count})</span>
                </div>
            </div>
        `;
    }
    
    // Carregar mais
    $('#load-more-reviews').on('click', function() {
        loadReviews(currentPage + 1);
    });
});
</script>
