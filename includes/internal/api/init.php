<?php

namespace WooHive\Internal\Api;

use WooHive\Config\Constants;
use WooHive\Internal\Api\Endpoints\Sync_Endpoint;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Init {

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Register REST API routes.
     */
    public static function register_routes(): void {
        $namespace = Constants::API_BASE_NAME . '/' . Constants::API_VERSION;

        Sync_Endpoint::register_routes($namespace);
    }
}
