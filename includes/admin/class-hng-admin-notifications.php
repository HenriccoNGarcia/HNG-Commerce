<?php
/**
 * HNG Commerce - Global Admin Notifications Handler
 * 
 * Fornece dados de notificações para:
 * - Chats aguardando atendimento
 * - Novos pedidos
 * 
 * @package HNG_Commerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Admin_Notifications {
    
    /**
     * @var HNG_Admin_Notifications
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
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
        add_action('wp_ajax_hng_get_notification_counts', [$this, 'ajax_get_notification_counts']);
        add_filter('heartbeat_received', [$this, 'heartbeat_received'], 10, 2);
        
        // Adicionar campos de atendente na edição de usuário
        add_action('show_user_profile', [$this, 'add_attendant_field']);
        add_action('edit_user_profile', [$this, 'add_attendant_field']);
        add_action('personal_options_update', [$this, 'save_attendant_field']);
        add_action('edit_user_profile_update', [$this, 'save_attendant_field']);
    }
    
    /**
     * Verificar se o usuário atual pode receber notificações
     */
    private function can_receive_notifications() {
        // Administradores sempre podem
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Verificar se é atendente
        $user_id = get_current_user_id();
        return get_user_meta($user_id, 'hng_is_attendant', true) === '1';
    }
    
    /**
     * Adicionar campo de atendente na página de usuário
     */
    public function add_attendant_field($user) {
        // Só administradores podem editar isso
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $is_attendant = get_user_meta($user->ID, 'hng_is_attendant', true);
        ?>
        <h3><?php esc_html_e('HNG Commerce - Atendimento', 'hng-commerce'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="hng_is_attendant">
                        <?php esc_html_e('Atendente do Sistema', 'hng-commerce'); ?>
                    </label>
                </th>
                <td>
                    <label for="hng_is_attendant">
                        <input type="checkbox" 
                               name="hng_is_attendant" 
                               id="hng_is_attendant" 
                               value="1" 
                               <?php checked($is_attendant, '1'); ?> />
                        <?php esc_html_e('Este usuário pode atender chats e receber notificações de pedidos', 'hng-commerce'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Atendentes receberão notificações sonoras e visuais de novos chats e pedidos.', 'hng-commerce'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Salvar campo de atendente
     */
    public function save_attendant_field($user_id) {
        // Só administradores podem salvar
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $is_attendant = isset($_POST['hng_is_attendant']) ? '1' : '0';
        update_user_meta($user_id, 'hng_is_attendant', $is_attendant);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Carregar apenas em páginas admin
        if (!is_admin()) {
            return;
        }
        
        // Verificar se usuário pode receber notificações
        $can_receive = $this->can_receive_notifications();
        error_log('[HNG Notifications] Can receive notifications: ' . ($can_receive ? 'YES' : 'NO') . ' - Hook: ' . $hook);
        
        if (!$can_receive) {
            return;
        }
        
        error_log('[HNG Notifications] Enqueuing scripts on hook: ' . $hook);
        
        // Enqueue CSS
        wp_enqueue_style(
            'hng-admin-notifications',
            HNG_COMMERCE_URL . 'assets/css/admin-notifications.css',
            [],
            HNG_COMMERCE_VERSION
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'hng-admin-notifications',
            HNG_COMMERCE_URL . 'assets/js/admin-notifications.js',
            ['jquery', 'heartbeat'],
            HNG_COMMERCE_VERSION,
            true
        );
        
        // Localizar script
        wp_localize_script('hng-admin-notifications', 'hngAdminNotifications', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hng_admin_notifications'),
            'soundUrl' => HNG_COMMERCE_URL . 'assets/sounds/notification.mp3', // Opcional
        ]);
    }
    
    /**
     * AJAX: Get notification counts
     */
    public function ajax_get_notification_counts() {
        check_ajax_referer('hng_admin_notifications', 'nonce');
        
        $counts = $this->get_notification_counts();
        
        wp_send_json_success($counts);
    }
    
    /**
     * WordPress Heartbeat: enviar contagens
     */
    public function heartbeat_received($response, $data) {
        if (isset($data['hng_check_notifications'])) {
            $response['hng_notifications'] = $this->get_notification_counts();
        }
        
        return $response;
    }
    
    /**
     * Get notification counts
     */
    private function get_notification_counts() {
        global $wpdb;
        
        $counts = [
            'waiting_chats' => 0,
            'new_orders' => 0,
        ];
        
        // Contar chats aguardando atendimento
        // Tabela: hng_live_chat_sessions (não apenas hng_chat_sessions)
        $table_chat = $wpdb->prefix . 'hng_live_chat_sessions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_chat'") === $table_chat) {
            $counts['waiting_chats'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table_chat} WHERE status = 'waiting'"
            );
        }
        
        // Contar novos pedidos (pending ou processing) das últimas 24h
        $table_orders = $wpdb->prefix . 'hng_orders';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_orders'") === $table_orders) {
            // Verificar se coluna viewed existe
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table_orders}");
            if (in_array('viewed', $columns)) {
                $counts['new_orders'] = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_orders} 
                         WHERE status IN ('pending', 'processing')
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                         AND viewed = 0"
                    )
                );
            } else {
                // Fallback se coluna viewed não existir
                $counts['new_orders'] = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_orders} 
                         WHERE status IN ('pending', 'processing')
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                    )
                );
            }
        }
        
        return $counts;
    }
    
    /**
     * Marcar pedido como visualizado
     */
    public static function mark_order_as_viewed($order_id) {
        global $wpdb;
        
        $table_orders = $wpdb->prefix . 'hng_orders';
        
        $wpdb->update(
            $table_orders,
            ['viewed' => 1],
            ['id' => $order_id],
            ['%d'],
            ['%d']
        );
    }
}

// Inicializar
HNG_Admin_Notifications::instance();
