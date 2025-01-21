<?php

namespace WooHive\Internal\Demons;

use WooHive\Config\Constants;
use WooHive\Utils\Helpers;

use WC_Product;
use WP_Post;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Request {

    public static function init(): void {
        add_action( 'save_post_product', [ self::class, 'on_product_save' ], 10, 3 );
        add_action( 'save_post_product_variation', [ self::class, 'on_product_variation_save' ], 10, 3 );

        add_action( 'woocommerce_product_import_pre_insert_product_object', [ self::class, 'on_import_start' ], 10, 2);
        add_action( 'woocommerce_product_import_inserted_product_object', [ self::class, 'on_import_finished' ], 10, 2 );

        add_action( Constants::PLUGIN_SLUG . '_sync_product',  [ self::class, 'on_sync_product' ] );
    }

    public static function on_sync_product( WC_Product $product ): void {
        if ( $product && Helpers::should_sync( $product ) ) {
            self::set_sync_in_progress( $product->get_id(), true );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }
        }
    }

    public static function on_product_save( int $post_id, WP_Post $post, bool $update ): void {
        $sync_in_process = self::is_sync_in_progress( $post_id ) || self::is_importing_in_progress( $post_id );
        if ( $sync_in_process || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
            return;
        }

        $product = wc_get_product( $post_id );
        if ( $product && Helpers::should_sync( $product ) ) {
            self::set_sync_in_progress( $post_id, true );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }
        }
    }

    public static function on_product_variation_save( int $post_id, WP_Post $post, bool $update ): void {
        $product_id = get_post_field( 'post_parent', $post_id );
        $sync_in_process = self::is_sync_in_progress( $post_id ) || self::is_importing_in_progress( $post_id );
        if ( ! $product_id || $sync_in_process ) {
            return;
        }

        $product = wc_get_product( $product_id );
        if ( $product && Helpers::should_sync( $product ) ) {
            self::set_sync_in_progress( $post_id, true );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }
        }
    }

    public static function on_import_start( WC_Product $product, array $_data ): void {
        $post_id = $product->get_id();
        $unused = $_data;

        self::set_importing_in_progress( $post_id, true );
    }

    public static function on_import_finished( WC_Product $product, array $_data ): void {
        $post_id = $product->get_id();
        $unused = $_data;

        self::set_importing_in_progress( $post_id, false );
        do_action( Constants::PLUGIN_SLUG . '_sync_product', $product );
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

        $should_sync_only_stock = false;
        if ( get_option( Constants::PLUGIN_SLUG . '_sync_only_stock', 'yes' ) === 'yes' ) {
            $should_sync_only_stock = true;
        }

        $external_site_url = "{$main_site['url']}/wp-json/woohive/v1/sync-product";
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

    public static function is_importing_in_progress( int $post_id ): bool {
        return get_transient( Constants::PLUGIN_SLUG . '_importing_in_progress_' . $post_id);
    }

    public static function is_sync_in_progress( int $post_id ): bool {
        return get_transient( Constants::PLUGIN_SLUG . '_sync_in_progress_' . $post_id );
    }

    public  static function set_sync_in_progress( int $post_id, bool $finished ): void {
        if ( $finished ) {
            delete_transient( Constants::PLUGIN_SLUG . '_sync_in_progress_' . $post_id );
        }

        set_transient( Constants::PLUGIN_SLUG . '_sync_in_progress_' . $post_id, true, 9 );
    }

    public static function set_importing_in_progress( int $post_id, bool $finished ): void {
        if ( $finished ) {
            delete_transient( Constants::PLUGIN_SLUG . '_importing_in_progress_' . $post_id );
        }

        set_transient( Constants::PLUGIN_SLUG . '_importing_in_progress_' . $post_id, true, 9 );
    }

}
