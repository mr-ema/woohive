<?php

namespace WooHive\Internal\Crud;

use WooHive\Config\Constants;
use WooHive\Utils\Debugger;
use WooHive\Utils\Helpers;

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
     * @param array  $data Datos sin procesar de la variación.
     * @param string $operation operacion a realizar en el crud [update, create, delete]
     *
     * @return array Datos filtrados con las propiedades inválidas eliminadas.
     */
    public static function clean_data( array $data, string $operation = 'create' ): array {
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

        $operation = strtolower( $operation );

        $conditional_invalid_props = ( function () {
            if ( ! Helpers::is_sync_stock_enabled() && $operation === 'update' ) {
                return array( 'stock_quantity', 'stock_status', 'manage_stock', 'old_stock', 'stock_change', 'low_stock_amount' );
            }

            return array();
        } )();

        $all_invalid_props = array_merge( $invalid_props, $conditional_invalid_props );
        return array_filter(
            $data,
            fn( $key ) => ! in_array( $key, $all_invalid_props, true ),
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
        $valid_set_props = array( 'regular_price', 'sale_price', 'stock_quantity', 'status', 'manage_stock', 'weight', 'sku', 'stock_status' );
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

        if ( ! empty( $data['attributes'] ) && is_array( $data['attributes'] ) ) {
            $attributes_map = array_reduce(
                $data['attributes'],
                function ( $carry, $attribute ) {
                    if ( is_array( $attribute ) && isset( $attribute['name'] ) && isset( $attribute['option'] ) ) {
                        $attribute_name = sanitize_title( $attribute['name'] );

                        $slug      = wc_attribute_taxonomy_name( $attribute_name );
                        $is_global = taxonomy_exists( $slug );

                        if ( $is_global ) {
                            $term = get_term_by( 'name', $attribute['option'], $slug );
                            if ( $term ) {
                                $carry[ $slug ] = $term->slug;
                            }
                        } else {
                            $carry[ $attribute_name ] = $attribute['option'];
                        }
                    }

                    return $carry;
                },
                array()
            );

            if ( ! empty( $attributes_map ) ) {
                $variation->set_attributes( $attributes_map );
            }
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
                $variation->update_meta_data( $meta_key, $meta_data );
            }
        }
    }

    /**
     * Crea una nueva variación para un producto.
     *
     * @param WC_Product $wc_product Objeto del producto al que pertenece la variación.
     * @param array      $filtered_data Datos de la variación a crear.
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
            $filtered_data = self::clean_data( $filtered_data, 'create' );
            $variation     = new WC_Product_Variation();

            $variation->set_parent_id( $wc_product->get_id() );
            self::set_props( $variation, $filtered_data );

            $variation->save();

            $id = $variation->get_id();

            Debugger::ok( __( 'Variacion creada correctamente', Constants::TEXT_DOMAIN ) );
            return $id;
        } catch ( \Exception $e ) {
            $message = __( 'Error al crear la variación: ' . $e->getMessage(), Constants::TEXT_DOMAIN );

            Debugger::error( $message );
            return new WP_Error( 'create_error', $message );
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
            $filtered_data = self::clean_data( $data, 'update' );

            if ( Helpers::is_sync_only_stock_enabled() ) {
                $allowed_keys  = array( 'stock_quantity', 'stock_status', 'manage_stock' );
                $filtered_data = array_intersect_key( $filtered_data, array_flip( $allowed_keys ) );
            }

            if ( isset( $filtered_data['stock_quantity'] ) ) {
                $response = self::normalize_stock( $variation, $filtered_data );
                if ( ! is_wp_error( $response ) ) {
                    unset( $filtered_data['stock_quantity'] );
                }
            }

            self::set_props( $variation, $filtered_data );
            $variation->save();

            $variation_id = $variation->get_id();
            wc_delete_product_transients( $variation_id );

            Debugger::ok( __( 'Variacion actualizada correctamente', Constants::TEXT_DOMAIN ) );
            return $variation_id;
        } catch ( \Exception $e ) {
            $message = __( 'Error al actualizar la variación: ' . $e->getMessage(), Constants::TEXT_DOMAIN );

            Debugger::error( $message );
            return new WP_Error( 'update_error', $message );
        }
    }

    /**
     * Elimina una variación de un producto.
     *
     * @param int|WC_Product_Variation $variation La variación o su ID a eliminar.
     *
     * @return bool|WP_Error Retorna true si se elimina correctamente o un error en caso de fallo.
     */
    public static function delete( int|WC_Product_Variation $variation ): bool|WP_Error {
        if ( is_numeric( $variation ) ) {
            $variation = wc_get_product( $variation );

            if ( ! $variation || $variation->get_type() !== 'variation' ) {
                return new WP_Error( 'invalid_variation', __( 'El producto no es una variación válida.', Constants::TEXT_DOMAIN ) );
            }
        }

        if ( ! $variation instanceof WC_Product_Variation ) {
            return new WP_Error( 'invalid_variation', __( 'El objeto no es una instancia de WC_Product_Variation.', Constants::TEXT_DOMAIN ) );
        }

        try {
            wp_delete_post( $variation->get_id(), true );

            Debugger::ok( __( 'Variación eliminada correctamente.', Constants::TEXT_DOMAIN ) );
            return true;
        } catch ( \Exception $e ) {
            $message = __( 'Error al eliminar la variación: ' . $e->getMessage(), Constants::TEXT_DOMAIN );

            Debugger::error( $message );
            return new WP_Error( 'delete_error', $message );
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
        $attributes = $data['attributes'] ?? array();

        $variations = $wc_product->get_children();
        foreach ( $variations as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( $sku && $variation->get_sku() === $sku && $sku !== $parent_sku ) {
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
        if ( empty( $existing_attributes ) && empty( $new_attributes ) ) {
            return true;
        } elseif ( empty( $existing_attributes ) || empty( $new_attributes ) ) {
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
     * Obtiene una variación de producto de WooCommerce a partir del SKU del producto principal y el SKU de la variación.
     *
     * Este método busca una variación en WooCommerce utilizando el SKU de la variación.
     * Si se encuentra, verifica que el producto principal coincida. Si no coincide,
     * intenta buscar manualmente entre las variaciones del producto principal.
     *
     * @param string $parent_sku El SKU del producto principal.
     * @param string $variation_sku El SKU de la variación a buscar.
     *
     * @return WC_Product_Variation|WP_Error La instancia de la variación de WooCommerce asociada,
     *                                       o un objeto WP_Error si ocurre algún error.
     */
    public static function get_by_sku( string $parent_sku, string $variation_sku ): WC_Product_Variation|WP_Error {
        if ( empty( $parent_sku ) ) {
            return new WP_Error( 'invalid_parent_sku', __( 'El SKU del producto principal está vacío.', Constants::TEXT_DOMAIN ) );
        } elseif ( empty( $variation_sku ) ) {
            return new WP_Error( 'invalid_variation_sku', __( 'El SKU de la variación está vacío.', Constants::TEXT_DOMAIN ) );
        }

        $parent_product = wc_get_product( wc_get_product_id_by_sku( $parent_sku ) );
        if ( ! $parent_product || ! $parent_product instanceof WC_Product ) {
            return new WP_Error(
                'parent_product_not_found',
                __( "No se encontró un producto con el SKU: {$parent_sku}.", Constants::TEXT_DOMAIN )
            );
        }

        $variation = wc_get_product( wc_get_product_id_by_sku( $variation_sku ) );
        if ( $variation && $variation->get_type() === 'variation' ) {
            if ( $variation->get_parent_id() === $parent_product->get_id() ) {
                return $variation;
            }
        }

        $variations = $parent_product->get_available_variations( 'objects' );
        if ( empty( $variations ) ) {
            return new WP_Error(
                'no_variations_found',
                __( "No se encontraron variaciones para el producto con el SKU: {$parent_sku}.", Constants::TEXT_DOMAIN )
            );
        }

        foreach ( $variations as $variation ) {
            if ( $variation && $variation instanceof WC_Product_Variation ) {
                if ( $variation->get_sku() === $variation_sku ) {
                    return $variation;
                }
            }
        }

        return new WP_Error(
            'variation_not_found',
            __( "No se encontró una variación con el SKU: {$variation_sku} para el producto principal con SKU: {$parent_sku}.", Constants::TEXT_DOMAIN )
        );
    }

    /**
     * Normaliza el stock de una variación de producto en WooCommerce.
     *
     * Esta función toma un objeto de variación de producto y un array de datos,
     * valida la cantidad de stock proporcionada y actualiza el stock de la variación
     * si la gestión de inventario está habilitada.
     *
     * @param WC_Product_Variation $variation Objeto de la variación de producto.
     * @param array                $data      Array de datos que contiene información como la cantidad de stock.
     *
     * @return int|WP_Error Devuelve el nuevo stock actualizado si es exitoso,
     *                      o un objeto WP_Error si ocurre un error.
     */
    public static function normalize_stock( WC_Product_Variation $variation, array $data ): int|WP_Error {
        if ( ! isset( $data['stock_quantity'] ) || ! is_numeric( $data['stock_quantity'] ) ) {
            return new WP_Error(
                'invalid_stock_quantity',
                __( 'La cantidad de stock proporcionada no es válida.', 'tu-textdomain' )
            );
        }

        $current_stock = $variation->get_stock_quantity() ?? 0;
        if ( empty( $data['stock_change'] ) ) {
            return $current_stock;
        }

        $stock_change = (int) $data['stock_change'];
        if ( $variation->managing_stock() ) {
            $new_stock = $current_stock + $stock_change;
            $new_stock = max( 0, $new_stock );

            $variation->set_stock_quantity( $new_stock );
            $variation->save();

            return $new_stock;
        }

        return new WP_Error(
            'stock_management_disabled',
            __( 'La gestión de inventario no está habilitada para esta variación.', 'tu-textdomain' )
        );
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
