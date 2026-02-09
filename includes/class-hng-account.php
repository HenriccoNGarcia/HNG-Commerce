<?php

/**

 * Minha Conta - Gerenciamento da área do Cliente

 *

 * @package HNG_Commerce

 * @since 1.0.0

 */



if (!defined('ABSPATH')) {

    exit;

}



// DB helper

if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {

    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';

}



class HNG_Account {

    

    /**

     * Instância única

     */

    private static $instance = null;

    

    /**

     * Endpoint atual

     */

    private $current_endpoint = 'dashboard';

    

    /**

     * Endpoints disponíveis (serão traduzidos no construtor)

     */

    private $endpoints = [];

    

    /**

     * Obter instância

     */

    public static function instance() {

        if (is_null(self::$instance)) {

            self::$instance = new self();

        }

        return self::$instance;

    }

    

    /**

     * Construtor

     */

    private function __construct() {

        add_shortcode('hng_my_account', [$this, 'render_account']);

        add_action('template_redirect', [$this, 'handle_actions']);

        // Inicializar e traduzir labels dos endpoints com strings literais

        $this->endpoints = [

            'dashboard'     => __('Dashboard', 'hng-commerce'),

            'orders'        => __('Pedidos', 'hng-commerce'),

            'view-order'    => __('Ver Pedido', 'hng-commerce'),

            'edit-account'  => __('Editar Conta', 'hng-commerce'),

            'edit-address'  => __('Endereá§os', 'hng-commerce'),

            'logout'        => __('Sair', 'hng-commerce'),

        ];

    }

    

    /**

     * Renderizar área da conta

     */

    public function render_account($atts) {

        // Verificar se usuário está logado

        if (!is_user_logged_in()) {

            return $this->render_login_form();

        }

        

        // Determinar endpoint

        $this->current_endpoint = $this->get_current_endpoint();

        

        // Processar logout

        if ($this->current_endpoint === 'logout') {

            wp_logout();

            wp_safe_redirect(home_url());

            exit;

        }

        

        ob_start();

        

        echo '<div class="hng-account">';

        echo '<div class="hng-account-navigation">';

        $this->render_navigation();

        echo '</div>';

        

        echo '<div class="hng-account-content">';

        $this->render_content();

        echo '</div>';

        echo '</div>';

        

        return ob_get_clean();

    }

    

    /**

     * Obter endpoint atual

     */

    private function get_current_endpoint() {

        global $wp;

        

        // Verificar query var personalizada

        if (!empty($wp->query_vars['account_endpoint'])) {

            return sanitize_key($wp->query_vars['account_endpoint']);

        }



        // Verificar GET parameter

        $get = wp_unslash($_GET);

        if (isset($get['endpoint'])) {

            return sanitize_key($get['endpoint']);

        }

        

        return 'dashboard';

    }

    

    /**

     * Renderizar navegação

     */

    private function render_navigation() {

        $current_user = wp_get_current_user();

        

        echo '<div class="hng-account-user">';

        echo '<div class="hng-account-avatar">';

        echo get_avatar($current_user->ID, 60);

        echo '</div>';

        echo '<div class="hng-account-user-info">';

        echo '<strong>' . esc_html($current_user->display_name) . '</strong>';

        echo '<span>' . esc_html($current_user->user_email) . '</span>';

        echo '</div>';

        echo '</div>';

        

        echo '<nav class="hng-account-menu">';

        foreach ($this->endpoints as $endpoint => $label) {

            if ($endpoint === 'view-order') continue;

            

            $active_class = $this->current_endpoint === $endpoint ? 'active' : '';

            echo '<a href="' . esc_url($this->get_endpoint_url($endpoint)) . '" class="hng-account-menu-item ' . esc_attr($active_class) . '">';

            echo esc_html($label);

            echo '</a>';

        }

        echo '</nav>';

    }

    

    /**

     * Renderizar conteúdo

     */

