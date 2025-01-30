<?php

namespace WooHive\Internal\Api\Endpoints\Sync;

use WooHive\Config\Constants;
use WooHive\Internal\Crud\Variations;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;


/** Evitar acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Variations_Endpoint {

    /**
     * Registra las rutas de la API REST para sincronización de variaciones.
     *
     * @param string $namespace Namespace para las rutas.
     */
    public static function register_routes( string $namespace ): void {
        register_rest_route(
            $namespace,
            '/products/(?P<product_sku>[a-zA-Z0-9_-]+)/variations/(?P<variation_sku>[a-zA-Z0-9_-]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( self::class, 'handle_delete' ),
                    'permission_callback' => array( self::class, 'permission_check' ),
                    'args'                => self::get_args(),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( self::class, 'handle_update' ),
                    'permission_callback' => array( self::class, 'permission_check' ),
                    'args'                => self::get_args(),
                ),
            ),
        );
    }

    public static function get_args(): array {
        return array();
    }

    private static function get_body_data( WP_REST_Request $request ): array {
        $params        = $request->get_json_params();
        $params['sku'] = $request->get_param( 'variation_sku' );

        unset( $params['variation_sku'], $params['product_sku'] );

        return $params;
    }

    public static function handle_delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! Helpers::is_sync_product_data_enabled() && ! Helpers::is_sync_stock_enabled() ) {
            $message = __( 'La sincronización está bloqueada por el sitio.', Constants::TEXT_DOMAIN );
            return new WP_Error( 'sync_blocked', $message, array( 'status' => 403 ) );
        }

        $product_sku   = $request->get_param( 'product_sku' );
        $variation_sku = $request->get_param( 'variation_sku' );

        $result = Variations::get_by_sku( $product_sku, $variation_sku );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $variation = $result;
        $result    = Variations::delete( $variation );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( array( 'message' => __( 'Variación eliminada exitosamente.', Constants::TEXT_DOMAIN ) ), 200 );
    }

    public static function handle_update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! Helpers::is_sync_product_data_enabled() && ! Helpers::is_sync_stock_enabled() ) {
            $message = __( 'La sincronización está bloqueada por el sitio.', Constants::TEXT_DOMAIN );
            return new WP_Error( 'sync_blocked', $message, array( 'status' => 403 ) );
        }

        $product_sku   = $request->get_param( 'product_sku' );
        $variation_sku = $request->get_param( 'variation_sku' );

        $body_data = self::get_body_data( $request );
        if ( ! empty( $body_data ) ) {
            $result = Variations::get_by_sku( $product_sku, $variation_sku );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $variation = $result;
            $result    = Variations::update( $variation, $body_data );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        return new WP_REST_Response( array( 'message' => __( 'Variación actualizada exitosamente.', Constants::TEXT_DOMAIN ) ), 200 );
    }

    /**
     * Verifica los permisos para el endpoint.
     *
     * @return bool Retorna true si tiene permisos.
     */
    public static function permission_check(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    private static function get_request_domain( WP_REST_Request $request ): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $request->get_header( 'X-Source-Server-Host' ) ?? $request->get_header( 'host' );

        return "{$scheme}://{$host}";
    }

    private static function find_site_by_domain( array $sites, string $domain ): ?array {
        foreach ( $sites as $site ) {
            if ( isset( $site['url'] ) && strpos( $site['url'], $domain ) !== false ) {
                return $site;
            }
        }

        return null;
    }
}
