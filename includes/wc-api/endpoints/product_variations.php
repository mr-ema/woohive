<?php

namespace WooHive\WCApi\Endpoints;

use WooHive\WCApi\Response;
use WooHive\WCApi\Client;

/** Evitar el acceso directo */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Product_Variations {

    private Client $client;

    /**
     * Limpiar los datos de una variación eliminando campos innecesarios.
     *
     * @param array $variation_data Los datos de la variación.
     */
    public static function clean_data( &$variation_data ) {
        unset(
            $variation_data['id'],
            $variation_data['date_created'],
            $variation_data['date_modified']
        );
    }

    /**
     * Constructor para la clase de variaciones de productos.
     *
     * @param Client $client La instancia del cliente API.
     */
    public function __construct( $client ) {
        $this->client = $client;
    }

    /**
     * Crear o enviar una variación al sitio de WooCommerce para un producto específico.
     *
     * @param int   $parent_id      El ID del producto principal.
     * @param array $variation_data Los datos de la variación a enviar.
     * @return Response La respuesta de la API.
     */
    public function push( $parent_id, $variation_data ): Response {
        if ( empty( $variation_data['sku'] ) ) {
            return new Response( 422, null, array(), 'SKU de variación es requerido' );
        }

        self::clean_data( $variation_data );
        return $this->client->post(
            "products/{$parent_id}/variations",
            $variation_data
        );
    }

    /**
     * Actualizar una variación en el sitio de WooCommerce para un producto específico con nuevos datos.
     *
     * @param int   $parent_id      El ID del producto principal.
     * @param array $variation_data Los datos de la variación que se actualizarán.
     * @return Response La respuesta de la API.
     */
    public function update( $parent_id, $variation_data ): Response {
        $response = $this->pull_by_sku( $parent_id, $variation_data['sku'] );
        if ( $response->has_error() || empty( $response->body() ) ) {
            return $response;
        }

        $variation = $response->body()[0];
        self::clean_data( $variation_data );
        return $this->client->put( "products/{$parent_id}/variations/{$variation['id']}", $variation_data );
    }

    /**
     * Enviar una variación nueva o actualizarla si ya existe para un producto específico.
     *
     * @param int   $parent_id      El ID del producto principal.
     * @param array $variation_data Los datos de la variación a enviar o actualizar.
     * @return Response La respuesta de la API.
     */
    public function push_or_update( $parent_id, $variation_data ): Response {
        $response = $this->pull_by_sku( $parent_id, $variation_data['sku'] );
        if ( $response->has_error() || empty( $response->body() ) ) {
            return $this->push( $parent_id, $variation_data );
        }

        $variation = $response->body()[0];
        self::clean_data( $variation_data );
        return $this->client->put( "products/{$parent_id}/variations/{$variation['id']}", $variation_data );
    }

    /**
     * Obtener una variacion por su id.
     *
     * @param int $parent_id El ID del producto principal.
     * @param int $variation_id ID de la variacion.
     * @return Response La respuesta de la API que contiene la lista de variaciones.
     */
    public function pull( $parent_id, $variation_id ): Response {
        return $this->client->get( "products/{$parent_id}/variations/{$variation_id}" );
    }

    /**
     * Obtener una variación desde el sitio de WooCommerce para un producto específico por su ID.
     *
     * @param int $parent_id El ID del producto principal.
     * @param int $variation_sku El sku de la variación.
     * @return Response La respuesta de la API.
     */
    public function pull_by_sku( $parent_id, $variation_sku ): Response {
        if ( empty( $parent_id ) || empty( $variation_sku ) ) {
            return new Response( 422, null, array(), 'ID de producto y variación son requeridos' );
        }

        return $this->pull_all( $parent_id, array( 'sku' => $variation_sku ) );
    }

    /**
     * Obtener todas las variaciones de un producto desde el sitio WooCommerce.
     *
     * @param int   $parent_id El ID del producto principal.
     * @param array $args Argumentos adicionales para la solicitud (opcional), como filtros de paginación, estado de las variaciones, etc.
     * @return Response La respuesta de la API que contiene la lista de variaciones.
     */
    public function pull_all( $parent_id, $args = array() ): Response {
        return $this->client->get( "products/{$parent_id}/variations", $args );
    }

    /**
     * Enviar varias variaciones en un solo lote para un producto específico.
     *
     * @param int   $parent_id El ID del producto principal.
     * @param array $variations Un array de variaciones a enviar.
     * @param int   $batch_size El tamaño del lote.
     * @return array Un array de respuestas de la API.
     */
    public function push_batch( $parent_id, $variations, $batch_size ) {
        $max_batch_size = 100;
        if ( $batch_size > $max_batch_size ) {
            $batch_size = $max_batch_size;
        }

        $variations_data = array();
        foreach ( $variations as $variation ) {
            $temp = $variation;
            if ( ! is_array( $temp ) ) {
                continue;
            }

            self::clean_data( $temp );

            $variations_data[] = $temp;
        }

        return $this->client->post( "products/{$parent_id}/variations/batch", array( 'create' => $variations_data ) );
    }

    /**
     * Actualizar varias variaciones en un solo lote para un producto específico utilizando el endpoint de batch update de WooCommerce.
     *
     * @param int   $parent_id El ID del producto principal.
     * @param array $variations Un array de variaciones a actualizar.
     * @param int   $batch_size El tamaño del lote (máximo permitido: 100).
     * @return respuesta de la API.
     */
    public function update_batch( $parent_id, $variations, $batch_size ) {
        $max_batch_size = 100;
        if ( $batch_size > $max_batch_size ) {
            $batch_size = $max_batch_size;
        }

        $variations_data = array();
        foreach ( $variations as $variation ) {
            $temp = $variation;
            if ( ! is_array( $temp ) ) {
                continue;
            }

            self::clean_data( $temp );

            $variations_data[] = $temp;
        }

        return $this->client->post( "products/{$parent_id}/variations/batch", array( 'update' => $variations_data ) );
    }
}
