<?php

namespace WooHive\WCApi\Endpoints;

use WooHive\WCApi\Response;


/** Evitar el acceso directo */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Product_Categories {

    private $client;

    /**
     * Constructor para la clase de categorías de productos.
     *
     * @param Client $client La instancia del cliente API.
     */
    public function __construct( $client ) {
        $this->client = $client;
    }

    /**
     * Crear o enviar una categoría al sitio de WooCommerce.
     *
     * @param array $category_data Los datos de la categoría a enviar.
     * @return Response La respuesta de la API.
     */
    public function push( $category_data ): Response {
        if ( empty( $category_data['name'] ) ) {
            return new Response( 422, null, array(), 'Category name is required' );
        }

        self::clean_data( $category_data );
        return $this->client->post(
            'products/categories',
            $category_data
        );
    }

    /**
     * Actualizar una categoría en el sitio de WooCommerce con nuevos datos.
     *
     * @param array $category_data Los datos de la categoría que se actualizarán.
     * @return Response La respuesta de la API.
     */
    public function update( $category_data ) {
        $response = $this->pull_by_id( $category_data['id'] );
        if ( $response->has_error() || empty( $response->body() ) ) {
            return $response;
        }

        $category = $response->body()[0];
        self::clean_data( $category_data );
        return $this->client->put( "products/categories/{$category['id']}", $category_data );
    }

    /**
     * Enviar una categoría nueva o actualizarla si ya existe.
     *
     * @param array $category_data Los datos de la categoría a enviar o actualizar.
     * @return Response La respuesta de la API.
     */
    public function push_or_update( $category_data ) {
        $response = $this->pull_by_id( $category_data['id'] );
        if ( $response->has_error() || empty( $response->body() ) ) {
            return $this->push( $category_data );
        }

        $category = $response->body()[0];
        self::clean_data( $category_data );
        return $this->client->put( "products/categories/{$category['id']}", $category_data );
    }

    /**
     * Obtener una categoría desde el sitio de WooCommerce por su ID.
     *
     * @param int $category_id El ID de la categoría.
     * @return Response La respuesta de la API.
     */
    public function pull_by_id( $category_id ) {
        if ( empty( $category_id ) ) {
            return new Response( 422, null, array(), 'Category ID is required' );
        }

        return $this->client->get( "products/categories/{$category_id}" );
    }

    /**
     * Obtener todas las categorías desde el sitio WooCommerce.
     *
     * @param array $args Argumentos adicionales para la solicitud (opcional), como filtros de paginación, estado de las categorías, etc.
     *
     * @return Response La respuesta de la API que contiene la lista de categorías.
     */
    public function pull_all( $args = array() ) {
        return $this->client->get( 'products/categories', $args );
    }

    /**
     * Enviar varias categorías en un solo lote.
     *
     * @param array $categories  Un array de categorías a enviar.
     * @param int   $batch_size El tamaño del lote.
     * @return array Un array de respuestas de la API.
     */
    public function push_batch( $categories, $batch_size ) {
        $max_batch_size = 100;
        if ( $batch_size > $max_batch_size ) {
            $batch_size = $max_batch_size;
        }

        $categories_data = array();
        foreach ( $categories as $category ) {
            $temp = $category;
            if ( ! is_array( $temp ) ) {
                continue;
            }

            self::clean_data( $temp );

            $categories_data[] = $temp;
        }

        return $this->client->post( 'products/categories/batch', array( 'create' => $categories_data ) );
    }

    /**
     * Actualizar varias categorías en un solo lote utilizando el endpoint de batch update de WooCommerce.
     *
     * @param array $categories    Un array de categorías a actualizar.
     * @param int   $batch_size  El tamaño del lote (máximo permitido: 100).
     * @return respuesta de la API.
     */
    public function update_batch( $categories, $batch_size ) {
        $max_batch_size = 100;
        if ( $batch_size > $max_batch_size ) {
            $batch_size = $max_batch_size;
        }

        $categories_data = array();
        foreach ( $categories as $category ) {
            $temp = $category;
            if ( ! is_array( $temp ) ) {
                continue;
            }

            self::clean_data( $temp );

            $categories_data[] = $temp;
        }

        return $this->client->post( 'products/categories/batch', array( 'update' => $categories_data ) );
    }

    /**
     * Limpiar los datos de una categoría eliminando campos innecesarios.
     *
     * @param array $category_data Los datos de la categoría.
     */
    public static function clean_data( &$category_data ) {
        unset(
            $category_data['id'],
            $category_data['date_created'],
            $category_data['date_modified'],
            $category_data['count']
        );
    }
}
