<?php
/**
 * PadelZero - Database Setup
 */

if (!defined('ABSPATH')) exit;

function pz_create_database_tables() {
    pz_create_wallet_table();
}

function pz_create_wallet_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'pz_wallet_transactions';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `balance_after` decimal(10,2) NOT NULL,
        `type` varchar(20) NOT NULL,
        `note` text DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `created_by` bigint(20) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) {$charset_collate};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

