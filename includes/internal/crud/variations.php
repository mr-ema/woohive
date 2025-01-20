<?php

namespace WooHive\Internal\Crud;

use WooHive\Config\Constants;

use WP_Error;
use WC_Product_Variation;
use WC_Product;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Variations {

    /**
     * Limpia los datos para eliminar propiedades inválidas en una variación.
     *
     * @param array $data Datos sin procesar de la variación.
     *
     * @return array Datos filtrados con las propiedades inválidas eliminadas.
     */
    public static function clean_data( array $data ): array {
        $invalid_props = array(
            'id',
            'date_created',
            'date_modified',
            'date_created_gmt',
            'permalink',
            'average_rating',
            'rating_count',
            'review_count',
            '_edit_lock',
            '_edit_last',
        );

        return array_filter(
            $data,
            fn( $key ) => ! in_array( $key, $invalid_props, true ),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Configura múltiples propiedades para una variación de producto.
     *
     * Este método permite establecer diversas propiedades de un producto
     * de variación en función de los datos proporcionados. Es útil para
     * actualizar múltiples atributos de forma eficiente.
     *
     * @param WC_Product_Variation $variation La instancia de la variación del producto.
     * @param array                $data Un array asociativo con las propiedades a configurar.
     *
     * @return void
     */
    public static function set_props( WC_Product_Variation $variation, array $data ): void {
        $valid_set_props = array( 'regular_price', 'sale_price', 'stock_quantity', 'status', 'manage_stock', 'weight', 'sku' );
        $filtered_data   = array_intersect_key( $data, array_flip( $valid_set_props ) );
        $variation->set_props( $filtered_data );

        if ( ! empty( $data['dimensions'] ) ) {
            $variation->set_length( $data['dimensions']['length'] ?? 0 );
            $variation->set_width( $data['dimensions']['width'] ?? 0 );
            $variation->set_height( $data['dimensions']['height'] ?? 0 );
        }

        if ( ! empty( $data['description'] ) ) {
            $variation->set_description( $data['description'] );
        }

        if ( ! empty( $data['attributes'] ) ) {
            $attributes_map = array_reduce(
                $data['attributes'],
                function ( $carry, $attribute ) {
                    $slug = sanitize_title( $attribute['name'] );
                    $carry[ $slug ] = $attribute['option'];
                    return $carry;
                },
                array()
            );

            $variation->set_attributes( $attributes_map );
        }

        if ( ! empty( $data['image'] ) ) {
            $external_id  = $data['image']['id'];
            $image_result = self::search_image( $data['image']['src'], $external_id );
            if ( $image_result ) {
                $variation->set_image_id( $image_result );
            }
        }

        if ( ! empty( $data['meta_data'] ) ) {
            foreach ( $data['meta_data'] as $meta_data => $meta_key ) {
                $variation->update_meta_data($meta_key, $meta_data);
            }
        }
    }

    /**
     * Crea una nueva variación para un producto.
     *
     * @param WC_Product $wc_product Objeto del producto al que pertenece la variación.
     * @param array      $data Datos de la variación a crear.
     *
     * @return int|WP_Error Retorna el ID de la variación creada o un error en caso de fallo.
     */
    public static function create( WC_Product $wc_product, array $filtered_data ): int|WP_Error {
        if ( ! $wc_product ) {
            return new WP_Error( 'invalid_product', __( 'El producto padre no existe o es inválido.', Constants::TEXT_DOMAIN ) );
        }

        $parent_sku = $wc_product->get_sku();
        if ( empty( $filtered_data['sku'] ) || $parent_sku === $filtered_data['sku'] ) {
            return new WP_Error( 'create_error', __( 'Sku de la variacion no existe o es igual al padre.', Constants::TEXT_DOMAIN ) );
        }

        try {
            $filtered_data = self::clean_data( $filtered_data );
            $variation     = new WC_Product_Variation();

            $variation->set_parent_id( $wc_product->get_id() );
            self::set_props( $variation, $filtered_data );

            $variation->save();

            $id = $variation->get_id();

            return $id;
        } catch ( \Exception $e ) {
            return new WP_Error( 'create_error', __( 'Error al crear la variación: ' . $e->getMessage(), Constants::TEXT_DOMAIN ) );
        }
    }

    /**
     * Actualiza una variación existente.
     *
     * @param WC_Product_Variation $variation Objeto de la variación a actualizar.
     * @param array                $data Datos a actualizar en la variación.
     *
     * @return int|WP_Error Retorna el ID de la variación actualizada o un error en caso de fallo.
     */
    public static function update( WC_Product_Variation $variation, array $data ): int|WP_Error {
        if ( ! $variation || $variation->get_type() !== 'variation' ) {
            return new WP_Error( 'invalid_variation', __( 'La variación no existe o es inválida.', Constants::TEXT_DOMAIN ) );
        }

        $wc_product = wc_get_product( $variation->get_parent_id() );
        $parent_sku = $wc_product->get_sku();
        if ( empty( $data['sku'] ) || $parent_sku === $data['sku'] ) {
            return new WP_Error( 'update_error', __( 'Sku de la variacion no existe o es igual al padre.', Constants::TEXT_DOMAIN ) );
        }

        try {
            $filtered_data = self::clean_data( $data );

            self::set_props( $variation, $filtered_data );
            $variation->save();

            $variation_id = $variation->get_id();
            wc_delete_product_transients($variation_id);

            return $variation_id;
        } catch ( \Exception $e ) {
            return new WP_Error( 'update_error', __( 'Error al actualizar la variación: ' . $e->getMessage(), Constants::TEXT_DOMAIN ) );
        }
    }

    /**
     * Crea o actualiza una variación de un producto dependiendo de si ya existe.
     *
     * @param WC_Product $wc_product Objeto del producto padre.
     * @param array      $data Datos de la variación a crear o actualizar.
     * @return int|WP_Error El ID de la variación creada o actualizada o un WP_Error en caso de error.
     */
    public static function create_or_update( WC_Product $wc_product, array $data ): int|WP_Error {
        $variation = self::get_existing_variation( $wc_product, $data );
        if ( $variation ) {
            return self::update( $variation, $data );
        }

        return self::create( $wc_product, $data );
    }

    /**
     * Crea múltiples variaciones asociadas a un producto variable en WooCommerce.
     *
     * @param int   $product_id ID del producto variable al que pertenecen las variaciones.
     * @param array $variations Lista de datos para crear las variaciones.
     *                          Cada elemento debe incluir:
     *                          - 'data' (array): Los datos de la variación.
     *
     * @return array Resultado para cada variación y estadísticas generales.
     *               - 'error_count'        (int)
     *               - 'total_created'      (int)
     *               - 'total_processed'    (int)
     */
    public static function create_batch( int $product_id, array $variations ): array {
        $results         = array();
        $error_count     = 0;
        $total_processed = 0;
        $total_created   = 0;

        $wc_product = wc_get_product( $product_id );
        foreach ( $variations as $data ) {
            $result = self::create( $wc_product, $data );
            if ( is_wp_error( $result ) ) {
                $results[]    = $result;
                $error_count += 1;
            } else {
                $results[]        = $result; // ID de la variación creada
                $total_processed += 1;
                $total_created   += 1;
            }
        }

        $results['error_count']     = $error_count;
        $results['total_processed'] = $total_processed;
        $results['total_created']   = $total_created;

        return $results;
    }

    /**
     * Verifica si ya existe una variación con los mismos atributos o SKU.
     *
     * @param WC_Product $wc_product Objeto del producto padre.
     * @param array      $data Datos de la variación a verificar.
     * @return WC_Product_Variation|null Retorna la variación existente o null si no existe.
     */
    public static function get_existing_variation( WC_Product $wc_product, array $data ): ?WC_Product_Variation {
        if ( ! $wc_product ) {
            return null;
        }

        $parent_sku = $wc_product->get_sku();
        $sku        = $data['sku'] ?? null;
        $attributes = $data['attributes'] ?? [];

        $variations = $wc_product->get_children();
        foreach ( $variations as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( $sku && $variation->get_sku() === $sku ) {
                return $variation;
            }

            $existing_attributes = $variation->get_attributes();
            if ( self::match_attributes( $existing_attributes, $attributes ) ) {
                return $variation;
            }
        }

        return null;
    }

    /**
     * Verifica si las características de dos variaciones coinciden.
     *
     * @param array $existing_attributes Atributos de la variación existente.
     * @param array $new_attributes Atributos de la nueva variación.
     * @return bool True si los atributos coinciden, false si no.
     */
    public static function match_attributes( array $existing_attributes, array $new_attributes ): bool {
        if ( empty( $existing_attributes ) && empty( $new_attributes) ) {
            return true;
        } else if ( empty( $existing_attributes ) || empty( $new_attributes) ) {
            return false;
        }

        // Check if attributes match (e.g., size, color)
        foreach ( $new_attributes as $attribute_name => $attribute_value ) {
            if ( ! isset( $existing_attributes[ $attribute_name ] ) || $existing_attributes[ $attribute_name ] !== $attribute_value ) {
                return false; // If any attribute does not match, return false
            }
        }
        return true; // All attributes match
    }

    /**
     * Actualiza múltiples variaciones existentes en WooCommerce.
     *
     * @param int   $product_id ID del producto variable al que pertenecen las variaciones.
     * @param array $variations Lista de datos para procesar las variaciones.
     *                          Cada elemento debe incluir:
     *                          - 'id' (int|null): El ID de la variación a actualizar. Si es null, se creará una nueva variación.
     *                          - 'data' (array): Los datos de la variación.
     *
     * @return array Resultado para cada variación y estadísticas generales.
     *               - 'error_count'        (int)
     *               - 'total_processed'    (int)
     */
    public static function update_batch( int $product_id, array $variations ): array {
        $results = array();

        $error_count     = 0;
        $total_processed = 0;

        $wc_product = wc_get_product( $product_id );
        foreach ( $variations as $variation_data ) {
            $variation = self::get_existing_variation( $wc_product, $variation_data );
            if ( ! $variation ) {
                continue;
            }

            $result = self::update( $variation, $variation_data );
            if ( is_wp_error( $result ) ) {
                $results[]    = $result;
                $error_count += 1;
                continue;
            }

            $total_processed += 1;
        }

        // Resumen de las estadísticas
        $results['error_count']     = $error_count;
        $results['total_processed'] = $total_processed;

        return $results;
    }

    /**
     * Crea o actualiza múltiples variaciones asociadas a un producto variable en WooCommerce.
     *
     * @param int   $product_id ID del producto variable al que pertenecen las variaciones.
     * @param array $variations Lista de datos para procesar las variaciones.
     *                          Cada elemento debe incluir:
     *                          - 'id' (int|null): El ID de la variación a actualizar. Si es null, se creará una nueva variación.
     *                          - 'data' (array): Los datos de la variación.
     *
     * @return array Resultado para cada variación y estadísticas generales.
     *               - 'error_count'        (int)
     *               - 'total_updated'      (int)
     *               - 'total_created'      (int)
     *               - 'total_processed'    (int)
     */
    public static function create_or_update_batch( int $product_id, array $variations ): array {
        $results = array();

        $error_count     = 0;
        $total_creates   = 0;
        $total_updates   = 0;
        $total_processed = 0;

        $wc_product = wc_get_product( $product_id );
        foreach ( $variations as $variation_data ) {
            $result = self::create_or_update( $wc_product, $variation_data );
            if ( is_wp_error( $result ) ) {
                $results[]    = $result;
                $error_count += 1;
                continue;
            }

            $total_processed += 1;
        }

        // Resumen de las estadísticas
        $results['error_count']     = $error_count;
        $results['total_created']   = $total_creates;
        $results['total_updated']   = $total_updates;
        $results['total_processed'] = $total_processed;

        return $results;
    }

    /**
     * Buscar una imagen en la biblioteca de medios de WordPress por su URL o por un ID externo.
     *
     * @param string      $image_url URL de la imagen a buscar.
     * @param string|null $external_id ID externo opcional.
     *
     * @return int|null El ID de la imagen si existe, o null si no se encuentra.
     */
    public static function search_image( string $image_url, ?string $external_id = null ): ?int {
        $image_id = attachment_url_to_postid( $image_url );
        if ( ! $image_id && $external_id ) {
            $image_id = self::search_image_by_external_id( $external_id );
        }

        return $image_id;
    }

    /**
     * Buscar una imagen en la biblioteca de medios por su ID externo.
     *
     * @param string $external_id ID externo
     *
     * @return int|null El ID de la imagen si existe, o null si no se encuentra.
     */
    private static function search_image_by_external_id( string $external_id ): ?int {
        $args = array(
            'post_type'      => 'attachment',
            'meta_key'       => '_external_image_id',
            'meta_value'     => $external_id,
            'posts_per_page' => 1,
        );

        $images = get_posts( $args );
        if ( ! empty( $images ) ) {
            $image_id  = $images[0]->ID;
            $file_path = get_attached_file( $image_id );
            if ( file_exists( $file_path ) ) {
                return $image_id;
            }
        }

        return null;
    }
}
