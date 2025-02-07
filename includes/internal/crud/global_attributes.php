<?php

namespace WooHive\Internal\Crud;

use WooHive\Config\Constants;
use WooHive\Utils\Debugger;

use WP_Error;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Global_Attributes {

    /**
     * Verifica si la taxonomia existe en base a su nombre
     *
     * @since 1.1.0
     * @param string $name El nombre o slug del atributo.
     * @return bool True si el atributo es global, false si no lo es.
     */
    public static function check_taxonomy_exists( string $name ): bool {
        if ( wc_attribute_taxonomy_id_by_name( $name ) !== 0 ) {
            return true;
        }

        if ( ! str_starts_with( $name, 'pa_' ) ) {
            $name = wc_sanitize_taxonomy_name( $name );
            $name = 'pa_' . $name;
        }

        return taxonomy_exists( $name );
    }

    /**
     * Verifica si el slug del atributo es un nombre válido para un atributo global.
     *
     * @since 1.1.0
     * @param string $slug El slug del atributo.
     * @return bool True si el slug es válido, false si no lo es.
     */
    public static function is_global_by_slug( string $slug ): bool {
        return str_starts_with( $slug, 'pa_' );
    }

    /**
     * Crea un atributo global usando wc_create_attribute.
     *
     * @param string $name El nombre del atributo.
     * @param array  $options Las opciones del atributo.
     * @return int|WP_Error El ID del atributo creado o WP_Error si falla.
     */
    public static function create( string $name, array $options ): int|WP_Error {
        $taxonomy_name = self::get_taxonomy_name( $name );
        if ( self::check_taxonomy_exists( $taxonomy_name ) ) {
            return new WP_Error( 'attribute_exists', __( 'El atributo ya existe.', Constants::TEXT_DOMAIN ) );
        }

        $args = array(
            'name'         => sanitize_text_field( $name ),
            'slug'         => sanitize_title( $name ),
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        );

        $attribute_id_result = wc_create_attribute( $args );
        if ( is_wp_error( $attribute_id_result ) ) {
            return $attribute_id_result;
        }

        // Forzar registro de taxonomia ya que wc_create_attribute demora en registrar la taxonomia
        if ( true ) {
            Debugger::debug( 'Registrando taxonomia: ' . $taxonomy_name );

            register_taxonomy(
                $taxonomy_name,
                apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
                apply_filters(
                    'woocommerce_taxonomy_args_' . $taxonomy_name,
                    array(
                        'labels'       => array(
                            'name' => $name,
                        ),
                        'hierarchical' => false,
                        'show_ui'      => false,
                        'query_var'    => true,
                        'rewrite'      => false,
                    )
                )
            );
        }

        if ( ! empty( $taxonomy_name ) && ! empty( $options ) ) {
            foreach ( $options as $option ) {
                $term = wp_insert_term( sanitize_text_field( $option ), $taxonomy_name );
                if ( is_wp_error( $term ) ) {
                    Debugger::error( $term );
                    continue;
                }
            }
        }

        if ( true ) {
            clean_taxonomy_cache( $taxonomy_name );
        }

        return $attribute_id_result;
    }

    /**
     * Obtiene el ID de la taxonomía de un atributo global basado en su nombre.
     *
     * Esta función busca la taxonomía global del atributo por su nombre y devuelve el ID correspondiente.
     * Si no se encuentra la taxonomía, se devuelve un error.
     *
     * @since 1.1.0
     * @param string $name El nombre del atributo para el cual se desea obtener el ID de la taxonomía.
     * @return int|WP_Error El ID de la taxonomía si se encuentra, o un objeto WP_Error si no se encuentra.
     */
    public static function get_attribute_taxonomy_id_by_name( string $name ): int|WP_Error {
        $id = wc_attribute_taxonomy_id_by_name( $name );
        if ( $id === 0 ) {
            return new WP_Error( 'taxonomy_not_found', __( 'No se encontró la taxonomía', Constants::TEXT_DOMAIN ) );
        }

        return $id;
    }

    /**
     * Actualiza un atributo global.
     *
     * @since 1.1.0
     * @param int   $attribute_id El ID del término (atributo global).
     * @param array $data Datos actualizados para el atributo global.
     * @return WP_Error|int El ID del término actualizado o WP_Error en caso de error.
     */
    public static function update( int $attribute_id, array $data ): int|WP_Error {
        $taxonomy_name = wc_attribute_taxonomy_name_by_id( $attribute_id );
        if ( ! $taxonomy_name || ! self::check_taxonomy_exists( $taxonomy_name ) ) {
            return new WP_Error( 'attribute_not_found', __( 'El atributo global no existe.', Constants::TEXT_DOMAIN ) );
        }

        foreach ( $data['options'] as $option ) {
            $result = wp_insert_term( sanitize_text_field( $option ), $taxonomy_name );
            if ( is_wp_error( $result ) ) {
                Debugger::error( $result );
            }
        }

        if ( true ) {
            clean_taxonomy_cache( $taxonomy_name );
        }

        return $attribute_id;
    }

    /**
     * Obtiene el ID del término global por nombre.
     *
     * @since 1.1.0
     * @param string $name El nombre del atributo.
     * @param string $taxonomy El nombre de la taxonomia.
     * @return int|WP_Error El ID del término o WP_Error si no se encuentra.
     */
    public static function get_term_id_by_name( string $name, string $taxonomy ): int|WP_Error {
        $taxonomy = self::get_taxonomy_name( $name );
        $name = sanitize_text_field( $name );

        $term = get_term_by( 'name', $name, $taxonomy );
        if ( is_wp_error( $term ) || ! $term ) {
            return new WP_Error( 'term_not_found', __( 'Término no encontrado.', Constants::TEXT_DOMAIN ) );
        }

        return $term->term_id;
    }

    /**
     * Obtiene el nombre de la taxonomía global por nombre.
     *
     * @param string $name El nombre del atributo.
     * @return string El nombre de la taxonomía.
     */
    public static function get_taxonomy_name( string $name ): string {
        $name = wc_sanitize_taxonomy_name( $name );
        return wc_attribute_taxonomy_name( $name );
    }

    /**
     * Obtiene los IDs de términos correspondientes a las opciones proporcionadas para un atributo global.
     *
     * @since 1.1.0
     * @param string $attribute_name El nombre del atributo global.
     * @param array  $options Las opciones cuyos IDs de término se quieren obtener.
     * @return array|WP_Error Un array de IDs de términos o WP_Error en caso de error.
     */
    public static function get_term_ids_by_options( string $attribute_name, array $options ): array|WP_Error {
        $taxonomy = self::get_taxonomy_name( $attribute_name );
        if ( ! self::check_taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'invalid_taxonomy', __( 'El atributo no es global.', Constants::TEXT_DOMAIN ) );
        }

        $term_ids = array();
        foreach ( $options as $option ) {
            $option = sanitize_text_field( $option );

            $term = get_term_by( 'name', $option, $taxonomy );
            if ( $term && ! is_wp_error( $term ) ) {
                $term_ids[] = $term->term_id;
            } else {
                $error = new WP_Error( 'term_not_found', sprintf( __( 'La opción "%1$s" no existe en el atributo "%2$s".', Constants::TEXT_DOMAIN ), $option, $attribute_name ) );
                Debugger::error( $error );
            }
        }

        return $term_ids;
    }
}
