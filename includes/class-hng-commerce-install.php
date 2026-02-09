<?php
/**
 * Instalação e Ativação do Plugin
 *
 * @package HNG_Commerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// DB helper (used to build/validate table names when needed)
if (file_exists(HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php')) {
    require_once HNG_COMMERCE_PATH . 'includes/helpers/hng-db.php';
}

class HNG_Commerce_Install {
    
    /**
     * Versão do banco de dados
     */
    private static $db_version = '1.0.0';
    
    /**
     * Hook de ativação
     */
    public static function activate() {
        self::create_tables();
        self::create_pages();
        self::set_default_options();
        self::create_roles();
        
        // Salvar versá¡o
        update_option('hng_commerce_version', HNG_COMMERCE_VERSION);
        update_option('hng_commerce_db_version', self::$db_version);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Redirecionar para assistente de configuraá§á¡o
        set_transient('hng_commerce_activation_redirect', true, 30);
        
        // Ledger
        if (function_exists('hng_ledger_create_table')) {
            hng_ledger_create_table();
        }
        
        // Conversion Tracking table
        if (class_exists('HNG_Conversion_Tracker')) {
            HNG_Conversion_Tracker::create_table();
        }
        
        // Refund Requests table
        if (class_exists('HNG_Refund_Requests')) {
            HNG_Refund_Requests::create_table();
        }
    }
    
    /**
     * Hook de desativação
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Atualizar capabilities (pode ser chamado manualmente)
     */
    public static function update_capabilities() {
        self::create_roles();
        return true;
    }
    
    /**
     * Criar tabelas no banco de dados
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Tabelas (usar helper para montar nomes sanitizados)
        $t_customers = hng_db_full_table_name('hng_customers');
        $t_customer_tokens = hng_db_full_table_name('hng_customer_payment_tokens');
        $t_subscriptions = hng_db_full_table_name('hng_subscriptions');
        $t_security_log = hng_db_full_table_name('hng_security_log');
        $t_orders = hng_db_full_table_name('hng_orders');
        $t_product_meta = hng_db_full_table_name('hng_product_meta');
        $t_variations = hng_db_full_table_name('hng_product_variations');
        $t_data_requests = hng_db_full_table_name('hng_data_requests');
        $t_order_items = hng_db_full_table_name('hng_order_items');
        $t_order_meta = hng_db_full_table_name('hng_order_meta');
        $t_order_notes = hng_db_full_table_name('hng_order_notes');
        $t_transactions = hng_db_full_table_name('hng_transactions');
        $t_customer_addresses = hng_db_full_table_name('hng_customer_addresses');
        $t_cart_abandoned = hng_db_full_table_name('hng_cart_abandoned');
        $t_coupons = hng_db_full_table_name('hng_coupons');
        $t_coupon_usage = hng_db_full_table_name('hng_coupon_usage');
        $t_analytics = hng_db_full_table_name('hng_analytics_events');

        // Tabela de clientes (estendida)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_customers = "CREATE TABLE IF NOT EXISTS `{$t_customers}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            email varchar(255) NOT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            cpf_cnpj varchar(20) DEFAULT NULL,
            birth_date date DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_customers);

        // Tabela de tokens de pagamento (criptografados)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_tokens = "CREATE TABLE IF NOT EXISTS `{$t_customer_tokens}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            gateway varchar(50) NOT NULL,
            token text NOT NULL COMMENT 'Número do cartão criptografado AES-256-GCM',
            token_hash varchar(64) NOT NULL COMMENT 'Hash SHA-256 para auditoria',
            card_brand varchar(20) DEFAULT NULL,
            card_last4 varchar(4) DEFAULT NULL,
            card_expiry varchar(7) DEFAULT NULL COMMENT 'Format: MM/YYYY',
            holder_name varchar(255) DEFAULT NULL,
            is_default tinyint(1) DEFAULT 0,
            gateway_token varchar(255) DEFAULT NULL COMMENT 'Token do gateway (Asaas, etc)',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY token_hash (token_hash),
            KEY is_default (is_default)
        ) $charset_collate;";
        dbDelta($sql_tokens);

        // Tabela de assinaturas
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via $wpdb->prefix, dbDelta requires literal SQL
        $sql_subscriptions = "CREATE TABLE IF NOT EXISTS `{$t_subscriptions}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            payment_token_id bigint(20) unsigned DEFAULT NULL,
            billing_cycle varchar(20) NOT NULL COMMENT 'monthly, quarterly, annual',
            amount decimal(10,2) NOT NULL,
            next_billing_date datetime DEFAULT NULL,
            last_billing_date datetime DEFAULT NULL,
            trial_end_date datetime DEFAULT NULL,
            canceled_at datetime DEFAULT NULL,
            suspended_at datetime DEFAULT NULL,
            retry_count int(11) DEFAULT 0,
            max_retries int(11) DEFAULT 3,
            retry_interval_days int(11) DEFAULT 3,
            grace_period_days int(11) DEFAULT 7,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY next_billing_date (next_billing_date)
        ) $charset_collate;";
        dbDelta($sql_subscriptions);

        // Tabela de log de seguraná§a
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_security_log = "CREATE TABLE IF NOT EXISTS `{$t_security_log}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL COMMENT 'csrf_failure, rate_limit, sql_injection, etc',
            ip varchar(45) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            severity varchar(20) DEFAULT 'medium' COMMENT 'low, medium, high, critical',
            data longtext DEFAULT NULL COMMENT 'JSON data',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY ip (ip),
            KEY user_id (user_id),
            KEY severity (severity),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_security_log);

        // Tabela de pedidos
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_orders = "CREATE TABLE IF NOT EXISTS `{$t_orders}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned DEFAULT NULL,
            order_number varchar(50) NOT NULL,
            customer_id bigint(20) unsigned DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'hng-pending',
            currency varchar(10) NOT NULL DEFAULT 'BRL',
            subtotal decimal(12,6) NOT NULL DEFAULT 0.000000,
            shipping_total decimal(12,6) NOT NULL DEFAULT 0.000000,
            discount_total decimal(12,6) NOT NULL DEFAULT 0.000000,
            total decimal(12,6) NOT NULL DEFAULT 0.000000,
            commission decimal(12,6) NOT NULL DEFAULT 0.000000 COMMENT 'Comissão HNG',
            product_type varchar(30) DEFAULT 'physical',
            payment_method varchar(50) DEFAULT NULL,
            payment_method_title varchar(100) DEFAULT NULL,
            payment_status varchar(20) DEFAULT 'pending',
            billing_first_name varchar(100) DEFAULT NULL,
            billing_last_name varchar(100) DEFAULT NULL,
            billing_email varchar(255) DEFAULT NULL,
            billing_phone varchar(50) DEFAULT NULL,
            billing_cpf varchar(20) DEFAULT NULL,
            billing_postcode varchar(20) DEFAULT NULL,
            billing_address_1 varchar(255) DEFAULT NULL,
            billing_number varchar(20) DEFAULT NULL,
            billing_address_2 varchar(255) DEFAULT NULL,
            billing_neighborhood varchar(100) DEFAULT NULL,
            billing_city varchar(100) DEFAULT NULL,
            billing_state varchar(10) DEFAULT NULL,
            shipping_method varchar(100) DEFAULT NULL,
            customer_note text DEFAULT NULL,
            customer_ip varchar(50) DEFAULT NULL,
            customer_user_agent text DEFAULT NULL,
            transaction_id varchar(255) DEFAULT NULL,
            gateway varchar(50) DEFAULT NULL,
            source_page_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_number (order_number),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY payment_status (payment_status),
            KEY payment_method (payment_method),
            KEY product_type (product_type),
            KEY created_at (created_at),
            KEY post_id (post_id)
        ) $charset_collate;";
        dbDelta($sql_orders);

        // Tabela de produtos (meta data adicional)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_product_meta = "CREATE TABLE IF NOT EXISTS `{$t_product_meta}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";
        dbDelta($sql_product_meta);

        // Tabela de variaá§áµes
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_variations = "CREATE TABLE IF NOT EXISTS `{$t_variations}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            sku varchar(100) DEFAULT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            sale_price decimal(10,2) DEFAULT NULL,
            stock_quantity int(11) DEFAULT NULL,
            stock_status varchar(20) DEFAULT 'instock',
            attributes longtext DEFAULT NULL COMMENT 'JSON attributes',
            image_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sku (sku),
            KEY product_id (product_id),
            KEY stock_status (stock_status)
        ) $charset_collate;";
        dbDelta($sql_variations);

        // Tabela LGPD - Requisiá§áµes de dados
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_data_requests = "CREATE TABLE IF NOT EXISTS `{$t_data_requests}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            request_type varchar(20) NOT NULL COMMENT 'export, delete, anonimize',
            status varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, completed, canceled',
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            file_path varchar(500) DEFAULT NULL COMMENT 'Para exports',
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY request_type (request_type),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_data_requests);

        // Tabela de itens do pedido
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_order_items = "CREATE TABLE IF NOT EXISTS `{$t_order_items}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            product_name varchar(255) NOT NULL,
            product_type varchar(20) DEFAULT 'physical' COMMENT 'physical, digital, subscription',
            variation_id bigint(20) unsigned DEFAULT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            product_cost decimal(10,2) DEFAULT 0.00 COMMENT 'Custo do produto para cá¯Â¿Â½lculo de lucro',
            subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            commission decimal(10,2) NOT NULL DEFAULT 0.00,
            commission_rate decimal(5,2) NOT NULL DEFAULT 0.00,
            meta_data longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta($sql_order_items);

        // Adicionar coluna product_cost se ná¡o existir (migration)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name()
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema installation, column existence check
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_full_table_name()
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$t_order_items}` LIKE 'product_cost'");
        if (empty($column_exists)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema installation
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_full_table_name()
            $wpdb->query("ALTER TABLE `{$t_order_items}` ADD COLUMN product_cost decimal(10,2) DEFAULT 0.00 COMMENT 'Custo do produto para cá¡Â¡lculo de lucro' AFTER price");
        }

        // Tabela de meta dados do pedido
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_order_meta = "CREATE TABLE IF NOT EXISTS `{$t_order_meta}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";
        dbDelta($sql_order_meta);

        // Tabela de notas do pedido
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_order_notes = "CREATE TABLE IF NOT EXISTS `{$t_order_notes}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            note text NOT NULL,
            note_type varchar(20) DEFAULT 'private',
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id)
        ) $charset_collate;";
        dbDelta($sql_order_notes);

        // Tabela de transaá§áµes financeiras (taxas e fees)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_transactions = "CREATE TABLE IF NOT EXISTS `{$t_transactions}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            gateway_name varchar(50) NOT NULL COMMENT 'asaas, mercadopago, etc',
            payment_method varchar(50) NOT NULL COMMENT 'pix, boleto, credit_card',
            gross_amount decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor bruto da venda',
            gateway_fee decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa do gateway',
            plugin_fee decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa do plugin HNG',
            plugin_tier tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Tier usado no cá¯Â¿Â½lculo (1-4)',
            product_type varchar(20) NOT NULL DEFAULT 'physical' COMMENT 'physical, digital, subscription',
            gmv_month decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'GMV do má¯Â¿Â½s no momento da venda',
            net_amount decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor lá¯Â¿Â½quido apá¯Â¿Â½s taxas',
            signature_valid tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Assinatura HMAC validada?',
            is_fallback tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Calculado localmente (VPS offline)?',
            vps_response text DEFAULT NULL COMMENT 'Resposta JSON completa da VPS',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY gateway_name (gateway_name),
            KEY plugin_tier (plugin_tier),
            KEY created_at (created_at),
            KEY is_fallback (is_fallback)
        ) $charset_collate;";
        dbDelta($sql_transactions);

        // Tabela de endereá§os
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_addresses = "CREATE TABLE IF NOT EXISTS `{$t_customer_addresses}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'shipping',
            first_name varchar(255) DEFAULT NULL,
            last_name varchar(255) DEFAULT NULL,
            company varchar(255) DEFAULT NULL,
            address_1 varchar(255) DEFAULT NULL,
            address_2 varchar(255) DEFAULT NULL,
            city varchar(255) DEFAULT NULL,
            state varchar(50) DEFAULT NULL,
            postcode varchar(20) DEFAULT NULL,
            country varchar(2) DEFAULT 'BR',
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id)
        ) $charset_collate;";
        dbDelta($sql_addresses);

        // Tabela de carrinho abandonado
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_abandoned_cart = "CREATE TABLE IF NOT EXISTS `{$t_cart_abandoned}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            customer_email varchar(255) DEFAULT NULL,
            cart_data longtext NOT NULL,
            status varchar(20) DEFAULT 'abandoned',
            recovery_sent_at datetime DEFAULT NULL,
            recovered_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY customer_email (customer_email),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_abandoned_cart);

        // Tabela de cupons
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_coupons = "CREATE TABLE IF NOT EXISTS `{$t_coupons}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            type varchar(20) DEFAULT 'percent',
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            usage_limit int(11) DEFAULT NULL,
            usage_count int(11) DEFAULT 0,
            min_purchase decimal(10,2) DEFAULT NULL,
            max_discount decimal(10,2) DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_coupons);

        // Tabela de uso de cupons
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_coupon_usage = "CREATE TABLE IF NOT EXISTS `{$t_coupon_usage}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned DEFAULT NULL,
            discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            used_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY coupon_id (coupon_id),
            KEY order_id (order_id)
        ) $charset_collate;";
        dbDelta($sql_coupon_usage);

        // Tabela de analytics
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name(), dbDelta requires literal SQL
        $sql_analytics = "CREATE TABLE IF NOT EXISTS `{$t_analytics}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            product_id bigint(20) unsigned DEFAULT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            customer_id bigint(20) unsigned DEFAULT NULL,
            session_id varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY product_id (product_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_analytics);
    }
    
    /**
     * Criar pá¯Â¿Â½ginas necessá¯Â¿Â½rias
     */
    private static function create_pages() {
        $pages = array(
            'shop' => array(
                'title' => 'Loja',
                'content' => '[hng_products]',
                'slug' => 'loja'
            ),
            'cart' => array(
                'title' => 'Carrinho',
                'content' => '[hng_cart]',
                'slug' => 'carrinho'
            ),
            'checkout' => array(
                'title' => 'Finalizar Compra',
                'content' => '[hng_checkout]',
                'slug' => 'finalizar-compra'
            ),
            'my-account' => array(
                'title' => 'Minha Conta',
                'content' => '[hng_account]',
                'slug' => 'minha-conta'
            ),
            'order-confirmation' => array(
                'title' => 'Pedido Confirmado',
                'content' => '[hng_order_received]',
                'slug' => 'obrigado'
            ),
            'client-registration' => array(
                'title' => 'Seja Nosso Cliente',
                'content' => '', // Uses page template
                'slug' => 'seja-nosso-cliente',
                'template' => 'page-seja-nosso-cliente.php'
            )
        );
        
        foreach ($pages as $key => $page) {
            // Verificar se já existe
            $existing = get_page_by_path($page['slug']);
            if (!$existing) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_name' => $page['slug'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ));
                
                // Definir template se especificado
                if (!empty($page['template']) && $page_id && !is_wp_error($page_id)) {
                    update_post_meta($page_id, '_wp_page_template', $page['template']);
                }
                
                // Salvar ID da página
                update_option('hng_commerce_' . $key . '_page_id', $page_id);
            } else {
                update_option('hng_commerce_' . $key . '_page_id', $existing->ID);
                
                // Atualizar template se necessário
                if (!empty($page['template'])) {
                    $current_template = get_post_meta($existing->ID, '_wp_page_template', true);
                    if (empty($current_template) || $current_template === 'default') {
                        update_post_meta($existing->ID, '_wp_page_template', $page['template']);
                    }
                }
            }
        }
    }
    
    /**
     * Definir opá¯Â¿Â½á¯Â¿Â½es padrá¯Â¿Â½o
     */
    private static function set_default_options() {
        $defaults = array(
            'currency' => 'BRL',
            'currency_symbol' => 'R$',
            'decimal_separator' => ',',
            'thousand_separator' => '.',
            'decimals' => 2,
            'store_name' => get_bloginfo('name'),
            'store_email' => get_option('admin_email'),
            'default_country' => 'BR',
            'tax_enabled' => false,
            'stock_management' => true,
            'enable_reviews' => true,
            'enable_guest_checkout' => true
        );

        // Option de debug (opt-in) e controle de logs por padrá¡o
        if (!isset($defaults['hng_enable_debug'])) {
            $defaults['hng_enable_debug'] = false;
        }
        if (!isset($defaults['hng_transaction_log'])) {
            $defaults['hng_transaction_log'] = false;
        }
        
        foreach ($defaults as $key => $value) {
            if (get_option('hng_commerce_' . $key) === false) {
                update_option('hng_commerce_' . $key, $value);
            }
        }
    }
    
    /**
     * Criar roles customizadas
     */
    private static function create_roles() {
        // Adicionar capabilities ao Administrador
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_hng_commerce');
            $admin->add_cap('manage_products');
            $admin->add_cap('manage_orders');
            $admin->add_cap('view_reports');
        }
        
        // Role de Gerente de Loja
        add_role('shop_manager', __('Gerente de Loja', 'hng-commerce'), array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'manage_hng_commerce' => true,
            'manage_products' => true,
            'manage_orders' => true,
            'view_reports' => true
        ));
        
        // Role de Vendedor (para marketplace)
        add_role('vendor', __('Vendedor', 'hng-commerce'), array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'manage_products' => true,
            'view_orders' => true
        ));
        
        // Role de Cliente HNG
        add_role('hng_customer', __('Cliente HNG', 'hng-commerce'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ));
    }
}
