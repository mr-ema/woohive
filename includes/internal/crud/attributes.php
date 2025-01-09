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
        return array_filter(
            $data,
            fn( $key ) => ! in_array( $key, self::$invalid_props, true ),
            ARRAY_FILTER_USE_KEY
        );
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

        // Obtener los atributos existentes del producto.
        $existing_attributes = $product->get_attributes();
        foreach ( $existing_attributes as $attribute ) {
            if ( $attribute->get_name() === $filtered_data['name'] ) {
                // Si el atributo ya existe, actualizarlo.
                $attribute->set_options( $filtered_data['options'] );
                $product->save();
                return true;
            }
        }

        // Si el atributo no existe, crear uno nuevo.
        $attribute = new WC_Product_Attribute();
        $attribute->set_name( $filtered_data['name'] );
        $attribute->set_options( $filtered_data['options'] );
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
