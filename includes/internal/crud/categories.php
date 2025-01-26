<?php

namespace WooHive\Internal\Crud;

use WooHive\Config\Constants;
use WooHive\Utils\Debugger;

use WP_Error;
use WC_Product;


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
    private static array $invalid_props = array(
        'id',              // No permitimos actualizar el ID directamente.
        'count',           // Propiedad de solo lectura.
    );

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
     * Asocia categorías existentes a un producto usando nombre o slug (sin crear nuevas categorías).
     *
     * @param WC_Product $product El objeto del producto.
     * @param array      $categories Datos de las categorías a asociar (pueden ser nombres o slugs).
     *
     * @return bool|WP_Error Devuelve true en caso de éxito o WP_Error en caso de fallo.
     */
    public static function assign_categories( WC_Product $product, array $categories ): bool|WP_Error {
        if ( empty( $categories ) ) {
            return new WP_Error( 'invalid_data', __( 'Las categorías no pueden estar vacías.', Constants::TEXT_DOMAIN ) );
        }

        $category_ids = array();
        foreach ( $categories as $category ) {
            $category_name = ( function () use ( $category ) {
                if ( isset( $category['name'] ) ) {
                    return sanitize_text_field( $category['name'] );
                } elseif ( isset( $category['slug'] ) ) {
                    return sanitize_text_field( $category['slug'] );
                }

                return '';
            } )();

            if ( $category_name ) {
                $term = term_exists( $category_name, 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $category_ids[] = $term['term_id'];
                }
            }
        }

        if ( empty( $category_ids ) ) {
            return new WP_Error( 'invalid_categories', __( 'No se encontraron categorías válidas.', Constants::TEXT_DOMAIN ) );
        }

        $product->set_category_ids( $category_ids );
        try {
            $product->save();
        } catch ( \Exception $e ) {
            $message = sprintf( __( 'Error al guardar el producto: %s', Constants::TEXT_DOMAIN ), $e->getMessage() );

            Debugger::error( $message );
            return new WP_Error( 'category_error', $message );
        }

        Debugger::ok( __( 'Se Asignaron las categorias correctamente', Constants::TEXT_DOMAIN ) );
        return true;
    }

    /**
     * Crea una nueva categoría si no existe.
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

        $term = wp_insert_term( $filtered_data['name'], 'product_cat', $filtered_data );
        if ( is_wp_error( $term ) ) {
            return $term;
        }

        $term_id = $term['term_id'];

        if ( isset( $filtered_data['image'] ) && ! empty( $filtered_data['image']['src'] ) ) {
            $image_url   = $filtered_data['image']['src'];
            $external_id = isset( $filtered_data['image']['id'] ) ? $filtered_data['image']['id'] : null;

            $result = self::upload_image( $image_url, $term_id, $external_id );
            if ( is_wp_error( $result ) ) {
                Debugger::error('Image error on update category: ', $result);
            }

            $image_id = $result;
            update_term_meta( $term_id, 'thumbnail_id', $image_id );
        }

        return $term_id;
    }

    /**
     * Actualiza una categoría existente con los nuevos datos.
     *
     * @param int $term_id ID de la categoría que se va a actualizar.
     * @param array $data Datos para actualizar la categoría.
     *
     * @return int|WP_Error Devuelve el ID de la categoría actualizada o un WP_Error en caso de fallo.
     */
    public static function update( int $term_id, array $data ): int|WP_Error {
        $filtered_data = self::clean_data( $data );
        $result = wp_update_term( $term_id, 'product_cat', $filtered_data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( isset( $filtered_data['image'] ) && ! empty( $filtered_data['image']['src'] ) ) {
            $image_url   = $filtered_data['image']['src'];
            $external_id = isset( $filtered_data['image']['id'] ) ? $filtered_data['image']['id'] : null;

            $result = self::upload_image( $image_url, $term_id, $external_id );
            if ( is_wp_error( $result ) ) {
                Debugger::error('Image error on update category: ', $result);
            }

            $image_id = $result;
            update_term_meta( $term_id, 'thumbnail_id', $image_id );
        }

        return $term_id;
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
            return new WP_Error( 'invalid_data', __( 'El nombre es obligatorio para crear o actualizar una categoría.', Constants::TEXT_DOMAIN ) );
        }

        $existing_term = get_term_by( 'name', $filtered_data['name'], 'product_cat' );
        if ( $existing_term ) {
            return self::update( $existing_term->term_id, $filtered_data );
        }

        return self::create( $filtered_data );
    }

    /**
     * Crea múltiples categorías en un solo lote.
     *
     * @param array $categories Lista de datos para crear las categorías.
     *
     * @return array Resultado para cada categoría y estadísticas generales.
     *               - 'error_count'        (int)
     *               - 'total_processed'    (int)
     */
    public static function create_batch( array $categories ): array {
        $results         = array();
        $error_count     = 0;
        $total_processed = 0;

        foreach ( $categories as $data ) {
            $result = self::create( $data );

            if ( is_wp_error( $result ) ) {
                $results[]    = $result;
                $error_count += 1;
            } else {
                $results[]        = $result;
                $total_processed += 1;
            }
        }

        $results['error_count']     = $error_count;
        $results['total_processed'] = $total_processed;

        return $results;
    }

    /**
     * Crea o actualiza múltiples categorías según su existencia.
     *
     * @param array $categories Lista de datos para crear o actualizar categorías.
     *
     * @return array Resultados para cada categoría y estadísticas generales.
     *               - 'error_count'        (int)
     *               - 'total_processed'    (int)
     */
    public static function create_or_update_batch( array $categories ): array {
        $results         = array();
        $error_count     = 0;
        $total_processed = 0;

        foreach ( $categories as $data ) {
            $result = self::create_or_update( $data );
            if ( is_wp_error( $result ) ) {
                $results[]    = $result;
                $error_count += 1;
            } else {
                $results[]        = $result;
                $total_processed += 1;
            }
        }

        $results['error_count']     = $error_count;
        $results['total_processed'] = $total_processed;

        return $results;
    }

    /**
     * Subir una imagen a la biblioteca de medios de WordPress y asociarla a una categoria de WooCommerce.
     *
     * @param string      $image_url URL de la imagen a subir.
     * @param int         $category_id ID de la categoria de WooCommerce al que se asociará la imagen.
     * @param string|null $external_id ID externo (opcional), para evitar duplicados.
     *
     * @return WP_Error|int ID de la imágen agregada a la galería del producto o un objeto WP_Error si ocurre un error.
     */
    public static function upload_image( string $image_url, int $category_id, ?string $external_id = null ): WP_Error|int {
        $image_id = self::search_image( $image_url, $external_id );
        if ( $image_id ) {
            return $image_id;
        }

        // Sin esto media_sideload_image no functiona cuando se usa la api interna
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $image_id = media_sideload_image( $image_url, $category_id, null, 'id' );
        if ( is_wp_error( $image_id ) ) {
            return new WP_Error( 'upload_error', 'Error al subir la imagen: ' . $image_id->get_error_message() );
        }

        if ( $external_id ) {
            update_post_meta( $image_id, '_external_image_id', $external_id );
        }

        return $image_id;
    }

    /**
     * Asocia un padre a una categoría si el padre existe.
     *
     * @param int   $term_id El ID de la categoría a la que se le asignará el padre.
     * @param string $parent_name El nombre o slug del padre de la categoría.
     *
     * @return bool|WP_Error Devuelve true si el padre se ha establecido correctamente o un WP_Error si ocurre un error.
     */
    public static function set_parent( int $term_id, string $parent_name ): bool|WP_Error {
        $parent_term = term_exists( $parent_name, 'product_cat' );

        if ( ! $parent_term ) {
            return new WP_Error( 'parent_not_found', __( 'El padre especificado no existe.', Constants::TEXT_DOMAIN ) );
        }

        $result = wp_update_term( $term_id, 'product_cat', array(
            'parent' => $parent_term['term_id'],
        ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    /**
     * Buscar una imagen en la biblioteca de medios de WordPress por su URL o por un ID externo.
     *
     * @param string      $image_url URL de la imagen a buscar.
     * @param string|null $external_id ID externo (opcional)
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