    private function render_content() {

        switch ($this->current_endpoint) {

            case 'dashboard':

                $this->render_dashboard();

                break;

                

            case 'orders':

                $this->render_orders();

                break;

                

            case 'view-order':

                $this->render_view_order();

                break;

                

            case 'edit-account':

                $this->render_edit_account();

                break;

                

            case 'edit-address':

                $this->render_edit_address();

                break;

                

            default:

                $this->render_dashboard();

        }

    }

    

    /**

     * Renderizar lista de pedidos

     */

    private function render_orders() {

        $orders = $this->get_user_orders();

        

        echo '<h2>' . esc_html_e('Meus Pedidos', 'hng-commerce') . '</h2>';

        

        if (!empty($orders)) {

            echo '<table class="hng-orders-table">';

            echo '<thead>';

            echo '<tr>';

            echo '<th>' . esc_html_e('Pedido', 'hng-commerce') . '</th>';

            echo '<th>' . esc_html_e('Data', 'hng-commerce') . '</th>';

            echo '<th>' . esc_html_e('Status', 'hng-commerce') . '</th>';

            echo '<th>' . esc_html_e('Total', 'hng-commerce') . '</th>';

            echo '<th>' . esc_html_e('Ações', 'hng-commerce') . '</th>';

            echo '</tr>';

            echo '</thead>';

            echo '<tbody>';

            

            foreach ($orders as $order) {

                echo '<tr>';

                echo '<td><strong>#' . esc_html($order->get_order_number()) . '</strong></td>';

                echo '<td>' . esc_html(date_i18n('d/m/Y H:i', strtotime($order->get_date_created()))) . '</td>';

                echo '<td>';

                echo '<span class="hng-order-status hng-status-' . esc_attr($order->get_status()) . '">';

                echo esc_html($this->get_status_label($order->get_status()));

                echo '</span>';

                echo '</td>';

                echo '<td>' . esc_html(hng_price($order->get_total())) . '</td>';

                echo '<td>';

                echo '<a href="' . esc_url($this->get_endpoint_url('view-order', $order->get_id())) . '" class="hng-button hng-button-small">';

                esc_html_e('Ver', 'hng-commerce');

                echo '</a>';

                echo '</td>';

                echo '</tr>';

            }

            

            echo '</tbody>';

            echo '</table>';

        } else {

            echo '<p>' . esc_html_e('Você ainda não fez nenhum pedido.', 'hng-commerce') . '</p>';

            echo '<a href="' . esc_url(hng_get_shop_url()) . '" class="hng-button">';

            esc_html_e('Começar a Comprar', 'hng-commerce');

            echo '</a>';

        }

    }

    

    /**

     * Renderizar visualização de pedido

     */

