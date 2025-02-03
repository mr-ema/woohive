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

/**
 * Sync Price Demon
 *
 * @since 1.1.0
 */
class Sync_Price {

    public static function init(): void {
        add_action( 'woocommerce_product_object_updated_props', array( self::class, 'on_product_or_variation_update' ), 10, 2 );

        add_action( Constants::PLUGIN_SLUG . '_sync_product_price', array( self::class, 'sync_product_price' ) );
        add_action( Constants::PLUGIN_SLUG . '_sync_variation_price', array( self::class, 'sync_variation_price' ) );
    }

    /**
     * Se activa cuando se actualizan propiedades de un producto o variación en WooCommerce.
     *
     * Este hook detecta cambios en las propiedades del producto, incluyendo el precio.
     * Solo ejecuta la sincronización si se han actualizado los precios (precio regular,
     * precio de oferta o precio final).
     *
     * @param WC_Product $product El producto o variación que ha sido actualizado.
     * @param array      $updated_props Lista de propiedades que han cambiado.
     */
    public static function on_product_or_variation_update( WC_Product $product, $updated_props ): void {
        $price_keys = array( 'regular_price', 'sale_price', 'price' );

        if ( array_intersect( $price_keys, $updated_props ) ) {
            if ( $product->is_type( 'variation' ) ) {
                do_action( Constants::PLUGIN_SLUG . '_sync_variation_price', $product );
            } else {
                do_action( Constants::PLUGIN_SLUG . '_sync_product_price', $product );
            }
        }
    }

    /**
     * Se activa cuando se actualiza el precio de un producto.
     *
     * @param WC_Product $product El producto cuyo precio se ha actualizado.
     */
    public static function on_product_update( int $product_id ): void {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        if (
            $product->get_regular_price() !== $product->get_price() ||
            $product->get_sale_price() !== $product->get_price()
        ) {
            do_action( Constants::PLUGIN_SLUG . '_sync_product_price', $product );
        }
    }

    /**
     * Se activa cuando se actualiza el precio de una variación de producto.
     *
     * @param WC_Product_Variation $variation La variación cuyo precio se ha actualizado.
     */
    public static function on_variation_update( int $variation_id ): void {
        $variation = new WC_Product_Variation( $variation_id );
        if ( ! $variation ) {
            return;
        }

        if (
            $variation->get_regular_price() !== $variation->get_price() ||
            $variation->get_sale_price() !== $variation->get_price()
        ) {
            do_action( Constants::PLUGIN_SLUG . '_sync_variation_price', $variation );
        }
    }

    /**
     * Sincroniza el precio del producto con los sitios secundarios.
     *
     * @param WC_Product $product El producto cuyo precio se debe sincronizar.
     */
    public static function sync_product_price( WC_Product $product ): void {
        $sku = $product->get_sku();
        if ( ! $sku ) {
            return;
        }

        if ( Transients::is_sync_price_in_progress( $sku ) ) {
            return;
        }

        Debugger::debug( 'Sync Product Price Has Being Fire' );
        Transients::set_sync_price_in_progress( $sku, true );

        $data = array(
            'regular_price' => $product->get_regular_price(),
            'sale_price'    => $product->get_sale_price(),
            'price'         => $product->get_price(),
        );

        if ( Helpers::is_primary_site() ) {
            self::sync_price_to_secondary_sites( $sku, $data );
        } elseif ( Helpers::is_secondary_site() ) {
            self::sync_price_to_primary_site( $sku, $data );
        }
    }

