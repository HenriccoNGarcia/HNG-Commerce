<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Template: Minha Conta
 * 
 * Este template pode ser sobrescrito pelo tema criando:
 * - tema/hng-commerce/my-account.php
 * 
 * @package HNG_Commerce
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar se está logado
if (!is_user_logged_in()) {
    // Mostrar formulário de login/registro
    ?>
    <div class="hng-account-auth">
        <div class="hng-auth-container">
            <div class="hng-auth-tabs">
                <button class="hng-auth-tab active" data-tab="login"><?php esc_html_e('Entrar', 'hng-commerce'); ?></button>
                <button class="hng-auth-tab" data-tab="register"><?php esc_html_e('Cadastrar', 'hng-commerce'); ?></button>
            </div>
            
            <!-- Login Form -->
            <div class="hng-auth-panel active" id="hng-login-panel">
                <form class="hng-login-form" method="post">
                    <?php wp_nonce_field('hng_login', 'hng_login_nonce'); ?>
                    
                    <div class="hng-form-group">
                        <label for="hng_username"><?php esc_html_e('E-mail ou usuário', 'hng-commerce'); ?></label>
                        <input type="text" id="hng_username" name="username" required autocomplete="username" />
                    </div>
                    
                    <div class="hng-form-group">
                        <label for="hng_password"><?php esc_html_e('Senha', 'hng-commerce'); ?></label>
                        <input type="password" id="hng_password" name="password" required autocomplete="current-password" />
                    </div>
                    
                    <div class="hng-form-group hng-form-checkbox">
                        <label>
                            <input type="checkbox" name="rememberme" value="forever" />
                            <?php esc_html_e('Lembrar-me', 'hng-commerce'); ?>
                        </label>
                    </div>
                    
                    <button type="submit" name="hng_login" class="hng-btn hng-btn-primary hng-btn-block">
                        <?php esc_html_e('Entrar', 'hng-commerce'); ?>
                    </button>
                    
                    <p class="hng-auth-link">
                        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                            <?php esc_html_e('Esqueceu sua senha?', 'hng-commerce'); ?>
                        </a>
                    </p>
                </form>
            </div>
            
            <!-- Register Form -->
            <div class="hng-auth-panel" id="hng-register-panel">
                <form class="hng-register-form" method="post">
                    <?php wp_nonce_field('hng_register', 'hng_register_nonce'); ?>
                    
                    <div class="hng-form-group">
                        <label for="hng_reg_email"><?php esc_html_e('E-mail *', 'hng-commerce'); ?></label>
                        <input type="email" id="hng_reg_email" name="email" required autocomplete="email" />
                    </div>
                    
                    <div class="hng-form-row-2">
                        <div class="hng-form-group">
                            <label for="hng_reg_first_name"><?php esc_html_e('Nome', 'hng-commerce'); ?></label>
                            <input type="text" id="hng_reg_first_name" name="first_name" autocomplete="given-name" />
                        </div>
                        <div class="hng-form-group">
                            <label for="hng_reg_last_name"><?php esc_html_e('Sobrenome', 'hng-commerce'); ?></label>
                            <input type="text" id="hng_reg_last_name" name="last_name" autocomplete="family-name" />
                        </div>
                    </div>
                    
                    <div class="hng-form-group">
                        <label for="hng_reg_password"><?php esc_html_e('Senha *', 'hng-commerce'); ?></label>
                        <input type="password" id="hng_reg_password" name="password" required autocomplete="new-password" minlength="6" />
                    </div>
                    
                    <button type="submit" name="hng_register" class="hng-btn hng-btn-primary hng-btn-block">
                        <?php esc_html_e('Cadastrar', 'hng-commerce'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        var tabs = document.querySelectorAll('.hng-auth-tab');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var target = this.dataset.tab;
                tabs.forEach(function(t) { t.classList.remove('active'); });
                document.querySelectorAll('.hng-auth-panel').forEach(function(p) { p.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById('hng-' + target + '-panel').classList.add('active');
            });
        });
    })();
    </script>
    <?php
    return;
}

// Usuário logado - Mostrar dashboard
$user = wp_get_current_user();
$user_id = $user->ID;