    private function render_view_order() {

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for order lookup in account area, no data modification

        $order_id = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;

        

        if (!$order_id) {

            echo '<p>' . esc_html__('Pedido não encontrado.', 'hng-commerce') . '</p>';

            return;

        }

        

        $order = new HNG_Order($order_id);

        

        // Verificar se o pedido pertence ao usuário

        if ($order->get_customer_id() != get_current_user_id()) {

            echo '<p>' . esc_html__('Você não tem permissão para visualizar este pedido.', 'hng-commerce') . '</p>';

            return;

        }

        

        /* translators: %1$s: order number */

        $order_title_format = esc_html__('Pedido #%1$s', 'hng-commerce');

        echo '<h2>' . esc_html(sprintf($order_title_format, $order->get_order_number())) . '</h2>';

        

        echo '<p>';

        echo '<strong>' . esc_html_e('Data:', 'hng-commerce') . '</strong> ';

        echo esc_html(date_i18n('d/m/Y H:i', strtotime($order->get_date_created())));

        echo '</p>';

        

        echo '<p>';

        echo '<strong>' . esc_html_e('Status:', 'hng-commerce') . '</strong> ';

        echo '<span class="hng-order-status hng-status-' . esc_attr($order->get_status()) . '">';

        echo esc_html($this->get_status_label($order->get_status()));

        echo '</span>';

        echo '</p>';

        

        echo '<h3>' . esc_html_e('Detalhes do Pedido', 'hng-commerce') . '</h3>';

        

        echo '<table class="hng-order-details-table">';

        echo '<thead><tr>';

        echo '<th>' . esc_html_e('Produto', 'hng-commerce') . '</th>';

        echo '<th>' . esc_html_e('Quantidade', 'hng-commerce') . '</th>';

        echo '<th>' . esc_html_e('Preço', 'hng-commerce') . '</th>';

        echo '<th>' . esc_html_e('Subtotal', 'hng-commerce') . '</th>';

        echo '</tr></thead>';

        echo '<tbody>';

        

        foreach ($order->get_items() as $item) {

            echo '<tr>';

            echo '<td>' . esc_html($item['product_name']) . '</td>';

            echo '<td>' . esc_html($item['quantity']) . '</td>';

            echo '<td>' . esc_html(hng_price($item['price'])) . '</td>';

            echo '<td>' . esc_html(hng_price($item['subtotal'])) . '</td>';

            echo '</tr>';

        }

        

        echo '</tbody>';

        echo '<tfoot>';

        echo '<tr>';

        echo '<th colspan="3">' . esc_html__('Subtotal', 'hng-commerce') . '</th>';

        echo '<td>' . esc_html(hng_price($order->get_subtotal())) . '</td>';

        echo '</tr>';

        

        if ($order->get_shipping_total() > 0) {

            echo '<tr>';

            echo '<th colspan="3">' . esc_html__('Frete', 'hng-commerce') . '</th>';

            echo '<td>' . esc_html(hng_price($order->get_shipping_total())) . '</td>';

            echo '</tr>';

        }

        

        if ($order->get_discount() > 0) {

            echo '<tr>';

            echo '<th colspan="3">' . esc_html__('Desconto', 'hng-commerce') . '</th>';

            echo '<td>-' . esc_html(hng_price($order->get_discount())) . '</td>';

            echo '</tr>';

        }

        

        echo '<tr class="hng-order-total-row">';

        echo '<th colspan="3">' . esc_html__('Total', 'hng-commerce') . '</th>';

        echo '<td><strong>' . esc_html(hng_price($order->get_total())) . '</strong></td>';

        echo '</tr>';

        echo '</tfoot>';

        echo '</table>';

        

        echo '<div class="hng-order-address">';

        echo '<h3>' . esc_html__('Endereço de Entrega', 'hng-commerce') . '</h3>';

        echo '<address>';

        echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . '<br>';

        echo esc_html($order->get_billing_address_1()) . ', ' . esc_html($order->get_billing_number()) . '<br>';

        if ($order->get_billing_address_2()) {

            echo esc_html($order->get_billing_address_2()) . '<br>';

        }

        echo esc_html($order->get_billing_neighborhood()) . '<br>';

        echo esc_html($order->get_billing_city()) . ' - ' . esc_html($order->get_billing_state()) . '<br>';

        echo 'CEP: ' . esc_html($order->get_billing_postcode()) . '<br>';

        echo '<br>';

        echo 'E-mail: ' . esc_html($order->get_billing_email()) . '<br>';

        echo 'Telefone: ' . esc_html($order->get_billing_phone());

        echo '</address>';

        echo '</div>';

        

        echo '<a href="' . esc_url($this->get_endpoint_url('orders')) . '" class="hng-button hng-button-secondary">';

        /* translators: Link text to go back to the user's orders list */

        esc_html_e('Voltar para Pedidos', 'hng-commerce');

        echo '</a>';

    }

    

    /**

     * Renderizar ediá¯Â¿Â½á¯Â¿Â½o de conta

     */