    /**
     * Sincroniza el precio de una variación de producto con los sitios secundarios.
     *
     * @param WC_Product_Variation $variation La variación cuyo precio se debe sincronizar.
     */
    public static function sync_variation_price( WC_Product_Variation $variation ): void {
        $parent_sku    = wc_get_product( $variation->get_parent_id() )->get_sku();
        $variation_sku = $variation->get_sku();

        if ( ! $variation_sku || ! $parent_sku || Transients::is_sync_price_in_progress( $variation_sku ) ) {
            return;
        }

        Debugger::debug( 'Sync Variation Price Has Being Fire' );
        Transients::set_sync_price_in_progress( $parent_sku, true );
        Transients::set_sync_price_in_progress( $variation_sku, true );

        $data = array(
            'regular_price' => $variation->get_regular_price(),
            'sale_price'    => $variation->get_sale_price(),
            'price'         => $variation->get_price(),
        );

        if ( Helpers::is_primary_site() ) {
            self::sync_variation_price_to_secondary_sites( $parent_sku, $data, $variation_sku );
        } elseif ( Helpers::is_secondary_site() ) {
            self::sync_variation_price_to_primary_site( $parent_sku, $data, $variation_sku );
        }
    }

    /**
     * Sincroniza el precio de un producto a los sitios secundarios.
     *
     * @param string $sku El SKU del producto.
     * @param array  $data Los datos del precio (precio regular, precio de oferta, precio).
     */
    private static function sync_price_to_secondary_sites( string $sku, array $data ): void {
        $sites = Helpers::sites();
        if ( empty( $sites ) ) {
            return;
        }

        foreach ( $sites as $site ) {
            $client   = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
            $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$sku}", $data, array() );
            Debugger::debug( 'Sincronización de precio desde el sitio primario: ', $response );
        }
    }

    /**
     * Sincroniza el precio de un producto al sitio principal.
     *
     * @param string $sku El SKU del producto.
     * @param array  $data Los datos del precio (precio regular, precio de oferta, precio).
     */
    private static function sync_price_to_primary_site( string $sku, array $data ): void {
        if ( ! Helpers::is_sync_to_primary_site_enabled() ) {
            return;
        }

        $main_site = Helpers::primary_site();
        if ( empty( $main_site ) ) {
            return;
        }

        $client   = Client::create( $main_site['url'], $main_site['api_key'], $main_site['api_secret'] );
        $headers  = array( 'X-Source-Server-Host' => $_SERVER['HTTP_HOST'] );
        $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$sku}", $data, array(), $headers );
        Debugger::debug( 'Sincronización de precio desde el sitio secundario: ', $response );
    }

    /**
     * Sincroniza el precio de una variación de producto a los sitios secundarios.
     *
     * @param string $parent_sku El SKU del producto principal.
     * @param array  $data Los datos del precio (precio regular, precio de oferta, precio).
     * @param string $sku El SKU de la variación.
     */
    private static function sync_variation_price_to_secondary_sites( string $parent_sku, array $data, string $sku ): void {
        $sites = Helpers::sites();
        if ( empty( $sites ) ) {
            return;
        }

        foreach ( $sites as $site ) {
            $client   = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
            $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$parent_sku}/variations/{$sku}", $data, array() );
            Debugger::debug( 'Sincronización de precio desde el sitio primario para variación: ', $response );
        }
    }

    /**
     * Sincroniza el precio de una variación de producto al sitio principal.
     *
     * @param string $parent_sku El SKU del producto principal.
     * @param array  $data Los datos del precio (precio regular, precio de oferta, precio).
     * @param string $sku El SKU de la variación.
     */
    private static function sync_variation_price_to_primary_site( string $parent_sku, array $data, string $sku ): void {
        if ( ! Helpers::is_sync_to_primary_site_enabled() ) {
            return;
        }

        $main_site = Helpers::primary_site();
        if ( empty( $main_site ) ) {
            return;
        }

        $client   = Client::create( $main_site['url'], $main_site['api_key'], $main_site['api_secret'] );
        $headers  = array( 'X-Source-Server-Host' => $_SERVER['HTTP_HOST'] );
        $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$parent_sku}/variations/{$sku}", $data, array(), $headers );
        Debugger::debug( 'Sincronización de precio desde el sitio secundario para variación: ', $response );
    }
}
