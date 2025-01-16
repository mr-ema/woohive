<?php

namespace WooHive\Internal\Demons;

use WooHive\WCApi\Client;
use WooHive\Utils\Helpers;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Attributes {

    public static function init(): void {
        add_action( 'save_post', [ __CLASS__, 'on_product_attribute_update'], 10, 3 );
    }

    /**
     * Maneja la actualización de los atributos de un producto.
     *
     * Este método se llama cuando un producto se guarda y sus atributos son modificados.
     *
     * @param int    $post_id ID del producto.
     * @param WP_Post $post   Instancia del post (producto).
     * @param bool   $update  Indica si es una actualización.
     *
     * @return void
     */
    public static function on_product_attribute_update( int $post_id, \WP_Post $post, bool $update ): void {
        if ( 'product' !== $post->post_type ) {
            return;
        }

        // Evitar ejecuciones infinitas
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $product = wc_get_product( $post_id );
        if ( !$product ) {
            return;
        }

        // Obtener los atributos anteriores y actuales
        $old_attributes = self::get_old_attributes( $post_id );
        $new_attributes = $product->get_attributes();

        // Si los atributos han cambiado, sincronizar
        if ( self::attributes_changed( $old_attributes, $new_attributes ) ) {
            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_attributes( $post_id, $new_attributes );
            } else if ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_attributes( $post_id, $new_attributes );
            }
        }
    }

    /**
     * Obtiene los atributos antiguos de un producto desde la base de datos.
     *
     * @param int $post_id ID del producto.
     *
     * @return array Atributos antiguos del producto.
     */
    private static function get_old_attributes( int $post_id ): array {
        $product = wc_get_product( $post_id );
        if ( !$product ) {
            return [];
        }

        return $product->get_attributes();
    }

    /**
     * Compara los atributos antiguos y nuevos para verificar si han cambiado.
     *
     * @param array $old_attributes Atributos antiguos.
     * @param array $new_attributes Atributos nuevos.
     *
     * @return bool Retorna true si los atributos han cambiado, de lo contrario false.
     */
    private static function attributes_changed( array $old_attributes, array $new_attributes ): bool {
        if ( count( $old_attributes ) !== count( $new_attributes ) ) {
            return true; // Diferente número de atributos
        }

        foreach ( $old_attributes as $old_key => $old_attribute ) {
            if ( ! isset( $new_attributes[ $old_key ] ) || $old_attribute !== $new_attributes[ $old_key ] ) {
                return true; // Los valores de los atributos son diferentes
            }
        }

        return false;
    }

    /**
     * Sincroniza los atributos actualizados del producto con los sitios secundarios.
     *
     * @param int   $post_id        ID del producto.
     * @param array $new_attributes Nuevos atributos del producto.
     *
     * @return void
     */
    private static function sync_to_secondary_sites_attributes( int $post_id, array $new_attributes ): void {
        $product = wc_get_product( $post_id );
        $sku = $product->get_sku();

        if ( empty( $sku ) ) {
            return;
        }

        $data = [
            'sku'        => $sku,
            'attributes' => self::format_attributes( $new_attributes ),
        ];

        $sites = Helpers::sites();

        foreach ( $sites as $site ) {
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

            $response = $client->products->push_or_update( $data );
        }
    }

    /**
     * Sincroniza los atributos del producto al sitio principal.
     *
     * @param int   $post_id        ID del producto.
     * @param array $new_attributes Nuevos atributos del producto.
     *
     * @return void
     */
    private static function sync_to_primary_site_attributes( int $post_id, array $new_attributes ): void {
        $product = wc_get_product( $post_id );
        $sku = $product->get_sku();

        if ( empty( $sku ) ) {
            return;
        }

        $data = [
            'sku'        => $sku,
            'attributes' => self::format_attributes( $new_attributes ),
        ];

        $site = Helpers::primary_site();
        if ( empty( $site ) ) {
            return;
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
        $response = $client->products->push_or_update( $data );
    }

    /**
     * Formatea los atributos de un producto para su sincronización.
     *
     * @param array $attributes Atributos del producto.
     *
     * @return array Atributos formateados para la sincronización.
     */
    private static function format_attributes( array $attributes ): array {
        $formatted = [];

        foreach ( $attributes as $attribute ) {
            $formatted[] = [
                'name'   => $attribute->get_name(),
                'value'  => implode( ', ', $attribute->get_options() ),
                'visible' => $attribute->get_visible(),
                'variation' => $attribute->get_variation(),
            ];
        }

        return $formatted;
    }
}
