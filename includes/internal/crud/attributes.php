<?php

namespace WooHive\Internal\Crud;

use WooHive\Config\Constants;
use WooHive\Utils\Debugger;
use WooHive\Internal\Crud\Global_Attributes;

use WP_Error;
use WC_Product;
use WC_Product_Attribute;


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
    private static array $invalid_props = array(
        'id', // No permitimos actualizar el ID directamente.
    );

    /**
     * Limpia los datos para eliminar propiedades inválidas y genera el slug.
     *
     * @param array $data Datos sin procesar.
     * @return array Datos filtrados con las propiedades inválidas eliminadas y el slug generado.
     */
    public static function clean_data( array $data ): array {
        // Get human-readable name
        $human_name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

        // Generate the slug for internal use
        $slug = wc_sanitize_taxonomy_name( $human_name );

        return array(
            'name'      => $human_name,  // Use human-readable name for display
            'slug'      => $slug,        // Use slug for internal taxonomy or URL
            'options'   => isset( $data['options'] ) ? array_map( 'sanitize_text_field', $data['options'] ) : array(),
            'visible'   => isset( $data['visible'] ) ? (bool) $data['visible'] : true,
            'variation' => isset( $data['variation'] ) ? (bool) $data['variation'] : false,
        );
    }

    /**
     * Actualiza un atributo de producto (global o específico del producto).
     *
     * @param WC_Product $product El objeto del producto.
     * @param array      $data Datos del atributo a actualizar (nombre, opciones, visible, variación).
     * @return int|WP_Error Devuelve el ID del atributo actualizado o WP_Error en caso de fallo.
     */
    public static function update( WC_Product $product, array $data ): int|WP_Error {
        $filtered_data = self::clean_data( $data );

        if ( empty( $filtered_data['name'] ) ) {
            return new WP_Error(
                'invalid_data',
                __( 'El nombre es obligatorio para actualizar un atributo de producto.', Constants::TEXT_DOMAIN )
            );
        }

        $existing_attributes = $product->get_attributes();
        $attribute_name = $filtered_data['slug'];  // Use the slug for taxonomy or internal storage

        if ( Global_Attributes::is_global( $attribute_name ) ) {
            $taxonomy_name = Global_Attributes::get_taxonomy_name( $attribute_name );
            $result = Global_Attributes::create_or_update( $attribute_name, $filtered_data['options'] );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $attribute = new WC_Product_Attribute();
            $attribute->set_name( $filtered_data['name'] );  // Set human-readable name here
            $attribute->set_options( $filtered_data['options'] );
            $attribute->set_visible( $filtered_data['visible'] );
            $attribute->set_variation( $filtered_data['variation'] );

            $existing_attributes[ $taxonomy_name ] = $attribute;
        } else {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name( $filtered_data['name'] );  // Set human-readable name here
            $attribute->set_options( $filtered_data['options'] );
            $attribute->set_visible( $filtered_data['visible'] );
            $attribute->set_variation( $filtered_data['variation'] );

            $existing_attributes[ $attribute_name ] = $attribute;
        }

        $product->set_attributes( $existing_attributes );

        try {
            $product->save();

            Debugger::ok( __( 'Atributos actualizados correctamente', Constants::TEXT_DOMAIN ) );
            return $attribute->get_id();
        } catch ( \Exception $e ) {
            $message = sprintf( __( 'Error al guardar el producto: %s', Constants::TEXT_DOMAIN ), $e->getMessage() );

            Debugger::error( $message );
            return new WP_Error( 'save_error', $message);
        }
    }

    /**
     * Crea o actualiza un atributo de producto (maneja solo un atributo).
     *
     * @param WC_Product $product El objeto del producto.
     * @param array      $data Datos para crear o actualizar el atributo del producto.
     * @return int|WP_Error Devuelve el ID del atributo creado o actualizado, o WP_Error en caso de fallo.
     */
    public static function create_or_update( WC_Product $product, array $data ): int|WP_Error {
        $update_result = self::update( $product, $data );
        if ( ! is_wp_error( $update_result ) ) {
            return $update_result;
        }

        return self::create( $product, $data );
    }

    /**
     * Crea un atributo de producto (maneja solo un atributo).
     *
     * @param WC_Product $product El objeto del producto.
     * @param array      $data Datos del atributo a crear.
     * @return int|WP_Error Devuelve el ID del atributo creado o WP_Error en caso de fallo.
     */
    public static function create( WC_Product $product, array $data ): int|WP_Error {
        $filtered_data = self::clean_data( $data );

        if ( empty( $filtered_data ) || empty( $filtered_data['name'] ) ) {
            return new WP_Error(
                'invalid_data',
                __( 'El nombre es obligatorio para crear un atributo de producto.', Constants::TEXT_DOMAIN )
            );
        }

        $existing_attributes = $product->get_attributes();
        $attribute_name = $filtered_data['slug'];  // Use the slug for taxonomy or internal storage

        if ( Global_Attributes::is_global( $attribute_name ) ) {
            $taxonomy_name = Global_Attributes::get_taxonomy_name( $attribute_name );
            $result = Global_Attributes::create( $attribute_name, $filtered_data['options'] ?? array() );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $attribute = new WC_Product_Attribute();
            $attribute->set_name( $filtered_data['name'] );  // Set human-readable name here
            $attribute->set_options( $filtered_data['options'] ?? array() );
            $attribute->set_visible( $filtered_data['visible'] ?? true );
            $attribute->set_variation( $filtered_data['variation'] ?? false );

            $existing_attributes[ $taxonomy_name ] = $attribute;
        } else {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name( $filtered_data['name'] );  // Set human-readable name here
            $attribute->set_options( $filtered_data['options'] ?? array() );
            $attribute->set_visible( $filtered_data['visible'] ?? true );
            $attribute->set_variation( $filtered_data['variation'] ?? false );

            $existing_attributes[ $attribute_name ] = $attribute;
        }

        $product->set_attributes( $existing_attributes );

        try {
            $product->save();

            Debugger::ok( __( 'Atributos creados correctamente', Constants::TEXT_DOMAIN ) );
            return $attribute->get_id();
        } catch ( \Exception $e ) {
            $message = sprintf( __( 'Error al guardar el producto: %s', Constants::TEXT_DOMAIN ), $e->getMessage() );

            Debugger::error( $message );
            return new WP_Error( 'save_error', $message );
        }
    }

    /**
     * Crea o actualiza múltiples atributos de producto.
     *
     * @param WC_Product $product Producto a modificar.
     * @param array      $attributes Datos de los atributos.
     * @return array Resumen de resultados.
     */
    public static function create_or_update_batch( WC_Product $product, array $attributes ): array {
        $results         = array();
        $error_count     = 0;
        $total_processed = 0;

        foreach ( $attributes as $data ) {
            $result = self::create_or_update( $product, $data );
            if ( is_wp_error( $result ) ) {
                $results[]    = $result;
                $error_count += 1;
            } else {
                $results[]        = $result;
                $total_processed += 1;
            }
        }

        return compact( 'results', 'error_count', 'total_processed' );
    }
}
