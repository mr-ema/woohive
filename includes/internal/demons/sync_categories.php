<?php

namespace WooHive\Internal\Demons;

use WooHive\WCApi\Client;
use WooHive\Utils\Helpers;

use WP_Term;
use WP_Post;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Categories {

    public static function init(): void {
        add_action( 'created_term', array( __CLASS__, 'on_category_create' ), 10, 3 );
        add_action( 'edited_term', array( __CLASS__, 'on_category_update' ), 10, 3 );
        add_action( 'save_post', array( __CLASS__, 'on_product_category_update' ), 10, 3 );
    }

    /**
     * Maneja la creación de una nueva categoría de producto.
     *
     * @param int    $term_id   ID del término (categoría) creado.
     * @param int    $tt_id     ID del término de taxonomía.
     * @param string $taxonomy  Nombre de la taxonomía.
     *
     * @return void
     */
    public static function on_category_create( int $term_id, int $tt_id, string $taxonomy ): void {
        if ( $taxonomy !== 'product_cat' ) {
            return;
        }

        $term = get_term( $term_id );
        if ( ! $term ) {
            return;
        }

        // Lógica para sincronizar la categoría con los sitios secundarios
        if ( Helpers::is_primary_site() ) {
            self::sync_to_secondary_sites( $term );
        }
    }

    /**
     * Maneja la actualización de una categoría de producto.
     *
     * @param int    $term_id   ID del término (categoría) actualizado.
     * @param int    $tt_id     ID del término de taxonomía.
     * @param string $taxonomy  Nombre de la taxonomía.
     *
     * @return void
     */
    public static function on_category_update( int $term_id, int $tt_id, string $taxonomy ): void {
        if ( $taxonomy !== 'product_cat' ) {
            return;
        }

        $term = get_term( $term_id );
        if ( ! $term ) {
            return;
        }

        // Lógica para sincronizar la categoría actualizada con los sitios secundarios
        if ( Helpers::is_primary_site() ) {
            self::sync_to_secondary_sites( $term );
        } elseif ( Helpers::is_secondary_site() ) {
            self::sync_to_primary_site( $term );
        }
    }

    /**
     * Maneja la actualización de las categorías de un producto.
     *
     * Este método se llama cuando un producto se guarda y sus categorías son modificadas.
     *
     * @param int     $post_id ID del producto.
     * @param WP_Post $post   Instancia del post (producto).
     * @param bool    $update  Indica si es una actualización.
     *
     * @return void
     */
    public static function on_product_category_update( int $post_id, WP_Post $post, bool $update ): void {
        if ( 'product' !== $post->post_type ) {
            return;
        }

        // Evitar ejecuciones infinitas
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return;
        }

        // Obtener las categorías anteriores y actuales
        $old_categories = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
        $new_categories = $product->get_category_ids();

        // Si las categorías han cambiado, sincronizar
        if ( array_diff( $old_categories, $new_categories ) || array_diff( $new_categories, $old_categories ) ) {
            if ( Helpers::is_primary_site() ) {
                self::sync_to_secondary_sites_categories( $post_id, $new_categories );
            } elseif ( Helpers::is_secondary_site() ) {
                self::sync_to_primary_site_categories( $post_id, $new_categories );
            }
        }
    }

    /**
     * Sincroniza la creación o actualización de una categoría con los sitios secundarios.
     *
     * @param WP_Term $term Instancia del término (categoría).
     *
     * @return void
     */
    private static function sync_to_secondary_sites( WP_Term $term ): void {
        $data = array(
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'parent'      => $term->parent,
        );

        $sites = Helpers::sites();

        foreach ( $sites as $site ) {
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

            $response = $client->product_categories->push_or_update( $data );
        }
    }

    /**
     * Sincroniza la actualización de una categoría al sitio principal.
     *
     * @param WP_Term $term Instancia del término (categoría).
     *
     * @return void
     */
    private static function sync_to_primary_site( WP_Term $term ): void {
        $data = array(
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'parent'      => $term->parent,
        );

        $site = Helpers::primary_site();
        if ( empty( $site ) ) {
            return;
        }

        $client   = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
        $response = $client->product_categories->push_or_update( $data );
    }

    /**
     * Sincroniza las categorías actualizadas del producto con los sitios secundarios.
     *
     * @param int   $post_id        ID del producto.
     * @param array $new_categories Nuevas categorías del producto.
     *
     * @return void
     */
    private static function sync_to_secondary_sites_categories( int $post_id, array $new_categories ): void {
        $product = wc_get_product( $post_id );
        $sku     = $product->get_sku();

        if ( empty( $sku ) ) {
            return;
        }

        $data = array(
            'sku'        => $sku,
            'categories' => $new_categories,
        );

        $sites = Helpers::sites();

        foreach ( $sites as $site ) {
            $client   = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
            $response = $client->products->push_or_update( $data );
        }
    }

    /**
     * Sincroniza las categorías del producto al sitio principal.
     *
     * @param int   $post_id        ID del producto.
     * @param array $new_categories Nuevas categorías del producto.
     *
     * @return void
     */
    private static function sync_to_primary_site_categories( int $post_id, array $new_categories ): void {
        $product = wc_get_product( $post_id );

        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            return;
        }

        $data = array(
            'sku'        => $sku,
            'categories' => $new_categories,
        );

        $site = Helpers::primary_site();
        if ( empty( $site ) ) {
            return;
        }

        $client   = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
        $response = $client->products->push_or_update( $data );
    }
}
