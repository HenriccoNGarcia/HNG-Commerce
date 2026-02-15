<?php
/**
 * Atualizador de Banco de Dados
 * Adiciona colunas e tabelas faltantes
 * 
 * @package HNG_Commerce
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Commerce_Updater {
    
    /**
     * Versão atual do banco
     */
    const DB_VERSION = '1.1.1';
    
    /**
     * Executar atualizações necessárias
     */
    public static function check_updates() {
        $current_version = get_option('hng_commerce_db_version', '1.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::run_updates($current_version);
            update_option('hng_commerce_db_version', self::DB_VERSION);
            error_log("[HNG Commerce Updater] Banco atualizado de {$current_version} para " . self::DB_VERSION);
        }
    }
    
    /**
     * Executar atualizações incrementais
     */
    private static function run_updates($from_version) {
        global $wpdb;
        
        // Atualização 1.0.0 -> 1.0.1
        if (version_compare($from_version, '1.0.1', '<')) {
            self::update_to_101();
        }
        
        // Atualização 1.0.1 -> 1.1.0 (Migrações explícitas de segurança)
        if (version_compare($from_version, '1.1.0', '<')) {
            self::update_to_110();
        }
        
        // Atualização 1.1.0 -> 1.1.1 (Garantir índices otimizados)
        if (version_compare($from_version, '1.1.1', '<')) {
            self::update_to_111();
        }
    }
    
    /**
     * Atualizaï¿½ï¿½o para versï¿½o 1.0.1
     * - Adiciona coluna user_id na tabela hng_coupon_usage
     * - Adiciona coluna product_cost na tabela hng_order_items (se nï¿½o existir)
     * - Recria tabela hng_transactions se nï¿½o existir
     */
    private static function update_to_101() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. Verificar e adicionar user_id em hng_coupon_usage
            $table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_coupon_usage') : ($wpdb->prefix . 'hng_coupon_usage');
        $column = 'user_id';
        
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = %s",
                DB_NAME,
                $table,
                $column
            )
        );
        
        if (empty($column_exists)) {
            $table_sql = '`' . str_replace('`','', $table) . '`';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin version upgrade, schema update
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_full_table_name()
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN user_id bigint(20) unsigned DEFAULT NULL AFTER customer_id");
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin version upgrade, schema update
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_full_table_name()
            $wpdb->query("ALTER TABLE {$table_sql} ADD INDEX user_id (user_id)");

                // Preencher user_id baseado em customer_id
                $customers_table = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_customers') : ($wpdb->prefix . 'hng_customers');
                $customers_table_sql = '`' . str_replace('`','', $customers_table) . '`';

                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names sanitized via hng_db_full_table_name()
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin version upgrade, data migration
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_full_table_name()
                $wpdb->query(
                    "UPDATE {$table_sql} cu
                    INNER JOIN {$customers_table_sql} c ON cu.customer_id = c.id
                    SET cu.user_id = c.user_id
                    WHERE c.user_id IS NOT NULL"
                );
        }
        
        // 2. Verificar e adicionar product_cost em hng_order_items
            $table_items = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_order_items') : ($wpdb->prefix . 'hng_order_items');
        $column_cost = 'product_cost';
        
        $cost_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = %s",
                DB_NAME,
                $table_items,
                $column_cost
            )
        );
        
        if (empty($cost_exists)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via hng_db_full_table_name()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin version upgrade, schema update
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names sanitized via hng_db_full_table_name()
            $wpdb->query("
                ALTER TABLE {$table_items} 
                ADD COLUMN product_cost decimal(10,2) DEFAULT 0.00 
                COMMENT 'Custo do produto para cï¿½lculo de lucro' 
                AFTER price
            ");
        }
        
        // 3. Verificar se tabela hng_transactions existe
            $table_transactions = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_transactions') : ($wpdb->prefix . 'hng_transactions');
            $table_transactions_sql = '`' . str_replace('`','', $table_transactions) . '`';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SHOW TABLES with sanitized table name via $wpdb->prepare()
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_transactions));
        
        if ($table_exists != $table_transactions) {
            // Criar tabela de transaï¿½ï¿½es
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via backtick escaping, dbDelta requires literal SQL
            $sql = "CREATE TABLE {$table_transactions_sql} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                order_id bigint(20) unsigned NOT NULL,
                gateway_name varchar(50) NOT NULL COMMENT 'asaas, mercadopago, etc',
                payment_method varchar(50) NOT NULL COMMENT 'pix, boleto, credit_card',
                gross_amount decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor bruto da venda',
                gateway_fee decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa do gateway',
                plugin_fee decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa do plugin HNG',
                plugin_tier tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Tier usado no cï¿½lculo (1-4)',
                product_type varchar(20) NOT NULL DEFAULT 'physical' COMMENT 'physical, digital, subscription',
                gmv_month decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'GMV do mï¿½s no momento da venda',
                net_amount decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor lï¿½quido apï¿½s taxas',
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
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        return true;
    }
    
    /**
     * Atualização para versão 1.1.0
     * - Garante existência de hng_coupon_usage com customer_id e user_id
     * - Garante existência de hng_security_log
     */
    private static function update_to_110() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // 1. Verificar e criar hng_coupon_usage
        $table_coupon = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_coupon_usage') : ($wpdb->prefix . 'hng_coupon_usage');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema check during plugin update
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_coupon));
        
        if ($table_exists !== $table_coupon) {
            $table_sql = '`' . str_replace('`', '', $table_coupon) . '`';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via backtick escaping, dbDelta requires literal SQL
            $sql_coupon = "CREATE TABLE {$table_sql} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                coupon_id bigint(20) unsigned NOT NULL,
                order_id bigint(20) unsigned NOT NULL,
                customer_id bigint(20) unsigned DEFAULT NULL,
                user_id bigint(20) unsigned DEFAULT NULL,
                discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                used_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY coupon_id (coupon_id),
                KEY order_id (order_id),
                KEY customer_id (customer_id),
                KEY user_id (user_id),
                KEY used_at (used_at)
            ) $charset_collate;";
            dbDelta($sql_coupon);
            error_log("[HNG Commerce Updater] Tabela {$table_coupon} criada");
        }
        
        // 2. Verificar e criar hng_security_log
        $table_security = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_security_log') : ($wpdb->prefix . 'hng_security_log');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema check during plugin update
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_security));
        
        if ($table_exists !== $table_security) {
            $table_sql = '`' . str_replace('`', '', $table_security) . '`';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name sanitized via backtick escaping, dbDelta requires literal SQL
            $sql_security = "CREATE TABLE {$table_sql} (
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
            dbDelta($sql_security);
            error_log("[HNG Commerce Updater] Tabela {$table_security} criada");
        }
        
        return true;
    }
    
    /**
     * Atualização para versão 1.1.1
     * - Adiciona índices compostos para melhor performance
     * - Valida integridade das tabelas críticas
     */
    private static function update_to_111() {
        global $wpdb;
        
        // Adicionar índice composto em hng_coupon_usage (coupon_id, used_at)
        $table_coupon = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_coupon_usage') : ($wpdb->prefix . 'hng_coupon_usage');
        
        // Verificar se índice já existe
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema check during plugin update
        $index_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW INDEX FROM `{$table_coupon}` WHERE Key_name = %s",
            'idx_coupon_date'
        ));
        
        if (empty($index_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema update during plugin update
            $wpdb->query("ALTER TABLE `{$table_coupon}` ADD INDEX idx_coupon_date (coupon_id, used_at)");
            error_log("[HNG Commerce Updater] Indice composto idx_coupon_date adicionado em {$table_coupon}");
        }
        
        // Adicionar índice composto em hng_security_log (type, created_at)
        $table_security = function_exists('hng_db_full_table_name') ? hng_db_full_table_name('hng_security_log') : ($wpdb->prefix . 'hng_security_log');
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database schema check during plugin update
        $index_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW INDEX FROM `{$table_security}` WHERE Key_name = %s",
            'idx_type_date'
        ));
        
        if (empty($index_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Database schema update during plugin update
            $wpdb->query("ALTER TABLE `{$table_security}` ADD INDEX idx_type_date (type, created_at)");
            error_log("[HNG Commerce Updater] Indice composto idx_type_date adicionado em {$table_security}");
        }
        
        return true;
    }
    
    /**
     * Corrigir chamadas incorretas de current_user_can
     */
    public static function fix_capability_checks() {
        // Este método será chamado para corrigir verificações de capabilities
        // que estão sendo chamadas sem post_id
        return true;
    }
}
