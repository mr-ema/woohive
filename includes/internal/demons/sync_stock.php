<?php

namespace WooHive\Internal\Demons;

use WooHive\Config\Constants;
use WooHive\Utils\Debugger;
use WooHive\WCApi\Client;
use WooHive\Utils\Helpers;

use WC_Product;
use WC_Product_Variation;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Stock {

    public static function init(): void {
        add_action( 'woocommerce_product_set_stock', [ self::class, 'on_stock_update' ], 10, 2 );
        add_action( 'woocommerce_variation_set_stock', [ self::class, 'on_variation_stock_update' ], 10, 2 );
    }

    /**
     * Maneja la actualización del stock de un producto.
     *
     * @param WC_Product $product Instancia del producto actualizado.
     *
     * @return void
     */
    public static function on_stock_update( WC_Product $product ): void {
        $product_id = $product->get_id();
        if ( self::is_sync_stock_in_progress( $product_id ) ) {
            return;
        }

        if ( Helpers::should_sync_stock( $product ) ) {
            self::set_sync_stock_in_progress( $product_id, true );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site( $product );
            }
        }
    }

    /**
     * Maneja la actualización del stock de una variación de producto.
     *
     * @param WC_Product_Variation $variation Instancia de la variación de producto.
     *
     * @return void
     */
    public static function on_variation_stock_update( WC_Product_Variation $variation ): void {
        $variation_id = $variation->get_id();
        if ( self::is_sync_stock_in_progress( $variation_id ) ) {
            return;
        }

        if ( Helpers::should_sync_stock( $variation ) ) {
            self::set_sync_stock_in_progress( $variation_id, true );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites( $variation);
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site( $variation );
            }
        }
    }

    /**
     * Sincroniza el stock del producto con los sitios secundarios configurados.
     *
     * @param WC_Product $product Instancia del producto.
     *
     * @return void
     */
    private static function sync_to_secondary_sites( WC_Product $product ): void {
        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            return;
        }

        $sites = Helpers::sites();
        if ( empty( $sites ) ) {
            return;
        }

        $product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
        $variation_id = $product->is_type( 'variation' ) ? $product->get_id() : null;

        foreach ( $sites as $site ) {
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
            $data = [
                'product_id'    => $product_id,
                'variation_id'  => $variation_id,
                'from'          => 'primary',
            ];

            $response = $client->put( Constants::INTERNAL_API_BASE_NAME . '/sync-stock', $data );
            Debugger::debug( 'sync stock from primary: ', $response );
        }
    }

    /**
     * Sincroniza el stock del producto al sitio principal.
     *
     * @param WC_Product $product Instancia del producto.
     *
     * @return void
     */
    private static function sync_to_primary_site( WC_Product $product ): void {
        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            return;
        }

        $main_site = Helpers::primary_site();
        if ( empty( $main_site ) ) {
            return;
        }

        $product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
        $variation_id = $product->is_type( 'variation' ) ? $product->get_id() : null;

        $client = Client::create( $main_site['url'], $main_site['api_key'], $main_site['api_secret'] );

        $server_host = $_SERVER['HTTP_HOST'];
        $headers = [ 'X-Source-Server-Host' => $server_host ];
        $data = [
            'product_id'    => $product_id,
            'variation_id'  => $variation_id,
            'from'          => 'secondary',
        ];

        $response = $client->put( Constants::INTERNAL_API_BASE_NAME . '/sync-stock', $data, [], $headers );
        Debugger::debug( 'sync stock from secondary: ', $response );
    }

    /**
     * Verifica si la sincronización de stock está en progreso para un producto.
     *
     * @param int $post_id ID del producto.
     *
     * @return bool
     */
    public static function is_sync_stock_in_progress( int $post_id ): bool {
        return get_transient( Constants::PLUGIN_SLUG . '_sync_stock_in_progress_' . $post_id );
    }

    /**
     * Establece el estado de sincronización en progreso para un producto.
     *
     * @param int  $post_id  ID del producto.
     * @param bool $in_progress Indica si la sincronización esta en progreso.
     *
     * @return void
     */
    public  static function set_sync_stock_in_progress( int $post_id, bool $in_progress ): void {
        if ( $in_progress ) {
            set_transient( Constants::PLUGIN_SLUG . '_sync_stock_in_progress_' . $post_id, true, 3 );
        } else {
            delete_transient( Constants::PLUGIN_SLUG . '_sync_stock_in_progress_' . $post_id );
        }
    }
}
