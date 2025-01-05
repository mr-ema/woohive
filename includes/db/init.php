<?php

namespace WooHive\Database;

use WooHive\Config\Constants;


/** Prevent direct access to the script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DB_Init {

    /**
     * Initialize the database tables.
     *
     * This method checks if the necessary tables for the plugin exist, and if not,
     * it creates them. This should be run on plugin activation or when needed.
     *
     * @return void
     */
    public static function init(): void {
        global $wpdb;

        // Table name with the WordPress prefix
        $table_name = $wpdb->prefix . Constants::PLUGIN_PREFIX . '_log';

        // Check if the table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
            // If the table doesn't exist, create it
            self::create_log_table();
        }
    }

    /**
     * Create the log table if it does not exist.
     *
     * This function creates the '_log' table with the necessary fields for logging
     * the stock sync process. The table will store information such as the product ID,
     * message, and error state.
     *
     * @return void
     */
    private static function create_log_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . Constants::PLUGIN_PREFIX . '_log';
        $charset_collate = $wpdb->get_charset_collate();

        // SQL query to create the table
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned,
            type varchar(255) default '',
            message text,
            data longtext,
            has_error smallint(1) default 0,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Include the upgrade file to run dbDelta
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

// Register plugin activation hook to initialize the database
register_activation_hook(
    Constants::PLUGIN_FILE,
    function () {
        // Initialize the database table when the plugin is activated
        DB_Init::init();
    }
);

/**
 * Check if the database schema needs updating and update the table if necessary.
 *
 * This function is called during the 'plugins_loaded' hook to ensure that the table
 * is updated if the plugin version changes.
 *
 * @return void
 */
add_action(
    'plugins_loaded',
    function () {
        // If the database version is different from the current version, update the table
        if ( get_option( Constants::PLUGIN_PREFIX . '_db_version' ) != Constants::DB_VERSION ) {
            DB_Init::init();
            // Update the option with the new database version
            update_option( Constants::PLUGIN_PREFIX . '_db_version', Constants::DB_VERSION );
        }
    }
);