    private function render_edit_account() {

        $current_user = wp_get_current_user();

        

        echo '<h2>' . esc_html_e('Editar Informações da Conta', 'hng-commerce') . '</h2>';

        

        hng_print_notices();

        

        echo '<form method="post" action="" class="hng-edit-account-form">';

        wp_nonce_field('hng_edit_account', 'hng_edit_account_nonce');

        

        echo '<h3>' . esc_html_e('Informações Pessoais', 'hng-commerce') . '</h3>';

        

        echo '<p class="hng-form-row">';

        echo '<label for="first_name">' . esc_html_e('Nome *', 'hng-commerce') . '</label>';

        echo '<input type="text" id="first_name" name="first_name" value="' . esc_attr($current_user->first_name) . '" required>';

        echo '</p>';

        

        echo '<p class="hng-form-row">';

        echo '<label for="last_name">' . esc_html_e('Sobrenome *', 'hng-commerce') . '</label>';

        echo '<input type="text" id="last_name" name="last_name" value="' . esc_attr($current_user->last_name) . '" required>';

        echo '</p>';

        

        echo '<p class="hng-form-row">';

        echo '<label for="display_name">' . esc_html_e('Nome de Exibição *', 'hng-commerce') . '</label>';

        echo '<input type="text" id="display_name" name="display_name" value="' . esc_attr($current_user->display_name) . '" required>';

        echo '</p>';

        

        echo '<p class="hng-form-row">';

        echo '<label for="account_email">' . esc_html_e('E-mail *', 'hng-commerce') . '</label>';

        echo '<input type="email" id="account_email" name="account_email" value="' . esc_attr($current_user->user_email) . '" required>';

        echo '</p>';

        

        echo '<h3>' . esc_html_e('Alterar Senha', 'hng-commerce') . '</h3>';

        echo '<p>' . esc_html_e('Deixe em branco para manter a senha atual.', 'hng-commerce') . '</p>';

        

        echo '<p class="hng-form-row">';

        echo '<label for="current_password">' . esc_html_e('Senha Atual', 'hng-commerce') . '</label>';

        echo '<input type="password" id="current_password" name="current_password">';

        echo '</p>';

        

        echo '<p class="hng-form-row">';

        echo '<label for="new_password">' . esc_html_e('Nova Senha', 'hng-commerce') . '</label>';

        echo '<input type="password" id="new_password" name="new_password">';

        echo '</p>';

        

        echo '<p class="hng-form-row">';

        echo '<label for="confirm_password">' . esc_html_e('Confirmar Nova Senha', 'hng-commerce') . '</label>';

        echo '<input type="password" id="confirm_password" name="confirm_password">';

        echo '</p>';

        

        echo '<p>';

        echo '<button type="submit" name="save_account_details" class="hng-button">';

        esc_html_e('Salvar Alterações', 'hng-commerce');

        echo '</button>';

        echo '</p>';

        echo '</form>';

    }

    

    /**

     * Renderizar ediá¯Â¿Â½á¯Â¿Â½o de endereá¯Â¿Â½o

     */

