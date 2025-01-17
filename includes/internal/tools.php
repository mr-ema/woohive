<?php

namespace WooHive\Internal;

use WooHive\Config\Constants;
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
     * @param int    $product_id ID del producto que se desea importar desde la API remota.
     *
     * @return int|WP_Error Retorna el ID del producto creado o actualizado en caso de éxito,
     *                      o un objeto WP_Error si ocurre algún problema durante el proceso.
     */
    public static function import_product( Client &$client, int $product_id ): int|WP_Error {
        if ( ! isset( $product_id ) ) {
            return new WP_Error( 'invalid_id', __( 'El ID del producto es invalido', Constants::TEXT_DOMAIN ) );
        }

        $res = $client->products->pull( $product_id );
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
        $product_id = Crud\Products::create_or_update( null, $product );
        if ( is_wp_error( $product_id ) ) {
            remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );
            return $product_id;
        }

        if ( $product_id && ! empty( $product['variations'] ) ) {
            $ids = $product['variations'];

            $variations_res = $client->product_variations->pull_all( $product['id'], array( 'include' => implode( ',', $ids ) ) );
            if ( ! $variations_res->has_error() ) {
                $variations = $variations_res->body();
                $unused     = Crud\Variations::create_or_update_batch( $product_id, $variations );
            }
        }

        remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );

        return $product_id;
    }

    /**
     * Actualiza un producto desde un cliente remoto en el sistema local.
     *
     * @param Client $client     Instancia del cliente remoto utilizado para la comunicación con la API.
     * @param int    $product_id ID del producto que se desea importar desde la API remota.
     *
     * @return int|WP_Error Retorna el ID del producto actualizado en caso de éxito,
     *                      o un objeto WP_Error si ocurre algún problema durante el proceso.
     */
    public static function update_product( Client &$client, int $product_id ): int|WP_Error {
        if ( ! isset( $product_id ) ) {
            return new WP_Error( 'invalid_id', __( 'El ID del producto es invalido', Constants::TEXT_DOMAIN ) );
        }

        $res = $client->products->pull( $product_id );
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

        $wc_product_id = wc_get_product_id_by_sku( $product['sku'] );
        if ( empty( $wc_product_id ) ) {
            return new WP_Error( 'update_product_error', __( 'El producto no existe por lo que no se puede actualizar.', Constants::TEXT_DOMAIN ) );
        }

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
        $product_id = Crud\Products::update( $wc_product_id, $product );
        if ( is_wp_error( $product_id ) ) {
            remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );
            return $product_id;
        }

        if ( $product_id && ! empty( $product['variations'] ) ) {
            $ids = $product['variations'];

            $variations_res = $client->product_variations->pull_all( $product['id'], array( 'include' => implode( ',', $ids ) ) );
            if ( ! $variations_res->has_error() ) {
                $variations = $variations_res->body();
                $unused     = Crud\Variations::create_or_update_batch( $product_id, $variations );
            }
        }

        remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );

        return $product_id;
    }

    public static function export_product() {}

    public static function massive_product_import( array $product_data ) {}
    public static function massive_product_export( array $product_data ) {}
}
