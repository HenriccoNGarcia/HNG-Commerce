<?php
/**
 * Migration: Add viewed column to hng_orders table
 * 
 * Para suportar notificações de novos pedidos
 * 
 * @package HNG_Commerce
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adicionar coluna viewed na tabela hng_orders
 */
function hng_migration_add_orders_viewed_column() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'hng_orders';
    
    // Verificar se a tabela existe
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        return;
    }
    
    // Verificar se a coluna já existe
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'viewed'
        )
    );
    
    // Adicionar coluna se não existir
    if (empty($column_exists)) {
        $wpdb->query(
            "ALTER TABLE `{$table_name}` 
             ADD COLUMN `viewed` TINYINT(1) NOT NULL DEFAULT 0 
             AFTER `status`"
        );
        
        error_log('[HNG Commerce] Added viewed column to hng_orders table');
    }
}

// Executar migration
add_action('admin_init', 'hng_migration_add_orders_viewed_column');