    private function render_edit_address() {

        $current_user = wp_get_current_user();

        $user_meta = get_user_meta($current_user->ID);

        

        ?>

        <h2><?php esc_html_e('Endereá¯Â¿Â½o de Cobraná¯Â¿Â½a/Entrega', 'hng-commerce'); ?></h2>

        

        <?php hng_print_notices(); ?>

        

        <form method="post" action="" class="hng-edit-address-form">

            <?php wp_nonce_field('hng_edit_address', 'hng_edit_address_nonce'); ?>

            

            <div class="hng-form-row-double">

                <p class="hng-form-row">

                    <label for="billing_postcode"><?php esc_html_e('CEP *', 'hng-commerce'); ?></label>

                    <input type="text" id="billing_postcode" name="billing_postcode" 

                           value="<?php echo esc_attr($user_meta['billing_postcode'][0] ?? ''); ?>" 

                           class="hng-cep-input" required>

                </p>

                

                <p class="hng-form-row">

                    <label for="billing_number"><?php esc_html_e('Ná¯Â¿Â½mero *', 'hng-commerce'); ?></label>

                    <input type="text" id="billing_number" name="billing_number" 

                           value="<?php echo esc_attr($user_meta['billing_number'][0] ?? ''); ?>" required>

                </p>

            </div>

            

            <p class="hng-form-row">

                <label for="billing_address_1"><?php esc_html_e('Endereá¯Â¿Â½o *', 'hng-commerce'); ?></label>

                <input type="text" id="billing_address_1" name="billing_address_1" 

                       value="<?php echo esc_attr($user_meta['billing_address_1'][0] ?? ''); ?>" required>

            </p>

            

            <p class="hng-form-row">

                <label for="billing_address_2"><?php esc_html_e('Complemento', 'hng-commerce'); ?></label>

                <input type="text" id="billing_address_2" name="billing_address_2" 

                       value="<?php echo esc_attr($user_meta['billing_address_2'][0] ?? ''); ?>">

            </p>

            

            <p class="hng-form-row">

                <label for="billing_neighborhood"><?php esc_html_e('Bairro *', 'hng-commerce'); ?></label>

                <input type="text" id="billing_neighborhood" name="billing_neighborhood" 

                       value="<?php echo esc_attr($user_meta['billing_neighborhood'][0] ?? ''); ?>" required>

            </p>

            

            <div class="hng-form-row-double">

                <p class="hng-form-row">

                    <label for="billing_city"><?php esc_html_e('Cidade *', 'hng-commerce'); ?></label>

                    <input type="text" id="billing_city" name="billing_city" 

                           value="<?php echo esc_attr($user_meta['billing_city'][0] ?? ''); ?>" required>

                </p>

                

                <p class="hng-form-row">

                    <label for="billing_state"><?php esc_html_e('Estado *', 'hng-commerce'); ?></label>

                    <select id="billing_state" name="billing_state" required>

                        <option value="">Selecione...</option>

                        <?php 

                        $states = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

                        $current_state = $user_meta['billing_state'][0] ?? '';

                        foreach ($states as $state):

                        ?>

                            <option value="<?php echo esc_attr($state); ?>" <?php selected($current_state, $state); ?>>

                                <?php echo esc_html($state); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </p>

            </div>

            

            <p class="hng-form-row">

                <label for="billing_phone"><?php esc_html_e('Telefone *', 'hng-commerce'); ?></label>

                <input type="tel" id="billing_phone" name="billing_phone" 

                       value="<?php echo esc_attr($user_meta['billing_phone'][0] ?? ''); ?>" required>

            </p>

            

            <p>

                <button type="submit" name="save_address" class="hng-button">

                    <?php esc_html_e('Salvar Endereá¯Â¿Â½o', 'hng-commerce'); ?>

                </button>

            </p>

        </form>

        <?php

    }

    

    /**

     * Renderizar formulá¯Â¿Â½rio de login

     */

    private function render_login_form() {

        ob_start();

        

        ?>

        <div class="hng-login-form">

            <h2><?php esc_html_e('Login', 'hng-commerce'); ?></h2>

            

            <?php hng_print_notices(); ?>

            

            <form method="post" action="<?php echo esc_url(wp_login_url()); ?>">

                <p class="hng-form-row">

                    <label for="username"><?php esc_html_e('Usuá¯Â¿Â½rio ou E-mail', 'hng-commerce'); ?></label>

                    <input type="text" id="username" name="log" required>

                </p>

                

                <p class="hng-form-row">

                    <label for="password"><?php esc_html_e('Senha', 'hng-commerce'); ?></label>

                    <input type="password" id="password" name="pwd" required>

                </p>

                

                <p class="hng-form-row">

                    <label>

                        <input type="checkbox" name="rememberme" value="forever">

                        <?php esc_html_e('Lembrar-me', 'hng-commerce'); ?>

                    </label>

                </p>

                

                <input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink()); ?>">

                

                <p>

                    <button type="submit" class="hng-button"><?php esc_html_e('Entrar', 'hng-commerce'); ?></button>

                </p>

                

                <p>

                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Esqueceu a senha?', 'hng-commerce'); ?></a>

                </p>

            </form>

