<?php

namespace WooHive\Internal\Crud;

use WooHive\Config\Constants;
use WooHive\Internal\Crud\Attributes;

use \WP_Error;
use \WC_Product_Variation;
use \WP_Query;
use \WC_Product;


/** Prevenir el acceso directo al script. */
if (!defined('ABSPATH')) {
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
    public static function clean_data(array $data): array {
        $invalid_props = [
            'id',
            'date_created',
            'date_modified',
            'permalink',
            'average_rating',
            'rating_count',
            'review_count',
            '_edit_lock',
            '_edit_last',
        ];

        return array_filter(
            $data,
            fn($key) => !in_array($key, $invalid_props, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Crea una nueva variación para un producto.
     *
     * @param WC_Product $wc_product Objeto del producto al que pertenece la variación.
     * @param array $data Datos de la variación a crear.
     *
     * @return int|WP_Error Retorna el ID de la variación creada o un error en caso de fallo.
     */
    public static function create(WC_Product $wc_product, array $data): int|WP_Error {
        if ( ! $wc_product ) {
            return new WP_Error('invalid_product', __( 'El producto no existe o es inválido.', Constants::TEXT_DOMAIN ));
        }

        try {
            $data = self::clean_data($data);
            $variation = new WC_Product_Variation();
            $variation->set_parent_id( $wc_product->get_id() );

            //if ( ! empty( $data['sku'] ) )            $variation->set_sku($data['sku']);
            if ( ! empty( $data['regular_price'] ) )  $variation->set_regular_price($data['regular_price']);
            if ( ! empty( $data['sale_price'] ) )     $variation->set_sale_price($data['sale_price']);
            if ( ! empty( $data['stock_quantity'] ) ) $variation->set_stock_quantity($data['stock_quantity']);
            if ( ! empty( $data['manage_stock'] ) )   $variation->set_manage_stock($data['manage_stock']);
            if ( ! empty( $data['status'] ) )         $variation->set_status($data['status']);

            if ( ! empty( $data['attributes'] ) ) {
                $attributes_map = array_reduce($data['attributes'], function($carry, $attribute) {
                    $carry[$attribute['name']] = $attribute['option'];
                    return $carry;
                }, []);

                $variation->set_attributes( $attributes_map );
            }

            if ( ! empty( $data['image'] ) ) {
                $image_result = self::search_image($data['image']['src'], $data['image']['id']);
                if ( $image_result ) {
                    $variation->set_image_id($image_result);
                }
            }

            $variation->save();

            return $variation->get_id();
        } catch (\Exception $e) {
            return new WP_Error('create_error', __('Error al crear la variación: ' . $e->getMessage(), Constants::TEXT_DOMAIN));
        }
    }

    /**
     * Actualiza una variación existente.
     *
     * @param WC_Product_Variation $variation Objeto de la variación a actualizar.
     * @param array $data Datos a actualizar en la variación.
     *
     * @return int|WP_Error Retorna el ID de la variación actualizada o un error en caso de fallo.
     */
    public static function update(WC_Product_Variation $variation, array $data): int|WP_Error {
        if ( ! $variation || $variation->get_type() !== 'variation' ) {
            return new WP_Error('invalid_variation', __('La variación no existe o es inválida.', Constants::TEXT_DOMAIN));
        }

        try {
            $data = self::clean_data($data);

//            if ( ! empty( $data['sku'] ) )            $variation->set_sku($data['sku']);
            if ( ! empty( $data['regular_price'] ) )  $variation->set_regular_price($data['regular_price']);
            if ( ! empty( $data['sale_price'] ) )     $variation->set_sale_price($data['sale_price']);
            if ( ! empty( $data['stock_quantity'] ) ) $variation->set_stock_quantity($data['stock_quantity']);
            if ( ! empty( $data['manage_stock'] ) )   $variation->set_manage_stock($data['manage_stock']);
            if ( ! empty( $data['status'] ) )         $variation->set_status($data['status']);

            if ( ! empty( $data['attributes'] ) ) {
                $attributes_map = array_reduce($data['attributes'], function($carry, $attribute) {
                    $carry[$attribute['name']] = $attribute['option'];
                    return $carry;
                }, []);

                $variation->set_attributes( $attributes_map );
            }

            if ( ! empty( $data['image'] ) ) {
                $image_result = self::search_image($data['image']['src'], $data['image']['id']);
                if ( $image_result ) {
                    $variation->set_image_id($image_result);
                }
            }

            $variation->save();

            return $variation->get_id();
        } catch (\Exception $e) {
            return new WP_Error('update_error', __('Error al actualizar la variación: ' . $e->getMessage(), Constants::TEXT_DOMAIN));
        }
    }

    /**
     * Crea o actualiza una variación de un producto dependiendo de si ya existe.
     *
     * @param WC_Product $wc_product Objeto del producto padre.
     * @param array $data Datos de la variación a crear o actualizar.
     * @return int|WP_Error El ID de la variación creada o actualizada o un WP_Error en caso de error.
     */
    public static function create_or_update(WC_Product $wc_product, array $data): int|WP_Error {
        $variation = self::get_existing_variation($wc_product, $data);
        if ($variation) {
            return self::update($variation, $data);
        }

        return self::create($wc_product, $data);
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
    public static function create_batch(int $product_id, array $variations): array {
        $results = [];
        $error_count = 0;
        $total_processed = 0;
        $total_created = 0;

        $wc_product = wc_get_product($product_id);
        foreach ($variations as $data) {
            $result = self::create($wc_product, $data);
            if (is_wp_error($result)) {
                $results[] = $result;
                $error_count += 1;
            } else {
                $results[] = $result; // ID de la variación creada
                $total_processed += 1;
                $total_created += 1;
            }
        }

        $results['error_count'] = $error_count;
        $results['total_processed'] = $total_processed;
        $results['total_created'] = $total_created;

        return $results;
    }

    /**
     * Verifica si ya existe una variación con los mismos atributos o SKU.
     *
     * @param WC_Product $wc_product Objeto del producto padre.
     * @param array $data Datos de la variación a verificar.
     * @return WC_Product_Variation|null Retorna la variación existente o null si no existe.
     */
    public static function get_existing_variation(WC_Product $wc_product, array $data): ?WC_Product_Variation {
        if ( ! $wc_product ) {
            return null;
        }

        $sku = $data['sku'] ?? null;
        $attributes = $data['attributes'] ?? [];

        $variations = $wc_product->get_children();
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);

            if ($sku && $variation->get_sku() === $sku) {
                return $variation;
            }

            $existing_attributes = $variation->get_attributes();
            if (self::match_attributes($existing_attributes, $attributes)) {
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
    public static function match_attributes(array $existing_attributes, array $new_attributes): bool {
        // Check if attributes match (e.g., size, color)
        foreach ($new_attributes as $attribute_name => $attribute_value) {
            if ( ! isset( $existing_attributes[$attribute_name] ) || $existing_attributes[$attribute_name] !== $attribute_value ) {
                return false; // If any attribute does not match, return false
            }
        }
        return true; // All attributes match
    }

    /**
     * Actualiza múltiples variaciones existentes en WooCommerce.
     *
     * @param array $variations Lista de datos para actualizar las variaciones.
     *                          Cada elemento debe incluir:
     *                          - 'id' (int): El ID de la variación a actualizar.
     *                          - 'data' (array): Los datos de la variación.
     *
     * @return array Resultado para cada variación y estadísticas generales.
     *               - 'error_count'        (int)
     *               - 'total_updated'      (int)
     *               - 'total_processed'    (int)
     */
    public static function update_batch(array $variations): array {
        $results = [];
        $error_count = 0;
        $total_processed = 0;
        $total_updated = 0;

        foreach ($variations as $variation_data) {
            $variation_id = $variation_data['id'] ?? null;
            $data = $variation_data['data'] ?? null;

            if (!$variation_id || !$data) {
                $results[] = new WP_Error('invalid_data', __('El formato de la variación no es válido.', Constants::TEXT_DOMAIN));
                $error_count += 1;
                continue;
            }

            $result = self::update($variation_id, $data);

            if (is_wp_error($result)) {
                $results[] = $result;
                $error_count += 1;
            } else {
                $results[] = $result; // ID de la variación actualizada
                $total_processed += 1;
                $total_updated += 1;
            }
        }

        // Resumen de las estadísticas
        $results['error_count'] = $error_count;
        $results['total_processed'] = $total_processed;
        $results['total_updated'] = $total_updated;

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
    public static function create_or_update_batch(int $product_id, array $variations): array {
        $results = [];

        $error_count = 0;
        $total_creates = 0;
        $total_updates = 0;
        $total_processed = 0;

        $wc_product = wc_get_product( $product_id );
        foreach ($variations as $variation_data) {
            $result = self::create_or_update($wc_product, $variation_data);
            if ( is_wp_error( $result ) ) {
                $results[] = $result;
                $error_count += 1;
                continue;
            }

            $total_processed += 1;
        }

        // Resumen de las estadísticas
        $results['error_count'] = $error_count;
        $results['total_created'] = $total_creates;
        $results['total_updated'] = $total_updates;
        $results['total_processed'] = $total_processed;

        return $results;
    }

    /**
     * Subir una imagen a la biblioteca de medios de WordPress y asociarla a un producto de WooCommerce.
     *
     * @param string $image_url URL de la imagen a subir.
     * @param int $product_id ID del producto de WooCommerce al que se asociará la imagen.
     * @param string|null $external_id ID externo para evitar duplicados.
     *
     * @return WP_Error|array Array con el ID de la imagen o un WP_Error en caso de error.
     */
    public static function upload_image(string $image_url, int $product_id, ?string $external_id = null): WP_Error|array {
        $image_id = self::search_image($image_url, $external_id);
        if ($image_id) {
            return [$image_id];
        }

        $image_id = media_sideload_image($image_url, $product_id, null, 'id');
        if (is_wp_error($image_id)) {
            return new WP_Error('upload_error', 'Error al subir la imagen: ' . $image_id->get_error_message());
        }

        if ($external_id) {
            update_post_meta($image_id, '_external_image_id', $external_id);
        }

        return [$image_id];
    }

    /**
     * Buscar una imagen en la biblioteca de medios de WordPress por su URL o ID externo.
     *
     * @param string $image_url URL de la imagen.
     * @param string|null $external_id ID externo opcional.
     *
     * @return int|null El ID de la imagen si existe, o null si no se encuentra.
     */
    public static function search_image(string $image_url, ?string $external_id = null): ?int {
        $image_id = attachment_url_to_postid($image_url);
        if ( ! $image_id && $external_id ) {
            $image_id = self::search_image_by_external_id($external_id);
        }

        return $image_id;
    }

    /**
     * Buscar una imagen en la biblioteca de medios por su ID externo.
     *
     * @param string $external_id ID externo.
     *
     * @return int|null El ID de la imagen si existe, o null si no se encuentra.
     */
    private static function search_image_by_external_id(string $external_id): ?int {
        $args = [
            'post_type' => 'attachment',
            'meta_key' => '_external_image_id',
            'meta_value' => $external_id,
            'posts_per_page' => 1,
        ];

        $query = new WP_Query( $args );
        if ($query->have_posts()) {
            return $query->posts[0]->ID;
        }

        return null;
    }
}
