<?php

namespace WooHive\Internal\Api\Endpoints\Sync;

use WooHive\Config\Constants;
use WooHive\Internal\Crud\Products;
use WooHive\Internal\Demons\Transients;
use WooHive\Internal\Tools;
use WooHive\Utils\Helpers;
use WooHive\WCApi\Client;

use WP_REST_Request;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;


/** Evitar acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Products_Endpoint {

    public static function register_routes( string $namespace ): void {
        register_rest_route(
            $namespace,
            '/products/(?P<product_sku>[a-zA-Z0-9_-]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( self::class, 'handle_create_product' ),
                    'permission_callback' => array( self::class, 'permission_check' ),
                    'args'                => array(),
                ),

                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( self::class, 'handle_update_product' ),
                    'permission_callback' => array( self::class, 'permission_check' ),
                    'args'                => array(),
                ),
            )
        );
    }

    private static function get_body_data( WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        $params['sku'] = $request->get_param( 'product_sku' );
        unset( $params['product_sku'] );

        return $params;
    }

    public static function handle_create_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! Helpers::is_create_products_in_site_enabled() ) {
            return self::handle_update_product( $request );
        }

        if ( Helpers::is_sync_only_stock_enabled() || ! Helpers::is_sync_product_data_enabled() ) {
            $message = __( 'La sincronización está bloqueada por el sitio.', Constants::TEXT_DOMAIN );
            return new WP_Error( 'sync_blocked', $message, array( 'status' => 403 ) );
        }

        $site = self::get_site_by_source( $request );
        if ( is_wp_error( $site ) ) {
            return $site;
        }

        $product_sku = $request->get_param( 'product_sku' );
        if ( ! $product_sku ) {
            return new WP_REST_Response(
                array(
                    'message'     => __( 'No se encontro el producto', Constants::TEXT_DOMAIN ),
                    'product_sku' => $product_sku,
                ),
                404
            );
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
        $result = $client->products->pull_by_sku( $product_sku );
        if ( $result->has_error() ) {
            return $result;
        }

        $product    = $result->body()[0];
        $product_id = (int) $product['id'];

        Transients::set_importing_in_progress( $product_sku, true );

        $result     = Tools::import_product( $client, $product_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        Transients::set_importing_in_progress( $product_sku, false );

        $product_id = $result;
        return new WP_REST_Response(
            array(
                'message'    => __( 'Producto importado exitosamente', Constants::TEXT_DOMAIN ),
                'product_id' => $product_id,
            ),
            200
        );
    }

    public static function handle_update_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! Helpers::is_sync_product_data_enabled() && ! Helpers::is_sync_stock_enabled() ) {
            $message = __( 'La sincronización está bloqueada por el sitio.', Constants::TEXT_DOMAIN );
            return new WP_Error( 'sync_blocked', $message, array( 'status' => 403 ) );
        }

        $site = self::get_site_by_source( $request );
        if ( is_wp_error( $site ) ) {
            return $site;
        }

        $product_sku = $request->get_param( 'product_sku' );
        if ( ! $product_sku ) {
            return new WP_REST_Response(
                array(
                    'message'     => __( 'No se encontro el producto', Constants::TEXT_DOMAIN ),
                    'product_sku' => $product_sku,
                ),
                404
            );
        }

        Transients::set_sync_in_progress( $product_sku, true );

        $body_data = self::get_body_data( $request );
        if ( ! empty( $body_data ) ) {
            $product_id = wc_get_product_id_by_sku( $product_sku );
            $result     = Products::update( $product_id, $body_data );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $product_id = $result;
            return new WP_REST_Response(
                array(
                    'message'    => __( 'Producto actualizado exitosamente', Constants::TEXT_DOMAIN ),
                    'product_id' => $product_id,
                    'data'       => $body_data,
                ),
                200
            );
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
        $result = $client->products->pull_by_sku( $product_sku );
        if ( $result->has_error() ) {
            return $result;
        }

        $product    = $result->body()[0];
        $product_id = (int) $product['id'];
        $result     = Tools::update_product( $client, $product_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        Transients::set_sync_in_progress( $product_sku, false );

        $product_id = $result;
        return new WP_REST_Response(
            array(
                'message'    => __( 'Producto actualizado exitosamente', Constants::TEXT_DOMAIN ),
                'product_id' => $product_id,
            ),
            200
        );
    }

    private static function get_site_by_source( WP_REST_Request $request ): null|array|WP_Error {
        if ( Helpers::is_secondary_site() ) {
            return Helpers::primary_site();
        }

        if ( Helpers::is_primary_site() ) {
            $sites  = Helpers::sites();
            $domain = self::get_request_domain( $request );
            $site   = self::find_site_by_domain( $sites, $domain );

            if ( empty( $site ) ) {
                return new WP_Error(
                    'site_not_found',
                    __( 'No se encontro sitios para el dominio ', Constants::TEXT_DOMAIN ) . $domain,
                    array( 'status' => 404 )
                );
            }

            return $site;
        }

        return new WP_Error( 'invalid_source', __( 'Fuente invalida.', Constants::TEXT_DOMAIN ), array( 'status' => 400 ) );
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

    public static function permission_check(): bool {
        return current_user_can( 'manage_woocommerce' );
    }
}
