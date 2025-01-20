<?php

namespace WooHive\Internal\Demons;

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
        if ( get_transient( 'stock_sync_in_progress_' .  $product_id ) ) {
            return;
        }

        if ( Helpers::should_sync_stock( $product ) ) {
            set_transient( 'stock_sync_in_progress_' . $product_id, true, 6 );

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
        if ( get_transient( 'stock_sync_in_progress_' .  $variation_id ) ) {
            return;
        }

        if ( Helpers::should_sync_stock( $variation ) ) {
            set_transient( 'stock_sync_in_progress_' . $variation_id, true, 6 );

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

        $should_sync_only_stock = true;
        $product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
        $variation_id = $product->is_type( 'variation' ) ? $product->get_id() : null;

        foreach ( $sites as $site ) {
            // temporal way of handling endpoints
            $external_site_url = "{$site['url']}/wp-json/woohive/v1/sync-product";

            $response = wp_remote_post(
                $external_site_url,
                array(
                    'body' => array(
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'from'       => 'primary',
                        'should_sync_only_stock' => $should_sync_only_stock
                    ),
                )
            );
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

        $external_site_url = "{$main_site['url']}/wp-json/woohive/v1/sync-product";
        $should_sync_only_stock = true;
        $product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
        $variation_id = $product->is_type( 'variation' ) ? $product->get_id() : null;

        $server_host = $_SERVER['HTTP_HOST'];
        $response    = wp_remote_post(
            $external_site_url,
            array(
                'body'    => array(
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'from'       => 'secondary',
                    'should_sync_only_stock' => $should_sync_only_stock
                ),
                'headers' => array(
                    'X-Source-Server-Host' => $server_host,
                ),
            )
        );
    }
}
