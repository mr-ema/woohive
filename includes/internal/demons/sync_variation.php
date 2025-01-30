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
        add_action( 'woocommerce_update_product_variation', array( self::class, 'on_variation_update' ), 9, 1 );
        add_action( 'before_delete_post', array( self::class, 'on_variation_delete' ), 10 );
    }

    /**
     * Maneja la eliminación de una variación de producto.
     *
     * @param int $post_id ID del post que está siendo eliminado.
     *
     * @return void
     */
    public static function on_variation_delete( int $post_id ): void {
        if ( Helpers::is_sync_only_stock_enabled() ) {
            return;
        }

        $post_type = get_post_type( $post_id );

        if ( 'product_variation' === $post_type ) {
            $variation = new WC_Product_Variation( $post_id );

            $parent = wc_get_product( $variation->get_parent_id() );
            $variation_sku = $variation->get_sku();

            $parent_sku = $parent->get_sku();
            if ( ! $parent_sku || ! $variation_sku ) {
                return;
            }

            if ( Helpers::should_sync( $variation ) ) {
                Transients::set_sync_in_progress( $parent_sku, true );
                Transients::set_sync_in_progress( $variation_sku, true );

                self::sync_variation_deletion( $variation );
            }
        }
    }

    public static function on_variation_update( int $variation_id ): void {
        if ( Helpers::is_sync_only_stock_enabled() ) {
            return;
        }

        $variation = new WC_Product_Variation( $variation_id );
        $parent = wc_get_product( $variation->get_parent_id() );
        $variation_sku = $variation->get_sku();

        $parent_sku = $parent->get_sku();
        if ( ! $parent_sku || ! $variation_sku ) {
            return;
        }

        $sync_in_progress = self::is_sync_in_progress( $variation_sku );
        if ( ! $sync_in_progress && $variation && Helpers::should_sync( $variation ) ) {
            Debugger::debug( 'Variation Sync On Update Has Been Fired' );
            Transients::set_sync_in_progress( $parent_sku, true );
            Transients::set_sync_in_progress( $variation_sku, true );

            if ( Helpers::is_primary_site() ) {
                self::sync_variation_update( $variation );
            }
        }
    }

    /**
     * Sincroniza la actualizacion de una variación de producto a otros sitios o al servidor principal.
     *
     * @param WC_Product_Variation $variation Instancia de la variación que será actualizada
     *
     * @return void
     */
    private static function sync_variation_update( WC_Product_Variation $variation ): void {
        if ( Helpers::is_sync_only_stock_enabled() ) {
            return;
        }

        $parent_id = $variation->get_parent_id();
        $parent    = wc_get_product( $parent_id );
        if ( ! $parent ) {
            return;
        }

        $parent_sku    = $parent->get_sku();
        $variation_sku = $variation->get_sku();
        if ( empty( $variation_sku ) || empty( $parent_sku ) ) {
            Debugger::debug( 'SKU de la variacion o padre vacío. No se puede sincronizar la eliminación de la variación.' );
            return;
        }

        $data = $variation->get_data();
        if ( Helpers::is_primary_site() ) {
            $sites = Helpers::sites();
            foreach ( $sites as $site ) {
                $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

                $response = $client->put( Constants::INTERNAL_API_BASE_NAME . "/products/{$parent_sku}/variations/{$variation_sku}", $data );
                Debugger::debug( 'Sync variation update to secondary site:', $response );
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
        if ( Helpers::is_sync_only_stock_enabled() ) {
            return;
        }

        $parent_id = $variation->get_parent_id();
        $parent    = wc_get_product( $parent_id );
        if ( ! $parent ) {
            return;
        }

        $parent_sku    = $parent->get_sku();
        $variation_sku = $variation->get_sku();
        if ( empty( $variation_sku ) || empty( $parent_sku ) ) {
            Debugger::debug( 'SKU de la variacion o padre vacío. No se puede sincronizar la eliminación de la variación.' );
            return;
        }

        if ( Helpers::is_primary_site() ) {
            $sites = Helpers::sites();
            foreach ( $sites as $site ) {
                $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

                $response = $client->delete( Constants::INTERNAL_API_BASE_NAME . "/products/{$parent_sku}/variations/{$variation_sku}" );
                Debugger::debug( 'Sync variation deletion to secondary site:', $response );
            }
        }
    }

    private static function is_sync_in_progress( string|int $post_sku ): bool {
        $sync_in_progress  = Transients::is_sync_in_progress( $post_sku );
        $sync_in_progress |= Transients::is_importing_in_progress( $post_sku );
        $sync_in_progress |= Transients::is_sync_stock_in_progress( $post_sku );
        $sync_in_progress |= Transients::is_sync_price_in_progress( $post_sku );

        return $sync_in_progress;
    }
}
