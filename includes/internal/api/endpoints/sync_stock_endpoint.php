<?php

namespace WooHive\Internal\Api\Endpoints;

use WooHive\Config\Constants;
use WooHive\Internal\Tools;
use WooHive\Utils\Helpers;
use WooHive\WCApi\Client;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;


/** Evitar acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sync_Stock_Endpoint {

    /**
     * Registra las rutas de la API REST para sincronización de inventarios.
     *
     * @param string $namespace Namespace para las rutas.
     */
    public static function register_routes( string $namespace ): void {
        register_rest_route(
            $namespace,
            '/sync-stock',
            array(
                'methods'             => array( \WP_REST_Server::CREATABLE, \WP_REST_Server::EDITABLE ),
                'callback'            => array( self::class, 'handle_sync_stock' ),
                'permission_callback' => array( self::class, 'permission_check' ),
                'args'                => array(
                    'product_id' => array(
                        'required'          => true,
                        'validate_callback' => fn( $param ) => is_numeric( $param ),
                    ),
                    'variation_id' => array(
                        'required'          => false,
                        'validate_callback' => fn( $param ) => is_numeric( $param ),
                    ),
                    'from' => array(
                        'required'          => true,
                        'validate_callback' => fn( $param ) => in_array( $param, array( 'primary', 'secondary' ), true ),
                    ),
                ),
            )
        );
    }

    /**
     * Maneja el endpoint para sincronizar inventario.
     *
     * @param WP_REST_Request $request Petición de la API REST.
     * @return WP_REST_Response|WP_Error Respuesta de la API REST.
     */
    public static function handle_sync_stock( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $product_id   = (int) $request->get_param( 'product_id' );
        $variation_id = (int) $request->get_param( 'variation_id' ) ?? null;
        $from         = $request->get_param( 'from' );

        $site = null;
        if ( $from === 'primary' ) {
            $site = Helpers::primary_site();
        } elseif ( $from === 'secondary' ) {
            $sites  = Helpers::sites();
            $domain = self::get_request_domain( $request );
            $site   = self::find_site_by_domain( $sites, $domain );
        }

        if ( empty( $site ) ) {
            return new WP_REST_Response(
                array( 'message' => __( 'No se puede sincronizar porque el sitio no existe en esta página.', Constants::TEXT_DOMAIN ) ),
                404
            );
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

        $result = Tools::sync_stock( $client, $product_id, $variation_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( array( 'message' => __( 'Inventario sincronizado exitosamente.', Constants::TEXT_DOMAIN ) ), 200 );
    }

    /**
     * Verifica los permisos para el endpoint.
     *
     * @return bool Retorna true si tiene permisos.
     */
    public static function permission_check(): bool {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Obtiene el dominio desde la petición.
     *
     * @param WP_REST_Request $request Petición de la API REST.
     * @return string Dominio.
     */
    private static function get_request_domain( WP_REST_Request $request ): string {
        $scheme = is_ssl() ? 'https' : 'http';

        $host = $request->get_header( 'X-Source-Server-Host' );
        if ( empty( $host ) ) {
            $host = $request->get_header( 'host' );
        }

        return "{$scheme}://{$host}";
    }

    /**
     * Encuentra un sitio que coincida con el dominio.
     *
     * @param array  $sites  Lista de sitios obtenidos de Helpers::sites().
     * @param string $domain Dominio a buscar.
     *
     * @return array|null Retorna el sitio que coincide o null si no encuentra.
     */
    private static function find_site_by_domain( array $sites, string $domain ): ?array {
        foreach ( $sites as $site ) {
            if ( isset( $site['url'] ) && strpos( $site['url'], $domain ) !== false ) {
                return $site;
            }
        }
        return null;
    }
}
