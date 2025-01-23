<?php

namespace WooHive\Internal\Api\Endpoints;

use WooHive\Config\Constants;
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

class Sync_Product_Endpoint {

    public static function register_routes( string $namespace ): void {
        register_rest_route(
            $namespace,
            '/sync-product',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( self::class, 'handle_import_product' ),
                    'permission_callback' => array( self::class, 'permission_check' ),
                    'args'                => array(
                        'product_id' => array(
                            'required'          => true,
                            'validate_callback' => fn( $param ) => is_numeric( $param ),
                        ),
                        'from'       => array(
                            'required'          => true,
                            'validate_callback' => fn( $param ) => in_array( $param, array( 'primary', 'secondary' ), true ),
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( self::class, 'handle_update_product' ),
                    'permission_callback' => array( self::class, 'permission_check' ),
                    'args'                => array(
                        'product_id' => array(
                            'required'          => true,
                            'validate_callback' => fn( $param ) => is_numeric( $param ),
                        ),
                        'from'       => array(
                            'required'          => true,
                            'validate_callback' => fn( $param ) => in_array( $param, array( 'primary', 'secondary' ), true ),
                        ),
                    ),
                ),
            )
        );
    }

    public static function handle_import_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( Helpers::is_secondary_site() && Helpers::is_sync_only_stock_enabled() ) {
            $message = __( 'La sincronización está bloqueada porque solo está habilitada la sincronización de stock.', Constants::TEXT_DOMAIN );
            return new WP_Error( 'sync_blocked', $message, array( 'status' => 403 ) );
        }

        $product_id = (int) $request->get_param( 'product_id' );
        $from       = $request->get_param( 'from' );

        $site = self::get_site_by_source( $request, $from );
        if ( is_wp_error( $site ) ) {
            return $site;
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

        $result = Tools::import_product( $client, $product_id ); // Import logic
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response(
            array(
                'message'    => 'Product imported successfully',
                'product_id' => $result,
            ),
            200
        );
    }

    public static function handle_update_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( Helpers::is_secondary_site() && Helpers::is_sync_only_stock_enabled() ) {
            $message = __( 'La sincronización está bloqueada porque solo está habilitada la sincronización de stock.', Constants::TEXT_DOMAIN );
            return new WP_Error( 'sync_blocked', $message, array( 'status' => 403 ) );
        }

        $product_id = (int) $request->get_param( 'product_id' );
        $from       = $request->get_param( 'from' );

        $site = self::get_site_by_source( $request, $from );
        if ( is_wp_error( $site ) ) {
            return $site;
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

        $result = Tools::update_product( $client, $product_id ); // Update logic
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response(
            array(
                'message'    => 'Product updated successfully',
                'product_id' => $result,
            ),
            200
        );
    }

    private static function get_site_by_source( WP_REST_Request $request, string $from ): null|array|WP_Error {
        if ( $from === 'primary' ) {
            return Helpers::primary_site();
        }

        if ( $from === 'secondary' ) {
            $sites  = Helpers::sites();
            $domain = self::get_request_domain( $request );
            $site   = self::find_site_by_domain( $sites, $domain );

            if ( empty( $site ) ) {
                return new WP_Error( 'site_not_found', __( 'No site found for the given domain.', Constants::TEXT_DOMAIN ), array( 'status' => 404 ) );
            }

            return $site;
        }

        return new WP_Error( 'invalid_source', __( 'Invalid source provided.', Constants::TEXT_DOMAIN ), array( 'status' => 400 ) );
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
