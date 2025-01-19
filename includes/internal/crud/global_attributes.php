<?php

namespace WooHive\Internal\Crud;

use WP_Error;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Global_Attributes {

    /**
     * Verifica si el atributo es global.
     *
     * @param string $attribute_name El nombre del atributo.
     * @return bool
     */
    public static function is_global( string $attribute_name ): bool {
        $global_attributes = get_option( 'woocommerce_attribute_taxonomies', array() );
        foreach ( $global_attributes as $attr ) {
            if ( strtolower( $attr['attribute_name'] ) === strtolower( $attribute_name ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene el nombre del taxonomy para un atributo global.
     *
     * @param string $attribute_name El nombre del atributo.
     * @return string
     */
    public static function get_taxonomy_name( string $attribute_name ): string {
        return 'pa_' . wc_sanitize_taxonomy_name( $attribute_name );
    }

    /**
     * Crea un atributo global.
     *
     * @param string $attribute_name Nombre del atributo.
     * @param array  $options Opciones del atributo.
     * @return int|WP_Error El ID del atributo creado o WP_Error en caso de error.
     */
    public static function create( string $attribute_name, array $options = array() ): int|WP_Error {
        $attribute_name = wc_sanitize_taxonomy_name( $attribute_name );
        $args           = array(
            'slug'         => $attribute_name,
            'name'         => ucfirst( $attribute_name ),
            'options'      => $options,
        );

        $result = wp_insert_term( $attribute_name, 'pa_' . $attribute_name, $args );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $result['term_id'];  // Return the created term ID
    }

    /**
     * Crea o actualiza un término en una taxonomía global (maneja solo un término).
     *
     * @param string $attribute_name Nombre del atributo.
     * @param string $option El término a crear o actualizar.
     * @return int|WP_Error Devuelve el ID del término creado o actualizado o WP_Error en caso de error.
     */
    public static function create_or_update( string $attribute_name, string $option ): int|WP_Error {
        return self::create( $attribute_name, $option ); // Create handles both create and update for single term
    }

    /**
     * Crea o actualiza múltiples términos en una taxonomía global (maneja múltiples términos).
     *
     * @param string $attribute_name Nombre del atributo.
     * @param array  $options Lista de términos.
     * @return array|WP_Error Devuelve un array de IDs de términos creados o actualizados, o WP_Error en caso de error.
     */
    public static function create_or_update_batch( string $attribute_name, array $options ): array|WP_Error {
        $created_or_updated_ids = [];
        foreach ( $options as $option ) {
            $result = self::create_or_update( $attribute_name, $option );
            if ( is_wp_error( $result ) ) {
                return $result; // If any error occurs, return the error
            }
            $created_or_updated_ids[] = $result; // Collect term IDs
        }
        return $created_or_updated_ids; // Return an array of term IDs
    }

    /**
     * Actualiza un término en una taxonomía global (maneja solo un término).
     *
     * @param string $attribute_name Nombre del atributo.
     * @param string $option El término a actualizar.
     * @return int|WP_Error Devuelve el ID del término actualizado o WP_Error en caso de error.
     */
    public static function update( string $attribute_name, string $option ): int|WP_Error {
        return self::create_or_update( $attribute_name, $option ); // Simplified as WP handles duplicate terms
    }

    /**
     * Actualiza múltiples términos en una taxonomía global (maneja múltiples términos).
     *
     * @param string $attribute_name Nombre del atributo.
     * @param array  $options Lista de términos a actualizar.
     * @return array|WP_Error Devuelve un array de IDs de términos actualizados, o WP_Error en caso de error.
     */
    public static function update_batch( string $attribute_name, array $options ): array|WP_Error {
        return self::create_or_update_batch( $attribute_name, $options ); // Simplified as WP handles duplicate terms
    }
}
