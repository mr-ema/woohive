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
            '/sync/products/(?P<parent_sku>[a-zA-Z0-9_-]+)/variations/(?P<variation_sku>[a-zA-Z0-9_-]+)',
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( self::class, 'handle_delete' ),
                'permission_callback' => array( self::class, 'permission_check' ),
                'args'                => self::get_args(),
            )
        );
    }

    public static function get_args(): array {
        return array();
    }

    public static function handle_delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parent_sku    = $request->get_param( 'parent_sku' );
        $variation_sku = $request->get_param( 'variation_sku' );

        $result = Variations::get_by_sku( $parent_sku, $variation_sku );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $variation = $result;
        $result = Variations::delete( $variation );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( array( 'message' => __( 'Variación eliminada exitosamente.', Constants::TEXT_DOMAIN ) ), 200 );
    }

    /**
     * Verifica los permisos para el endpoint.
     *
     * @return bool Retorna true si tiene permisos.
     */
    public static function permission_check(): bool {
        return current_user_can( 'manage_woocommerce' );
    }
}
