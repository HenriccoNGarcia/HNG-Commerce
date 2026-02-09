<?php
/**
 * Migration: Update hng_orders table to v1.2.12 schema
 * 
 * Adiciona colunas faltantes na tabela hng_orders
 * 
 * @package HNG_Commerce
 * @since 1.2.12
 */

if (!defined('ABSPATH')) {
    exit;
}

class HNG_Migration_Orders_Schema {
    
    /**
     * Run the migration
     */
    public static function run() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hng_orders';
        $db_name = DB_NAME;
        
        // Verificar se tabela existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if (!$table_exists) {
            return; // Tabela não existe, será criada pelo install
        }
        
        // Lista de colunas necessárias com suas definições
        $required_columns = [
            'post_id' => "ALTER TABLE `{$table_name}` ADD COLUMN `post_id` bigint(20) unsigned DEFAULT NULL AFTER `id`",
            'currency' => "ALTER TABLE `{$table_name}` ADD COLUMN `currency` varchar(10) NOT NULL DEFAULT 'BRL' AFTER `status`",
            'subtotal' => "ALTER TABLE `{$table_name}` MODIFY COLUMN `subtotal` decimal(12,6) NOT NULL DEFAULT 0.000000",
            'shipping_total' => "ALTER TABLE `{$table_name}` ADD COLUMN `shipping_total` decimal(12,6) NOT NULL DEFAULT 0.000000 AFTER `subtotal`",
            'discount_total' => "ALTER TABLE `{$table_name}` ADD COLUMN `discount_total` decimal(12,6) NOT NULL DEFAULT 0.000000 AFTER `shipping_total`",
            'product_type' => "ALTER TABLE `{$table_name}` ADD COLUMN `product_type` varchar(30) DEFAULT 'physical' AFTER `commission`",
            'payment_method_title' => "ALTER TABLE `{$table_name}` ADD COLUMN `payment_method_title` varchar(100) DEFAULT NULL AFTER `payment_method`",
            'billing_first_name' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_first_name` varchar(100) DEFAULT NULL AFTER `payment_status`",
            'billing_last_name' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_last_name` varchar(100) DEFAULT NULL AFTER `billing_first_name`",
            'billing_email' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_email` varchar(255) DEFAULT NULL AFTER `billing_last_name`",
            'billing_phone' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_phone` varchar(50) DEFAULT NULL AFTER `billing_email`",
            'billing_cpf' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_cpf` varchar(20) DEFAULT NULL AFTER `billing_phone`",
            'billing_postcode' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_postcode` varchar(20) DEFAULT NULL AFTER `billing_cpf`",
            'billing_address_1' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_address_1` varchar(255) DEFAULT NULL AFTER `billing_postcode`",
            'billing_number' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_number` varchar(20) DEFAULT NULL AFTER `billing_address_1`",
            'billing_address_2' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_address_2` varchar(255) DEFAULT NULL AFTER `billing_number`",
            'billing_neighborhood' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_neighborhood` varchar(100) DEFAULT NULL AFTER `billing_address_2`",
            'billing_city' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_city` varchar(100) DEFAULT NULL AFTER `billing_neighborhood`",
            'billing_state' => "ALTER TABLE `{$table_name}` ADD COLUMN `billing_state` varchar(10) DEFAULT NULL AFTER `billing_city`",
            'customer_note' => "ALTER TABLE `{$table_name}` ADD COLUMN `customer_note` text DEFAULT NULL AFTER `shipping_method`",
            'customer_ip' => "ALTER TABLE `{$table_name}` ADD COLUMN `customer_ip` varchar(50) DEFAULT NULL AFTER `customer_note`",
            'customer_user_agent' => "ALTER TABLE `{$table_name}` ADD COLUMN `customer_user_agent` text DEFAULT NULL AFTER `customer_ip`",
            'gateway' => "ALTER TABLE `{$table_name}` ADD COLUMN `gateway` varchar(50) DEFAULT NULL AFTER `transaction_id`",
            'source_page_id' => "ALTER TABLE `{$table_name}` ADD COLUMN `source_page_id` bigint(20) unsigned DEFAULT NULL AFTER `gateway`",
        ];
        
        foreach ($required_columns as $column => $alter_sql) {
            // Verificar se coluna já existe
            $column_exists = $wpdb->get_var(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = '{$db_name}' 
                 AND TABLE_NAME = '{$table_name}' 
                 AND COLUMN_NAME = '{$column}'"
            );
            
            if (!$column_exists) {
                // Adicionar coluna
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL statement (ALTER TABLE) não suporta placeholders
                $wpdb->query($alter_sql);
                error_log("HNG Commerce: Added column '{$column}' to {$table_name}");
            }
        }
        
        // Adicionar índices se não existirem
        $indexes = [
            'post_id' => "ALTER TABLE `{$table_name}` ADD INDEX `post_id` (`post_id`)",
            'payment_method' => "ALTER TABLE `{$table_name}` ADD INDEX `payment_method` (`payment_method`)",
            'product_type' => "ALTER TABLE `{$table_name}` ADD INDEX `product_type` (`product_type`)",
        ];
        
        foreach ($indexes as $index_name => $index_sql) {
            $index_exists = $wpdb->get_var(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = '{$db_name}' 
                 AND TABLE_NAME = '{$table_name}' 
                 AND INDEX_NAME = '{$index_name}'"
            );
            
            if (!$index_exists) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL statement (CREATE INDEX) não suporta placeholders
                $wpdb->query($index_sql);
            }
        }
        
        error_log('HNG Commerce: Orders table schema migration completed');
    }
}