// Endpoints
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for page navigation, no data modification
$current_endpoint = isset($_GET['account-page']) ? sanitize_text_field(wp_unslash($_GET['account-page'])) : 'dashboard';

// Menu items
$menu_items = [
    'dashboard' => [
        'label' => __('Painel', 'hng-commerce'),
        'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'
    ],
    'orders' => [
        'label' => __('Pedidos', 'hng-commerce'),
        'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>'
    ],
    'quotes' => [
        'label' => __('Meus Orçamentos', 'hng-commerce'),
        'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'
    ],
    'downloads' => [
        'label' => __('Downloads', 'hng-commerce'),
        'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>'
    ],
    'subscriptions' => [
        'label' => __('Assinaturas', 'hng-commerce'),
        'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/><polyline points="21 3 21 9 15 9"/></svg>'
    ],
    'appointments' => [
        'label' => __('Agendamentos', 'hng-commerce'),
        'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
    ],
    'addresses' => [
        'label' => __('Endereços', 'hng-commerce'),
        'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>'
    ],
    'edit-account' => [
        'label' => __('Dados da Conta', 'hng-commerce'),
        'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
    ],
];

// Filtrar menu items baseado nas funcionalidades ativas
$menu_items = apply_filters('hng_account_menu_items', $menu_items);
?>

