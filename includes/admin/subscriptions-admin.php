<?php
/**
 * Subscriptions admin AJAX handlers
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure notes table exists
 */
function hng_ensure_subscription_notes_table() {
    global $wpdb;
    $table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_subscription_notes') : ($wpdb->prefix . 'hng_subscription_notes');
    $table_sql = function_exists('hng_db_backtick_table') ? hng_db_backtick_table('hng_subscription_notes') : ('`' . str_replace('`','', $table) . '`');
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
        return $table;
    }

    $charset_collate = $wpdb->get_charset_collate();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_backtick_table() helper
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_backtick_table()
    $sql = "CREATE TABLE {$table_sql} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        subscription_id BIGINT(20) NOT NULL,
        note TEXT NOT NULL,
        author_id BIGINT(20) DEFAULT 0,
        author_name VARCHAR(191) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        INDEX (subscription_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    return $table;
}


// Fetch subscriptions for admin table
add_action('wp_ajax_hng_fetch_subscriptions', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }

    global $wpdb;
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    $table = hng_db_full_table_name( 'hng_subscriptions' );
    $table_sql = '`' . str_replace('`','', $table) . '`';

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results( "SELECT * FROM {$table_sql} ORDER BY created_at DESC LIMIT 200", ARRAY_A );
    $out = [];
    foreach ($rows as $r) {
        $product_name = '';
        $prod_id = absint($r['product_id'] ?? 0);
        if ($prod_id) {
            $post = get_post($prod_id);
            $product_name = $post ? $post->post_title : '';
        }
        
        // Buscar dados do cliente
        $customer_id = absint($r['customer_id'] ?? 0);
        $customer_name = '';
        $customer_email = $r['customer_email'] ?? '';
        
        if ($customer_id) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT first_name, last_name, email FROM {$wpdb->prefix}hng_customers WHERE id = %d",
                $customer_id
            ));
            if ($customer) {
                $customer_name = trim($customer->first_name . ' ' . $customer->last_name);
                if (empty($customer_email)) {
                    $customer_email = $customer->email;
                }
            }
        }
        
        // Se ainda não tem nome, usar o email
        if (empty($customer_name) && !empty($customer_email)) {
            $customer_name = $customer_email;
        }
        
        $amount = floatval($r['amount'] ?? 0);
        $next_payment = $r['next_payment_date'] ?? $r['next_billing_date'] ?? '';
        
        // Formatar data
        $next_payment_formatted = '';
        if (!empty($next_payment) && $next_payment !== '0000-00-00 00:00:00') {
            $next_payment_formatted = date_i18n('d/m/Y H:i', strtotime($next_payment));
        } else {
            $next_payment_formatted = 'Não definido';
        }
        
        $out[] = [
            'id' => intval($r['id']),
            'customer_id' => $customer_id,
            'customer_name' => esc_html($customer_name),
            'customer_email' => esc_html($customer_email),
            'product_id' => $prod_id,
            'product_name' => esc_html($product_name),
            'amount' => $amount,
            'amount_formatted' => function_exists('hng_price') ? hng_price($amount) : number_format($amount, 2, ',', '.'),
            'next_payment_date' => $next_payment_formatted,
            'gateway' => esc_html($r['gateway'] ?? ''),
            'status' => esc_html($r['status'] ?? ''),
        ];
    }

    wp_send_json_success($out);
});


