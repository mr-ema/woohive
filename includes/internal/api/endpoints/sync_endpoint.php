<?php

namespace WooHive\Internal\Api\Endpoints;

use WooHive\Internal\Tools;
use WooHive\Utils\Helpers;
use WooHive\WCApi\Client;

use \WP_REST_Request;
use \WP_REST_Response;
use \WP_Error;


/** Prevent direct script access. */
if (!defined('ABSPATH')) {
    exit;
}

class Sync_Endpoint {

    /**
     * @param string $namespace
     * Register REST API routes.
     */
    public static function register_routes(string $namespace): void {
        register_rest_route($namespace, '/sync-product', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'handle_sync_product'],
            'permission_callback' => [self::class, 'permission_check'],
            'args'     => [
                'product_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
                'from' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return in_array($param, ['primary', 'secondary'], true);
                    },
                ],
            ],
        ]);
    }

    /**
     * Handle the sync-product endpoint.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_sync_product(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $product_id = (int) $request->get_param('product_id');
        $from = $request->get_param('from');

        $site = null;

        if ( $from === 'primary' ) {
            $site = Helpers::primary_site();
        } else if ( $from === 'secondary' ) {
            $sites = Helpers::sites();
            $domain = self::get_request_domain( $request );
            $site = self::find_site_by_domain($sites, $domain);
        }

        if ( empty( $site ) ) {
            return new WP_REST_Response(['message' => 'Can not sync because site does no exist on this page' . $domain], 404);
        }

        $client = Client::create($site['url'], $site['api_key'], $site['api_secret']);
        if ( Helpers::is_primary_site() ) {
            $result = Tools::import_product($client, $product_id);
            if (is_wp_error($result)) {
                return $result;
            }
        } else {
            $result = Tools::update_product($client, $product_id);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return new WP_REST_Response(['product_id' => $result], 200);
    }

    /**
     * Permission check for the endpoint.
     *
     * @return bool
     */
    public static function permission_check(): bool {
        return true;
    }

    private static function get_request_domain(WP_REST_Request $request): string {
        $scheme = is_ssl() ? 'https' : 'http';

        $host = $request->get_header('X-Source-Server-Host');
        if (empty($host)) {
            // Fallback to the normal 'Host' header if no proxy headers are found
            $host = $request->get_header('host');
        }

        return "{$scheme}://{$host}";
    }

    /**
     * Find a matching site by domain.
     *
     * @param array  $sites  List of sites from Helpers::sites().
     * @param string $domain The domain to search for.
     *
     * @return array|null The matching site or null if no match is found.
     */
    private static function find_site_by_domain(array $sites, string $domain): ?array {
        foreach ($sites as $site) {
            if (isset($site['url']) && strpos($site['url'], $domain) !== false) {
                return $site;
            }
        }
        return null;
    }

}
