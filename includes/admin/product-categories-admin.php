<?php
/**
 * Gerenciamento de Categorias de Produtos
 * 
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderizar página de categorias
 */
function hng_product_categories_page() {
    global $wpdb;
    
    // Verificar permissões
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'hng-commerce'));
    }
    
    // Processar ações
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below with check_admin_referer()
    if (isset($_POST['action'])) {
        check_admin_referer('hng_category_action');
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above
        if (sanitize_text_field(wp_unslash($_POST['action'])) === 'add_category') {
            hng_add_product_category();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above
        } elseif (sanitize_text_field(wp_unslash($_POST['action'])) === 'edit_category') {
            hng_edit_product_category();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above
        } elseif (sanitize_text_field(wp_unslash($_POST['action'])) === 'delete_category' && isset($_POST['term_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above
            wp_delete_term(intval(wp_unslash($_POST['term_id'])), 'hng_product_cat');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Categoria excluída com sucesso!', 'hng-commerce') . '</p></div>';
        }
    }
    
    // Obter categorias
    $categories = get_terms(array(
        'taxonomy' => 'hng_product_cat',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    // Modo de edição
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display, no data modification
    $editing = isset($_GET['edit']) && isset($_GET['term_id']);
    $edit_term = null;
    if ($editing) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation, term_id sanitized with intval()
        $edit_term = get_term(intval(wp_unslash($_GET['term_id'])), 'hng_product_cat');
    }
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-category" style="font-size: 28px; margin-right: 8px;"></span>
            <?php esc_html_e('📂 Categorias de Produtos', 'hng-commerce'); ?>
        </h1>
        
        <?php if (!$editing): ?>
            <a href="#add-category" class="page-title-action"><?php esc_html_e('Adicionar Nova', 'hng-commerce'); ?></a>
        <?php else: ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-product-categories')); ?>" class="page-title-action"><?php esc_html_e('← Voltar', 'hng-commerce'); ?></a>
        <?php endif; ?>
        
        <hr class="wp-header-end">
        
        <div class="hng-categories-wrap" style="display: flex; gap: 30px; margin-top: 20px;">
            
            <!-- Formulário de Adicionar/Editar -->
            <div class="hng-category-form" style="flex: 0 0 350px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2><?php echo $editing ? esc_html__('Editar Categoria', 'hng-commerce') : esc_html__('Adicionar Nova Categoria', 'hng-commerce'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('hng_category_action'); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr($editing ? 'edit_category' : 'add_category'); ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="term_id" value="<?php echo esc_attr($edit_term->term_id); ?>">
                    <?php endif; ?>
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="category_name"><?php esc_html_e('Nome', 'hng-commerce'); ?> <span class="required">*</span></label>
                            </th>
                        </tr>
                        <tr>
                            <td>
                                <input type="text" 
                                       id="category_name" 
                                       name="category_name" 
                                       class="regular-text" 
                                       value="<?php echo $editing ? esc_attr($edit_term->name) : ''; ?>" 
                                       required>
                                <p class="description"><?php esc_html_e('O nome é como aparece no site.', 'hng-commerce'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="category_slug"><?php esc_html_e('Slug', 'hng-commerce'); ?></label>
                            </th>
                        </tr>
                        <tr>
                            <td>
                                <input type="text" 
                                       id="category_slug" 
                                       name="category_slug" 
                                       class="regular-text" 
                                       value="<?php echo $editing ? esc_attr($edit_term->slug) : ''; ?>">
                                <p class="description"><?php esc_html_e('O slug é a versão amigável da URL. Geralmente contém apenas letras minúsculas, números e hífens.', 'hng-commerce'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="parent_category"><?php esc_html_e('Categoria Pai', 'hng-commerce'); ?></label>
                            </th>
                        </tr>
                        <tr>
                            <td>
                                <select id="parent_category" name="parent_category" class="regular-text">
                                    <option value="0"><?php esc_html_e('Nenhuma (Categoria Principal)', 'hng-commerce'); ?></option>
                                    <?php
                                    $parent_id = $editing ? $edit_term->parent : 0;
                                    foreach ($categories as $cat) {
                                        if (!$editing || $cat->term_id != $edit_term->term_id) {
                                            printf(
                                                '<option value="%d"%s>%s</option>',
                                                esc_attr($cat->term_id),
                                                selected($parent_id, $cat->term_id, false),
                                                esc_html($cat->name)
                                            );
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('Categorias podem ter hierarquia. Por exemplo, Eletrônicos pode ter a subcategoria Smartphones.', 'hng-commerce'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="category_description"><?php esc_html_e('Descrição', 'hng-commerce'); ?></label>
                            </th>
                        </tr>
                        <tr>
                            <td>
                                <textarea id="category_description" 
                                          name="category_description" 
                                          rows="5" 
                                          class="large-text"><?php echo $editing ? esc_textarea($edit_term->description) : ''; ?></textarea>
                                <p class="description"><?php esc_html_e('A descrição não é exibida por padrão, mas alguns temas podem mostrá-la.', 'hng-commerce'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">
                            <?php echo $editing ? esc_html__('Atualizar Categoria', 'hng-commerce') : esc_html__('Adicionar Nova Categoria', 'hng-commerce'); ?>
                        </button>
                    </p>
                </form>
            </div>
            
            <!-- Lista de Categorias -->
            <div class="hng-categories-list" style="flex: 1; background: #fff; padding: 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><?php esc_html_e('ID', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Nome', 'hng-commerce'); ?></th>
                            <th><?php esc_html_e('Slug', 'hng-commerce'); ?></th>
                            <th style="width: 100px; text-align: center;"><?php esc_html_e('Produtos', 'hng-commerce'); ?></th>
                            <th style="width: 150px;"><?php esc_html_e('Ações', 'hng-commerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php hng_display_category_tree($categories, 0, 0); ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <p style="color: #666; font-size: 16px; margin: 0;">
                                        <?php esc_html_e('Nenhuma categoria encontrada. Adicione sua primeira categoria!', 'hng-commerce'); ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
    
    <style>
        .hng-category-level-0 { font-weight: bold; }
        .hng-category-level-1 { padding-left: 30px; }
        .hng-category-level-2 { padding-left: 60px; }
        .hng-category-level-3 { padding-left: 90px; }
        .hng-category-actions { display: flex; gap: 10px; }
        .hng-category-actions a { text-decoration: none; }
        .required { color: #d63638; }
    </style>
    <?php
}

/**
 * Exibir árvore de categorias
 */
function hng_display_category_tree($categories, $parent_id = 0, $level = 0) {
    foreach ($categories as $category) {
        if ($category->parent == $parent_id) {
            $indent = str_repeat('—', $level);
            echo '<tr>
                <td>' . esc_html($category->term_id) . '</td>
                <td class="hng-category-level-' . esc_attr($level) . '">
                    ' . ($level > 0 ? esc_html($indent) . ' ' : '') . '
                    ' . esc_html($category->name) . '
                </td>
                <td><code>' . esc_html($category->slug) . '</code></td>
                <td style="text-align: center;">
                    <span class="count">' . esc_html($category->count) . '</span>
                </td>
                <td class="hng-category-actions">
                    <a href="' . esc_url(admin_url('admin.php?page=hng-product-categories&edit=1&term_id=' . $category->term_id)) . '" 
                       class="button button-small">
                        ' . esc_html__('Editar', 'hng-commerce') . '
                    </a>
                    <form method="post" style="display: inline;" onsubmit="return confirm(\'' . esc_attr__('Tem certeza que deseja excluir esta categoria?', 'hng-commerce') . '\');">
                        ';
            wp_nonce_field('hng_category_action');
            echo '
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="term_id" value="' . esc_attr($category->term_id) . '">
                        <button type="submit" class="button button-small button-link-delete">
                            ' . esc_html__('Excluir', 'hng-commerce') . '
                        </button>
                    </form>
                </td>
            </tr>';
            // Recursivamente exibir subcategorias
            hng_display_category_tree($categories, $category->term_id, $level + 1);
        }
    }
}

/**
 * Adicionar nova categoria
 */
function hng_add_product_category() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function hng_product_categories_page()
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_text_field()
    if (empty($_POST['category_name'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function hng_product_categories_page()
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('O nome da categoria é obrigatório.', 'hng-commerce') . '</p></div>';
        return;
    }
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_text_field()
    $name = sanitize_text_field(wp_unslash($_POST['category_name']));
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_title()
    $slug = !empty($_POST['category_slug']) ? sanitize_title(wp_unslash($_POST['category_slug'])) : '';
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Sanitized with intval()
    $parent = isset($_POST['parent_category']) ? intval($_POST['parent_category']) : 0;
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_textarea_field()
    $description = !empty($_POST['category_description']) ? sanitize_textarea_field(wp_unslash($_POST['category_description'])) : '';
    
    $args = array(
        'description' => $description,
        'parent' => $parent,
    );
    
    if (!empty($slug)) {
        $args['slug'] = $slug;
    }
    
    $result = wp_insert_term($name, 'hng_product_cat', $args);
    
    if (is_wp_error($result)) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result->get_error_message()) . '</p></div>';
    } else {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Categoria adicionada com sucesso!', 'hng-commerce') . '</p></div>';
    }
}

/**
 * Editar categoria existente
 */
function hng_edit_product_category() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function hng_product_categories_page()
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated with intval() and empty() check
    if (empty($_POST['term_id']) || empty($_POST['category_name'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function hng_product_categories_page()
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Dados inválidos.', 'hng-commerce') . '</p></div>';
        return;
    }
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Sanitized with intval()
    $term_id = intval($_POST['term_id']);
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_text_field()
    $name = sanitize_text_field(wp_unslash($_POST['category_name']));
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_title()
    $slug = !empty($_POST['category_slug']) ? sanitize_title(wp_unslash($_POST['category_slug'])) : '';
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Sanitized with intval()
    $parent = isset($_POST['parent_category']) ? intval($_POST['parent_category']) : 0;
    
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in parent function
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with sanitize_textarea_field()
    $description = !empty($_POST['category_description']) ? sanitize_textarea_field(wp_unslash($_POST['category_description'])) : '';
    
    $args = array(
        'name' => $name,
        'description' => $description,
        'parent' => $parent,
    );
    
    if (!empty($slug)) {
        $args['slug'] = $slug;
    }
    
    $result = wp_update_term($term_id, 'hng_product_cat', $args);
    
    if (is_wp_error($result)) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result->get_error_message()) . '</p></div>';
    } else {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Categoria atualizada com sucesso!', 'hng-commerce') . '</p></div>';
        echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=hng-product-categories')) . '";</script>';
    }
}
