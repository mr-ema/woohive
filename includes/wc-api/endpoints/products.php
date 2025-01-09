<?php

namespace WooHive\WCApi\Endpoints;

use WooHive\WCApi\Response;


/** Evitar el acceso directo */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Products {

    private $client;

    /**
     * Constructor para la clase de productos.
     *
     * @param Client $client La instancia del cliente API.
     */
    public function __construct( $client ) {
        $this->client = $client;
    }

    /**
     * Crear o enviar un producto al sitio de WooCommerce.
     *
     * @param array $product_data Los datos del producto a enviar.
     * @return Response La respuesta de la API.
     */
    public function push( $product_data ): Response {
        if ( empty( $product_data['sku'] ) ) {
            return new Response( 422, null, array(), 'SKU is required' );
        }

        self::clean_data( $product_data );
        return $this->client->post(
            'products',
            $product_data
        );
    }

    /**
     * Actualizar un producto en el sitio de WooCommerce con nuevos datos.
     *
     * @param array $product_data Los datos del producto que se actualizarán.
     * @return Response La respuesta de la API.
     */
    public function update( $product_data ) {
        $response = $this->pull_by_sku( $product_data['sku'] );
        if ( $response->has_error() || empty( $response->body() ) ) {
            return $response;
        }

        $product    = $response->body()[0];
        $product_id = $product['id'];

        self::clean_data( $product_data );
        return $this->client->put( "products/{$product_id}", $product_data );
    }

    /**
     * Enviar un producto nuevo o actualizarlo si ya existe.
     *
     * @param array $product_data Los datos del producto a enviar o actualizar.
     * @return Response La respuesta de la API.
     */
    public function push_or_update( $product_data ) {
        $response = $this->pull_by_sku( $product_data['sku'] );
        if ( $response->has_error() || empty( $response->body() ) ) {
            return $this->push( $product_data );
        }

        $product    = $response->body()[0];
        $product_id = $product['id'];

        self::clean_data( $product_data );
        return $this->client->put( "products/{$product_id}", $product_data );
    }

    /**
     * Obtener un producto desde el sitio de WooCommerce por su ID.
     *
     * @param string $id El ID del producto.
     * @return Response La respuesta de la API.
     */
    public function pull( $id ) {
        return $this->client->get( "products/{$id}" );
    }

    /**
     * Obtener un producto desde el sitio de WooCommerce por su SKU.
     *
     * @param string $sku El SKU del producto.
     * @return Response La respuesta de la API.
     */
    public function pull_by_sku( $sku ) {
        if ( empty( $sku ) ) {
            return new Response( 422, null, array(), 'SKU is required' );
        }

        return $this->client->get( "products?sku={$sku}" );
    }

    /**
     * Enviar varios productos en un solo lote.
     *
     * @param array $products  Un array de productos a enviar.
     * @param int   $batch_size El tamaño del lote.
     * @return array Un array de respuestas de la API.
     */
    public function push_batch( $products, $batch_size ) {
        $max_batch_size = 100;
        if ( $batch_size > $max_batch_size ) {
            $batch_size = $max_batch_size;
        }

        $products_data = array();
        $skus          = array();
        foreach ( $products as $product ) {
            $temp = $product;
            if ( is_a( $temp, 'WC_Product' ) ) {
                $temp = self::parse_product_to_json( $product );
            }

            $sku = $temp['sku'];
            if ( empty( $sku ) ) {
                continue;
            }

            self::clean_data( $temp );

            $products_data[] = $temp;
            $skus[]          = $sku;
        }

        $response = $this->pull_all(
            array(
                'sku'      => implode( ',', $skus ),
                'per_page' => 100,
                'context'  => 'edit',
            )
        );

        if ( $response->has_error() ) {
            return $response;
        }

        $skus_to_remove    = array_column( $response->body(), 'sku' );
        $filtered_products = array_filter(
            $products_data,
            function ( $product ) use ( $skus_to_remove ) {
                return ! in_array( $product['sku'], $skus_to_remove );
            }
        );

        $data = array( 'create' => $filtered_products );
        return $this->client->post( 'products/batch', $data );
    }

    /**
     * Actualizar varios productos en un solo lote utilizando el endpoint de batch update de WooCommerce.
     *
     * @param array $products    Un array de productos a actualizar.
     * @param int   $batch_size  El tamaño del lote (máximo permitido: 100).
     * @return respuesta de la API.
     */
    public function update_batch( $products, $batch_size ) {
        $max_batch_size = 100;
        if ( $batch_size > $max_batch_size ) {
            $batch_size = $max_batch_size;
        }

        $products_data = array();
        $skus          = array();
        foreach ( $products as $product ) {
            $temp = $product;
            if ( is_a( $temp, 'WC_Product' ) ) {
                $temp = self::parse_product_to_json( $product );
            }

            $sku = $temp['sku'];
            if ( empty( $sku ) ) {
                continue;
            }

            self::clean_data( $temp );

            $products_data[] = $temp;
            $skus[]          = $sku;
        }

        $response = $this->pull_all(
            array(
                'sku'      => implode( ',', $skus ),
                'per_page' => 100,
                'context'  => 'edit',
            )
        );
        if ( $response->has_error() ) {
            return $response;
        }

        $products_merged = self::merge_arrays_by_sku( $response->body(), $products_data );

        $data = array( 'update' => $products_merged );
        return $this->client->post( 'products/batch', $data );
    }

    /**
     * Enviar varios productos en un solo lote (crear o actualizar).
     *
     * @param array $products  Un array de productos a enviar o actualizar.
     * @param int   $batch_size El tamaño del lote.
     * @return array Un array de respuestas de la API.
     */
    public function push_or_update_batch( $products, $batch_size ) {
        $responses = array();
        foreach ( array_chunk( $products, $batch_size ) as $batch ) {
            foreach ( $batch as $product ) {
                $responses[] = $this->push_or_update( $product );
            }
        }
        return $responses;
    }

    /**
     * Obtener todos los productos desde el sitio WooCommerce.
     *
     * @param array $args Argumentos adicionales para la solicitud (opcional), como filtros de paginación, estado de los productos, etc.
     *
     * @return Response La respuesta de la API que contiene la lista de productos.
     */
    public function pull_all( $args = array() ) {
        return $this->client->get( 'products', $args );
    }

    /**
     * Obtener varios productos en lotes desde el sitio WooCommerce.
     *
     * @param int           $batch_size      El tamaño del lote.
     * @param array         $args            Argumentos adicionales para la solicitud, como filtros.
     * @param callable|null $callback   (Opcional) Función para procesar cada lote recuperado.
     *
     * @return void
     */
    public function pull_batch( $batch_size, $args = array(), callable $callback = null ) {
        $page = 1;

        do {
            $args['per_page'] = $batch_size;
            $args['page']     = $page;

            $response = $this->client->get( 'products', $args );

            if ( is_callable( $callback ) ) {
                call_user_func( $callback, $response, $page );
            }

            if ( $response->has_error() || empty( $response->body() ) ) {
                break;
            }

            $page += 1;
        } while ( true );
    }

    /**
     * Parsear los datos del producto en un formato adecuado para la API de WooCommerce.
     *
     * @param WC_Product $product El objeto del producto de WooCommerce.
     * @param array      $custom_fields (opcional) Array de campos personalizados para fusionar con los datos del producto.
     *                             Si los campos personalizados ya existen, se sobrescribirán los valores predeterminados.
     * @return array Los datos del producto en formato de array.
     */
    public static function parse_product_to_json( $product, $custom_fields = array() ) {
        if ( ! is_a( $product, 'WC_Product' ) ) {
            return array(); // No es un objeto de producto válido, retornar un array vacío.
        }

        $data = $product->get_data();
        self::clean_data( $data );

        if ( ! empty( $custom_fields ) ) {
            // Fusionar los campos personalizados en los datos del producto,
            // sobrescribiendo los valores predeterminados si existen.
            $data = array_merge( $data, $custom_fields );
        }

        return $data;
    }

    /**
     * Convertir los datos JSON del producto a un objeto WC_Product adecuado para WooCommerce.
     *
     * @param array $json_data Los datos del producto en formato JSON (array).
     * @return WC_Product|null El objeto WC_Product, o null si los datos no son válidos.
     */
    public static function json_product_to_wc_product( $json_data ) {
        if ( empty( $json_data ) || ! is_array( $json_data ) ) {
            return null;
        }

        self::clean_data( $json_data );
        $product_type = isset( $json_data['type'] ) ? $json_data['type'] : 'simple';
        $product      = null;

        switch ( $product_type ) {
            case 'variable':
                $product = new WC_Product_Variable();
                break;
            case 'grouped':
                $product = new WC_Product_Grouped();
                break;
            case 'external':
                $product = new WC_Product_External();
                break;
            case 'simple':
            default:
                $product = new WC_Product();
                break;
        }

        $product->set_props( $json_data );

        return $product;
    }

    /**
     * Actualiza un producto utilizando las propiedades de otro producto.
     *
     * @param WC_Product $source_product El producto fuente.
     * @param WC_Product $target_product El producto a actualizar.
     * @return WC_Product El producto actualizado.
     */
    public static function merge_products( $source_product, $target_product ) {
        if ( is_a( $source_product, 'WC_Product' ) ) {
            $source_data = $source_product->get_data();
        }

        if ( ! is_a( $target_product, 'WC_Product' ) ) {
            $target_product = self::json_product_to_wc_product( $target_product );
        }

        self::clean_data( $source_data );
        $target_product->set_props( $source_data );

        return $target_product;
    }

    /**
     * Realiza un merge entre dos arrays de productos, uniendo aquellos que tienen el mismo 'sku'.
     *
     * Esta función compara dos arrays de datos de productos utilizando la clave 'sku' y devuelve un nuevo array que
     * contiene los productos que tienen la misma clave en ambos arrays. Los productos coincidentes se combinan usando
     * `array_merge`, donde los valores del array `target` sobrescriben los valores del array `source` en caso de conflicto.
     *
     * Los productos de WooCommerce (`WC_Product`) pueden ser convertidos a JSON dependiendo de las opciones proporcionadas.
     *
     * @param array $source El primer array de productos (fuente), donde cada elemento es un array asociativo o un `WC_Product`.
     * @param array $target El segundo array de productos (destino), donde los datos del array `source` se combinan.
     * @param array $options (Opcional) Array asociativo con opciones:
     *                       - 'parse_source' (bool): Si es `true`, los productos en el array `source` serán convertidos a JSON. Por defecto es `false`.
     *                       - 'parse_target' (bool): Si es `true`, los productos en el array `target` serán convertidos a JSON. Por defecto es `false`.
     *
     * @return array Un array con los productos que tienen el mismo 'sku' en ambos arrays.
     *               Cada elemento es un array asociativo combinado de ambos arrays, con los valores del array `target`
     *               sobrescribiendo los del array `source` en caso de conflicto.
     */
    public static function merge_arrays_by_sku( $source, $target, $options = array() ) {
        $parse_source = isset( $options['parse_source'] ) ? $options['parse_source'] : false;
        $parse_target = isset( $options['parse_target'] ) ? $options['parse_target'] : false;

        $merged = array();

        if ( $parse_source ) {
            foreach ( $source as &$item ) {
                if ( is_a( $item, 'WC_Product' ) ) {
                    $item = self::parse_product_to_json( $item );
                }
            }
        }

        if ( $parse_target ) {
            foreach ( $target as &$item ) {
                if ( is_a( $item, 'WC_Product' ) ) {
                    $item = self::parse_product_to_json( $item );
                }
            }
        }

        // Convertir ambos arrays a arrays asociativos por la clave 'sku' para facilitar la comparación
        $source_by_sku = array_column( $source, null, 'sku' );
        $target_by_sku = array_column( $target, null, 'sku' );

        foreach ( $source_by_sku as $sku => $item1 ) {
            if ( isset( $target_by_sku[ $sku ] ) ) {
                $merged[] = array_merge( $item1, $target_by_sku[ $sku ] );
            }
        }

        return $merged;
    }

    public static function clean_data( &$product_data ) {
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
}
