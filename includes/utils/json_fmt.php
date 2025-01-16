<?php

namespace WooHive\Utils;

use \WC_Product;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Json_Fmt {

    private static function clean_data( &$product_data ): void {
        unset(
            $product_data['id'],
            $product_data['date_created'],
            $product_data['date_modified'],
            $product_data['low_stock_amount'],
            $product_data['image']['id'],
            $product_data['image']['date_created'],
            $product_data['image']['date_created_gmt'],
            $product_data['image']['date_modified'],
            $product_data['image']['date_modified_gmt'],
        );
    }

    /**
     * Parsear los datos del producto en un formato adecuado para la API de WooCommerce.
     *
     * @param WC_Product $product El objeto del producto de WooCommerce.
     * @param array      $custom_fields (opcional) Array de campos personalizados para fusionar con los datos del producto.
     *                             Si los campos personalizados ya existen, se sobrescribirán los valores predeterminados.
     * @return array Los datos del producto en formato de array.
     */
    public static function wc_product_to_json( WC_Product $product, array $custom_fields = [] ): array {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return array(); // No es un objeto de producto válido, retornar un array vacío.
        }

        $data = $product->get_data();
        self::clean_data( $data );

        $data['dimensions'] = [
            'width'  => $data['width']  ?? 0,
            'length' => $data['length'] ?? 0,
            'height' => $data['height'] ?? 0,
        ];
        unset( $data['width'], $data['length'], $data['height'] );

        // FIXME: attributos no estan siendo pasados como un array
        unset( $data['attributes'] ); // Solucion temporal

        if ( ! empty( $custom_fields ) ) {
            // Fusionar los campos personalizados en los datos del producto,
            // sobrescribiendo los valores predeterminados si existen.
            $data = array_merge( $data, $custom_fields );
        }

        return $data;
    }

    /**
     * Convierte un array de productos de WooCommerce a formato JSON.
     *
     * Este método toma un array de productos de WooCommerce, recorre cada producto y extrae sus datos
     * utilizando el método `get_data()`. Devuelve un array de datos de todos los productos.
     *
     * @param WC_Product[] $products Array de productos de WooCommerce.
     * @return array Array con los datos de los productos en formato JSON.
     */
    public static function wc_product_list_to_json( array $products ): array {
        $data = array();

        foreach ( $products as $product ) {
            if ( $product instanceof WC_Product ) {
                $data[] = $product->get_data();
            }
        }

        return $data;
    }
}
