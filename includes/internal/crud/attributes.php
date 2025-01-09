<?php

namespace WooHive\Internal\Crud;

use WooHive\Config\Constants;
use \WP_Error;
use \WC_Product;
use \WC_Product_Attribute;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Attributes {

    /**
     * Lista de propiedades inválidas que no deberían usarse al actualizar o crear atributos.
     *
     * @var array
     */
    private static array $invalid_props = [
        'id',              // No permitimos actualizar el ID directamente.
    ];

    /**
     * Limpia los datos para eliminar propiedades inválidas.
     *
     * @param array $data Datos sin procesar.
     *
     * @return array Datos filtrados con las propiedades inválidas eliminadas.
     */
    public static function clean_data( array $data ): array {
        $cleaned_data = [
            'name'     => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
            'options'  => isset( $data['options'] ) ? array_map( 'sanitize_text_field', $data['options'] ) : [],
            'visible'  => isset( $data['visible'] ) ? (bool) $data['visible'] : true,
            'variation'=> isset( $data['variation'] ) ? (bool) $data['variation'] : false,
        ];

        return array_filter(
            $cleaned_data,
            fn( $key ) => ! in_array( $key, self::$invalid_props, true ),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Actualiza un atributo de producto (puede ser de taxonomía o personalizado).
     *
     * @param WC_Product $product El objeto del producto.
     * @param array $data Datos del atributo a actualizar (nombre, opciones, visible, variación).
     *
     * @return bool|WP_Error Devuelve true si la actualización fue exitosa o WP_Error en caso de error.
     */
    public static function update( WC_Product $product, array $data ): bool|WP_Error {
        $filtered_data = self::clean_data( $data );

        if ( empty( $filtered_data ) || empty( $filtered_data['name'] ) ) {
            return new WP_Error( 'invalid_data', __( 'El nombre es obligatorio para actualizar un atributo de producto.', Constants::TEXT_DOMAIN ) );
        }

        $existing_attributes = $product->get_attributes();

        $attribute_name = wc_sanitize_taxonomy_name( $filtered_data['name'] );
        $taxonomy_name  = 'pa_' . $attribute_name; // Para taxonomías de WooCommerce.

        if ( taxonomy_exists( $taxonomy_name ) ) {
            if ( isset( $filtered_data['options'] ) && is_array( $filtered_data['options'] ) ) {
                foreach ( $filtered_data['options'] as $term ) {
                    if ( ! term_exists( $term, $taxonomy_name ) ) {
                        wp_insert_term( $term, $taxonomy_name );
                    }
                }

                $existing_attributes[ $taxonomy_name ] = array(
                    'name'      => $taxonomy_name,
                    'options'   => $filtered_data['options'],
                    'visible'   => $filtered_data['visible'] ?? true,
                    'variation' => $filtered_data['variation'] ?? false,
                );
            }
        } else {
            foreach ( $existing_attributes as $key => $attribute ) {
                if ( $attribute instanceof WC_Product_Attribute && $attribute->get_name() === $attribute_name ) {
                    if ( isset( $filtered_data['options'] ) && is_array( $filtered_data['options'] ) ) {
                        $attribute->set_options( $filtered_data['options'] );
                    }
                    $attribute->set_visible( $filtered_data['visible'] ?? true );
                    $attribute->set_variation( $filtered_data['variation'] ?? false );

                    $existing_attributes[ $key ] = $attribute;
                    $product->set_attributes( $existing_attributes );

                    try {
                        $product->save();
                    } catch ( Exception $e ) {
                        return new WP_Error( 'save_error', __( 'Error al guardar el producto.', Constants::TEXT_DOMAIN ) );
                    }

                    return true;
                }
            }

            return new WP_Error( 'attribute_not_found', __( 'El atributo no existe para este producto.', Constants::TEXT_DOMAIN ) );
        }

        $product->set_attributes( $existing_attributes );

        try {
            $product->save();
        } catch ( Exception $e ) {
            return new WP_Error( 'save_error', __( 'Error al guardar el producto.', Constants::TEXT_DOMAIN ) );
        }

        return true;
    }

    /**
     * Crea un nuevo atributo de producto para un producto específico.
     *
     * @param WC_Product $product El objeto del producto.
     * @param array $data Datos para crear el atributo del producto.
     *
     * @return bool|WP_Error Devuelve true en caso de éxito o WP_Error en caso de fallo.
     */
    public static function create( WC_Product $product, array $data ): bool|WP_Error {
        $filtered_data = self::clean_data( $data );

        if ( empty( $filtered_data ) || empty( $filtered_data['name'] ) ) {
            return new WP_Error( 'invalid_data', __( 'El nombre es obligatorio para crear un atributo de producto.', Constants::TEXT_DOMAIN ) );
        }

        // Obtener los atributos existentes del producto.
        $existing_attributes = $product->get_attributes();

        // Crear un nuevo atributo.
        $attribute = new WC_Product_Attribute();
        $attribute->set_name( $filtered_data['name'] );

        if ( ! empty( $filtered_data['options'] ) ) {
            $attribute->set_options( $filtered_data['options'] );
        }

        $attribute->set_position( count( $existing_attributes ) + 1 );
        $attribute->set_visible( isset( $filtered_data['visible'] ) ? $filtered_data['visible'] : true );
        $attribute->set_variation( isset( $filtered_data['variation'] ) ? $filtered_data['variation'] : false );

        // Añadir el nuevo atributo al producto.
        $existing_attributes[] = $attribute;
        $product->set_attributes( $existing_attributes );
        $product->save();

        return true;
    }

    /**
     * Crea o actualiza un atributo de producto para un producto específico.
     *
     * @param WC_Product $product El objeto del producto.
     * @param array $data Datos para crear o actualizar el atributo del producto.
     *
     * @return bool|WP_Error Devuelve true en caso de éxito o WP_Error en caso de fallo.
     */
    public static function create_or_update( WC_Product $product, array $data ): bool|WP_Error {
        $filtered_data = self::clean_data( $data );

        if ( empty( $filtered_data ) || empty( $filtered_data['name'] ) ) {
            return new WP_Error( 'invalid_data', __( 'El nombre es obligatorio para crear o actualizar un atributo de producto.', Constants::TEXT_DOMAIN ) );
        }

        // Verificar si el atributo ya existe y actualizarlo.
        $update_result = self::update( $product, $data );
        if ( ! is_wp_error( $update_result ) ) {
            return true; // Si se actualizó con éxito, retornamos true.
        }

        // Si no existe, crear un nuevo atributo.
        return self::create( $product, $data );
    }

    /**
     * Crea o actualiza múltiples atributos de producto para un producto específico.
     *
     * @param WC_Product $product El objeto del producto.
     * @param array $attributes Lista de datos para crear o actualizar atributos.
     *
     * @return array Resultados de la creación o actualización de atributos:
     *               - 'error_count'        (int)
     *               - 'total_updated'      (int)
     *               - 'total_created'      (int)
     *               - 'total_processed'    (int)
     */
    public static function create_or_update_batch( WC_Product $product, array $attributes ): array {
        $results          = array();
        $error_count      = 0;
        $total_processed  = 0;
        $total_created    = 0;
        $total_updated    = 0;

        foreach ( $attributes as $data ) {
            $result = self::create_or_update( $product, $data );

            if ( is_wp_error( $result ) ) {
                $results[] = $result;
                $error_count += 1;
            } else {
                $results[] = $result;
                $total_processed += 1;

                // Si el atributo fue creado, incrementa 'total_created'.
                // Si el atributo fue actualizado, incrementa 'total_updated'.
                if ( isset( $result['created'] ) && $result['created'] ) {
                    $total_created += 1;
                } elseif ( isset( $result['updated'] ) && $result['updated'] ) {
                    $total_updated += 1;
                }
            }
        }

        // Resumen
        $results['error_count']      = $error_count;
        $results['total_processed']  = $total_processed;
        $results['total_created']    = $total_created;
        $results['total_updated']    = $total_updated;

        return $results;
    }
}
