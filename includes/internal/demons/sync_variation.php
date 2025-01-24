<?php

namespace WooHive\Internal\Demons;

use WooHive\Config\Constants;
use WooHive\Utils\Debugger;
use WooHive\WCApi\Client;
use WooHive\Utils\Helpers;

use WC_Product_Variation;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Variation {

    public static function init(): void {
        add_action( 'before_delete_post', [ self::class, 'on_variation_delete' ], 10 );
    }

    /**
     * Maneja la eliminación de una variación de producto.
     *
     * @param int $post_id ID del post que está siendo eliminado.
     *
     * @return void
     */
    public static function on_variation_delete( int $post_id ): void {
        $post_type = get_post_type( $post_id );

        if ( 'product_variation' === $post_type ) {
            $variation = new WC_Product_Variation( $post_id );

            $parent = wc_get_product( $variation->get_parent_id() );
            if ( Helpers::should_sync( $parent ) ) {
                self::sync_variation_deletion( $variation );
            }
        }
    }

    /**
     * Sincroniza la eliminación de una variación de producto a otros sitios o al servidor principal.
     *
     * @param WC_Product_Variation $variation Instancia de la variación que será eliminada.
     *
     * @return void
     */
    private static function sync_variation_deletion( WC_Product_Variation $variation ): void {
        $parent_id = $variation->get_parent_id();
        $parent = wc_get_product( $parent_id );
        if ( ! $parent ) {
            return;
        }

        $parent_sku = $parent->get_sku();
        $variation_sku = $variation->get_sku();
        if ( empty( $variation_sku ) || empty( $parent_sku) ) {
            Debugger::debug( 'SKU de la variacion o padre vacío. No se puede sincronizar la eliminación de la variación.' );
            return;
        }

        if ( Helpers::is_primary_site() ) {
            $sites = Helpers::sites();
            foreach ( $sites as $site ) {
                $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

                $response = $client->delete( Constants::INTERNAL_API_BASE_NAME . "/sync/products/{$parent_sku}/variations/{$variation_sku}" );
                Debugger::debug( 'Sync deletion to secondary site:', $response );
            }
        }
    }
}
