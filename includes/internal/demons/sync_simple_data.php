<?php

namespace WooHive\Internal\Demons;

use WPFlashMessages;
use WooHive\WCApi\Client;
use WooHive\Utils\Helpers;

use WC_Product;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Simple_Data {

    public static function init(): void {
        add_action( 'woocommerce_update_product', array( self::class, 'on_product_update' ), 10, 2 );
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

        $data = self::wc_product_to_json( $product );

        $sites = Helpers::sites();
        foreach ( $sites as $site ) {
            $client   = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
            $response = $client->products->update( $data );
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

        $site = Helpers::primary_site();
        if ( empty( $site ) ) {
            return; // Si no hay un sitio principal configurado, no se realiza la sincronización.
        }

        $data = self::wc_product_to_json( $product );

        $client   = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
        $response = $client->products->update( $data );
    }

    /**
     * Convierte un producto estándar en un array con los datos esenciales.
     *
     * @param WC_Product $product Instancia del producto.
     *
     * @return array Datos esenciales del producto.
     */
    private static function wc_product_to_json( WC_Product $product ): array {
        return array(
            'name'              => $product->get_name(),
            'description'       => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'dimensions'        => array(
                'length' => $product->get_length(),
                'width'  => $product->get_width(),
                'height' => $product->get_height(),
            ),
            'weight'            => $product->get_weight(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'sku'               => $product->get_sku(),
            'status'            => $product->get_status(),
            'type'              => $product->get_type(),
            'manage_stock'      => $product->get_manage_stock() ? 'true' : 'false',
        );
    }
}