// Generate subscription manual payment link (creates renewal order and triggers manual renewal hook)
add_action('wp_ajax_hng_generate_subscription_link', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }

    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $subscription_id = absint($post['subscription_id'] ?? 0);
    if (!$subscription_id) {
        wp_send_json_error(['message' => __('ID inválido.', 'hng-commerce')]);
    }

    global $wpdb;
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    $table = hng_db_full_table_name( 'hng_subscriptions' );
    $table_sql = '`' . str_replace('`','', $table) . '`';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_sql} WHERE id = %d", $subscription_id ), ARRAY_A );
    if (!$sub) {
        wp_send_json_error(['message' => __('Assinatura não encontrada.', 'hng-commerce')]);
    }

    // Create renewal order
    $order_id = wp_insert_post([
        'post_type' => 'hng_order',
        /* translators: %s: placeholder */
        'post_title' => sprintf(esc_html__('Renovação Assinatura #%d', 'hng-commerce'), $subscription_id),
        'post_status' => 'hng-pending',
        'post_author' => 0,
    ]);

    if (is_wp_error($order_id) || !$order_id) {
        wp_send_json_error(['message' => __('Erro ao criar pedido de renovação.', 'hng-commerce')]);
    }

    $amount = floatval($sub['amount'] ?? 0);
    update_post_meta($order_id, '_customer_email', sanitize_email($sub['customer_email'] ?? ''));
    update_post_meta($order_id, '_total', $amount);
    update_post_meta($order_id, '_payment_method', $sub['payment_method'] ?? 'pix');
    update_post_meta($order_id, '_gateway', $sub['gateway'] ?? '');
    update_post_meta($order_id, '_subscription_id', $subscription_id);
    update_post_meta($order_id, '_is_renewal', 'yes');
    update_post_meta($order_id, '_created_date', current_time('mysql'));
    update_post_meta($order_id, '_order_status', 'pending');

    update_post_meta($order_id, '_order_items', [
        [
            'product_id' => intval($sub['product_id'] ?? 0),
            'product_name' => '',
            'quantity' => 1,
            'price' => $amount,
        ]
    ]);

    // Trigger gateway hooks so gateway integrations can generate payment data/url
    do_action('hng_subscription_manual_renewal', $subscription_id, $order_id, $sub['payment_method'] ?? 'pix');

    // Try to read payment url (some gateways fill _payment_url)
    $payment_url = get_post_meta($order_id, '_payment_url', true);
    $tries = 0;
    while (empty($payment_url) && $tries < 3) {
        sleep(1);
        $payment_url = get_post_meta($order_id, '_payment_url', true);
        $tries++;
    }

    wp_send_json_success(['order_id' => $order_id, 'payment_url' => $payment_url]);
});


