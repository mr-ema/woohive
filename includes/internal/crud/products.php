<?php

namespace WooHive\Internal\Crud;

use WooHive\Config\Constants;
use WooHive\Internal\Crud\Attributes;
use WooHive\Internal\Crud\Categories;
use WooHive\Utils\Debugger;
use WooHive\Utils\Helpers;

use WC_Product_Factory;
use WP_Error;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Products {

    /**
     * Lista de propiedades inválidas que no deberían usarse al actualizar o crear productos.
     *
     * @var array
     */
    private static array $invalid_props = array(
        'id',              // No permitimos actualizar el ID del producto.
        'date_created',    // Propiedades relacionadas con la creación.
        'date_modified',   // Propiedades relacionadas con la modificación.
        'permalink',       // Propiedad de solo lectura.
        'average_rating',  // Calculada automáticamente.
        'rating_count',    // Calculada automáticamente.
        'review_count',    // Calculada automáticamente.
        'downloads',       // Si no corresponde a un producto descargable.
    );

    /**
     * Limpia los datos para eliminar propiedades inválidas.
     *
     * @param array  $data Datos sin procesar.
     * @param string $operation operacion a realizar en el crud [update, create, delete]
     *
     * @return array Datos filtrados con las propiedades inválidas eliminadas.
     */
    public static function clean_data( array $data, string $operation = 'create' ): array {
        $operation = strtolower( $operation );

        $conditional_invalid_props = ( function () {
            if ( ! Helpers::is_sync_stock_enabled() && $operation === 'update' ) {
                return array( 'stock_quantity', 'stock_status', 'manage_stock', 'old_stock', 'stock_change', 'low_stock_amount' );
            }

            return array();
        } )();

        $custom_invalid_props = apply_filters( Constants::PLUGIN_SLUG . '_product_invalid_props', array() );
        $all_invalid_props    = array_merge( self::$invalid_props, $custom_invalid_props, $conditional_invalid_props );

        $filtered_data = array_filter(
            $data,
            fn( $key ) => ! in_array( $key, $all_invalid_props, true ),
            ARRAY_FILTER_USE_KEY
        );

        if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
            $invalid_meta_keys = apply_filters( Constants::PLUGIN_SLUG . '_product_invalid_meta_keys', array() );

            $filtered_data['meta'] = array_filter(
                $data['meta'],
                fn( $meta_key ) => ! in_array( $meta_key, $invalid_meta_keys, true )
            );
        }

        return $filtered_data;
    }

    /**
     * Actualiza los datos de un producto específico en WooCommerce.
     *
     * @param int   $product_id El ID del producto que será actualizado.
     * @param array $data       Datos del producto a actualizar.
     *
     * @return int|WP_Error Devuelve id si la actualización fue exitosa o un WP_Error en caso de fallo.
     */
    public static function update( int $product_id, array $data ): WP_Error|int {
        $wc_product = wc_get_product( $product_id );
        if ( ! $wc_product ) {
            return new WP_Error( 'product_not_found', __( "El producto con ID $product_id no existe.", Constants::TEXT_DOMAIN ) );
        }

        $filtered_data = self::clean_data( $data, 'update' );
        if ( empty( $filtered_data ) ) {
            return new WP_Error( 'invalid_data', __( 'No se proporcionaron campos válidos para actualizar.', Constants::TEXT_DOMAIN ) );
        }

        if ( Helpers::is_sync_only_stock_enabled() ) {
            $allowed_keys  = array( 'stock_quantity', 'stock_status', 'manage_stock' );
            $filtered_data = array_intersect_key( $body_data, array_flip( $allowed_keys ) );
        }

        try {
            if ( isset( $filtered_data['type'] ) && ! $wc_product->is_type( $filtered_data['type'] ) ) {
                Helpers::update_product_type( $wc_product, $filtered_data['type'] );
            }

            if ( isset( $filtered_data['stock_quantity'] ) ) {
                $response = self::normalize_stock( $wc_product, $filtered_data );
                if ( ! is_wp_error( $response ) ) {
                    unset( $filtered_data['stock_quantity'] );
                }
            }

            $wc_product->set_props( $filtered_data );
            $wc_product->save();

            if ( isset( $filtered_data['attributes'] ) ) {
                $unused = Attributes::create_or_update_batch( $wc_product, $filtered_data['attributes'] );
            }

            if ( isset( $filtered_data['categories'] ) ) {
                $unused = Categories::assign_categories( $wc_product, $filtered_data['categories'] );
            }

            if ( isset( $filtered_data['images'] ) && is_array( $filtered_data['images'] ) ) {
                $image_ids = array();

                foreach ( $filtered_data['images'] as $index => $image_data ) {
                    $image_url   = $image_data['src'];
                    $external_id = $image_data['id'];

                    $uploaded_image = self::upload_image( $image_url, $product_id, $external_id );
                    if ( is_wp_error( $uploaded_image ) ) {
                        continue;
                    }

                    $image_ids[] = $uploaded_image;
                    if ( $index === 0 && $uploaded_image ) {
                        set_post_thumbnail( $product_id, $uploaded_image );
                    }
                }

                if ( ! empty( $image_ids ) ) {
                    update_post_meta( $product_id, '_product_image_gallery', implode( ',', $image_ids ) );
                }
            }

            if ( $filtered_data['meta_data'] ) {
                foreach ( $filtered_data['meta_data'] as $meta_data => $meta_key ) {
                    $wc_product->update_meta_data( $meta_key, $meta_data );
                }
            }

            Debugger::ok( __( 'Producto actualizado correctamente', Constants::TEXT_DOMAIN ) );
            $wc_product_id = $wc_product->get_id();
            wc_delete_product_transients( $wc_product_id );

            return $wc_product_id;
        } catch ( \Exception $e ) {
            $message = __( 'Error al guardar el producto: ' . $e->getMessage(), Constants::TEXT_DOMAIN );

            Debugger::error( $message );
            return new WP_Error( 'save_error', $message );
        }
    }

    /**
     * Actualiza múltiples productos en WooCommerce.
     *
     * @param array $products Lista de productos a actualizar. Cada elemento debe ser un array con las claves:
     *                        - 'id' (int): El ID del producto.
     *                        - 'data' (array): Los datos a actualizar para ese producto.
     *
     * @return array Un array asociativo con resultados para cada producto, y estadísticas generales.
     */
    public static function update_batch( array $products ): array {
        $results       = array();
        $error_count   = 0;
        $total_updates = 0;

        foreach ( $products as $product_data ) {
            if ( ! isset( $product_data['id'] ) || ! isset( $product_data['data'] ) ) {
                $results[]    = new WP_Error( 'invalid_data', __( 'El formato del producto no es válido.', Constants::TEXT_DOMAIN ) );
                $error_count += 1;
                continue;
            }

            $product_id = $product_data['id'];
            $data       = $product_data['data'];

            $result = self::update( $product_id, $data );

            if ( is_wp_error( $result ) ) {
                $results[ $product_id ] = $result;
                $error_count           += 1;
            } else {
                $results[ $product_id ] = true;
                $total_updates         += 1;
            }
        }

        // Sumary
        $results['error_count']   = $error_count;
        $results['total_updates'] = $total_updates;

        return $results;
    }


    /**
     * Crea un nuevo producto en WooCommerce.
     *
     * @param array $data Datos para crear el producto.
     *
     * @return int|WP_Error El ID del producto creado o un WP_Error en caso de fallo.
     */
    public static function create( array $data ): int|WP_Error {
        $filtered_data = self::clean_data( $data, 'create' );
        if ( empty( $filtered_data ) ) {
            return new WP_Error( 'invalid_data', __( 'No se proporcionaron campos válidos para crear el producto.', Constants::TEXT_DOMAIN ) );
        }

        try {
            if ( ! empty( $filtered_data['sku'] ) ) {
                $existing_product_id = wc_get_product_id_by_sku( $filtered_data['sku'] );
                if ( $existing_product_id ) {
                    return new WP_Error(
                        'product_exists',
                        sprintf( __( 'El producto con SKU "%s" ya existe.', Constants::TEXT_DOMAIN ), $filtered_data['sku'] )
                    );
                }
            }

            $product_type = $filtered_data['type'] ?? 'simple';
            unset( $filtered_data['type'] );

            $product_class = WC_Product_Factory::get_product_classname( 0, $product_type );
            $wc_product    = new $product_class();

            $wc_product->set_props( $filtered_data );
            $wc_product->save();

            if ( isset( $filtered_data['attributes'] ) ) {
                $unused = Attributes::create_or_update_batch( $wc_product, $filtered_data['attributes'] );
            }

            if ( isset( $filtered_data['categories'] ) ) {
                $unused = Categories::assign_categories( $wc_product, $filtered_data['categories'] );
            }

            $product_id = $wc_product->get_id();
            if ( ! empty( $filtered_data['images'] ) ) {
                $image_ids = array();

                foreach ( $filtered_data['images'] as $index => $image_data ) {
                    $image_url   = $image_data['src'];
                    $external_id = $image_data['id'];

                    $uploaded_image = self::upload_image( $image_url, $product_id, $external_id );
                    if ( is_wp_error( $uploaded_image ) ) {
                        continue;
                    }

                    $image_ids[] = $uploaded_image;
                    if ( $index === 0 && $uploaded_image ) {
                        set_post_thumbnail( $product_id, $uploaded_image );
                    }
                }

                if ( ! empty( $image_ids ) ) {
                    update_post_meta( $product_id, '_product_image_gallery', implode( ',', $image_ids ) );
                }
            }

            if ( $filtered_data['meta_data'] ) {
                foreach ( $filtered_data['meta_data'] as $meta_data => $meta_key ) {
                    $wc_product->add_meta_data( $meta_key, $meta_data, true );
                }
            }

            $wc_product->save();
            wc_delete_product_transients( $product_id );

            Debugger::ok( __( 'Producto creado correctamente', Constants::TEXT_DOMAIN ) );
            return $product_id;
        } catch ( \Exception $e ) {
            $message = __( 'Error al crear el producto: ' . $e->getMessage(), Constants::TEXT_DOMAIN );

            Debugger::error( $message );
            return new WP_Error( 'create_error', $message );
        }
    }

    /**
     * Crea múltiples productos en WooCommerce.
     *
     * @param array $products Lista de datos de productos para crear.
     *
     * @return array Un array asociativo con resultados para cada producto y estadísticas generales.
     */
    public static function create_batch( array $products ): array {
        $results       = array();
        $error_count   = 0;
        $total_creates = 0;

        foreach ( $products as $data ) {
            $result = self::create( $data );

            if ( is_wp_error( $result ) ) {
                $results[]    = $result;
                $error_count += 1;
            } else {
                $results[]      = $result; // El ID del producto creado.
                $total_creates += 1;
            }
        }

        $results['error_count']   = $error_count;
        $results['total_creates'] = $total_creates;

        return $results;
    }

    /**
     * Crea o actualiza un producto en WooCommerce.
     *
     * @param int|null $product_id El ID del producto a actualizar. Si es null, se creará un nuevo producto.
     * @param array    $data       Datos del producto a crear o actualizar.
     *
     * @return int|WP_Error El ID del producto creado/actualizado o un WP_Error en caso de fallo.
     */
    public static function create_or_update( int $product_id = null, array $data ): int|WP_Error {
        if ( ! empty( $data['sku'] ) ) {
            $product_id = wc_get_product_id_by_sku( $data['sku'] );
        }

        if ( $product_id ) {
            $product = wc_get_product( $product_id );

            if ( $product ) {
                $result = self::update( $product_id, $data );
                return is_wp_error( $result ) ? $result : $product_id;
            }
        }

        // Si no existe el producto, lo creamos
        return self::create( $data );
    }

    /**
     * Obtiene un producto de WooCommerce a partir de su SKU.
     *
     * Este método busca un producto en WooCommerce utilizando el SKU proporcionado
     * y devuelve la instancia del producto correspondiente.
     *
     * @param string $sku El SKU del producto a buscar.
     *
     * @return WC_Product|WP_Error La instancia del producto de WooCommerce asociado al SKU,
     *                             o un objeto WP_Error si ocurre algún error.
     */
    public static function get_by_sku( string $sku ): WC_Product|WP_Error {
        if ( empty( $sku ) ) {
            return new WP_Error(
                'invalid_sku',
                __( 'El SKU proporcionado está vacío.', Constants::TEXT_DOMAIN )
            );
        }

        $product = wc_get_product( wc_get_product_id_by_sku( $sku ) );
        if ( ! $product || ! $product instanceof WC_Product ) {
            return new WP_Error(
                'product_not_found',
                __( "No se encontró un producto con el SKU: {$sku}.", Constants::TEXT_DOMAIN )
            );
        }

        return $product;
    }

    /**
     * Crea o actualiza múltiples productos en WooCommerce.
     *
     * @param array $products Lista de productos a procesar. Cada elemento debe ser un array con las claves:
     *                        - 'id' (int|null): El ID del producto a actualizar. Si es null, se creará un nuevo producto.
     *                        - 'data' (array): Los datos del producto a crear o actualizar.
     * @param array $options Opciones adicionales para el procesamiento de productos:
     *                        - 'skip_ids' (bool): Si se establece en true, no se requerirá el ID del producto para la actualización. Se asumirá que solo se están pasando los datos para crear nuevos productos. Por defecto es false.
     *
     * @return array Un array asociativo con resultados para cada producto y estadísticas generales:
     *               - 'error_count'        (int)
     *               - 'total_updated'      (int)
     *               - 'total_created'      (int)
     *               - 'total_processed'    (int)
     */
    public static function create_or_update_batch( array $products, array $options = array( 'skip_ids' => false ) ): array {
        $results = array();

        $error_count     = 0;
        $total_creates   = 0;
        $total_updates   = 0;
        $total_processed = 0;

        foreach ( $products as $product_data ) {
            $product_id = $product_data['id'] ?? null;
            $data       = $product_data['data'] ?? null;

            if ( $options['skip_ids'] ) {
                $product_id = null;
                $data       = $product_data;
            }

            if ( ! $data ) {
                $results[]    = new WP_Error( 'invalid_data', __( 'El formato del producto no es válido.', Constants::TEXT_DOMAIN ) );
                $error_count += 1;
                continue;
            }

            $result = self::create_or_update( $product_id, $data );
            if ( is_wp_error( $result ) ) {
                $results[]    = $result;
                $error_count += 1;
                continue;
            }

            $results[] = $result;
            if ( $product_id ) {
                $total_updates += 1;
            } else {
                $total_creates += 1;
            }

            $total_processed += 1;
        }

        $results['error_count']     = $error_count;
        $results['total_creates']   = $total_creates;
        $results['total_updates']   = $total_updates;
        $results['total_processed'] = $total_processed;

        return $results;
    }

    /**
     * Normaliza el stock de un producto en WooCommerce.
     *
     * Esta función toma un objeto de producto y un array de datos,
     * valida la cantidad de stock proporcionada y actualiza el stock del producto
     * si la gestión de inventario está habilitada.
     *
     * @param object $product Objeto del producto.
     * @param array  $data    Array de datos que contiene información como la cantidad de stock.
     *
     * @return int|WP_Error Devuelve el nuevo stock actualizado si es exitoso,
     *                      o un objeto WP_Error si ocurre un error.
     */
    public static function normalize_stock( object $product, array $data ): int|WP_Error {
        if ( ! isset( $data['stock_quantity'] ) || ! is_numeric( $data['stock_quantity'] ) ) {
            return new WP_Error(
                'invalid_stock_quantity',
                __( 'La cantidad de stock proporcionada no es válida.', Constants::TEXT_DOMAIN )
            );
        }

        $current_stock = $product->get_stock_quantity() ?? 0;
        if ( empty( $data['stock_change'] ) ) {
            return $current_stock;
        }

        $stock_change = (int) $data['stock_change'];
        if ( $product->managing_stock() ) {
            $new_stock = $current_stock + $stock_change;
            $new_stock = max( 0, $new_stock );

            $product->set_stock_quantity( $new_stock );
            $product->save();

            return $new_stock;
        }

        return new WP_Error(
            'stock_management_disabled',
            __( 'La gestión de inventario no está habilitada para este producto.', Constants::TEXT_DOMAIN )
        );
    }

    /**
     * Subir una imagen a la biblioteca de medios de WordPress y asociarla a un producto de WooCommerce.
     *
     * @param string      $image_url URL de la imagen a subir.
     * @param int         $product_id ID del producto de WooCommerce al que se asociará la imagen.
     * @param string|null $external_id ID externo (opcional), para evitar duplicados.
     *
     * @return WP_Error|int ID de la imágen agregada a la galería del producto o un objeto WP_Error si ocurre un error.
     */
    public static function upload_image( string $image_url, int $product_id, ?string $external_id = null ): WP_Error|int {
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

        $image_id = media_sideload_image( $image_url, $product_id, null, 'id' );
        if ( is_wp_error( $image_id ) ) {
            return new WP_Error( 'upload_error', 'Error al subir la imagen: ' . $image_id->get_error_message() );
        }

        if ( $external_id ) {
            update_post_meta( $image_id, '_external_image_id', $external_id );
        }

        return $image_id;
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
