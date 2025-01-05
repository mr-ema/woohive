<?php

namespace WooHive\Utils;

use \WP_Error;
use \WP_Query;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Helper {

    /**
     * Subir una imagen a la biblioteca de medios de WordPress y asociarla a un producto de WooCommerce.
     *
     * @param string $image_url URL de la imagen a subir.
     * @param int $product_id ID del producto de WooCommerce al que se asociará la imagen.
     * @param string|null $external_id ID externo (opcional), para evitar duplicados.
     *
     * @return WP_Error|array Array de IDs de imágenes que se han agregado a la galería del producto o un objeto WP_Error si ocurre un error.
     */
    public static function upload_image(string $image_url, int $product_id, ?string $external_id = null) {
        $image_id = self::search_image($image_url, $external_id);
        if ($image_id) {
            return [ $image_id ];
        }

        $galeria = [];
        $image_id = media_sideload_image($image_url, $product_id, null, 'id');
        if (is_wp_error($image_id)) {
            return new WP_Error('upload_error', 'Error al subir la imagen: ' . $image_id->get_error_message());
        }

        $galeria[] = $image_id;
        if ($external_id) {
            update_post_meta($image_id, '_external_image_id', $external_id);
        }

        return $galeria;
    }

    /**
     * Buscar una imagen en la biblioteca de medios de WordPress por su URL o por un ID externo.
     *
     * @param string $image_url URL de la imagen a buscar.
     * @param string|null $external_id ID externo (opcional)
     *
     * @return int|null El ID de la imagen si existe, o null si no se encuentra.
     */
    public static function search_image(string $image_url, ?string $external_id = null): ?int {
        $image_id = attachment_url_to_postid($image_url);
        if (!$image_id && $external_id) {
            $image_id = self::search_by_external_id($external_id);
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
    private static function search_by_external_id(string $external_id): ?int {
        $args = array(
            'post_type'  => 'attachment',
            'meta_key'   => '_external_image_id',
            'meta_value' => $external_id,
            'posts_per_page' => 1,
        );

        $query = new WP_Query($args);
        if ($query->have_posts()) {
            $post = $query->posts[0];
            return $post->ID;
        }

        return null; // No se encontró ninguna imagen con el ID externo
    }
}
