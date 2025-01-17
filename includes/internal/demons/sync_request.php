<?php

namespace WooHive\Internal\Demons;

use WooHive\Utils\Helpers;

use WC_Product;
use WC_Product_Variation;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Request {

    public static function init(): void {
        add_action( 'woocommerce_update_product', array( self::class, 'on_product_update' ), 10, 2 );
        add_action( 'woocommerce_update_product_variation', array( self::class, 'on_product_variation_update' ), 10, 2 );
    }

    public static function on_product_update( int $product_id, WC_Product $product ): void {
        if ( get_transient( 'sync_in_progress_' . $product_id ) ) {
            return; // Prevent firing the function twice
        }

        set_transient( 'sync_in_progress_' . $product_id, true, 9 );

        if ( Helpers::should_sync( $product ) ) {
            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }
        }
    }

    public static function on_product_variation_update( int $product_id, WC_Product_Variation $variation ): void {
        $product_id = $variation->get_parent_id();
        if ( get_transient( 'sync_in_progress_' . $product_id ) ) {
            return; // Prevent firing the function twice
        }

        set_transient( 'sync_in_progress_' . $product_id, true, 9 );

        $product = wc_get_product( $product_id );
        if ( Helpers::should_sync( $product ) ) {
            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }
        }
    }

    /**
     * Sincroniza los datos de un producto estándar a los sitios secundarios.
     *
     * @param WC_Product $product Instancia del producto.
     *
     * @return void
     */
    private static function sync_to_secondary_sites_data( WC_Product $product ): void {
        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            return; // Si el producto no tiene SKU, no se sincroniza.
        }

        $product_id = $product->get_id();

        $sites = Helpers::sites();
        foreach ( $sites as $site ) {
            // temporal way of handling endpoints
            $external_site_url = "{$site['url']}/wp-json/woohive/v1/sync-product";

            $response = wp_remote_post(
                $external_site_url,
                array(
                    'body' => array(
                        'product_id' => $product_id,
                        'from'       => 'primary',
                    ),
                )
            );
        }
    }

    /**
     * Sincroniza los datos de un producto estándar al sitio principal.
     *
     * @param WC_Product $product Instancia del producto.
     *
     * @return void
     */
    private static function sync_to_primary_site_data( WC_Product $product ): void {
        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            return; // Si el producto no tiene SKU, no se sincroniza.
        }

        $product_id = $product->get_id();

        $main_site = Helpers::primary_site();
        if ( empty( $main_site ) ) {
            return; // Si no hay un sitio principal configurado, no se realiza la sincronización.
        }

        $sites             = Helpers::sites();
        $external_site_url = "{$main_site['url']}/wp-json/woohive/v1/sync-product";

            $server_host = $_SERVER['HTTP_HOST'];
            $response    = wp_remote_post(
                $external_site_url,
                array(
                    'body'    => array(
                        'product_id' => $product_id,
                        'from'       => 'secondary',
                    ),
                    'headers' => array(
                        'X-Source-Server-Host' => $server_host,
                    ),
                )
            );
    }
}
