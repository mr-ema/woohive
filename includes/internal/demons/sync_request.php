<?php

namespace WooHive\Internal\Demons;

use WooHive\Config\Constants;
use WooHive\Utils\Helpers;

use WC_Product;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Request {

    public static function init(): void {
        add_action( 'save_post_product', array( self::class, 'on_product_save' ), 10, 3 );
        add_action( 'save_post_product_variation', array( self::class, 'on_product_variation_save' ), 10, 3 );
    }

    public static function on_product_update( int $product_id, WC_Product $product ): void {
        if ( $product->is_type( 'variation' ) || get_transient( 'sync_in_progress_' . $product_id ) ) {
            return;
        }

        if ( Helpers::should_sync( $product ) ) {
            set_transient( 'sync_in_progress_' . $product_id, true, 9 );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }
        }
    }

    public static function on_product_variation_update( int $product_id, WC_Product_Variation $variation ): void {
        $product_id = $variation->get_parent_id();
        if ( ! $product_id ) {
            return;
        }

        if ( get_transient( 'sync_in_progress_' . $product_id ) ) return;

        $product = wc_get_product( $product_id );
        if ( Helpers::should_sync( $product ) ) {
            set_transient( 'sync_in_progress_' . $product_id, true, 9 );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }
        }
    }

    public static function on_product_save( int $post_id, \WP_Post $post, bool $update ): void {
        if ( 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
            return;
        }

        if ( get_transient( 'sync_in_progress_' . $post_id ) ) return;

        $product = wc_get_product( $post_id );
        if ( $product && Helpers::should_sync( $product ) ) {
            set_transient( 'sync_in_progress_' . $post_id, true, 9 );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }
        }
    }

    public static function on_product_variation_save( int $post_id, \WP_Post $post, bool $update ): void {
        $product_id = get_post_field( 'post_parent', $post_id );
        if ( ! $product_id ) {
            return;
        }

        if ( get_transient( 'sync_in_progress_' . $product_id ) ) return;

        $product = wc_get_product( $product_id );
        if ( $product && Helpers::should_sync( $product ) ) {
            set_transient( 'sync_in_progress_' . $product_id, true, 9 );

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

        $should_sync_only_stock = false;
        if ( get_option( Constants::PLUGIN_SLUG . '_sync_only_stock', 'yes' ) === 'yes' ) {
            $should_sync_only_stock = true;
        }

        $server_host = $_SERVER['HTTP_HOST'];
        $response    = wp_remote_post(
            $external_site_url,
            array(
                'body'    => array(
                    'product_id' => $product_id,
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