        </div>

        <?php

        

        return ob_get_clean();

    }

    

    /**

     * Lidar com aá¯Â¿Â½á¯Â¿Â½es (salvar dados, etc)

     */

    public function handle_actions() {

        // Desslash early and decide actions from $post

        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;



        // Salvar informaá¯Â¿Â½á¯Â¿Â½es da conta (verifica nonce cedo)

        if (isset($post['save_account_details']) && is_user_logged_in()) {

            $edit_account_nonce = isset($post['hng_edit_account_nonce']) ? sanitize_text_field($post['hng_edit_account_nonce']) : '';

            if (!$edit_account_nonce || !wp_verify_nonce($edit_account_nonce, 'hng_edit_account')) {

                hng_add_notice(__('Erro de seguraná§a.', 'hng-commerce'), 'error');

            } else {

                $this->save_account_details();

            }

        }



        // Salvar endereá¯Â¿Â½o (verifica nonce cedo)

        if (isset($post['save_address']) && is_user_logged_in()) {

            $edit_address_nonce = isset($post['hng_edit_address_nonce']) ? sanitize_text_field($post['hng_edit_address_nonce']) : '';

            if (!$edit_address_nonce || !wp_verify_nonce($edit_address_nonce, 'hng_edit_address')) {

                hng_add_notice(__('Erro de seguraná§a.', 'hng-commerce'), 'error');

            } else {

                $this->save_address();

            }

        }

    }

    

    /**

     * Salvar informaá¯Â¿Â½á¯Â¿Â½es da conta

     */

    private function save_account_details() {

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verificado na linha seguinte

        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;

        $nonce = isset($post['hng_edit_account_nonce']) ? sanitize_text_field($post['hng_edit_account_nonce']) : '';

        if (!wp_verify_nonce($nonce, 'hng_edit_account')) {

            hng_add_notice(__('Erro de seguraná§a.', 'hng-commerce'), 'error');

            return;

        }



        $user_id = get_current_user_id();

        $errors = [];



        // Validar campos

        $first_name = sanitize_text_field($post['first_name'] ?? '');

        $last_name = sanitize_text_field($post['last_name'] ?? '');

        $display_name = sanitize_text_field($post['display_name'] ?? '');

        $email = sanitize_email($post['account_email'] ?? '');

        

        if (empty($first_name)) {

            $errors[] = __('Nome á¯Â¿Â½ obrigatá¯Â¿Â½rio.', 'hng-commerce');

        }

        

        if (empty($email) || !is_email($email)) {

            $errors[] = __('E-mail invá¯Â¿Â½lido.', 'hng-commerce');

        }

        

        // Verificar se email já¯Â¿Â½ existe

        $email_exists = email_exists($email);

        if ($email_exists && $email_exists != $user_id) {

            $errors[] = __('Este e-mail já¯Â¿Â½ está¯Â¿Â½ em uso.', 'hng-commerce');

        }

        

        // Validar alteraá¯Â¿Â½á¯Â¿Â½o de senha

        if (!empty($post['current_password'] ?? '') || !empty($post['new_password'] ?? '') || !empty($post['confirm_password'] ?? '')) {

            $current_password = $post['current_password'] ?? '';

            $new_password = $post['new_password'] ?? '';

            $confirm_password = $post['confirm_password'] ?? '';

            

            $user = get_userdata($user_id);

            

            if (empty($current_password)) {

                $errors[] = esc_html__('Digite a senha atual.', 'hng-commerce');

            } elseif (!wp_check_password($current_password, $user->user_pass, $user_id)) {

                $errors[] = esc_html__('Senha atual incorreta.', 'hng-commerce');

            }

            

            if (empty($new_password)) {

                $errors[] = esc_html__('Digite a nova senha.', 'hng-commerce');

            }

            

            if ($new_password !== $confirm_password) {

                $errors[] = esc_html__('As senhas não conferem.', 'hng-commerce');

            }

            

            if (strlen($new_password) < 6) {

                $errors[] = esc_html__('A senha deve ter no mínimo 6 caracteres.', 'hng-commerce');

            }

        }

        

        if (!empty($errors)) {

            foreach ($errors as $error) {

                hng_add_notice($error, 'error');

            }

            return;

        }

        

        // Atualizar dados

        wp_update_user([

            'ID' => $user_id,

            'first_name' => $first_name,

            'last_name' => $last_name,

            'display_name' => $display_name,

            'user_email' => $email,

        ]);

        

        // Atualizar senha se fornecida

        if (!empty($new_password)) {

            wp_set_password($new_password, $user_id);

        }

        

        hng_add_notice(__('Informaá¯Â¿Â½á¯Â¿Â½es atualizadas com sucesso!', 'hng-commerce'), 'success');

    }

    

    /**

     * Salvar endereá¯Â¿Â½o

     */

    private function save_address() {

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verificado na linha seguinte

        $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;

        $nonce = isset($post['hng_edit_address_nonce']) ? sanitize_text_field($post['hng_edit_address_nonce']) : '';

        if (!wp_verify_nonce($nonce, 'hng_edit_address')) {

            hng_add_notice(__('Erro de seguraná§a.', 'hng-commerce'), 'error');

            return;

        }



        $user_id = get_current_user_id();



        $fields = [

            'billing_postcode',

            'billing_address_1',

            'billing_address_2',

            'billing_number',

            'billing_neighborhood',

            'billing_city',

            'billing_state',

            'billing_phone',

        ];

        

        foreach ($fields as $field) {

            $value = sanitize_text_field($post[$field] ?? '');

            update_user_meta($user_id, $field, $value);

        }

        

        hng_add_notice(__('Endereá¯Â¿Â½o atualizado com sucesso!', 'hng-commerce'), 'success');

    }

    

    /**

     * Obter pedidos do usuário (otimizado para evitar N+1 queries)

     */

    private function get_user_orders($limit = null) {

        global $wpdb;

        

        $user_id = get_current_user_id();

        $orders_table_full = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_orders') : ($wpdb->prefix . 'hng_orders');

        $orders_table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_orders') : ('`' . str_replace('`','', $orders_table_full) . '`');



        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name é sanitizado via hng_db_backtick_table()
        $query = "SELECT * FROM {$orders_table_sql} WHERE customer_id = %d ORDER BY created_at DESC";

        

        if ($limit) {

            $query .= " LIMIT " . absint($limit);

        }

        

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query usa variável $query preparada com $wpdb->prepare()
        $results = $wpdb->get_results($wpdb->prepare($query, $user_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        

        if (empty($results)) {

            return [];

        }

        

        // Extrair IDs e usar batch optimization (evita N+1 queries)

        $order_ids = wp_list_pluck($results, 'id');

        

        if (class_exists('HNG_Query_Optimizer')) {

            return HNG_Query_Optimizer::get_orders_batch($order_ids);

        }

        

        // Fallback se optimizer não disponível

        $orders = [];

        foreach ($results as $row) {

            $orders[] = new HNG_Order($row->id);

        }

        

        return $orders;

    }

    

    /**

     * Obter URL de endpoint

     */

    private function get_endpoint_url($endpoint, $value = '') {

        $url = get_permalink();

        

        if ($value) {

            $url = add_query_arg(['endpoint' => $endpoint, 'order_id' => $value], $url);

        } else {

            $url = add_query_arg(['endpoint' => $endpoint], $url);

        }

        

        return $url;

    }

    

    /**

     * Obter label de status

     */

    private function get_status_label($status) {

        $labels = [

            'pending' => __('Pendente', 'hng-commerce'),

            'processing' => __('Processando', 'hng-commerce'),

            'completed' => __('Concluá¯Â¿Â½do', 'hng-commerce'),

            'cancelled' => __('Cancelado', 'hng-commerce'),

            'refunded' => __('Reembolsado', 'hng-commerce'),

        ];

        

        return $labels[$status] ?? $status;

    }

}

