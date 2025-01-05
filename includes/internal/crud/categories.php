<?php

namespace WooHive\Internal\Crud;

use WooHive\Config\Constants;

use \WP_Error;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Categories {

    /**
     * Lista de propiedades inválidas que no deberían usarse al actualizar o crear categorías.
     *
     * @var array
     */
    private static array $invalid_props = [
        'id',              // No permitimos actualizar el ID directamente.
        'count',           // Propiedad de solo lectura.
    ];

    /**
     * Limpia los datos para eliminar propiedades inválidas.
     *
     * @param array $data Datos sin procesar.
     *
     * @return array Datos filtrados con las propiedades inválidas eliminadas.
     */
    public static function clean_data( array $data ): array {
        return array_filter(
            $data,
            fn( $key ) => ! in_array( $key, self::$invalid_props, true ),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Crea una nueva categoría en WooCommerce si no existe.
     *
     * @param array $data Datos para crear la categoría.
     *
     * @return int|WP_Error Devuelve el ID de la categoría existente/creada o un WP_Error en caso de fallo.
     */
    public static function create( array $data ): int|WP_Error {
        $filtered_data = self::clean_data( $data );
        if ( empty( $filtered_data ) || empty( $filtered_data['name'] ) ) {
            return new WP_Error( 'invalid_data', __( 'El nombre es obligatorio para crear una categoría.', Constants::TEXT_DOMAIN ) );
        }

        $existing_term = get_term_by( 'name', $filtered_data['name'], 'product_cat' );
        if ( $existing_term ) {
            return $existing_term->term_id;
        }

        // Si no existe, crear una nueva categoría.
        $term = wp_insert_term(
            $filtered_data['name'],
            'product_cat',
            $filtered_data
        );

        if ( is_wp_error( $term ) ) {
            return $term;
        }

        return $term['term_id'];
    }

    /**
     * Crea múltiples categorías en WooCommerce.
     *
     * @param array $categories Lista de datos para crear categorías.
     *
     * @return array Resultados para cada categoría y estadísticas generales.
     */
    public static function create_batch( array $categories ): array {
        $results       = array();
        $error_count   = 0;
        $total_created = 0;

        foreach ( $categories as $data ) {
            $result = self::create( $data );

            if ( is_wp_error( $result ) ) {
                $results[] = $result;
                ++$error_count;
            } else {
                $results[] = $result;
                ++$total_created;
            }
        }

        // Sumary
        $results['error_count']    = $error_count;
        $results['total_created']  = $total_created;
        $results['total_categories'] = count( $categories );

        return $results;
    }

    /**
     * Crea o actualiza una categoría según su existencia.
     *
     * @param array $data Datos para crear o actualizar la categoría.
     *
     * @return int|WP_Error Devuelve el ID de la categoría o un WP_Error en caso de fallo.
     */
    public static function create_or_update( array $data ): int|WP_Error {
        $filtered_data = self::clean_data( $data );
        if ( empty( $filtered_data ) || empty( $filtered_data['name'] ) ) {
            return new WP_Error( 'invalid_data', __( 'El nombre es obligatorio para crear o actualizar una categoría.', Constans::TEXT_DOMAIN ) );
        }

        $existing_term = get_term_by( 'name', $filtered_data['name'], 'product_cat' );
        if ( $existing_term ) {
            $term_id = $existing_term->term_id;

            $result = wp_update_term(
                $term_id,
                'product_cat',
                $filtered_data
            );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return $term_id;
        }

        return self::create( $filtered_data );
    }

    /**
     * Crea o actualiza múltiples categorías según su existencia.
     *
     * @param array $categories Lista de datos para crear o actualizar categorías.
     *
     * @return array Resultados para cada categoría y estadísticas generales.
     */
    public static function create_or_update_batch( array $categories ): array {
        $results          = array();
        $error_count      = 0;
        $total_processed  = 0;

        foreach ( $categories as $data ) {
            $result = self::create_or_update( $data );

            if ( is_wp_error( $result ) ) {
                $results[] = $result;
                ++$error_count;
            } else {
                $results[] = $result;
                ++$total_processed;
            }
        }

        // Sumary
        $results['error_count']      = $error_count;
        $results['total_processed']  = $total_processed;
        $results['total_categories'] = count( $categories );

        return $results;
    }
}