// Generate one-off payment link for product (admin product screen uses this)
add_action('wp_ajax_hng_generate_oneoff_link', function() {
    // Verificar nonce
    check_ajax_referer('hng-commerce-admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }

    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $product_id = absint($post['product_id'] ?? 0);
    $price = floatval($post['price'] ?? 0);
    $expires_days = absint($post['expires_days'] ?? 0);
    $payment_method = sanitize_text_field($post['payment_method'] ?? 'pix');
    $gateway = sanitize_text_field($post['gateway'] ?? '');

    if (!$product_id) {
        wp_send_json_error(['message' => __('ID do produto inválido.', 'hng-commerce')]);
    }

    if ($price <= 0) {
        wp_send_json_error(['message' => __('Informe um valor válido para gerar o link.', 'hng-commerce')]);
    }

    if (empty($gateway)) {
        $gateway = get_option('hng_default_gateway', '');
    }

    if (empty($gateway)) {
        wp_send_json_error(['message' => __('Nenhum gateway padrão configurado. Defina um gateway em HNG Commerce > Pagamentos.', 'hng-commerce')]);
    }

    $product = get_post($product_id);
    $product_name = $product ? $product->post_title : __('Produto', 'hng-commerce');

    $order_id = wp_insert_post([
        'post_type' => 'hng_order',
        /* translators: %s: product title */
        'post_title' => sprintf(esc_html__('Pedido avulso - %s', 'hng-commerce'), $product_name),
        'post_status' => 'hng-pending',
        'post_author' => get_current_user_id(),
    ]);

    if (is_wp_error($order_id) || !$order_id) {
        wp_send_json_error(['message' => __('Erro ao criar pedido avulso.', 'hng-commerce')]);
    }

    update_post_meta($order_id, '_product_id', $product_id);
    update_post_meta($order_id, '_total', $price);
    update_post_meta($order_id, '_payment_method', $payment_method ?: 'pix');
    update_post_meta($order_id, '_gateway', $gateway);
    update_post_meta($order_id, '_created_date', current_time('mysql'));
    update_post_meta($order_id, '_order_status', 'pending');
    if ($expires_days > 0) {
        $expires_at = gmdate('Y-m-d H:i:s', time() + ($expires_days * DAY_IN_SECONDS));
        update_post_meta($order_id, '_expires_at', $expires_at);
    }

    $items = [
        [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'quantity' => 1,
            'price' => $price,
        ],
    ];
    update_post_meta($order_id, '_order_items', $items);

    // Deixa gateways especializados preencherem _payment_url
    do_action('hng_generate_oneoff_payment', $order_id);

    $payment_url = get_post_meta($order_id, '_payment_url', true);
    $tries = 0;
    while (empty($payment_url) && $tries < 3) {
        usleep(500000); // 0.5 segundos em vez de 1 segundo
        $payment_url = get_post_meta($order_id, '_payment_url', true);
        $tries++;
    }

    // Se ainda não tiver URL, gerar URL de checkout genérica (fallback)
    if (empty($payment_url)) {
        // Tentar múltiplos fallbacks
        // 1. Página de checkout pelo slug
        $checkout_page = get_page_by_path('checkout');
        if (!$checkout_page) {
            // 2. Tentar pelo título via WP_Query (get_page_by_title() está obsoleto)
            $checkout_query = new WP_Query([
                'post_type' => 'page',
                'post_status' => 'publish',
                's' => 'Checkout',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);
            if (!empty($checkout_query->posts)) {
                $candidate = get_post($checkout_query->posts[0]);
                if ($candidate && $candidate->post_title === 'Checkout') {
                    $checkout_page = $candidate;
                }
            }
            wp_reset_postdata();
        }
        if (!$checkout_page) {
            // 3. Buscar por opção do plugin
            $checkout_page_id = get_option('hng_checkout_page_id', 0);
            if ($checkout_page_id) {
                $checkout_page = get_post($checkout_page_id);
            }
        }
        
        if ($checkout_page && $checkout_page->post_status === 'publish') {
            $payment_url = add_query_arg(['order_id' => $order_id], get_permalink($checkout_page));
        } else {
            // 4. Fallback final: usar URL do produto com parâmetro de pedido
            $product_url = get_permalink($product_id);
            if ($product_url) {
                $payment_url = add_query_arg(['hng_order' => $order_id, 'pay' => '1'], $product_url);
            } else {
                // 5. Último recurso: usar home com parâmetros
                $payment_url = add_query_arg(['hng_order' => $order_id, 'pay' => '1'], home_url('/'));
            }
        }
    }

    // Salvar URL de pagamento no meta do pedido
    if (!empty($payment_url)) {
        update_post_meta($order_id, '_payment_url', $payment_url);
    }

    wp_send_json_success([
        'order_id' => $order_id,
        'payment_url' => $payment_url,
        'gateway' => $gateway,
        'payment_method' => $payment_method,
    ]);
});


/**
 * Admin: get subscription detail + notes for a given email
 */
add_action('wp_ajax_hng_get_subscription_detail', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $email = sanitize_email($post['email'] ?? '');
    if (!$email) {
        wp_send_json_error(['message' => __('E-mail inválido.', 'hng-commerce')]);
    }

    global $wpdb;
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    $table = hng_db_full_table_name('hng_subscriptions');
    $table_sql = '`' . str_replace('`','', $table) . '`';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_sql} WHERE customer_email = %s ORDER BY created_at DESC", $email), ARRAY_A);

    $subs = [];
    $notes = [];
    foreach ($rows as $r) {
        $product_name = '';
        $prod_id = absint($r['product_id'] ?? 0);
        if ($prod_id) {
            $post = get_post($prod_id);
            $product_name = $post ? $post->post_title : '';
        }
        $subs[] = [
            'id' => intval($r['id']),
            'product_name' => esc_html($product_name),
            'next_payment_date' => $r['next_payment_date'] ?? '',
            'amount_formatted' => function_exists('hng_price') ? hng_price(floatval($r['amount'] ?? 0)) : number_format(floatval($r['amount'] ?? 0), 2, ',', '.'),
            'status' => esc_html($r['status'] ?? ''),
        ];

        // load notes per-subscription from table (fallback to options if table empty)
        $nid = intval($r['id']);
        $notes_table = hng_ensure_subscription_notes_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $nrows = $wpdb->get_results($wpdb->prepare("SELECT note, created_at, author_id, author_name FROM {$notes_table} WHERE subscription_id = %d ORDER BY created_at DESC", $nid), ARRAY_A);
        if ($nrows) {
            foreach ($nrows as $nr) {
                $notes[] = ['created_at' => $nr['created_at'], 'note' => $nr['note'], 'author_id' => intval($nr['author_id']), 'author_name' => $nr['author_name']];
            }
        } else {
            // backward compatibility: read options
            $nkey = 'hng_subscription_notes_' . $nid;
            $n = get_option($nkey, []);
            if ($n && is_array($n)) {
                foreach ($n as $note) {
                    $notes[] = $note;
                }
            }
        }
    }

    wp_send_json_success(['email' => $email, 'subscriptions' => $subs, 'notes' => $notes]);
});