<div class="hng-my-account">
    <?php hng_print_notices(); ?>
    
    <!-- Sidebar -->
    <aside class="hng-account-sidebar">
        <div class="hng-account-user">
            <?php echo get_avatar($user_id, 64); ?>
            <div class="hng-user-info">
                <span class="hng-user-name"><?php echo esc_html($user->display_name); ?></span>
                <span class="hng-user-email"><?php echo esc_html($user->user_email); ?></span>
            </div>
        </div>
        
        <nav class="hng-account-nav">
            <?php foreach ($menu_items as $endpoint => $item) : 
                $url = add_query_arg('account-page', $endpoint, hng_get_myaccount_url());
                if ($endpoint === 'dashboard') {
                    $url = hng_get_myaccount_url();
                }
            ?>
                <a href="<?php echo esc_url($url); ?>" 
                   class="hng-nav-item <?php echo esc_attr( $current_endpoint === $endpoint ? 'active' : '' ); ?>">
                    <?php echo wp_kses_post($item['icon']); ?>
                    <span><?php echo esc_html($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
            
            <a href="<?php echo esc_url(wp_logout_url(hng_get_myaccount_url())); ?>" class="hng-nav-item hng-nav-logout">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                <span><?php esc_html_e('Sair', 'hng-commerce'); ?></span>
            </a>
        </nav>
    </aside>
    
    <!-- Content -->
    <main class="hng-account-content">
        <?php
        switch ($current_endpoint) {
            case 'dashboard':
                ?>
                <div class="hng-dashboard">
                    <h2><?php
                    /* translators: %s: user name */
                    printf(esc_html__('Olá, %s!', 'hng-commerce'), esc_html($user->first_name ?: $user->display_name));
                    ?></h2>
                    <p><?php esc_html_e('No seu painel de controle você pode ver seus pedidos recentes, gerenciar seus endereços de entrega e cobrança, e editar sua senha e detalhes da conta.', 'hng-commerce'); ?></p>
                    
                    <div class="hng-dashboard-widgets">
                        <?php
                        // Pedidos recentes
                        $recent_orders = hng_get_customer_orders($user_id, 5);
                        ?>
                        <div class="hng-widget">
                            <h3><?php esc_html_e('Pedidos Recentes', 'hng-commerce'); ?></h3>
                            <?php if (!empty($recent_orders)) : ?>
                                <table class="hng-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Pedido', 'hng-commerce'); ?></th>
                                            <th><?php esc_html_e('Data', 'hng-commerce'); ?></th>
                                            <th><?php esc_html_e('Status', 'hng-commerce'); ?></th>
                                            <th><?php esc_html_e('Total', 'hng-commerce'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order) : 
                                            $order_obj = new HNG_Order($order->id);
                                        ?>
                                            <tr>
                                                <td>#<?php echo esc_html($order_obj->get_order_number()); ?></td>
                                                <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($order->created_at))); ?></td>
                                                <td><span class="hng-status hng-status-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(hng_get_order_status_label($order->status)); ?></span></td>
                                                <td><?php echo esc_html(hng_price($order->total)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p class="hng-no-data"><?php esc_html_e('Você ainda não fez nenhum pedido.', 'hng-commerce'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php
                break;
                
            case 'orders':
                $orders = hng_get_customer_orders($user_id);
                ?>
                <div class="hng-orders">
                    <h2><?php esc_html_e('Meus Pedidos', 'hng-commerce'); ?></h2>
                    
                    <?php if (!empty($orders)) : ?>
                        <table class="hng-table hng-orders-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Pedido', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Data', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Status', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Total', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Ações', 'hng-commerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order) : 
                                    $order_obj = new HNG_Order($order->id);
                                    $view_url = add_query_arg(['account-page' => 'view-order', 'order_id' => $order->id], hng_get_myaccount_url());
                                ?>
                                    <tr>
                                        <td data-label="<?php esc_attr_e('Pedido', 'hng-commerce'); ?>">#<?php echo esc_html($order_obj->get_order_number()); ?></td>
                                        <td data-label="<?php esc_attr_e('Data', 'hng-commerce'); ?>"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($order->created_at))); ?></td>
                                        <td data-label="<?php esc_attr_e('Status', 'hng-commerce'); ?>"><span class="hng-status hng-status-<?php echo esc_attr($order->status); ?>"><?php echo esc_html(hng_get_order_status_label($order->status)); ?></span></td>
                                        <td data-label="<?php esc_attr_e('Total', 'hng-commerce'); ?>"><?php echo esc_html(hng_price($order->total)); ?></td>
                                        <td data-label="<?php esc_attr_e('Ações', 'hng-commerce'); ?>">
                                            <a href="<?php echo esc_url($view_url); ?>" class="hng-btn hng-btn-small"><?php esc_html_e('Ver', 'hng-commerce'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="hng-no-data"><?php esc_html_e('Você ainda não fez nenhum pedido.', 'hng-commerce'); ?></p>
                        <a href="<?php echo esc_url(hng_get_shop_url()); ?>" class="hng-btn hng-btn-primary"><?php esc_html_e('Ir às compras', 'hng-commerce'); ?></a>
                    <?php endif; ?>
                </div>
                <?php
                break;
                
            case 'downloads':
                include HNG_COMMERCE_PATH . 'templates/my-account-downloads.php';
                break;
                
            case 'subscriptions':
                include HNG_COMMERCE_PATH . 'templates/my-account-subscriptions.php';
                break;
                
            case 'appointments':
                include HNG_COMMERCE_PATH . 'templates/my-account-appointments.php';
                break;
                
            case 'addresses':
                $billing_address = get_user_meta($user_id, 'billing_address', true) ?: [];
                $shipping_address = get_user_meta($user_id, 'shipping_address', true) ?: [];
                ?>
                <div class="hng-addresses">
                    <h2><?php esc_html_e('Endereços', 'hng-commerce'); ?></h2>
                    
                    <div class="hng-address-grid">
                        <div class="hng-address-card">
                            <h3><?php esc_html_e('Endereço de Cobrança', 'hng-commerce'); ?></h3>
                            <?php if (!empty($billing_address)) : ?>
                                <address>
                                    <?php echo esc_html($billing_address['address_1'] ?? ''); ?><?php echo !empty($billing_address['number']) ? ', ' . esc_html($billing_address['number']) : ''; ?><br>
                                    <?php echo !empty($billing_address['address_2']) ? esc_html($billing_address['address_2']) . '<br>' : ''; ?>
                                    <?php echo esc_html($billing_address['neighborhood'] ?? ''); ?><br>
                                    <?php echo esc_html($billing_address['city'] ?? ''); ?> - <?php echo esc_html($billing_address['state'] ?? ''); ?><br>
                                    CEP: <?php echo esc_html($billing_address['postcode'] ?? ''); ?>
                                </address>
                            <?php else : ?>
                                <p class="hng-no-data"><?php esc_html_e('Nenhum endereço cadastrado.', 'hng-commerce'); ?></p>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(add_query_arg(['account-page' => 'edit-address', 'type' => 'billing'], hng_get_myaccount_url())); ?>" class="hng-btn hng-btn-small">
                                <?php esc_html_e('Editar', 'hng-commerce'); ?>
                            </a>
                        </div>
                        
                        <div class="hng-address-card">
                            <h3><?php esc_html_e('Endereço de Entrega', 'hng-commerce'); ?></h3>
                            <?php if (!empty($shipping_address)) : ?>
                                <address>
                                    <?php echo esc_html($shipping_address['address_1'] ?? ''); ?><?php echo !empty($shipping_address['number']) ? ', ' . esc_html($shipping_address['number']) : ''; ?><br>
                                    <?php echo !empty($shipping_address['address_2']) ? esc_html($shipping_address['address_2']) . '<br>' : ''; ?>
                                    <?php echo esc_html($shipping_address['neighborhood'] ?? ''); ?><br>
                                    <?php echo esc_html($shipping_address['city'] ?? ''); ?> - <?php echo esc_html($shipping_address['state'] ?? ''); ?><br>
                                    CEP: <?php echo esc_html($shipping_address['postcode'] ?? ''); ?>
                                </address>
                            <?php else : ?>
                                <p class="hng-no-data"><?php esc_html_e('Nenhum endereço cadastrado.', 'hng-commerce'); ?></p>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(add_query_arg(['account-page' => 'edit-address', 'type' => 'shipping'], hng_get_myaccount_url())); ?>" class="hng-btn hng-btn-small">
                                <?php esc_html_e('Editar', 'hng-commerce'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php
                break;
                
            case 'edit-account':
                ?>
                <div class="hng-edit-account">
                    <h2><?php esc_html_e('Dados da Conta', 'hng-commerce'); ?></h2>
                    
                    <form method="post" class="hng-account-form">
                        <?php wp_nonce_field('hng_save_account', 'hng_account_nonce'); ?>
                        
                        <div class="hng-form-row-2">
                            <div class="hng-form-group">
                                <label for="first_name"><?php esc_html_e('Nome', 'hng-commerce'); ?></label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" />
                            </div>
                            <div class="hng-form-group">
                                <label for="last_name"><?php esc_html_e('Sobrenome', 'hng-commerce'); ?></label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" />
                            </div>
                        </div>
                        
                        <div class="hng-form-group">
                            <label for="display_name"><?php esc_html_e('Nome de exibição', 'hng-commerce'); ?></label>
                            <input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" />
                        </div>
                        
                        <div class="hng-form-group">
                            <label for="email"><?php esc_html_e('E-mail', 'hng-commerce'); ?></label>
                            <input type="email" id="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required />
                        </div>
                        
                        <fieldset class="hng-password-fields">
                            <legend><?php esc_html_e('Alteração de Senha', 'hng-commerce'); ?></legend>
                            
                            <div class="hng-form-group">
                                <label for="current_password"><?php esc_html_e('Senha atual (deixe em branco para não alterar)', 'hng-commerce'); ?></label>
                                <input type="password" id="current_password" name="current_password" autocomplete="current-password" />
                            </div>
                            
                            <div class="hng-form-row-2">
                                <div class="hng-form-group">
                                    <label for="new_password"><?php esc_html_e('Nova senha', 'hng-commerce'); ?></label>
                                    <input type="password" id="new_password" name="new_password" autocomplete="new-password" />
                                </div>
                                <div class="hng-form-group">
                                    <label for="confirm_password"><?php esc_html_e('Confirmar nova senha', 'hng-commerce'); ?></label>
                                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" />
                                </div>
                            </div>
                        </fieldset>
                        
                        <button type="submit" name="hng_save_account" class="hng-btn hng-btn-primary">
                            <?php esc_html_e('Salvar alterações', 'hng-commerce'); ?>
                        </button>
                    </form>
                </div>
                <?php
                break;
                
            case 'view-order':
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for order lookup, no data modification
                $order_id = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;
                if ($order_id) {
                    $order = new HNG_Order($order_id);
                    if ($order->get_id() && $order->get_customer_id() == $user_id) {
                        ?>
                        <div class="hng-view-order">
                            <h2><?php
                            /* translators: %s: order number */
                            printf(esc_html__('Pedido #%s', 'hng-commerce'), esc_html($order->get_order_number()));
                            ?></h2>
                            
                            <div class="hng-order-meta">
                                <p><?php
                                /* translators: %s: order date */
                                printf(esc_html__('Data: %s', 'hng-commerce'), esc_html(date_i18n('d/m/Y H:i', strtotime($order->get_created_at()))));
                                ?></p>
                                <p><?php
                                /* translators: %s: order status HTML element */
                                printf(esc_html__('Status: %s', 'hng-commerce'), '<span class="hng-status hng-status-' . esc_attr($order->get_status()) . '">' . esc_html(hng_get_order_status_label($order->get_status())) . '</span>');
                                ?></p>
                            </div>
                            
                            <h3><?php esc_html_e('Itens do Pedido', 'hng-commerce'); ?></h3>
                            <table class="hng-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Produto', 'hng-commerce'); ?></th>
                                        <th><?php esc_html_e('Qtd', 'hng-commerce'); ?></th>
                                        <th><?php esc_html_e('Total', 'hng-commerce'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order->get_items() as $item) : ?>
                                        <tr>
                                            <td><?php echo esc_html($item['name']); ?></td>
                                            <td><?php echo esc_html($item['quantity']); ?></td>
                                            <td><?php echo esc_html(hng_price($item['total'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2"><?php esc_html_e('Subtotal', 'hng-commerce'); ?></th>
                                        <td><?php echo esc_html(hng_price($order->get_subtotal())); ?></td>
                                    </tr>
                                    <?php if ($order->get_shipping_total() > 0) : ?>
                                        <tr>
                                            <th colspan="2"><?php esc_html_e('Frete', 'hng-commerce'); ?></th>
                                            <td><?php echo esc_html(hng_price($order->get_shipping_total())); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($order->get_discount_total() > 0) : ?>
                                        <tr>
                                            <th colspan="2"><?php esc_html_e('Desconto', 'hng-commerce'); ?></th>
                                            <td>-<?php echo esc_html(hng_price($order->get_discount_total())); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr class="hng-order-total">
                                        <th colspan="2"><?php esc_html_e('Total', 'hng-commerce'); ?></th>
                                        <td><strong><?php echo esc_html($order->get_formatted_total()); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                            
                            <a href="<?php echo esc_url(add_query_arg('account-page', 'orders', hng_get_myaccount_url())); ?>" class="hng-btn">
                                <?php esc_html_e('Voltar aos pedidos', 'hng-commerce'); ?>
                            </a>
                        </div>
                        <?php
                    }
                }
                break;
            
            case 'quotes':
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter
                $quote_order_id = isset($_GET['order']) ? absint(wp_unslash($_GET['order'])) : 0;
                
                if ($quote_order_id) {
                    // Single quote view with chat
                    $order = new HNG_Order($quote_order_id);
                    if ($order->get_id() && (int) $order->get_customer_id() === (int) $user_id) {
                        include HNG_COMMERCE_PATH . 'templates/my-account-quote-single.php';
                    }
                } else {
                    // Quotes list
                    include HNG_COMMERCE_PATH . 'templates/my-account-quotes.php';
                }
                break;
                
            default:
                // Permite que outros plugins adicionem endpoints
                do_action('hng_account_' . $current_endpoint);
                break;
        }
        ?>
    </main>
</div>

<style>
/* My Account Styles */
.hng-my-account {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.hng-account-sidebar {
    background: var(--hng-white, white);
    border-radius: var(--hng-radius-lg, 12px);
    box-shadow: var(--hng-shadow, 0 1px 3px rgba(0,0,0,0.1));
    padding: 1.5rem;
    height: fit-content;
    position: sticky;
    top: 2rem;
}

.hng-account-user {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--hng-border, #e2e8f0);
    margin-bottom: 1rem;
}

.hng-account-user img {
    border-radius: 50%;
}

.hng-user-info {
    display: flex;
    flex-direction: column;
}

.hng-user-name {
    font-weight: 600;
    color: var(--hng-text, #1e293b);
}

.hng-user-email {
    font-size: 0.75rem;
    color: var(--hng-text-light, #64748b);
}

.hng-account-nav {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.hng-nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: var(--hng-radius, 8px);
    color: var(--hng-text, #1e293b);
    text-decoration: none;
    transition: all 0.2s;
}

.hng-nav-item:hover {
    background: var(--hng-bg, #f8fafc);
}

.hng-nav-item.active {
    background: var(--hng-primary, #0066cc);
    color: white;
}

.hng-nav-logout {
    margin-top: 1rem;
    border-top: 1px solid var(--hng-border, #e2e8f0);
    padding-top: 1rem;
    color: var(--hng-danger, #dc2626);
}

.hng-account-content {
    background: var(--hng-white, white);
    border-radius: var(--hng-radius-lg, 12px);
    box-shadow: var(--hng-shadow, 0 1px 3px rgba(0,0,0,0.1));
    padding: 2rem;
}

.hng-account-content h2 {
    margin: 0 0 1.5rem;
    font-size: 1.5rem;
}

.hng-dashboard-widgets {
    display: grid;
    gap: 1.5rem;
}

.hng-widget {
    border: 1px solid var(--hng-border, #e2e8f0);
    border-radius: var(--hng-radius, 8px);
    padding: 1.5rem;
}

.hng-widget h3 {
    margin: 0 0 1rem;
    font-size: 1rem;
}

.hng-table {
    width: 100%;
    border-collapse: collapse;
}

.hng-table th,
.hng-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--hng-border, #e2e8f0);
}

.hng-table th {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--hng-text-light, #64748b);
}

.hng-status {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.hng-status-pending { background: #fef3c7; color: #92400e; }
.hng-status-processing { background: #dbeafe; color: #1e40af; }
.hng-status-completed { background: #dcfce7; color: #166534; }
.hng-status-cancelled { background: #fee2e2; color: #991b1b; }

.hng-no-data {
    color: var(--hng-text-light, #64748b);
    text-align: center;
    padding: 2rem;
}

.hng-address-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.hng-address-card {
    border: 1px solid var(--hng-border, #e2e8f0);
    border-radius: var(--hng-radius, 8px);
    padding: 1.5rem;
}

.hng-address-card h3 {
    margin: 0 0 1rem;
    font-size: 1rem;
}

.hng-address-card address {
    font-style: normal;
    line-height: 1.7;
    margin-bottom: 1rem;
}

.hng-password-fields {
    border: 1px solid var(--hng-border, #e2e8f0);
    border-radius: var(--hng-radius, 8px);
    padding: 1.5rem;
    margin: 1.5rem 0;
}

.hng-password-fields legend {
    font-weight: 600;
    padding: 0 0.5rem;
}

/* Auth Styles */
.hng-account-auth {
    max-width: 400px;
    margin: 2rem auto;
}

.hng-auth-container {
    background: var(--hng-white, white);
    border-radius: var(--hng-radius-lg, 12px);
    box-shadow: var(--hng-shadow, 0 1px 3px rgba(0,0,0,0.1));
    overflow: hidden;
}

.hng-auth-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.hng-auth-tab {
    padding: 1rem;
    border: none;
    background: var(--hng-bg, #f8fafc);
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.hng-auth-tab.active {
    background: var(--hng-white, white);
    color: var(--hng-primary, #0066cc);
}

.hng-auth-panel {
    display: none;
    padding: 2rem;
}

.hng-auth-panel.active {
    display: block;
}

.hng-form-checkbox label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.hng-auth-link {
    text-align: center;
    margin-top: 1rem;
}

.hng-form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .hng-my-account {
        grid-template-columns: 1fr;
    }
    
    .hng-account-sidebar {
        position: static;
    }
    
    .hng-form-row-2 {
        grid-template-columns: 1fr;
    }
    
    .hng-table thead {
        display: none;
    }
    
    .hng-table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid var(--hng-border, #e2e8f0);
        border-radius: var(--hng-radius, 8px);
        padding: 1rem;
    }
    
    .hng-table tbody td {
        display: flex;
        justify-content: space-between;
        border: none;
        padding: 0.5rem 0;
    }
    
    .hng-table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
    }
}
</style>
