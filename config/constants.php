<?php

namespace WooHive\Config;

class Constants {

    public const PLUGIN_NAME    = 'Multisite Product Sync';
    public const VERSION        = '1.0.0';
    public const DB_VERSION     = '1.0.0';
    public const MIN_WC_VERSION = '6.0';

    public const INTERNAL_API_BASE_NAME = 'woohive';
    public const WC_API_BASE_NAME       = 'wc';
    public const WC_API_VERSION         = 'v3';

    public const MAX_SITES = 50;

    /* Identificadores: Estas constantes permiten diferenciar al plugin de otros plugins */
    public const PLUGIN_PREFIX   = 'wmss';
    public const PLUGIN_SLUG     = 'woo_multisite_stock_sync';
    public const PLUGIN_URL_SLUG = 'woo-multisite-stock-sync';
    public const TEXT_DOMAIN     = 'woo-multisite-stock-sync';

    public static $PLUGIN_FILE;
    public static $PLUGIN_DIR_URL;
    public static $PLUGIN_BASENAME;
    public static $PLUGIN_DIR_PATH;

    public static function init( string $plugin_file ): void {
        self::$PLUGIN_FILE     = $plugin_file;
        self::$PLUGIN_DIR_URL  = plugin_dir_url( $plugin_file );
        self::$PLUGIN_BASENAME = plugin_basename( $plugin_file );
        self::$PLUGIN_DIR_PATH = plugin_dir_path( $plugin_file );
    }
}
