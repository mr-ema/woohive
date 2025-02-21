<?php

namespace WooHive\Internal;

use WooHive\Config\Constants;
use WooHive\Utils\Debugger;
use WooHive\Utils\Helpers;
use WooHive\WCApi\Client;
use WooHive\Internal\Crud;

use WP_Error;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tools {

    /**
     * Importa un producto desde un cliente remoto y lo crea o actualiza en el sistema local.
     *
     * @param Client $client     Instancia del cliente remoto utilizado para la comunicación con la API.
     * @param int    $external_product_id ID del producto que se desea importar desde la API remota.
     *
     * @return int|WP_Error Retorna el ID del producto creado o actualizado en caso de éxito,
     *                      o un objeto WP_Error si ocurre algún problema durante el proceso.
     */
    public static function import_product( Client &$client, int $external_product_id ): int|WP_Error {
        if ( ! isset( $external_product_id ) ) {
            return new WP_Error( 'invalid_id', __( 'El ID del producto es invalido', Constants::TEXT_DOMAIN ) );
        }

        $res = $client->products->pull( $external_product_id );
        if ( $res->has_error() || empty( $res->body() ) ) {
            return new WP_Error(
                'product_not_found',
                __( 'El producto especificado no pudo ser localizado. Por favor verifica el ID del producto e intenta nuevamente.', Constants::TEXT_DOMAIN )
            );
        }

        $product = $res->body();
        if ( empty( $product['sku'] ) ) {
            return new WP_Error( 'missing_sku', __( 'El producto no puede ser importado dado que su sku esta vacio.', Constants::TEXT_DOMAIN ) );
        }

        Debugger::debug( '[IMPORT] Data del producto: ', $res->json_fmt() );

        if ( ! empty( $product['categories'] ) ) {
            $normal_ids = array_column( $product['categories'], 'id' );
            $parent_ids = array_column( $product['categories'], 'parent' );

            $ids            = array_merge( $normal_ids, $parent_ids );
            $categories_res = $client->product_categories->pull_all( array( 'include' => implode( ',', $ids ) ) );
            if ( ! $categories_res->has_error() ) {
                $categories = $categories_res->body();
                $unused     = Crud\Categories::create_category_with_parents_batch( $categories );
            }
        }

        add_filter(
            Constants::PLUGIN_SLUG . '_exclude_skus_from_sync',
            function () use ( $product ) {
                return array( $product['sku'] );
            }
        );

        $internal_res = Crud\Products::create_or_update( null, $product );
        if ( is_wp_error( $internal_res ) ) {
            remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );
            return $internal_res;
        }

        $internal_product_id = $internal_res;
        if ( $internal_product_id && ! empty( $product['variations'] ) ) {
            $ids = $product['variations'];

            $variations_res = $client->product_variations->pull_all( $product['id'], array( 'include' => implode( ',', $ids ) ) );
            if ( ! $variations_res->has_error() ) {
                $variations = $variations_res->body();
                $unused     = Crud\Variations::create_or_update_batch( $internal_product_id, $variations );
            }
        }

        remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );

        if ( Helpers::is_primary_site() ) {
            $wc_product = wc_get_product( $internal_product_id );
            do_action( Constants::PLUGIN_SLUG . '_sync_product', $wc_product );
        }

        return $internal_product_id;
    }

    /**
     * Actualiza un producto desde un cliente remoto en el sistema local.
     *
     * @param Client $client     Instancia del cliente remoto utilizado para la comunicación con la API.
     * @param int    $external_product_id ID del producto que se desea importar desde la API remota.
     *
     * @return int|WP_Error Retorna el ID del producto actualizado en caso de éxito,
     *                      o un objeto WP_Error si ocurre algún problema durante el proceso.
     */
    public static function update_product( Client &$client, int $external_product_id ): int|WP_Error {
        if ( ! isset( $external_product_id ) ) {
            return new WP_Error( 'invalid_id', __( 'El ID del producto es invalido', Constants::TEXT_DOMAIN ) );
        }

        $res = $client->products->pull( $external_product_id );
        if ( $res->has_error() || empty( $res->body() ) ) {
            return new WP_Error(
                'product_not_found',
                __( 'El producto especificado no pudo ser localizado. Por favor verifica el ID del producto e intenta nuevamente.', Constants::TEXT_DOMAIN )
            );
        }

        $product = $res->body();
        if ( empty( $product['sku'] ) ) {
            return new WP_Error( 'missing_sku', __( 'El producto no puede ser importado dado que su sku esta vacio.', Constants::TEXT_DOMAIN ) );
        }

        Debugger::debug( '[UPDATE] Data del producto:', $res->json_fmt() );

        if ( ! empty( $product['categories'] ) ) {
            $ids = array_column( $product['categories'], 'id' );

            $categories_res = $client->product_categories->pull_all( array( 'include' => implode( ',', $ids ) ) );
            if ( ! $categories_res->has_error() ) {
                $categories = $categories_res->body();
                $unused     = Crud\Categories::create_batch( $categories );
            }
        }

        add_filter(
            Constants::PLUGIN_SLUG . '_exclude_skus_from_sync',
            function () use ( $product ) {
                return array( $product['sku'] );
            }
        );

        $internal_product_id = wc_get_product_id_by_sku( $product['sku'] );
        if ( ! $internal_product_id ) {
            return new WP_Error( 'product_does_not_exists', __( 'El producto no puede ser actualizado dado que no existe en esta pagina.', Constants::TEXT_DOMAIN ) );
        }

        $internal_res = Crud\Products::update( $internal_product_id, $product );
        if ( is_wp_error( $internal_res ) ) {
            remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );
            return $internal_res;
        }

        if ( $internal_product_id && ! empty( $product['variations'] ) ) {
            $ids = $product['variations'];

            $variations_res = $client->product_variations->pull_all( $product['id'], array( 'include' => implode( ',', $ids ) ) );
            if ( ! $variations_res->has_error() ) {
                $variations = $variations_res->body();
                $unused     = Crud\Variations::create_or_update_batch( $internal_product_id, $variations );
            }
        }

        remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );

        return $internal_product_id;
    }
}