/**
 * Admin: add note to all subscriptions of an email
 */
add_action('wp_ajax_hng_add_subscription_note', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $email = sanitize_email($post['email'] ?? '');
    $note = sanitize_textarea_field($post['note'] ?? '');
    if (!$email || !$note) {
        wp_send_json_error(['message' => __('E-mail ou nota inválidos.', 'hng-commerce')]);
    }

    global $wpdb;
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    $table = hng_db_full_table_name('hng_subscriptions');
    $table_sql = '`' . str_replace('`','', $table) . '`';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$table_sql} WHERE customer_email = %s", $email), ARRAY_A);
    $now = current_time('mysql');
    $added = 0;
    foreach ($rows as $r) {
        $nid = intval($r['id']);
        // insert note row into notes table
        $notes_table = hng_ensure_subscription_notes_table();
        $author_id = get_current_user_id();
        $author_name = sanitize_text_field(wp_get_current_user()->display_name ?: ''); 
        $wpdb->insert($notes_table, [
            'subscription_id' => $nid,
            'note' => sanitize_textarea_field($note),
            'author_id' => $author_id,
            'author_name' => $author_name,
            'created_at' => $now,
        ], ['%d','%s','%d','%s','%s']);
        $added++;
    }

    wp_send_json_success(['added' => $added]);
});


/**
 * Admin: transfer subscription to another email
 */
add_action('wp_ajax_hng_transfer_subscription', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $sub_id = absint($post['subscription_id'] ?? 0);
    $to = sanitize_email($post['to_email'] ?? '');
    if (!$sub_id || !$to) {
        wp_send_json_error(['message' => __('Dados inválidos.', 'hng-commerce')]);
    }

    global $wpdb;
    $table = hng_db_full_table_name('hng_subscriptions');
    $updated = $wpdb->update($table, ['customer_email' => $to], ['id' => $sub_id], ['%s'], ['%d']);
    if ($updated === false) {
        wp_send_json_error(['message' => __('Falha ao transferir assinatura.', 'hng-commerce')]);
    }

    // add note about transfer
    // insert transfer note into notes table
    $notes_table = hng_ensure_subscription_notes_table();
    $author_id = get_current_user_id();
    $author_name = sanitize_text_field(wp_get_current_user()->display_name ?: '');
    $wpdb->insert($notes_table, [
        'subscription_id' => $sub_id,
        /* translators: %s: placeholder */
        'note' => sanitize_textarea_field(sprintf(esc_html__('Transferido para %s', 'hng-commerce'), $to)),
        'author_id' => $author_id,
        'author_name' => $author_name,
        'created_at' => current_time('mysql'),
    ], ['%d','%s','%d','%s','%s']);

    wp_send_json_success(['updated' => (bool)$updated]);
});


