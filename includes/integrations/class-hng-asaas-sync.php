<?php
/**
 * HNG Commerce - Asaas Data Synchronization
 *
 * Handles synchronization of subscriptions and customers from Asaas.
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Asaas_Sync {

    /**
     * API Key
     */
    private $api_key;

    /**
     * Environment
     */
    private $environment;

    /**
     * API URLs
     */
    private $api_urls = [
        'sandbox' => 'https://sandbox.asaas.com/api/v3',
        'production' => 'https://api.asaas.com/v3',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('hng_asaas_api_key', '');
        $this->environment = get_option('hng_asaas_sandbox', 0) ? 'sandbox' : 'production';
        
        // Ensure required columns exist
        $this->ensure_database_columns();
    }

    /**
     * Ensure required database columns exist for Asaas sync
     */
    private function ensure_database_columns() {
        global $wpdb;
        
        // Check if we've already done this recently (cache for 1 day)
        $cache_key = 'hng_asaas_db_columns_checked';
        if (get_transient($cache_key)) {
            return;
        }
        
        // Add external_id column to hng_orders if missing
        $orders_table = $wpdb->prefix . 'hng_orders';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check, table name from $wpdb->prefix
        $has_external_id = $wpdb->get_var("SHOW COLUMNS FROM `{$orders_table}` LIKE 'external_id'");
        if (!$has_external_id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration, table name from $wpdb->prefix
            $wpdb->query("ALTER TABLE `{$orders_table}` ADD COLUMN `external_id` varchar(100) DEFAULT NULL AFTER `transaction_id`");
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Index creation
            $wpdb->query("ALTER TABLE `{$orders_table}` ADD INDEX `external_id` (`external_id`)");
        }
        
        // Add asaas_customer_id column to hng_customers if missing
        $customers_table = $wpdb->prefix . 'hng_customers';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check, table name from $wpdb->prefix
        $has_asaas_id = $wpdb->get_var("SHOW COLUMNS FROM `{$customers_table}` LIKE 'asaas_customer_id'");
        if (!$has_asaas_id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration, table name from $wpdb->prefix
            $wpdb->query("ALTER TABLE `{$customers_table}` ADD COLUMN `asaas_customer_id` varchar(50) DEFAULT NULL AFTER `id`");
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Index creation
            $wpdb->query("ALTER TABLE `{$customers_table}` ADD INDEX `asaas_customer_id` (`asaas_customer_id`)");
        }
        
        // Add cpf_cnpj column to hng_customers if missing (check existing in class-hng-commerce-install.php shows it exists, but double-check)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check
        $has_cpf = $wpdb->get_var("SHOW COLUMNS FROM `{$customers_table}` LIKE 'cpf_cnpj'");
        if (!$has_cpf) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration
            $wpdb->query("ALTER TABLE `{$customers_table}` ADD COLUMN `cpf_cnpj` varchar(20) DEFAULT NULL AFTER `phone`");
        }
        
        // Add asaas_subscription_id column to hng_subscriptions if missing
        $subscriptions_table = $wpdb->prefix . 'hng_subscriptions';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check
        $has_asaas_sub_id = $wpdb->get_var("SHOW COLUMNS FROM `{$subscriptions_table}` LIKE 'asaas_subscription_id'");
        if (!$has_asaas_sub_id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration
            $wpdb->query("ALTER TABLE `{$subscriptions_table}` ADD COLUMN `asaas_subscription_id` varchar(50) DEFAULT NULL AFTER `product_id`");
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Index creation
            $wpdb->query("ALTER TABLE `{$subscriptions_table}` ADD INDEX `asaas_subscription_id` (`asaas_subscription_id`)");
        }
        
        // Cache this check for 1 day
        set_transient($cache_key, 1, DAY_IN_SECONDS);
    }

    /**
     * Import subscriptions from Asaas
     * 
     * @param string|null $customer_id Optional Asaas customer ID to filter
     * @return array Result stats
     */
    public function import_subscriptions($customer_id = null, $start_date = '', $end_date = '') {
        $lock_key = 'hng_asaas_sync_lock';
        if (get_transient($lock_key)) {
            return ['success' => false, 'error' => 'sync_in_progress'];
        }
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

        // Validate and normalize dates
        if ($start_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            $start_date = '';
        }
        if ($end_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $end_date = '';
        }
        // Swap dates if reversed
        if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
            $temp = $start_date;
            $start_date = $end_date;
            $end_date = $temp;
        }

        $endpoint_base = '/subscriptions';
        $limit = 100;
        $offset = 0;

        if ($customer_id) {
            $customer_filter = $customer_id;
        } else {
            $customer_filter = null;
        }

        $count = 0;
        $updated = 0;
        $created = 0;

        try {
            while (true) {
                $params = ['limit' => $limit, 'offset' => $offset];
                if ($customer_filter) $params['customer'] = $customer_filter;
                // Add date range filters if provided
                if ($start_date) $params['dateCreated[ge]'] = $start_date;
                if ($end_date) $params['dateCreated[le]'] = $end_date;

                $endpoint = $endpoint_base . '?' . http_build_query($params);
                $response = $this->make_request($endpoint, 'GET');

                if (is_wp_error($response)) {
                    delete_transient($lock_key);
                    return ['success' => false, 'error' => $response->get_error_message()];
                }

                if (!isset($response['data']) || !is_array($response['data']) || empty($response['data'])) {
                    break;
                }

                foreach ($response['data'] as $sub) {
                    $result = $this->create_or_update_local_subscription($sub);
                    if ($result === 'created') $created++;
                    if ($result === 'updated') $updated++;
                    $count++;
                }

                // If returned less than limit, we've reached last page
                if (count($response['data']) < $limit) {
                    break;
                }

                $offset += $limit;
            }

            update_option('hng_asaas_last_sync_subscriptions', current_time('mysql'));

            delete_transient($lock_key);

            $response = [
                'success' => true,
                'processed' => $count,
                'created' => $created,
                'updated' => $updated
            ];

            // Include date range info if provided
            if ($start_date || $end_date) {
                $response['date_range'] = [
                    'start' => $start_date ?: 'início',
                    'end' => $end_date ?: 'fim'
                ];
            }

            return $response;
        } catch (Exception $e) {
            delete_transient($lock_key);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create or update local subscription based on Asaas data
     * 
     * @param array $asaas_sub
     * @return string 'created', 'updated', or 'error'
     */
    private function create_or_update_local_subscription($asaas_sub) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_subscriptions';
        
        // Map status
        $status_map = [
            'ACTIVE' => 'active',
            'EXPIRED' => 'expired',
            'OVERDUE' => 'past_due', // or overdue
            'CANCELED' => 'cancelled'
        ];
        $status = $status_map[$asaas_sub['status']] ?? 'pending';

        // Check if exists
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is safe
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE asaas_subscription_id = %s",
            $asaas_sub['id']
        ));

        // Get customer email from Asaas customer ID
        $customer_email = $this->get_customer_email_by_id($asaas_sub['customer']);

        $data = [
            'status' => $status,
            'amount' => $asaas_sub['value'],
            'next_billing_date' => $asaas_sub['nextDueDate'],
            'billing_cycle' => strtolower((string) $asaas_sub['cycle']), // MONTHLY -> monthly
            'updated_at' => current_time('mysql')
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['id' => $existing->id]);
            return 'updated';
        } else {
            // Create new
            $data['asaas_subscription_id'] = $asaas_sub['id'];
            $data['customer_id'] = 0; // TODO: Resolve local customer ID
            $data['product_id'] = 0; // Unknown product, maybe create a placeholder or try to match?
            $data['created_at'] = $asaas_sub['dateCreated'];
            
            // Try to find local customer
            if ($customer_email) {
                $user = get_user_by('email', $customer_email);
                if ($user) {
                    $data['customer_id'] = $user->ID;
                }

                // Prefer hng_customers mapping if present
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is safe
                $hng_customer = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}hng_customers WHERE email = %s", $customer_email));
                if ($hng_customer) {
                    $data['customer_id'] = $hng_customer->id;
                }
            }

            // Try to map a product from externalReference (common pattern: SKU or product ID)
            $mapped_product_id = 0;
            if (!empty($asaas_sub['externalReference'])) {
                $ref = trim($asaas_sub['externalReference']);

                // If numeric and corresponds to a post ID
                if (ctype_digit($ref)) {
                    $possible = get_post(intval($ref));
                    if ($possible && $possible->post_type === 'hng_product') {
                        $mapped_product_id = intval($possible->ID);
                    }
                }

                // Try to find product by SKU (meta _sku)
                if (!$mapped_product_id) {
                    $found = get_posts(['post_type' => 'hng_product', 'meta_query' => [['key' => '_sku', 'value' => $ref]], 'posts_per_page' => 1, 'fields' => 'ids']);
                    if (!empty($found)) $mapped_product_id = $found[0];
                }

                // Fallback: try by title
                if (!$mapped_product_id) {
                    $found = get_posts(['post_type' => 'hng_product', 's' => $ref, 'posts_per_page' => 1, 'fields' => 'ids']);
                    if (!empty($found)) $mapped_product_id = $found[0];
                }
            }

            $data['product_id'] = $mapped_product_id;

            $wpdb->insert($table, $data);
            return 'created';
        }
    }

    /**
     * Get customer email from Asaas ID
     * 
     * @param string $asaas_customer_id
     * @return string|false
     */
    private function get_customer_email_by_id($asaas_customer_id) {
        // Check local cache/db first?
        global $wpdb;
        $email = $wpdb->get_var($wpdb->prepare(
            "SELECT email FROM {$wpdb->prefix}hng_customers WHERE asaas_customer_id = %s",
            $asaas_customer_id
        ));

        if ($email) return $email;

        // Fetch from API
        $response = $this->make_request("/customers/{$asaas_customer_id}", 'GET');
        if (!is_wp_error($response) && isset($response['email'])) {
            return $response['email'];
        }

        return false;
    }

    /**
     * Import customers from Asaas
     */
    public function import_customers($start_date = '', $end_date = '') {
        $lock_key = 'hng_asaas_sync_lock';
        if (get_transient($lock_key)) {
            return ['success' => false, 'error' => 'sync_in_progress'];
        }
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

        // Validate and normalize dates
        if ($start_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            $start_date = '';
        }
        if ($end_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $end_date = '';
        }
        // Swap dates if reversed
        if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
            $temp = $start_date;
            $start_date = $end_date;
            $end_date = $temp;
        }

        $endpoint_base = '/customers';
        $limit = 100;
        $offset = 0;

        $count = 0;
        $created = 0;
        $updated = 0;

        try {
            while (true) {
                $params = ['limit' => $limit, 'offset' => $offset];
                // Add date range filters if provided
                if ($start_date) $params['dateCreated[ge]'] = $start_date;
                if ($end_date) $params['dateCreated[le]'] = $end_date;

                $endpoint = $endpoint_base . '?' . http_build_query($params);
                $response = $this->make_request($endpoint, 'GET');

                if (is_wp_error($response)) {
                    delete_transient($lock_key);
                    return ['success' => false, 'error' => $response->get_error_message()];
                }

                if (!isset($response['data']) || !is_array($response['data']) || empty($response['data'])) {
                    break;
                }

                foreach ($response['data'] as $customer) {
                    $result = $this->create_or_update_local_customer($customer);
                    if ($result === 'created') $created++;
                    if ($result === 'updated') $updated++;
                    $count++;
                }

                if (count($response['data']) < $limit) {
                    break;
                }

                $offset += $limit;
            }

            update_option('hng_asaas_last_sync_customers', current_time('mysql'));
            delete_transient($lock_key);

            $response = [
                'success' => true,
                'processed' => $count,
                'created' => $created,
                'updated' => $updated
            ];

            // Include date range info if provided
            if ($start_date || $end_date) {
                $response['date_range'] = [
                    'start' => $start_date ?: 'início',
                    'end' => $end_date ?: 'fim'
                ];
            }

            return $response;
        } catch (Exception $e) {
            delete_transient($lock_key);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Import payments (billing) from Asaas
     * 
     * @param int $days Number of days to look back (default 30)
     * @param string $start_date Optional start date (Y-m-d)
     * @param string $end_date Optional end date (Y-m-d)
     * @return array Result stats
     */
    public function import_payments($days = 30, $start_date = '', $end_date = '') {
        $lock_key = 'hng_asaas_sync_payments_lock';
        if (get_transient($lock_key)) {
            return ['success' => false, 'error' => 'Sincronização já em andamento'];
        }
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

        $endpoint_base = '/payments';
        $limit = 100;
        $offset = 0;

        $count = 0;
        $created = 0;
        $updated = 0;

        // Date filter - custom range or last X days
        $date_from = '';
        $date_to = '';

        $start_valid = $start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date);
        $end_valid   = $end_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date);

        if ($start_valid) {
            $date_from = $start_date;
        }

        if ($end_valid) {
            $date_to = $end_date;
        }

        if ($date_from && $date_to) {
            // Ensure start <= end
            if (strtotime($date_from) > strtotime($date_to)) {
                $tmp = $date_from;
                $date_from = $date_to;
                $date_to = $tmp;
            }
        }

        if (!$date_from) {
            $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        }

        try {
            while (true) {
                $params = [
                    'limit' => $limit,
                    'offset' => $offset,
                    'dateCreated[ge]' => $date_from
                ];

                if ($date_to) {
                    $params['dateCreated[le]'] = $date_to;
                }
                
                $endpoint = $endpoint_base . '?' . http_build_query($params);
                $response = $this->make_request($endpoint, 'GET');

                if (is_wp_error($response)) {
                    delete_transient($lock_key);
                    return ['success' => false, 'error' => $response->get_error_message()];
                }

                if (!isset($response['data']) || !is_array($response['data']) || empty($response['data'])) {
                    break;
                }

                foreach ($response['data'] as $payment) {
                    $result = $this->create_or_update_local_payment($payment);
                    if ($result === 'created') $created++;
                    if ($result === 'updated') $updated++;
                    $count++;
                }

                if (count($response['data']) < $limit) {
                    break;
                }

                $offset += $limit;
            }

            update_option('hng_asaas_last_sync_payments', current_time('mysql'));
            delete_transient($lock_key);

            return [
                'success' => true,
                'processed' => $count,
                'created' => $created,
                'updated' => $updated,
                'days' => $days,
                'start_date' => $date_from,
                'end_date' => $date_to
            ];
        } catch (Exception $e) {
            delete_transient($lock_key);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create or update local customer
     */
    private function create_or_update_local_customer($asaas_customer) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_customers';

        // Validate required fields
        if (empty($asaas_customer['id']) || empty($asaas_customer['email'])) {
            return 'error';
        }

        // Check by Asaas ID
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is safe
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE asaas_customer_id = %s",
            $asaas_customer['id']
        ));

        // If not found by ID, check by Email (to link existing)
        if (!$existing) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is safe
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE email = %s",
                $asaas_customer['email']
            ));
        }

        $data = [
            'first_name' => sanitize_text_field($asaas_customer['name'] ?? ''),
            'email' => sanitize_email($asaas_customer['email']),
            'cpf_cnpj' => sanitize_text_field($asaas_customer['cpfCnpj'] ?? ''),
            'phone' => sanitize_text_field($asaas_customer['mobilePhone'] ?? $asaas_customer['phone'] ?? ''),
            'asaas_customer_id' => sanitize_text_field($asaas_customer['id']),
            'updated_at' => current_time('mysql')
        ];

        if ($existing) {
            $result = $wpdb->update($table, $data, ['id' => $existing->id]);
            if ($result === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HNG Asaas: Error updating customer ' . $asaas_customer['id'] . ': ' . $wpdb->last_error);
                }
                return 'error';
            }
            return 'updated';
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
            if ($result === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HNG Asaas: Error inserting customer ' . $asaas_customer['id'] . ': ' . $wpdb->last_error);
                }
                return 'error';
            }
            return 'created';
        }
    }

    /**
     * Create or update local payment/order from Asaas payment
     */
    private function create_or_update_local_payment($asaas_payment) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_orders';

        // Validate required fields
        if (empty($asaas_payment['id'])) {
            return 'error';
        }

        // Check if payment already exists by external_id
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is safe
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE external_id = %s",
            $asaas_payment['id']
        ));

        // Map Asaas status to HNG status
        $status_map = [
            'PENDING' => 'pending',
            'RECEIVED' => 'completed',
            'CONFIRMED' => 'completed',
            'OVERDUE' => 'failed',
            'REFUNDED' => 'refunded',
            'RECEIVED_IN_CASH' => 'completed',
            'REFUND_REQUESTED' => 'refunded',
            'CHARGEBACK_REQUESTED' => 'refunded',
            'CHARGEBACK_DISPUTE' => 'refunded',
            'AWAITING_CHARGEBACK_REVERSAL' => 'refunded',
            'DUNNING_REQUESTED' => 'pending',
            'DUNNING_RECEIVED' => 'completed',
            'AWAITING_RISK_ANALYSIS' => 'pending',
        ];

        $status = $status_map[$asaas_payment['status'] ?? 'PENDING'] ?? 'pending';

        // Get customer email
        $customer_email = '';
        if (!empty($asaas_payment['customer'])) {
            $customer_email = $this->get_customer_email_by_id($asaas_payment['customer']);
        }

        // Generate order number if creating new
        $order_number = 'ASAAS-' . sanitize_text_field($asaas_payment['id']);

        $data = [
            'customer_email' => sanitize_email($customer_email),
            'status' => sanitize_text_field($status),
            'payment_status' => sanitize_text_field($status),
            'total' => floatval($asaas_payment['value'] ?? 0),
            'subtotal' => floatval($asaas_payment['value'] ?? 0),
            'payment_method' => sanitize_text_field($asaas_payment['billingType'] ?? ''),
            'gateway' => 'asaas',
            'external_id' => sanitize_text_field($asaas_payment['id']),
            'transaction_id' => sanitize_text_field($asaas_payment['id']),
            'updated_at' => current_time('mysql')
        ];

        if ($existing) {
            // Update existing order
            $result = $wpdb->update($table, $data, ['id' => $existing->id]);
            if ($result === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HNG Asaas: Error updating payment ' . $asaas_payment['id'] . ': ' . $wpdb->last_error);
                }
                return 'error';
            }
            return 'updated';
        } else {
            // Create new order
            $data['order_number'] = $order_number;
            $data['created_at'] = $asaas_payment['dateCreated'] ?? current_time('mysql');
            $result = $wpdb->insert($table, $data);
            if ($result === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HNG Asaas: Error inserting payment ' . $asaas_payment['id'] . ': ' . $wpdb->last_error);
                }
                return 'error';
            }
            return 'created';
        }
    }

    /**
     * Make API Request
     * 
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @return array|WP_Error
     */
    private function make_request($endpoint, $method = 'GET', $data = []) {
        // Check API key
        if (empty($this->api_key)) {
            return new WP_Error('asaas_no_api_key', __('Chave de API do Asaas não configurada. Configure em HNG Commerce > Configurações > Gateways > Asaas.', 'hng-commerce'));
        }
        
        $url = $this->api_urls[$this->environment] . '/' . ltrim($endpoint, '/');
        
        $args = [
            'method' => $method,
            'headers' => [
                'access_token' => $this->api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'HNG-Commerce/' . HNG_COMMERCE_VERSION
            ],
            'timeout' => 30,
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT'], true)) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            // Log the error for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('HNG Asaas API Error: ' . $response->get_error_message());
            }
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $decoded = json_decode($body, true);

        if ($code >= 400) {
            // Build error message
            $msg = __('Erro na API do Asaas', 'hng-commerce');
            
            if (isset($decoded['errors']) && is_array($decoded['errors'])) {
                $error_messages = [];
                foreach ($decoded['errors'] as $error) {
                    if (isset($error['description'])) {
                        $error_messages[] = $error['description'];
                    }
                }
                if (!empty($error_messages)) {
                    $msg = implode('; ', $error_messages);
                }
            }
            
            // Specific error codes
            if ($code === 401) {
                $msg = __('Chave de API inválida ou expirada. Verifique suas credenciais do Asaas.', 'hng-commerce');
            } elseif ($code === 403) {
                $msg = __('Acesso negado. Verifique as permissões da sua chave de API do Asaas.', 'hng-commerce');
            } elseif ($code === 404) {
                $msg = __('Recurso não encontrado no Asaas.', 'hng-commerce');
            } elseif ($code === 429) {
                $msg = __('Muitas requisições. Aguarde alguns minutos e tente novamente.', 'hng-commerce');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("HNG Asaas API Error (HTTP $code): $msg - Endpoint: $endpoint");
            }
            
            return new WP_Error('asaas_api_error', $msg, $decoded);
        }

        return $decoded;
    }
}
