<?php

namespace WooHive\Utils;

use \WC_Product;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Json_Fmt {

    /**
     * Convierte un producto de WooCommerce a formato JSON.
     *
     * Este método toma un producto de WooCommerce y extrae sus datos utilizando el método `get_data()`,
     * devolviendo un array con la información relevante del producto.
     *
     * @param WC_Product $product Producto de WooCommerce.
     * @return array Array con los datos del producto en formato JSON.
     */
    public static function wc_product_to_json( WC_Product $product ): array {
        $data = $product->get_data();

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
