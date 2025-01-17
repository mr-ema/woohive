<?php

namespace WooHive\Internal\Demons;

use WooHive\WCApi\Client;
use WooHive\Utils\Helpers;

use WC_Product_Variation;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Variations {

    public static function init(): void {
        add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'on_product_variation_update' ), 10, 2 );
    }

    /**
     * Maneja la actualización de una variación de producto.
     *
     * @param int                  $product_id ID de la variación.
     * @param WC_Product_Variation $variation  Instancia de la variación.
     *
     * @return void
     */
    public static function on_product_variation_update( int $product_id, WC_Product_Variation $variation ): void {
        if ( Helpers::should_sync( $variation ) ) {
            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_data( $variation );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_data( $variation );
            }
        }
    }

    /**
     * Sincroniza los datos de una variación de producto a los sitios secundarios.
     *
     * @param WC_Product_Variation $variation Instancia de la variación.
     *
     * @return void
     */
    private static function sync_to_secondary_sites_data( WC_Product_Variation $variation ): void {
        $sku = $variation->get_sku();
        if ( empty( $sku ) ) {
            return; // Si la variación no tiene SKU, no se sincroniza.
        }

        $data = self::wc_product_to_json( $variation );

        $sites = Helpers::sites();
        foreach ( $sites as $site ) {
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

            $parent   = wc_get_product( $variation->get_parent_id() );
            $response = $client->products->pull_by_sku( $parent->get_sku() );
            if ( ! $response->has_error() ) {
                $parent_id = $response->body()[0]['id'];
                $response  = $client->product_variations->update( $parent_id, $data );
            }
        }
    }

    /**
     * Sincroniza los datos de una variación al sitio principal.
     *
     * @param WC_Product_Variation $variation Instancia de la variación.
     *
     * @return void
     */
    private static function sync_to_primary_site_data( WC_Product_Variation $variation ): void {
        $sku = $variation->get_sku();
        if ( empty( $sku ) ) {
            return; // Si la variación no tiene SKU, no se sincroniza.
        }

        $site = Helpers::primary_site();
        if ( empty( $site ) ) {
            return; // Si no hay un sitio principal configurado, no se realiza la sincronización.
        }

        $data = self::wc_product_to_json( $variation );

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

        $parent   = wc_get_product( $variation->get_parent_id() );
        $response = $client->products->pull_by_sku( $parent->get_sku() );
        if ( ! $response->has_error() ) {
            $parent_id = $response->body()[0]['id'];
            $response  = $client->product_variations->update( $parent_id, $data );
        }
    }

    /**
     * Convierte una variación de producto en un array con los datos esenciales.
     *
     * @param WC_Product_Variation $variation Instancia de la variación.
     *
     * @return array Datos esenciales de la variación.
     */
    private static function wc_product_to_json( WC_Product_Variation $variation ): array {
        return array(
            'name'          => $variation->get_name(),
            'description'   => $variation->get_description(),
            'dimensions'    => array(
                'length' => $variation->get_length(),
                'width'  => $variation->get_width(),
                'height' => $variation->get_height(),
            ),
            'weight'        => $variation->get_weight(),
            'regular_price' => $variation->get_regular_price(),
            'sale_price'    => $variation->get_sale_price(),
            'sku'           => $variation->get_sku(),
            'status'        => $variation->get_status(),
            'virtual'       => $variation->get_virtual(),
        );
    }
}
