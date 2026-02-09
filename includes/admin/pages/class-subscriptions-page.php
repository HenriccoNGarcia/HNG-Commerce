<?php
/**
 * Subscriptions Page - Gerenciamento de Assinaturas
 * 
 * Página dedicada para visualizar e gerenciar assinaturas recorrentes do sistema.
 * 
 * @package HNG_Commerce
 * @since 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Subscriptions_Page {
    
    /**
     * Render subscriptions page
     */
    public static function render() {
        // Ensure subscription class is loaded
        if (!class_exists('HNG_Subscription')) {
            if (file_exists(HNG_COMMERCE_PATH . 'includes/class-hng-subscription.php')) {
                require_once HNG_COMMERCE_PATH . 'includes/class-hng-subscription.php';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Classe HNG_Subscription não encontrada.', 'hng-commerce') . '</p></div>';
                return;
            }
        }
        
        // Handle actions
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Action parameter verified below with nonce
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
        
        if ($action === 'view') {
            self::render_subscription_details();
            return;
        }
        
        if ($action === 'cancel' && isset($_GET['subscription_id']) && isset($_GET['_wpnonce'])) {
            self::handle_cancel_subscription();
        }
        
        if ($action === 'resume' && isset($_GET['subscription_id']) && isset($_GET['_wpnonce'])) {
            self::handle_resume_subscription();
        }
        
        // Render list view
        self::render_list_view();
    }
    
    /**
     * Render list view
     */
    private static function render_list_view() {
        global $wpdb;
        
        // Get filter status
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter for status filter
        $filter_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'all';
        
        // Get subscriptions
        $subscriptions_table = hng_db_full_table_name('hng_subscriptions');
        $subscriptions_table_sql = '`' . str_replace('`', '', $subscriptions_table) . '`';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Subscriptions list query with sanitized table name
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name sanitized via hng_db_full_table_name and backtick escaping
        if ($filter_status !== 'all') {
            $subscriptions = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$subscriptions_table_sql} WHERE status = %s ORDER BY created_at DESC", $filter_status)
            );
        } else {
            $subscriptions = $wpdb->get_results(
                "SELECT * FROM {$subscriptions_table_sql} ORDER BY created_at DESC"
            );
        }
        
        // Get counts for tabs
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Status count query
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name sanitized
        $counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$subscriptions_table_sql} GROUP BY status",
            OBJECT_K
        );
        
        ?>
        <div class="wrap hng-admin-wrap">
            <h1 class="hng-page-title">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Assinaturas', 'hng-commerce'); ?>
            </h1>
            
            <div class="hng-card" style="margin-top: 20px;">
                <div class="hng-card-content">
                    <!-- Status Tabs -->
                    <ul class="subsubsub">
                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-subscriptions')); ?>" class="<?php echo esc_attr( $filter_status === 'all' ? 'current' : '' ); ?>">
                                <?php esc_html_e('Todas', 'hng-commerce'); ?>
                                <span class="count">(<?php echo esc_html(array_sum(array_column($counts, 'count'))); ?>)</span>
                            </a> |
                        </li>
                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-subscriptions&status=active')); ?>" class="<?php echo esc_attr( $filter_status === 'active' ? 'current' : '' ); ?>">
                                <?php esc_html_e('Ativas', 'hng-commerce'); ?>
                                <span class="count">(<?php echo esc_html($counts['active']->count ?? 0); ?>)</span>
                            </a> |
                        </li>
                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-subscriptions&status=suspended')); ?>" class="<?php echo esc_attr( $filter_status === 'suspended' ? 'current' : '' ); ?>">
                                <?php esc_html_e('Suspensas', 'hng-commerce'); ?>
                                <span class="count">(<?php echo esc_html($counts['suspended']->count ?? 0); ?>)</span>
                            </a> |
                        </li>
                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-subscriptions&status=cancelled')); ?>" class="<?php echo esc_attr( $filter_status === 'cancelled' ? 'current' : '' ); ?>">
                                <?php esc_html_e('Canceladas', 'hng-commerce'); ?>
                                <span class="count">(<?php echo esc_html($counts['cancelled']->count ?? 0); ?>)</span>
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Subscriptions Table -->
                    <?php if (!empty($subscriptions)): ?>
                        <table class="widefat striped" style="margin-top: 20px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('ID', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Cliente', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Produto', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Valor', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Período', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Status', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Próximo Ciclo', 'hng-commerce'); ?></th>
                                    <th><?php esc_html_e('Ações', 'hng-commerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <tr>
                                        <td><?php echo esc_html($subscription->id); ?></td>
                                        <td>
                                            <?php echo esc_html($subscription->customer_name ?? __('Nome não disponível', 'hng-commerce')); ?><br>
                                            <small><?php echo esc_html($subscription->customer_email ?? __('Email não disponível', 'hng-commerce')); ?></small>
                                        </td>
                                        <td><?php echo esc_html($subscription->product_name ?? __('Produto removido', 'hng-commerce')); ?></td>
                                        <td><?php echo esc_html('R$ ' . number_format($subscription->amount, 2, ',', '.')); ?></td>
                                        <td>
                                            <?php
                                            $period_labels = array(
                                                'monthly' => __('Mensal', 'hng-commerce'),
                                                'quarterly' => __('Trimestral', 'hng-commerce'),
                                                'semiannual' => __('Semestral', 'hng-commerce'),
                                                'annual' => __('Anual', 'hng-commerce'),
                                            );
                                            $billing_period = $subscription->billing_period ?? '';
                                            echo esc_html($period_labels[$billing_period] ?? ($billing_period ? ucfirst($billing_period) : __('Não definido', 'hng-commerce')));
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_labels = array(
                                                'active' => __('Ativa', 'hng-commerce'),
                                                'suspended' => __('Suspensa', 'hng-commerce'),
                                                'cancelled' => __('Cancelada', 'hng-commerce'),
                                                'expired' => __('Expirada', 'hng-commerce'),
                                            );
                                            $status_colors = array(
                                                'active' => 'green',
                                                'suspended' => 'orange',
                                                'cancelled' => 'red',
                                                'expired' => 'gray',
                                            );
                                            $status_text = $status_labels[$subscription->status] ?? ucfirst($subscription->status);
                                            $status_color = $status_colors[$subscription->status] ?? 'gray';
                                            echo '<span class="hng-badge hng-badge-' . esc_attr($status_color) . '">' . esc_html($status_text) . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (!empty($subscription->next_billing_date)) {
                                                echo esc_html(mysql2date(get_option('date_format'), $subscription->next_billing_date));
                                            } else {
                                                echo '<span class="description">' . esc_html__('N/A', 'hng-commerce') . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=hng-subscriptions&action=view&subscription_id=' . $subscription->id)); ?>" class="button button-small">
                                                <?php esc_html_e('Ver', 'hng-commerce'); ?>
                                            </a>
                                            <?php if ($subscription->status === 'active'): ?>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hng-subscriptions&action=cancel&subscription_id=' . $subscription->id), 'cancel_subscription_' . $subscription->id)); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Tem certeza que deseja cancelar esta assinatura?', 'hng-commerce')); ?>');">
                                                    <?php esc_html_e('Cancelar', 'hng-commerce'); ?>
                                                </a>
                                            <?php elseif ($subscription->status === 'suspended'): ?>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=hng-subscriptions&action=resume&subscription_id=' . $subscription->id), 'resume_subscription_' . $subscription->id)); ?>" class="button button-small">
                                                    <?php esc_html_e('Reativar', 'hng-commerce'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="margin-top: 20px;"><?php esc_html_e('Nenhuma assinatura encontrada.', 'hng-commerce'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render subscription details
     */
    private static function render_subscription_details() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter
        if (!isset($_GET['subscription_id'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('ID da assinatura não fornecido.', 'hng-commerce') . '</p></div>';
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter
        $subscription_id = absint(wp_unslash($_GET['subscription_id']));
        
        if ($subscription_id <= 0) {
            echo '<div class="notice notice-error"><p>' . esc_html__('ID da assinatura inválido.', 'hng-commerce') . '</p></div>';
            return;
        }
        
        global $wpdb;
        
        // Get subscription
        $subscriptions_table = hng_db_full_table_name('hng_subscriptions');
        $subscriptions_table_sql = '`' . str_replace('`', '', $subscriptions_table) . '`';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Subscription details query
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name sanitized
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$subscriptions_table_sql} WHERE id = %d",
            $subscription_id
        ));
        
        if (!$subscription) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Assinatura não encontrada.', 'hng-commerce') . '</p></div>';
            return;
        }
        
        ?>
        <div class="wrap hng-admin-wrap">
            <h1 class="hng-page-title">
                <span class="dashicons dashicons-update"></span>
                <?php
                /* translators: %d: Subscription ID */
                echo esc_html(sprintf(__('Assinatura #%d', 'hng-commerce'), $subscription_id));
                ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hng-subscriptions')); ?>" class="page-title-action">
                    <?php esc_html_e('← Voltar', 'hng-commerce'); ?>
                </a>
            </h1>
            
            <div class="hng-grid hng-grid-2" style="margin-top: 20px;">
                <!-- Subscription Details -->
                <div class="hng-card">
                    <div class="hng-card-header">
                        <h2 class="hng-card-title"><?php esc_html_e('Detalhes da Assinatura', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="hng-card-content">
                        <table class="widefat striped">
                            <tbody>
                                <tr>
                                    <th><?php esc_html_e('Status:', 'hng-commerce'); ?></th>
                                    <td>
                                        <?php
                                        $status_labels = array(
                                            'active' => __('Ativa', 'hng-commerce'),
                                            'suspended' => __('Suspensa', 'hng-commerce'),
                                            'cancelled' => __('Cancelada', 'hng-commerce'),
                                            'expired' => __('Expirada', 'hng-commerce'),
                                        );
                                        echo '<span class="hng-badge hng-badge-' . esc_attr($subscription->status) . '">' . esc_html($status_labels[$subscription->status] ?? ucfirst($subscription->status)) . '</span>';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Valor:', 'hng-commerce'); ?></th>
                                    <td><strong><?php echo esc_html('R$ ' . number_format($subscription->amount, 2, ',', '.')); ?></strong></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Período de Cobrança:', 'hng-commerce'); ?></th>
                                    <td>
                                        <?php
                                        $period_labels = array(
                                            'monthly' => __('Mensal', 'hng-commerce'),
                                            'quarterly' => __('Trimestral', 'hng-commerce'),
                                            'semiannual' => __('Semestral', 'hng-commerce'),
                                            'annual' => __('Anual', 'hng-commerce'),
                                        );
                                        echo esc_html($period_labels[$subscription->billing_period] ?? ucfirst($subscription->billing_period));
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Data de Início:', 'hng-commerce'); ?></th>
                                    <td><?php echo esc_html(mysql2date(get_option('date_format'), $subscription->created_at)); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Próximo Ciclo:', 'hng-commerce'); ?></th>
                                    <td>
                                        <?php
                                        if (!empty($subscription->next_billing_date)) {
                                            echo esc_html(mysql2date(get_option('date_format'), $subscription->next_billing_date));
                                        } else {
                                            echo '<span class="description">' . esc_html__('N/A', 'hng-commerce') . '</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php if (!empty($subscription->gateway)): ?>
                                <tr>
                                    <th><?php esc_html_e('Gateway:', 'hng-commerce'); ?></th>
                                    <td><?php echo esc_html(ucfirst($subscription->gateway)); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Customer Info -->
                <div class="hng-card">
                    <div class="hng-card-header">
                        <h2 class="hng-card-title"><?php esc_html_e('Informações do Cliente', 'hng-commerce'); ?></h2>
                    </div>
                    <div class="hng-card-content">
                        <p>
                            <strong><?php echo esc_html($subscription->customer_name ?? __('Nome não disponível', 'hng-commerce')); ?></strong><br>
                            <a href="mailto:<?php echo esc_attr($subscription->customer_email ?? ''); ?>"><?php echo esc_html($subscription->customer_email ?? __('Email não disponível', 'hng-commerce')); ?></a>
                        </p>
                        <?php if (!empty($subscription->customer_phone)): ?>
                            <p>
                                <strong><?php esc_html_e('Telefone:', 'hng-commerce'); ?></strong><br>
                                <?php echo esc_html($subscription->customer_phone); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="hng-card" style="margin-top: 20px;">
                <div class="hng-card-header">
                    <h2 class="hng-card-title"><?php esc_html_e('Produto da Assinatura', 'hng-commerce'); ?></h2>
                </div>
                <div class="hng-card-content">
                    <p>
                        <strong><?php echo esc_html($subscription->product_name ?? __('Produto removido', 'hng-commerce')); ?></strong><br>
                        <?php if (!empty($subscription->product_id)): ?>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $subscription->product_id . '&action=edit')); ?>" target="_blank">
                                <?php esc_html_e('Editar Produto', 'hng-commerce'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle cancel subscription
     */
    private static function handle_cancel_subscription() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below
        if (!isset($_GET['subscription_id']) || !isset($_GET['_wpnonce'])) {
            wp_die(esc_html__('Parâmetros inválidos.', 'hng-commerce'));
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below
        $subscription_id = absint(wp_unslash($_GET['subscription_id']));
        
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'cancel_subscription_' . $subscription_id)) {
            wp_die(esc_html__('Erro de segurança. Por favor, tente novamente.', 'hng-commerce'));
        }
        
        global $wpdb;
        $subscriptions_table = hng_db_full_table_name('hng_subscriptions');
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Subscription status update
        $wpdb->update(
            $subscriptions_table,
            array('status' => 'cancelled'),
            array('id' => $subscription_id),
            array('%s'),
            array('%d')
        );
        
        wp_safe_redirect(admin_url('admin.php?page=hng-subscriptions&message=cancelled'));
        exit;
    }
    
    /**
     * Handle resume subscription
     */
    private static function handle_resume_subscription() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below
        if (!isset($_GET['subscription_id']) || !isset($_GET['_wpnonce'])) {
            wp_die(esc_html__('Parâmetros inválidos.', 'hng-commerce'));
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below
        $subscription_id = absint(wp_unslash($_GET['subscription_id']));
        
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'resume_subscription_' . $subscription_id)) {
            wp_die(esc_html__('Erro de segurança. Por favor, tente novamente.', 'hng-commerce'));
        }
        
        global $wpdb;
        $subscriptions_table = hng_db_full_table_name('hng_subscriptions');
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Subscription status update
        $wpdb->update(
            $subscriptions_table,
            array('status' => 'active'),
            array('id' => $subscription_id),
            array('%s'),
            array('%d')
        );
        
        wp_safe_redirect(admin_url('admin.php?page=hng-subscriptions&message=resumed'));
        exit;
    }
}
