<?php

namespace WooHive\Internal\Demons;

use WooHive\Config\Constants;
use WooHive\Utils\Debugger;
use WooHive\Utils\Helpers;
use WooHive\WCApi\Client;
use WooHive\Internal\Demons\Transients;

use WC_Product;
use WP_Post;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Product {

    public static function init(): void {
        add_action( 'save_post_product', array( self::class, 'on_product_save' ), 10, 3 );

        add_filter( 'woocommerce_product_import_get_product_object', array( self::class, 'on_import_start' ), 10, 2 );
        add_action( 'woocommerce_product_import_inserted_product_object', array( self::class, 'on_import_finished' ), 10, 2 );

        add_action( Constants::PLUGIN_SLUG . '_sync_product', array( self::class, 'on_sync_product' ) );
    }

    /**
     * Maneja la sincronización de un producto específico.
     *
     * @param WC_Product $product Instancia del producto.
     *
     * @return void
     */
    public static function on_sync_product( WC_Product $product ): void {
        if ( Helpers::is_secondary_site() && Helpers::is_sync_only_stock_enabled() ) {
            return;
        }

        if ( $product && Helpers::should_sync( $product ) ) {
            do_action( Constants::PLUGIN_SLUG . '_before_sync_product', $product );

            $post_id = $product->get_id();
            Transients::set_sync_in_progress( $post_id, true );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }

            Transients::set_sync_in_progress( $post_id, false );
            Transients::set_importing_in_progress( $post_id, false );
        }
    }

    /**
     * Maneja la sincronización cuando se guarda un producto.
     *
     * @param int     $post_id ID del post.
     * @param WP_Post $post    Objeto del post.
     * @param bool    $update  Indica si es una actualización.
     *
     * @return void
     */
    public static function on_product_save( int $post_id, WP_Post $post, bool $update ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( Helpers::is_secondary_site() && Helpers::is_sync_only_stock_enabled() ) {
            return;
        }

        $valid_status    = in_array( $post->post_status, [ 'publish' ], true );
        if ( self::is_sync_in_progress( $post_id ) || 'product' !== $post->post_type || ! $valid_status ) {
            return;
        }

        $product = wc_get_product( $post_id );
        if ( $product && Helpers::should_sync( $product ) ) {
            Debugger::debug( 'Product Sync On Save Has Being Fire' );
            do_action( Constants::PLUGIN_SLUG . '_sync_product', $product );
        }
    }

    /**
     * Marca el inicio del proceso de importación de un producto.
     *
     * @param WC_Product $product Instancia del producto.
     * @param array      $data   Raw CSV data.
     *
     * @return WC_Product
     */
    public static function on_import_start( WC_Product $product, array $data = array() ): WC_Product {
        $post_id = $product->get_id();
        Transients::set_importing_in_progress( $post_id, true );

        return $product;
    }

    /**
     * Marca el fin del proceso de importación de un producto.
     *
     * @param WC_Product $product Instancia del producto.
     * @param array      $data   Raw CSV data.
     *
     * @return void
     */
    public static function on_import_finished( WC_Product $product, array $data = array() ): void {
        $post_id = $product->get_id();

        Transients::set_sync_in_progress( $post_id, false );
        Transients::set_importing_in_progress( $post_id, false );
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
            return;
        }

        $should_create = Helpers::is_create_products_in_secondary_sites_enabled();
        $sites = Helpers::sites();

        foreach ( $sites as $site ) {
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

            if ( $should_create ) {
                $response = $client->post( Constants::INTERNAL_API_BASE_NAME . "/products/{$sku}", array() );
            } else {
                $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$sku}", array() );
            }

            Debugger::debug( 'sync product from primary: ', $response );
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
            return;
        }

        $main_site = Helpers::primary_site();
        if ( empty( $main_site ) ) {
            return;
        }

        $client  = Client::create( $main_site['url'], $main_site['api_key'], $main_site['api_secret'] );
        $headers = array( 'X-Source-Server-Host' => $_SERVER['HTTP_HOST'] );

        $response = $client->post( Constants::INTERNAL_API_BASE_NAME . "/products/{$sku}", array(), array(), $headers );
        Debugger::debug( 'sync product from secondary: ', $response );
    }

    private static function is_sync_in_progress( int $post_id ): bool {
        $sync_in_progress = Transients::is_sync_in_progress( $post_id );
        $sync_in_progress |= Transients::is_importing_in_progress( $post_id );
        $sync_in_progress |= Transients::is_sync_stock_in_progress($post_id);

        return $sync_in_progress;
    }
}
