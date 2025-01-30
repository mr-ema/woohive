<?php

namespace WooHive\Internal\Demons;

use WooHive\Config\Constants;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Transients {

    /**
     * Verifica si la importación está en progreso para un producto.
     *
     * @param string|int $post_sku ID o SKU del producto.
     *
     * @return bool
     */
    public static function is_importing_in_progress( string|int $post_sku ): bool {
        return get_transient( Constants::PLUGIN_SLUG . '_importing_in_progress_' . $post_sku );
    }

    /**
     * Verifica si la sincronización está en progreso para un producto.
     *
     * @param string|int $post_sku ID o SKU del producto.
     *
     * @return bool
     */
    public static function is_sync_in_progress( string|int $post_sku ): bool {
        return get_transient( Constants::PLUGIN_SLUG . '_sync_in_progress_' . $post_sku );
    }

    /**
     * Establece el estado de sincronización en progreso para un producto.
     *
     * @param string|int $post_sku ID o SKU del producto.
     * @param bool $in_progress Indica si la sincronización esta en progreso.
     *
     * @return void
     */
    public static function set_sync_in_progress( string|int $post_sku, bool $in_progress ): void {
        if ( $in_progress ) {
            set_transient( Constants::PLUGIN_SLUG . '_sync_in_progress_' . $post_sku, true, 9 );
        } else {
            delete_transient( Constants::PLUGIN_SLUG . '_sync_in_progress_' . $post_sku );
        }
    }

    /**
     * Establece el estado de importación en progreso para un producto.
     *
     * @param string|int $post_sku ID o SKU del producto.
     * @param bool $in_progress Indica si la importación esta en proceso.
     *
     * @return void
     */
    public static function set_importing_in_progress( string|int $post_sku, bool $in_progress ): void {
        if ( $in_progress ) {
            set_transient( Constants::PLUGIN_SLUG . '_importing_in_progress_' . $post_sku, true, 9 );
        } else {
            delete_transient( Constants::PLUGIN_SLUG . '_importing_in_progress_' . $post_sku );
        }
    }

    /**
     * Verifica si la sincronización de stock está en progreso para un producto.
     *
     * @param string|int $post_sku ID o SKU del producto.
     *
     * @return bool
     */
    public static function is_sync_stock_in_progress( string|int $post_sku ): bool {
        return get_transient( Constants::PLUGIN_SLUG . '_sync_stock_in_progress_' . $post_sku );
    }

    /**
     * Establece el estado de sincronización en progreso para un producto.
     *
     * @param string|int $post_sku ID o SKU del producto.
     * @param bool $in_progress Indica si la sincronización esta en progreso.
     *
     * @return void
     */
    public static function set_sync_stock_in_progress( string|int $post_sku, bool $in_progress ): void {
        if ( $in_progress ) {
            set_transient( Constants::PLUGIN_SLUG . '_sync_stock_in_progress_' . $post_sku, true, 3 );
        } else {
            delete_transient( Constants::PLUGIN_SLUG . '_sync_stock_in_progress_' . $post_sku );
        }
    }

    /**
     * Verifica si la sincronización de price está en progreso para un producto.
     *
     * @param string|int $post_sku ID o SKU del producto.
     *
     * @return bool
     */
    public static function is_sync_price_in_progress( string|int $post_sku ): bool {
        return get_transient( Constants::PLUGIN_SLUG . '_sync_price_in_progress_' . $post_sku );
    }

    /**
     * Establece el estado de sincronización en progreso para un producto.
     *
     * @param string|int $post_sku ID o SKU del producto.
     * @param bool $in_progress Indica si la sincronización esta en progreso.
     *
     * @return void
     */
    public static function set_sync_price_in_progress( string|int $post_sku, bool $in_progress ): void {
        if ( $in_progress ) {
            set_transient( Constants::PLUGIN_SLUG . '_sync_price_in_progress_' . $post_sku, true, 3 );
        } else {
            delete_transient( Constants::PLUGIN_SLUG . '_sync_price_in_progress_' . $post_sku );
        }
    }
}
