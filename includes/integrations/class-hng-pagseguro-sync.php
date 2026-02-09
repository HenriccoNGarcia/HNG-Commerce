<?php
/**
 * HNG Commerce - PagSeguro/PagBank Data Synchronization
 *
 * Handles synchronization of subscriptions, customers, and payments from PagBank.
 * Uses the new PagBank API v4 with Bearer token authentication.
 *
 * @package HNG_Commerce
 * @since 1.2.12
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_PagSeguro_Sync {

    /**
     * API Token
     */
    private $token;

    /**
     * Sandbox mode
     */
    private $sandbox;

    /**
     * API URLs - Orders/Charges API
     */
    private $api_urls = [
        'production' => 'https://api.pagseguro.com',
        'sandbox' => 'https://sandbox.api.pagseguro.com',
    ];

    /**
     * API URLs - Subscriptions/Recurring API (domínio diferente!)
     */
    private $subscriptions_api_urls = [
        'production' => 'https://api.assinaturas.pagseguro.com',
        'sandbox' => 'https://sandbox.api.assinaturas.pagseguro.com',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->token = trim((string) get_option('hng_ps_token', ''));
        $this->sandbox = get_option('hng_ps_sandbox', 'yes') === 'yes';
        
        // Ensure tables have required columns
        $this->maybe_update_tables();
    }

    /**
     * Update database tables to include required columns for sync
     */
    private function maybe_update_tables() {
        // Only run once per day
        $last_check = get_transient('hng_pagseguro_tables_checked');
        if ($last_check) {
            return;
        }
        set_transient('hng_pagseguro_tables_checked', 1, DAY_IN_SECONDS);

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Create payments table if not exists
        $payments_table = $wpdb->prefix . 'hng_payments';
        if ($wpdb->get_var("SHOW TABLES LIKE '$payments_table'") !== $payments_table) {
            $sql = "CREATE TABLE `$payments_table` (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                pagseguro_payment_id varchar(100) DEFAULT NULL,
                reference_id varchar(100) DEFAULT NULL,
                order_id bigint(20) unsigned DEFAULT NULL,
                customer_email varchar(255) DEFAULT NULL,
                customer_name varchar(255) DEFAULT NULL,
                amount decimal(10,2) NOT NULL DEFAULT 0.00,
                status varchar(50) NOT NULL DEFAULT 'pending',
                payment_method varchar(50) DEFAULT NULL,
                gateway varchar(50) DEFAULT 'pagseguro',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY pagseguro_payment_id (pagseguro_payment_id),
                KEY order_id (order_id),
                KEY status (status),
                KEY gateway (gateway)
            ) $charset_collate;";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }

        // Add 'source' column to customers table if not exists
        $customers_table = $wpdb->prefix . 'hng_customers';
        if ($wpdb->get_var("SHOW TABLES LIKE '$customers_table'") === $customers_table) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$customers_table` LIKE 'source'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE `$customers_table` ADD COLUMN `source` varchar(50) DEFAULT 'manual' AFTER `updated_at`");
            }
            // Also add 'name' and 'document' if they don't exist
            $name_exists = $wpdb->get_results("SHOW COLUMNS FROM `$customers_table` LIKE 'name'");
            if (empty($name_exists)) {
                $wpdb->query("ALTER TABLE `$customers_table` ADD COLUMN `name` varchar(255) DEFAULT NULL AFTER `email`");
            }
            $doc_exists = $wpdb->get_results("SHOW COLUMNS FROM `$customers_table` LIKE 'document'");
            if (empty($doc_exists)) {
                $wpdb->query("ALTER TABLE `$customers_table` ADD COLUMN `document` varchar(20) DEFAULT NULL AFTER `phone`");
            }
        }

        // Add 'gateway' and 'pagseguro_subscription_id' to subscriptions table if not exists
        $subs_table = $wpdb->prefix . 'hng_subscriptions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$subs_table'") === $subs_table) {
            $gateway_exists = $wpdb->get_results("SHOW COLUMNS FROM `$subs_table` LIKE 'gateway'");
            if (empty($gateway_exists)) {
                $wpdb->query("ALTER TABLE `$subs_table` ADD COLUMN `gateway` varchar(50) DEFAULT NULL AFTER `updated_at`");
            }
            $ps_id_exists = $wpdb->get_results("SHOW COLUMNS FROM `$subs_table` LIKE 'pagseguro_subscription_id'");
            if (empty($ps_id_exists)) {
                $wpdb->query("ALTER TABLE `$subs_table` ADD COLUMN `pagseguro_subscription_id` varchar(100) DEFAULT NULL AFTER `gateway`");
                $wpdb->query("ALTER TABLE `$subs_table` ADD INDEX `pagseguro_subscription_id` (`pagseguro_subscription_id`)");
            }
        }
    }

    /**
     * Get API URL
     */
    private function get_api_url() {
        return $this->sandbox ? $this->api_urls['sandbox'] : $this->api_urls['production'];
    }

    /**
     * Get Subscriptions API URL
     */
    private function get_subscriptions_api_url() {
        return $this->sandbox ? $this->subscriptions_api_urls['sandbox'] : $this->subscriptions_api_urls['production'];
    }

    /**
     * Check if configured
     */
    public function is_configured() {
        return !empty($this->token);
    }

    /**
     * Make API request to PagBank
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @param bool $use_subscriptions_api Use subscriptions API domain
     * @return array|WP_Error
     */
    private function make_request($endpoint, $method = 'GET', $data = [], $use_subscriptions_api = false) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('Token do PagBank não configurado.', 'hng-commerce'));
        }

        $base_url = $use_subscriptions_api ? $this->get_subscriptions_api_url() : $this->get_api_url();
        $url = $base_url . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'x-api-version' => '4.0',
            ],
            'timeout' => 30,
        ];

        if ($method !== 'GET' && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('HNG PagSeguro Sync Error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        // Handle invalid JSON responses from API
        if ($result === null && $body !== '') {
            $json_error = function_exists('json_last_error_msg') ? json_last_error_msg() : 'Invalid JSON';
            error_log('HNG PagSeguro Sync JSON decode error: ' . $json_error . ' - URL: ' . $url);
            return new WP_Error(
                'json_decode_error',
                __('Resposta inválida da API do PagBank.', 'hng-commerce'),
                [
                    'error' => $json_error,
                    'code' => $code,
                ]
            );
        }

        if ($code >= 400) {
            $error_message = $result['error_messages'][0]['description'] ?? $result['message'] ?? __('Erro na API do PagBank', 'hng-commerce');
            error_log('HNG PagSeguro Sync API Error: ' . $code . ' - ' . $error_message . ' - URL: ' . $url);
            return new WP_Error('api_error', $error_message, ['code' => $code]);
        }

        return $result;
    }

    /**
     * Import orders/charges (payments) from PagBank
     * 
     * This method syncs orders from hng_orders table that used PagSeguro
     * and creates/updates records in hng_payments table.
     * 
     * @param int $days Number of days to look back
     * @param string $start_date Optional start date (Y-m-d)
     * @param string $end_date Optional end date (Y-m-d)
     * @return array Result stats
     */
    public function import_payments($days = 30, $start_date = '', $end_date = '') {
        $lock_key = 'hng_pagseguro_sync_payments_lock';
        if (get_transient($lock_key)) {
            return ['success' => false, 'error' => 'sync_in_progress'];
        }
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

        $count = 0;
        $created = 0;
        $updated = 0;

        try {
            global $wpdb;
            
            $orders_table = $wpdb->prefix . 'hng_orders';
            $payments_table = $wpdb->prefix . 'hng_payments';
            
            // Check if orders table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$orders_table'") !== $orders_table) {
                delete_transient($lock_key);
                return [
                    'success' => true,
                    'message' => 'Tabela de pedidos não encontrada.',
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                ];
            }
            
            // Check which columns exist
            $has_gateway = $wpdb->get_results("SHOW COLUMNS FROM `{$orders_table}` LIKE 'gateway'");
            $has_transaction_id = $wpdb->get_results("SHOW COLUMNS FROM `{$orders_table}` LIKE 'transaction_id'");
            
            // Build WHERE clause for PagSeguro orders
            $where_clauses = [];
            if (!empty($has_gateway)) {
                $where_clauses[] = "gateway LIKE '%pagseguro%'";
                $where_clauses[] = "gateway LIKE '%pagbank%'";
            }
            // Also include by payment method
            $where_clauses[] = "payment_method LIKE '%pix%'";
            $where_clauses[] = "payment_method LIKE '%boleto%'";
            $where_clauses[] = "payment_method LIKE '%credit%'";
            $where_clauses[] = "payment_method LIKE '%debit%'";
            
            $where_sql = '(' . implode(' OR ', $where_clauses) . ')';
            
                        // Date range: use supplied range or fallback to last X days
                        $start_valid = $start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date);
                        $end_valid   = $end_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date);

                        $date_from = $start_valid ? $start_date : gmdate('Y-m-d', strtotime("-{$days} days"));
                        $date_to   = $end_valid ? $end_date : gmdate('Y-m-d');

                        if (strtotime($date_from) > strtotime($date_to)) {
                                $tmp = $date_from;
                                $date_from = $date_to;
                                $date_to = $tmp;
                        }

                        $date_from_ts = $date_from . ' 00:00:00';
                        $date_to_ts   = $date_to . ' 23:59:59';

                        // Get orders from the selected period
            // Build column selection based on available columns
            $gateway_col = !empty($has_gateway) ? 'gateway,' : "'' as gateway,";
            $transaction_col = !empty($has_transaction_id) ? 'transaction_id,' : "'' as transaction_id,";
            
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $orders_table sanitized via $wpdb->prefix, $where_sql uses safe LIKE patterns, column conditionals are safe strings
            $query = $wpdb->prepare(
                "SELECT id, order_number, customer_id, customer_email, customer_name,
                        total, status, payment_status, payment_method, 
                        {$gateway_col}
                        {$transaction_col}
                        created_at
                 FROM {$orders_table} 
                                 WHERE created_at BETWEEN %s AND %s
                                     AND {$where_sql}
                                 ORDER BY created_at DESC
                                 LIMIT 500",
                                $date_from_ts,
                                $date_to_ts
            );
            
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare above
            $orders = $wpdb->get_results($query, ARRAY_A);

            if (empty($orders)) {
                delete_transient($lock_key);
                update_option('hng_pagseguro_last_sync_payments', current_time('mysql'));
                return [
                    'success' => true,
                    'message' => sprintf('Nenhum pedido PagSeguro encontrado entre %s e %s.', $date_from, $date_to),
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'days' => $days,
                    'start_date' => $date_from,
                    'end_date' => $date_to,
                ];
            }

            // Process each order
            foreach ($orders as $order) {
                $result = $this->sync_order_to_payments($order);
                if ($result === 'created') $created++;
                if ($result === 'updated') $updated++;
                $count++;
            }

            update_option('hng_pagseguro_last_sync_payments', current_time('mysql'));

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
            error_log('HNG PagSeguro Sync Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Import customers/subscribers from PagBank API
     * 
     * Uses the Subscriptions API endpoint /customers to list subscribers.
     * If API is not available or returns no data, falls back to local orders.
     *
     * @return array Result stats
     */
    public function import_customers($start_date = '', $end_date = '') {
        $lock_key = 'hng_pagseguro_sync_customers_lock';
        if (get_transient($lock_key)) {
            return ['success' => false, 'error' => __('Sincronização já em andamento. Aguarde alguns minutos.', 'hng-commerce')];
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

        $count = 0;
        $created = 0;
        $updated = 0;

        try {
            // Check if token is configured
            if (!$this->is_configured()) {
                delete_transient($lock_key);
                return [
                    'success' => false,
                    'error' => __('Token do PagBank não configurado. Configure em HNG Commerce > Configurações > Gateways > PagSeguro.', 'hng-commerce')
                ];
            }
            
            // Debug log start
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("HNG PagSeguro Sync: Starting import_customers()");
                error_log("HNG PagSeguro Sync: Token configured: yes, Sandbox: " . ($this->sandbox ? 'yes' : 'no'));
            }
            
            // Try the Subscriptions API first
            // Endpoint: GET /customers - list all subscribers
            $api_available = false;
            $offset = 0;
            $limit = 100;
            $max_pages = 10;
            $page = 0;

            while ($page < $max_pages) {
                $query_params = [
                    'offset' => $offset,
                    'limit' => $limit,
                ];
                
                // Add date filters if provided
                if ($start_date) {
                    $query_params['createdAfter'] = $start_date;
                }
                if ($end_date) {
                    $query_params['createdBefore'] = $end_date;
                }
                
                $endpoint = '/customers?' . http_build_query($query_params);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("HNG PagSeguro Sync: Requesting endpoint: " . $this->get_subscriptions_api_url() . $endpoint);
                }
                
                $response = $this->make_request($endpoint, 'GET', [], true);

                if (is_wp_error($response)) {
                    $error_code = $response->get_error_data()['code'] ?? 0;
                    $error_msg = $response->get_error_message();
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("HNG PagSeguro Sync: API error $error_code - $error_msg");
                    }
                    
                    // If API not available (401/403/404), fall back to local orders
                    if (in_array($error_code, [401, 403, 404], true)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('HNG PagSeguro Sync: Customers API not available, falling back to local orders...');
                        }
                        delete_transient($lock_key);
                        return $this->import_customers_from_orders($start_date, $end_date);
                    }
                    
                    delete_transient($lock_key);
                    return ['success' => false, 'error' => $error_msg];
                }

                $api_available = true;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("HNG PagSeguro Sync: API response received: " . wp_json_encode(array_keys((array) $response)));
                }
                
                // Parse customers from response - handle multiple response formats
                // PagBank may return: { customers: [...] } or just [...]
                $customers = [];
                if (isset($response['customers']) && is_array($response['customers'])) {
                    $customers = $response['customers'];
                } elseif (isset($response['data']) && is_array($response['data'])) {
                    $customers = $response['data'];
                } elseif (is_array($response) && !isset($response['error_messages'])) {
                    // Check if it's a direct array of customers (each has 'email' key)
                    $first_item = reset($response);
                    if (is_array($first_item) && isset($first_item['email'])) {
                        $customers = $response;
                    }
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("HNG PagSeguro Sync: Found " . count($customers) . " customers in response");
                }
                
                if (empty($customers)) {
                    break;
                }

                foreach ($customers as $customer) {
                    $result = $this->create_or_update_local_customer($customer);
                    if ($result === 'created') $created++;
                    if ($result === 'updated') $updated++;
                    $count++;
                }

                // Check pagination
                if (count($customers) < $limit) {
                    break;
                }

                $offset += $limit;
                $page++;
            }

            // If API was available but returned no customers, still try local orders as supplement
            if ($api_available && $count === 0) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('HNG PagSeguro Sync: API returned no customers, supplementing with local orders...');
                }
                delete_transient($lock_key);
                $local_result = $this->import_customers_from_orders($start_date, $end_date);
                $local_result['note'] = __('A API do PagBank não retornou assinantes. Clientes foram importados dos pedidos locais.', 'hng-commerce');
                return $local_result;
            }

            update_option('hng_pagseguro_last_sync_customers', current_time('mysql'));
            delete_transient($lock_key);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("HNG PagSeguro Sync: Completed - processed: $count, created: $created, updated: $updated");
            }

            return [
                'success' => true,
                'processed' => $count,
                'created' => $created,
                'updated' => $updated,
                'source' => 'pagbank_api'
            ];
        } catch (Exception $e) {
            delete_transient($lock_key);
            error_log('HNG PagSeguro Sync Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Import customers from local orders (fallback)
     *
     * @param string $start_date Optional start date (YYYY-MM-DD)
     * @param string $end_date Optional end date (YYYY-MM-DD)
     * @return array Result stats
     */
    private function import_customers_from_orders($start_date = '', $end_date = '') {
        global $wpdb;
        
        $count = 0;
        $created = 0;
        $updated = 0;

        $orders_table = $wpdb->prefix . 'hng_orders';
        
        // Check if orders table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$orders_table'") !== $orders_table) {
            return [
                'success' => true,
                'message' => 'Tabela de pedidos não encontrada.',
                'total' => 0,
                'created' => 0,
                'updated' => 0,
            ];
        }
        
        // Get unique customers from orders
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orders_table sanitized via $wpdb->prefix, no user input in query
        $where_clause = "WHERE customer_email IS NOT NULL AND customer_email != ''";
        
        // Add date filters if provided
        if ($start_date) {
            $start_datetime = $start_date . ' 00:00:00';
            $where_clause .= " AND created_at >= '" . esc_sql($start_datetime) . "'";
        }
        if ($end_date) {
            $end_datetime = $end_date . ' 23:59:59';
            $where_clause .= " AND created_at <= '" . esc_sql($end_datetime) . "'";
        }
        
        $query = "SELECT DISTINCT 
                customer_email as email, 
                customer_name as name, 
                customer_phone as phone
             FROM {$orders_table} 
             $where_clause
             ORDER BY created_at DESC
             LIMIT 500";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query uses only safe table prefix, no user input in query
        $local_customers = $wpdb->get_results($query, ARRAY_A);

        if (empty($local_customers)) {
            return [
                'success' => true,
                'message' => 'Nenhum cliente encontrado nos pedidos locais.',
                'total' => 0,
                'created' => 0,
                'updated' => 0,
            ];
        }

        $processed_emails = [];

        foreach ($local_customers as $customer) {
            if (empty($customer['email'])) {
                continue;
            }

            $email = $customer['email'];
            
            // Skip if already processed in this run
            if (in_array($email, $processed_emails, true)) {
                continue;
            }
            $processed_emails[] = $email;

            $result = $this->create_or_update_local_customer($customer);
            if ($result === 'created') $created++;
            if ($result === 'updated') $updated++;
            $count++;
        }

        update_option('hng_pagseguro_last_sync_customers', current_time('mysql'));

        return [
            'success' => true,
            'processed' => $count,
            'created' => $created,
            'updated' => $updated,
            'source' => 'local_orders'
        ];
    }

    /**
     * Import subscriptions from PagBank
     * 
     * Uses the new PagBank Subscriptions API at api.assinaturas.pagseguro.com
     *
     * @param string $start_date Optional start date (YYYY-MM-DD)
     * @param string $end_date Optional end date (YYYY-MM-DD)
     * @return array Result stats
     */
    public function import_subscriptions($start_date = '', $end_date = '') {
        $lock_key = 'hng_pagseguro_sync_subscriptions_lock';
        if (get_transient($lock_key)) {
            return ['success' => false, 'error' => __('Sincronização já em andamento. Aguarde alguns minutos.', 'hng-commerce')];
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

        $count = 0;
        $created = 0;
        $updated = 0;

        try {
            // Check if token is configured
            if (!$this->is_configured()) {
                delete_transient($lock_key);
                return [
                    'success' => false,
                    'error' => __('Token do PagBank não configurado. Configure em HNG Commerce > Configurações > Gateways > PagSeguro.', 'hng-commerce')
                ];
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("HNG PagSeguro Sync: Starting import_subscriptions()");
            }
            
            // Use the Subscriptions API
            // Endpoint: GET /subscriptions with optional filters
            $query_params = [
                'Status' => ['ACTIVE', 'TRIAL', 'PENDING', 'OVERDUE'],
            ];
            
            // Add date range filters if provided
            if ($start_date) {
                $query_params['createdAfter'] = $start_date;
            }
            if ($end_date) {
                $query_params['createdBefore'] = $end_date;
            }
            
            $endpoint = '/subscriptions?' . http_build_query($query_params);
            $response = $this->make_request($endpoint, 'GET', [], true); // true = use subscriptions API

            if (is_wp_error($response)) {
                $error_code = $response->get_error_data()['code'] ?? 0;
                $error_msg = $response->get_error_message();
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("HNG PagSeguro Sync: Subscriptions API error $error_code - $error_msg");
                }
                
                // If API not available (401/403/404), try legacy
                if (in_array($error_code, [401, 403, 404], true)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('HNG PagSeguro Sync: New subscriptions API not available, trying legacy...');
                    }
                    delete_transient($lock_key);
                    return $this->import_subscriptions_legacy();
                }
                
                delete_transient($lock_key);
                return ['success' => false, 'error' => $error_msg];
            }

            // Parse subscriptions from response - handle multiple response formats
            $subscriptions = [];
            if (isset($response['subscriptions']) && is_array($response['subscriptions'])) {
                $subscriptions = $response['subscriptions'];
            } elseif (isset($response['data']) && is_array($response['data'])) {
                $subscriptions = $response['data'];
            } elseif (is_array($response) && !isset($response['error_messages'])) {
                // Check if it's a direct array of subscriptions
                $first_item = reset($response);
                if (is_array($first_item) && isset($first_item['id'])) {
                    $subscriptions = $response;
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("HNG PagSeguro Sync: Found " . count($subscriptions) . " subscriptions in response");
            }
            
            if (empty($subscriptions)) {
                delete_transient($lock_key);
                update_option('hng_pagseguro_last_sync_subscriptions', current_time('mysql'));
                return [
                    'success' => true,
                    'message' => __('Nenhuma assinatura encontrada no PagBank.', 'hng-commerce'),
                    'processed' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'source' => 'pagbank_api'
                ];
            }

            foreach ($subscriptions as $sub) {
                $result = $this->create_or_update_local_subscription($sub);
                if ($result === 'created') $created++;
                if ($result === 'updated') $updated++;
                $count++;
            }

            update_option('hng_pagseguro_last_sync_subscriptions', current_time('mysql'));
            delete_transient($lock_key);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("HNG PagSeguro Sync: Completed subscriptions - processed: $count, created: $created, updated: $updated");
            }

            return [
                'success' => true,
                'processed' => $count,
                'created' => $created,
                'updated' => $updated,
                'source' => 'pagbank_api'
            ];
        } catch (Exception $e) {
            delete_transient($lock_key);
            error_log('HNG PagSeguro Sync Subscriptions Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Import subscriptions using legacy pre-approvals API
     * 
     * @return array Result stats
     */
    private function import_subscriptions_legacy() {
        $email = get_option('hng_ps_email', '');
        $token = $this->token;

        // Se não tem email/token legado, retornar mensagem informativa
        if (empty($email) || empty($token)) {
            return [
                'success' => true,
                'message' => __('API de assinaturas requer configuração. Verifique se sua conta PagBank tem acesso à API de recorrência.', 'hng-commerce'),
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
            ];
        }

        $lock_key = 'hng_pagseguro_sync_subscriptions_lock';
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

        $count = 0;
        $created = 0;
        $updated = 0;

        try {
            $page = 1;
            $max_results = 50;

            while (true) {
                // Legacy API with email+token - requires XML headers
                $url = 'https://ws.pagseguro.uol.com.br/v2/pre-approvals?' . http_build_query([
                    'email' => $email,
                    'token' => $token,
                    'page' => $page,
                    'maxPageResults' => $max_results,
                ]);

                $response = wp_remote_get($url, [
                    'timeout' => 30,
                    'headers' => [
                        'Accept' => 'application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1',
                        'Content-Type' => 'application/json;charset=ISO-8859-1',
                    ],
                ]);

                if (is_wp_error($response)) {
                    delete_transient($lock_key);
                    return ['success' => false, 'error' => $response->get_error_message()];
                }

                $code = wp_remote_retrieve_response_code($response);
                
                // 406 = API não disponível para esta conta ou formato não aceito
                if ($code === 406) {
                    delete_transient($lock_key);
                    return [
                        'success' => true,
                        'message' => __('Sincronização de assinaturas não disponível. A API de recorrência pode não estar habilitada para sua conta PagSeguro.', 'hng-commerce'),
                        'processed' => 0,
                        'created' => 0,
                        'updated' => 0,
                    ];
                }
                
                // 401/403 = credenciais inválidas
                if ($code === 401 || $code === 403) {
                    delete_transient($lock_key);
                    return [
                        'success' => false,
                        'error' => __('Credenciais inválidas para API legada. Verifique email e token.', 'hng-commerce'),
                    ];
                }
                
                if ($code >= 400) {
                    delete_transient($lock_key);
                    return ['success' => false, 'error' => __('Erro na API legada: ', 'hng-commerce') . $code];
                }

                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                $subscriptions = $data['preApprovalList'] ?? [];

                if (empty($subscriptions)) {
                    break;
                }

                foreach ($subscriptions as $sub) {
                    $result = $this->create_or_update_local_subscription_legacy($sub);
                    if ($result === 'created') $created++;
                    if ($result === 'updated') $updated++;
                    $count++;
                }

                if (count($subscriptions) < $max_results) {
                    break;
                }

                $page++;

                // Safety limit
                if ($page > 20) {
                    break;
                }
            }

            update_option('hng_pagseguro_last_sync_subscriptions', current_time('mysql'));

            delete_transient($lock_key);

            return [
                'success' => true,
                'processed' => $count,
                'created' => $created,
                'updated' => $updated,
                'api' => 'legacy'
            ];
        } catch (Exception $e) {
            delete_transient($lock_key);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync an order from hng_orders to hng_payments table
     *
     * @param array $order Order data from hng_orders
     * @return string 'created', 'updated', or 'skipped'
     */
    private function sync_order_to_payments($order) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_payments';

        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 'skipped';
        }

        $order_id = $order['id'] ?? '';
        if (empty($order_id)) {
            return 'skipped';
        }

        $order_number = sanitize_text_field($order['order_number'] ?? '');
        $amount = floatval($order['total'] ?? 0);
        $status = sanitize_text_field($this->map_payment_status($order['payment_status'] ?? $order['status'] ?? ''));
        $payment_method = sanitize_text_field($order['payment_method'] ?? '');
        $transaction_id = sanitize_text_field($order['transaction_id'] ?? '');
        $created_at = sanitize_text_field($order['created_at'] ?? current_time('mysql'));

        // Check if already exists by order_id
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM $table WHERE order_id = %d OR reference_id = %s",
            $order_id,
            $order_number
        ));

        $data = [
            'amount' => $amount,
            'status' => $status,
            'payment_method' => strtolower($payment_method),
            'customer_email' => sanitize_email($order['customer_email'] ?? ''),
            'customer_name' => sanitize_text_field($order['customer_name'] ?? ''),
            'updated_at' => current_time('mysql'),
        ];

        if ($existing) {
            // Only update if status changed
            if ($existing->status !== $status) {
                $wpdb->update($table, $data, ['id' => $existing->id]);
                return 'updated';
            }
            return 'skipped';
        } else {
            $data['order_id'] = $order_id;
            $data['reference_id'] = $order_number;
            $data['pagseguro_payment_id'] = $transaction_id ?: null;
            $data['created_at'] = $created_at;
            $data['gateway'] = 'pagseguro';
            
            $wpdb->insert($table, $data);
            return 'created';
        }
    }

    /**
     * Create or update local payment from PagBank order
     *
     * @param array $order PagBank order data
     * @return string 'created', 'updated', or 'skipped'
     */
    private function create_or_update_local_payment($order) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_payments';

        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            // Table doesn't exist, skip for now
            return 'skipped';
        }

        $pagseguro_order_id = $order['id'] ?? '';
        if (empty($pagseguro_order_id)) {
            return 'skipped';
        }

        // Get charge info
        $charge = $order['charges'][0] ?? [];
        $amount = ($charge['amount']['value'] ?? 0) / 100; // Convert from cents
        $status = sanitize_text_field($this->map_payment_status($charge['status'] ?? $order['status'] ?? ''));
        $payment_method = sanitize_text_field($charge['payment_method']['type'] ?? '');
        $created_at = sanitize_text_field($order['created_at'] ?? current_time('mysql'));

        // Get customer info
        $customer = $order['customer'] ?? [];
        $customer_email = sanitize_email($customer['email'] ?? '');
        $customer_name = sanitize_text_field($customer['name'] ?? '');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE pagseguro_payment_id = %s",
            $pagseguro_order_id
        ));

        $data = [
            'amount' => $amount,
            'status' => $status,
            'payment_method' => strtolower($payment_method),
            'customer_email' => $customer_email,
            'customer_name' => $customer_name,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['id' => $existing->id]);
            return 'updated';
        } else {
            $data['pagseguro_payment_id'] = $pagseguro_order_id;
            $data['reference_id'] = sanitize_text_field($order['reference_id'] ?? '');
            $data['created_at'] = $created_at;
            $data['gateway'] = 'pagseguro';
            
            $wpdb->insert($table, $data);
            return 'created';
        }
    }

    /**
     * Create or update local customer from PagBank data or local orders
     *
     * @param array $customer Customer data (from API or local db)
     * @return string 'created', 'updated', or 'skipped'
     */
    private function create_or_update_local_customer($customer) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_customers';

        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 'skipped';
        }

        $email = sanitize_email($customer['email'] ?? '');
        if (empty($email)) {
            return 'skipped';
        }

        // Support both API format and local db format
        $full_name = sanitize_text_field($customer['name'] ?? $customer['customer_name'] ?? '');
        $name_parts = explode(' ', trim($full_name), 2);
        $first_name = sanitize_text_field($name_parts[0] ?? '');
        $last_name = sanitize_text_field($name_parts[1] ?? '');
        
        $cpf_cnpj = sanitize_text_field($customer['tax_id'] ?? $customer['document'] ?? $customer['cpf_cnpj'] ?? '');
        $phone = '';
        
        // Extract phone - support multiple formats
        if (!empty($customer['phones']) && is_array($customer['phones'])) {
            // API format
            $phone_data = $customer['phones'][0] ?? [];
            $phone = sanitize_text_field(($phone_data['area'] ?? '') . ($phone_data['number'] ?? ''));
        } elseif (!empty($customer['phone'])) {
            // Local db format
            $phone = sanitize_text_field($customer['phone']);
        } elseif (!empty($customer['customer_phone'])) {
            // Local orders format
            $phone = sanitize_text_field($customer['customer_phone']);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE email = %s",
            $email
        ));

        // Use correct column names for hng_customers table
        $data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'cpf_cnpj' => $cpf_cnpj,
            'updated_at' => current_time('mysql'),
        ];
        
        // Add source column if it exists
        $has_source = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'source'");
        if (!empty($has_source)) {
            $data['source'] = 'pagseguro';
        }

        if ($existing) {
            $wpdb->update($table, $data, ['id' => $existing->id]);
            
            // Try to link to WordPress user if not linked
            $this->maybe_link_customer_to_user($existing->id, $email);
            
            return 'updated';
        } else {
            $data['created_at'] = current_time('mysql');
            
            // Try to find WordPress user
            $user = get_user_by('email', $email);
            if ($user) {
                $data['user_id'] = $user->ID;
            }
            
            $wpdb->insert($table, $data);
            $customer_id = $wpdb->insert_id;
            
            // Link to user if found
            if ($customer_id && $user) {
                update_user_meta($user->ID, '_hng_customer_id', $customer_id);
            }
            
            return 'created';
        }
    }

    /**
     * Create or update local subscription from PagBank data
     *
     * @param array $sub PagBank subscription data
     * @return string 'created', 'updated', or 'skipped'
     */
    private function create_or_update_local_subscription($sub) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_subscriptions';

        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 'skipped';
        }

        $subscription_id = $sub['id'] ?? '';
        if (empty($subscription_id)) {
            return 'skipped';
        }

        // Map status from PagBank API
        $status_map = [
            'ACTIVE' => 'active',
            'TRIAL' => 'trial',
            'PAUSED' => 'paused',
            'SUSPENDED' => 'suspended',
            'CANCELED' => 'cancelled',
            'EXPIRED' => 'expired',
            'PENDING' => 'pending',
            'PENDING_ACTION' => 'pending',
            'OVERDUE' => 'past_due',
        ];
        $status = sanitize_text_field($status_map[strtoupper($sub['status'] ?? '')] ?? 'pending');

        // Extract amount - API returns in object format or directly
        $amount = 0;
        if (isset($sub['amount']['value'])) {
            $amount = $sub['amount']['value'] / 100; // Convert from cents
        } elseif (isset($sub['amount']) && is_numeric($sub['amount'])) {
            $amount = floatval($sub['amount']);
        }
        
        // Get customer info
        $customer = $sub['customer'] ?? [];
        $customer_email = sanitize_email($customer['email'] ?? '');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE pagseguro_subscription_id = %s",
            $subscription_id
        ));

        // Get billing cycle from plan if available
        $billing_cycle = 'monthly';
        if (isset($sub['plan']['interval']['unit'])) {
            $billing_cycle = sanitize_text_field(strtolower($sub['plan']['interval']['unit']));
        } elseif (isset($sub['interval']['unit'])) {
            $billing_cycle = sanitize_text_field(strtolower($sub['interval']['unit']));
        }

        $data = [
            'status' => $status,
            'amount' => $amount,
            'next_billing_date' => sanitize_text_field($sub['next_invoice_at'] ?? null),
            'billing_cycle' => $billing_cycle,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['id' => $existing->id]);
            return 'updated';
        } else {
            $data['pagseguro_subscription_id'] = $subscription_id;
            $data['created_at'] = sanitize_text_field($sub['created_at'] ?? current_time('mysql'));
            $data['customer_id'] = 0;
            $data['product_id'] = 0;
            $data['gateway'] = 'pagseguro';
            
            // Try to find local customer
            if ($customer_email) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $hng_customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}hng_customers WHERE email = %s",
                    $customer_email
                ));
                if ($hng_customer) {
                    $data['customer_id'] = $hng_customer->id;
                }
            }
            
            $wpdb->insert($table, $data);
            return 'created';
        }
    }

    /**
     * Create or update local subscription from legacy pre-approval data
     *
     * @param array $sub Legacy pre-approval data
     * @return string 'created', 'updated', or 'skipped'
     */
    private function create_or_update_local_subscription_legacy($sub) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_subscriptions';

        $subscription_code = $sub['code'] ?? '';
        if (empty($subscription_code)) {
            return 'skipped';
        }

        // Map status
        $status_map = [
            'ACTIVE' => 'active',
            'PAUSED' => 'paused',
            'CANCELLED' => 'cancelled',
            'CANCELLED_BY_RECEIVER' => 'cancelled',
            'CANCELLED_BY_SENDER' => 'cancelled',
            'EXPIRED' => 'expired',
            'PENDING' => 'pending',
        ];
        $status = sanitize_text_field($status_map[$sub['status'] ?? ''] ?? 'pending');

        $amount = floatval($sub['amountPerPayment'] ?? 0);
        $sender = $sub['sender'] ?? [];
        $customer_email = sanitize_email($sender['email'] ?? '');

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE pagseguro_subscription_id = %s",
            $subscription_code
        ));

        $data = [
            'status' => $status,
            'amount' => $amount,
            'next_billing_date' => sanitize_text_field($sub['nextPaymentDate'] ?? null),
            'billing_cycle' => sanitize_text_field(strtolower($sub['period'] ?? 'monthly')),
            'updated_at' => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['id' => $existing->id]);
            return 'updated';
        } else {
            $data['pagseguro_subscription_id'] = $subscription_code;
            $data['created_at'] = sanitize_text_field($sub['date'] ?? current_time('mysql'));
            $data['customer_id'] = 0;
            $data['product_id'] = 0;
            $data['gateway'] = 'pagseguro';
            
            // Try to find local customer
            if ($customer_email) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $hng_customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}hng_customers WHERE email = %s",
                    $customer_email
                ));
                if ($hng_customer) {
                    $data['customer_id'] = $hng_customer->id;
                }
            }
            
            $wpdb->insert($table, $data);
            return 'created';
        }
    }

    /**
     * Map PagBank payment status to local status
     *
     * @param string $status PagBank status
     * @return string Local status
     */
    private function map_payment_status($status) {
        $status_map = [
            'PAID' => 'paid',
            'AUTHORIZED' => 'authorized',
            'IN_ANALYSIS' => 'pending',
            'WAITING' => 'pending',
            'DECLINED' => 'failed',
            'CANCELED' => 'cancelled',
            'REFUNDED' => 'refunded',
        ];
        
        return $status_map[$status] ?? 'pending';
    }

    /**
     * Try to link a customer to a WordPress user
     *
     * @param int $customer_id HNG customer ID
     * @param string $email Customer email
     */
    private function maybe_link_customer_to_user($customer_id, $email) {
        global $wpdb;
        $table = $wpdb->prefix . 'hng_customers';

        // Check if already linked
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $customer = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $customer_id));
        
        if ($customer && !empty($customer->user_id)) {
            return; // Already linked
        }

        // Find WordPress user
        $user = get_user_by('email', $email);
        if ($user) {
            $wpdb->update($table, ['user_id' => $user->ID], ['id' => $customer_id]);
            update_user_meta($user->ID, '_hng_customer_id', $customer_id);
        }
    }

    /**
     * Get sync stats for display
     *
     * @return array
     */
    public function get_sync_stats() {
        global $wpdb;
        
        $stats = [
            'last_sync_subscriptions' => get_option('hng_pagseguro_last_sync_subscriptions', __('Nunca', 'hng-commerce')),
            'last_sync_customers' => get_option('hng_pagseguro_last_sync_customers', __('Nunca', 'hng-commerce')),
            'last_sync_payments' => get_option('hng_pagseguro_last_sync_payments', __('Nunca', 'hng-commerce')),
            'total_subscriptions' => 0,
            'total_customers' => 0,
            'total_payments' => 0,
        ];

        // Count subscriptions from PagSeguro
        $subs_table = $wpdb->prefix . 'hng_subscriptions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$subs_table'") === $subs_table) {
            $stats['total_subscriptions'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM $subs_table WHERE gateway = 'pagseguro'"
            );
        }

        // Count customers from PagSeguro
        $customers_table = $wpdb->prefix . 'hng_customers';
        if ($wpdb->get_var("SHOW TABLES LIKE '$customers_table'") === $customers_table) {
            $stats['total_customers'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM $customers_table WHERE source = 'pagseguro'"
            );
        }

        // Count payments from PagSeguro
        $payments_table = $wpdb->prefix . 'hng_payments';
        if ($wpdb->get_var("SHOW TABLES LIKE '$payments_table'") === $payments_table) {
            $stats['total_payments'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM $payments_table WHERE gateway = 'pagseguro'"
            );
        }

        return $stats;
    }
}
