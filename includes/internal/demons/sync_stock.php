<?php

namespace WooHive\Internal\Demons;

use WooHive\WCApi\Client;
use WooHive\Utils\Helpers;

use WC_Product;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Stock {

    public static function init(): void {
        add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_stock_update' ), 10, 2 );
        add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_stock_update' ), 10, 2 );
    }

    /**
     * Maneja la actualización del stock de un producto.
     * TODO: Hacer en background para evitar lag
     *
     * @param WC_Product $product Instancia del producto actualizado.
     *
     * @return void
     */
    public static function on_stock_update( WC_Product $product ): void {
        if ( Helpers::should_sync_stock( $product ) ) {
            $new_stock = $product->get_stock_quantity();

            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites( $product, $new_stock );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site( $product, $new_stock );
            }
        }
    }

    /**
     * Sincroniza el stock del producto con los sitios secundarios configurados.
     *
     * @param WC_Product $product Instancia del producto.
     * @param int        $stock_quantity Nueva cantidad de stock del producto.
     *
     * @return void
     */
    private static function sync_to_secondary_sites( WC_Product $product, int $stock_quantity ): void {
        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            return;
        }

        $data = array(
            'sku'            => $sku,
            'stock_quantity' => $stock_quantity,
            'stock_status'   => $product->get_stock_status(),
            'manage_stock'   => $product->managing_stock() ? 'true' : 'false',
        );

        $sites = Helpers::sites();

        $in_sites = $product->get_meta( '_in_sites', false );
        if ( ! empty( $in_sites ) ) {
            // Lógica futura optmizacion para manejar la sincronización en sitios específicos.
        }

        foreach ( $sites as $site ) {
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

            $response = $client->products->push_or_update( $data );
        }
    }

    /**
     * Sincroniza el stock del producto al sitio principal.
     *
     * @param WC_Product $product Instancia del producto.
     * @param int        $stock_quantity Nueva cantidad de stock del producto.
     *
     * @return void
     */
    private static function sync_to_primary_site( WC_Product $product, int $stock_quantity ): void {
        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            return;
        }

        $site = Helpers::primary_site();
        if ( empty( $site ) ) {
            return;
        }

        $data = array(
            'sku'            => $sku,
            'stock_quantity' => $stock_quantity,
            'stock_status'   => $product->get_stock_status(),
            'manage_stock'   => $product->managing_stock() ? 'true' : 'false',
        );

        $client   = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
        $response = $client->products->push_or_update( $data );
    }
}
