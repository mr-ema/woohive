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
        add_action( 'woocommerce_product_before_set_stock', array( self::class, 'store_old_stock' ), 6, 1 );
        add_action( 'woocommerce_variation_before_set_stock', array( self::class, 'store_old_variation_stock' ), 6, 1 );

        add_action( 'woocommerce_product_set_stock', array( self::class, 'on_product_set_stock' ), 6, 2 );
        add_action( 'woocommerce_variation_set_stock', array( self::class, 'on_variation_set_stock' ), 6, 2 );

        add_action( Constants::PLUGIN_SLUG . '_sync_product_stock', array( self::class, 'sync_product_stock' ) );
        add_action( Constants::PLUGIN_SLUG . '_sync_variation_stock', array( self::class, 'sync_variation_stock' ) );
    }

    /**
     * Guarda el stock antiguo antes de actualizar.
     *
     * @param WC_Product $product Instancia del producto.
     * @return void
     * @since 1.1.0
     */
    public static function store_old_stock( WC_Product $product ): void {
        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            return;
        }

        if ( $product->managing_stock() ) {
            $old_stock = get_post_meta( $product->get_id(), '_stock', true );
            if ( empty( $old_stock ) ) {
                $old_stock = 0;
            }

            Transients::set_old_stock( $sku, $old_stock );
        }
    }

    /**
     * Guarda el stock antiguo antes de actualizar.
     *
     * @param WC_Product $product Instancia del producto.
     * @return void
     * @since 1.1.0
     */
    public static function store_old_variation_stock( WC_Product_Variation $variation ): void {
        $sku = $variation->get_sku();
        if ( empty( $sku ) ) {
            return;
        }

        if ( $variation->managing_stock() ) {
            $old_stock = get_post_meta( $variation->get_id(), '_stock', true );
            if ( empty( $old_stock ) ) {
                $old_stock = 0;
            }

            Transients::set_old_stock( $sku, $old_stock );
        }
    }

    /**
     * Maneja la actualización del stock de un producto.
     *
     * @param WC_Product $product Instancia del producto actualizado.
     *
     * @return void
     */
    public static function sync_product_stock( WC_Product $product ): void {
        $product_sku = $product->get_sku();
        if ( ! $product_sku ) {
            return;
        }

        if ( Helpers::should_sync_stock( $product ) ) {
            do_action( Constants::PLUGIN_SLUG . '_before_product_sync_stock', $product );

            Debugger::debug( 'Sync Product Stock Has Being Fire' );
            Transients::set_sync_stock_in_progress( $product_sku, true );

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites( $product );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site( $product );
            }

            Transients::set_sync_stock_in_progress( $product_sku, false );
        }
    }

    /**
     * Maneja la actualización del stock de una variación de producto.
     *
     * @param WC_Product_Variation $variation Instancia de la variación de producto.
     *
     * @return void
     */
    public static function sync_variation_stock( WC_Product_Variation $variation ): void {
        $variation_sku = $variation->get_sku();
        if ( ! $variation_sku ) {
            return;
        }

        if ( Helpers::should_sync_stock( $variation ) ) {
            do_action( Constants::PLUGIN_SLUG . '_before_variation_sync_stock', $variation );

            Debugger::debug( 'Sync Variation Stock Has Being Fire' );
            Transients::set_sync_stock_in_progress( $variation_sku, true );

            if ( Helpers::is_primary_site() ) {
                self::sync_variation_to_secondary_sites( $variation );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_variation_to_primary_site( $variation );
            }

            Transients::set_sync_stock_in_progress( $variation_sku, false );
        }
    }

    /**
     * Maneja la actualización del stock de un producto.
     *
     * @param WC_Product $product Instancia del producto actualizado.
     *
     * @return void
     */
    public static function on_product_set_stock( WC_Product $product ): void {
        $product_sku = $product->get_sku();

        $is_sync_in_progress  = Transients::is_sync_stock_in_progress( $product_sku );
        $is_sync_in_progress |= Transients::is_importing_in_progress( $product_sku );

        if ( ! $product_sku || $is_sync_in_progress ) {
            return;
        }

        if ( Helpers::should_sync_stock( $product ) ) {
            do_action( Constants::PLUGIN_SLUG . '_sync_product_stock', $product );
        }
    }

    /**
     * Maneja la actualización del stock de una variación de producto.
     *
     * @param WC_Product_Variation $variation Instancia de la variación de producto.
     *
     * @return void
     */
    public static function on_variation_set_stock( WC_Product_Variation $variation ): void {
        $variation_sku = $variation->get_sku();

        $is_sync_in_progress  = Transients::is_sync_stock_in_progress( $variation_sku );
        $is_sync_in_progress |= Transients::is_importing_in_progress( $variation_sku );

        if ( ! $variation_sku || $is_sync_in_progress ) {
            return;
        }

        if ( Helpers::should_sync_stock( $variation ) ) {
            do_action( Constants::PLUGIN_SLUG . '_sync_variation_stock', $variation );
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

        $data = array( 'stock_status' => $product->get_stock_status() );
        if ( $product->managing_stock() ) {
            $old_stock = Transients::get_old_stock( $sku );
            $new_stock = $product->get_stock_quantity();

            $stock_diff = $new_stock - $old_stock;
            if ( $stock_diff === 0 ) {
                return;
            }

            $data['stock_quantity'] = $new_stock;
            $data['old_stock']      = $old_stock;
            $data['stock_change']   = $stock_diff;
        }

        foreach ( $sites as $site ) {
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

            $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$sku}", $data, array() );
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
        if ( ! Helpers::is_sync_to_primary_site_enabled() ) {
            return;
        }

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

        $data = array( 'stock_status' => $product->get_stock_status() );
        if ( $product->managing_stock() ) {
            $old_stock = Transients::get_old_stock( $sku );
            $new_stock = $product->get_stock_quantity();

            $stock_diff = $new_stock - $old_stock;
            if ( $stock_diff === 0 ) {
                return;
            }

            $data['stock_quantity'] = $new_stock;
            $data['old_stock']      = $old_stock;
            $data['stock_change']   = $stock_diff;
        }

        $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$sku}", $data, array(), $headers );
        Debugger::debug( 'sync stock from secondary: ', $response );
    }

    /**
     * Sincroniza el stock del producto con los sitios secundarios configurados.
     *
     * @param WC_Product_Variation $variation Instancia del producto.
     *
     * @return void
     */
    private static function sync_variation_to_secondary_sites( WC_Product_Variation $variation ): void {
        $parent     = wc_get_product( $variation->get_parent_id() );
        $parent_sku = $parent->get_sku();

        $sku = $variation->get_sku();
        if ( empty( $sku ) || empty( $parent_sku ) ) {
            return;
        }

        $sites = Helpers::sites();
        if ( empty( $sites ) ) {
            return;
        }

        $data = array( 'stock_status' => $variation->get_stock_status() );
        if ( $variation->managing_stock() ) {
            $old_stock = Transients::get_old_stock( $sku );
            $new_stock = $variation->get_stock_quantity();

            $stock_diff = $new_stock - $old_stock;
            if ( $stock_diff === 0 ) {
                return;
            }

            $data['stock_quantity'] = $new_stock;
            $data['old_stock']      = $old_stock;
            $data['stock_change']   = $stock_diff;
        }

        foreach ( $sites as $site ) {
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

            $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$parent_sku}/variations/{$sku}", $data, array() );
            Debugger::debug( 'sync stock from primary: ', $response );
        }
    }

    /**
     * Sincroniza el stock del producto al sitio principal.
     *
     * @param WC_Product_Variation $variation Instancia del producto.
     *
     * @return void
     */
    private static function sync_variation_to_primary_site( WC_Product_Variation $variation ): void {
        if ( ! Helpers::is_sync_to_primary_site_enabled() ) {
            return;
        }

        $parent     = wc_get_product( $variation->get_parent_id() );
        $parent_sku = $parent->get_sku();

        $sku = $variation->get_sku();
        if ( empty( $sku ) || empty( $parent_sku ) ) {
            return;
        }

        $main_site = Helpers::primary_site();
        if ( empty( $main_site ) ) {
            return;
        }

        $client  = Client::create( $main_site['url'], $main_site['api_key'], $main_site['api_secret'] );
        $headers = array( 'X-Source-Server-Host' => $_SERVER['HTTP_HOST'] );
        $data    = array( 'stock_status' => $variation->get_stock_status() );

        if ( $variation->managing_stock() ) {
            $old_stock = Transients::get_old_stock( $sku );
            $new_stock = $variation->get_stock_quantity();

            $stock_diff = $new_stock - $old_stock;
            if ( $stock_diff === 0 ) {
                return;
            }

            $data['stock_quantity'] = $new_stock;
            $data['old_stock']      = $old_stock;
            $data['stock_change']   = $stock_diff;
        }

        $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$parent_sku}/variations/{$sku}", $data, array(), $headers );
        Debugger::debug( 'sync stock from secondary: ', $response );
    }
}
