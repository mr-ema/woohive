<?php

namespace WooHive\Internal\Demons;

use WooHive\Config\Constants;
use WooHive\Utils\Debugger;
use WooHive\Utils\Helpers;
use WooHive\WCApi\Client;

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

        add_filter( 'woocommerce_product_import_get_product_object', [ self::class, 'on_import_start' ], 10, 2);
        add_action( 'woocommerce_product_import_inserted_product_object', [ self::class, 'on_import_finished' ], 10, 2 );

        add_action( Constants::PLUGIN_SLUG . '_sync_product',  [ self::class, 'on_sync_product' ] );
    }

    /**
     * Maneja la sincronización de un producto específico.
     *
     * @param WC_Product $product Instancia del producto.
     *
     * @return void
     */
    public static function on_sync_product( WC_Product $product ): void {
        if ( $product && Helpers::should_sync( $product ) ) {
            self::set_sync_in_progress( $product->get_id(), true );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $product );
            }

            $post_id = $product->get_id();
            self::set_sync_in_progress( $post_id, false );
            self::set_importing_in_progress( $post_id, false );
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

    /**
     * Maneja la sincronización cuando se guarda una variación de producto.
     *
     * @param int     $post_id ID del post.
     * @param WP_Post $post    Objeto del post.
     * @param bool    $update  Indica si es una actualización.
     *
     * @return void
     */
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

    /**
     * Marca el inicio del proceso de importación de un producto.
     *
     * @param WC_Product $product Instancia del producto.
     * @param array      $data   Raw CSV data.
     *
     * @return WC_Product
     */
    public static function on_import_start( WC_Product $product, array $data = [] ): WC_Product {
        $post_id = $product->get_id();
        self::set_importing_in_progress( $post_id, true );

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
    public static function on_import_finished( WC_Product $product, array $data = [] ): void {
        $post_id = $product->get_id();

        self::set_sync_in_progress( $post_id, false );
        self::set_importing_in_progress( $post_id, false );
        self::on_sync_product( $product );
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
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
            $data = [ 'product_id' => $product_id, 'from' => 'primary' ];

            $response = $client->put( Constants::INTERNAL_API_BASE_NAME . '/sync-product', $data );
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
            return; // Si el producto no tiene SKU, no se sincroniza.
        }

        $product_id = $product->get_id();

        $main_site = Helpers::primary_site();
        if ( empty( $main_site ) ) {
            return; // Si no hay un sitio principal configurado, no se realiza la sincronización.
        }

        if ( get_option( Constants::PLUGIN_SLUG . '_sync_only_stock', 'yes' ) === 'yes' ) {
            return;
        }

        $client = Client::create( $main_site['url'], $main_site['api_key'], $main_site['api_secret'] );

        $server_host = $_SERVER['HTTP_HOST'];
        $headers = [ 'X-Source-Server-Host' => $server_host ];
        $data = [ 'product_id' => $product_id, 'from' => 'secondary' ];

        $response = $client->post( Constants::INTERNAL_API_BASE_NAME . '/sync-product', $data, [], $headers );
        Debugger::debug( 'sync product from secondary: ', $response );
    }

    /**
     * Verifica si la importación está en progreso para un producto.
     *
     * @param int $post_id ID del producto.
     *
     * @return bool
     */
    public static function is_importing_in_progress( int $post_id ): bool {
        return get_transient( Constants::PLUGIN_SLUG . '_importing_in_progress_' . $post_id);
    }

    /**
     * Verifica si la sincronización está en progreso para un producto.
     *
     * @param int $post_id ID del producto.
     *
     * @return bool
     */
    public static function is_sync_in_progress( int $post_id ): bool {
        return get_transient( Constants::PLUGIN_SLUG . '_sync_in_progress_' . $post_id );
    }

    /**
     * Establece el estado de sincronización en progreso para un producto.
     *
     * @param int  $post_id  ID del producto.
     * @param bool $in_progress Indica si la sincronización esta en progreso.
     *
     * @return void
     */
    public  static function set_sync_in_progress( int $post_id, bool $in_progress ): void {
        if ( $in_progress ) {
            set_transient( Constants::PLUGIN_SLUG . '_sync_in_progress_' . $post_id, true, 9 );
        } else {
            delete_transient( Constants::PLUGIN_SLUG . '_sync_in_progress_' . $post_id );
        }
    }

    /**
     * Establece el estado de importación en progreso para un producto.
     *
     * @param int  $post_id  ID del producto.
     * @param bool $in_progress Indica si la importación esta en proceso.
     *
     * @return void
     */
    public static function set_importing_in_progress( int $post_id, bool $in_progress ): void {
        if ( $in_progress ) {
            set_transient( Constants::PLUGIN_SLUG . '_importing_in_progress_' . $post_id, true, 9 );
        } else {
            delete_transient( Constants::PLUGIN_SLUG . '_importing_in_progress_' . $post_id );
        }
    }
}