/**
 * Admin: mark a subscription payment as manual/paid (creates completed order)
 */
add_action('wp_ajax_hng_mark_payment_manual', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }
    $post = function_exists('wp_unslash') ? wp_unslash($_POST) : $_POST;
    $sub_id = absint($post['subscription_id'] ?? 0);
    if (!$sub_id) {
        wp_send_json_error(['message' => __('ID inválido.', 'hng-commerce')]);
    }

    global $wpdb;
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
    $table = hng_db_full_table_name('hng_subscriptions');
    $table_sql = '`' . str_replace('`','', $table) . '`';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $sub = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_sql} WHERE id = %d", $sub_id), ARRAY_A);
    if (!$sub) wp_send_json_error(['message' => __('Assinatura não encontrada.', 'hng-commerce')]);

    $amount = floatval($sub['amount'] ?? 0);
    $order_id = wp_insert_post([
        'post_type' => 'hng_order',
        /* translators: %s: placeholder */
        'post_title' => sprintf(esc_html__('Pagamento Manual Assinatura #%d', 'hng-commerce'), $sub_id),
        'post_status' => 'hng-completed',
        'post_author' => get_current_user_id(),
    ]);

    if (is_wp_error($order_id) || !$order_id) {
        wp_send_json_error(['message' => __('Erro ao criar pedido.', 'hng-commerce')]);
    }

    update_post_meta($order_id, '_total', $amount);
    update_post_meta($order_id, '_customer_email', sanitize_email($sub['customer_email'] ?? ''));
    update_post_meta($order_id, '_subscription_id', $sub_id);
    update_post_meta($order_id, '_payment_status', 'paid');
    update_post_meta($order_id, '_paid_date', current_time('mysql'));

    // update subscription next payment date heuristically by +30 days
    $next = gmdate('Y-m-d H:i:s', strtotime('+30 days'));
    $updated = $wpdb->update($table, ['last_payment_date' => current_time('mysql'), 'next_billing_date' => $next, 'status' => 'active'], ['id' => $sub_id], ['%s', '%s', '%s'], ['%d']);

    wp_send_json_success(['order_id' => $order_id, 'updated' => (bool)$updated]);
});


/**
 * Admin: migrate legacy option-based notes into new table
 */
add_action('wp_ajax_hng_migrate_subscription_notes', function() {
    check_ajax_referer('hng-commerce-admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permissão negada.', 'hng-commerce')]);
    }

    global $wpdb;
    $notes_table = hng_ensure_subscription_notes_table();
    $like = 'hng_subscription_notes_%';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $like), ARRAY_A);
    $migrated = 0;
    foreach ($rows as $r) {
        $opt = $r['option_name'];
        if (preg_match('/hng_subscription_notes_(\d+)/', $opt, $m)) {
            $sub_id = intval($m[1]);
            $vals = maybe_unserialize($r['option_value']);
            if (is_array($vals)) {
                foreach ($vals as $v) {
                    $note = $v['note'] ?? ($v[1] ?? '');
                    $created = $v['created_at'] ?? current_time('mysql');
                    $author_id = 0;
                    $author_name = '';
                    $wpdb->insert($notes_table, [
                        'subscription_id' => $sub_id,
                        'note' => $note,
                        'author_id' => $author_id,
                        'author_name' => $author_name,
                        'created_at' => $created,
                    ], ['%d','%s','%d','%s','%s']);
                    $migrated++;
                }
            }
            // Optionally remove old option
            // delete_option($opt);
        }
    }

    wp_send_json_success(['migrated' => $migrated]);
});
