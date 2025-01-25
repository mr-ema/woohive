<?php

namespace WooHive\Internal\Api;

use WooHive\Config\Constants;
use WooHive\Internal\Api\Endpoints\Sync\Variations_Endpoint;
use WooHive\Internal\Api\Endpoints\Sync\Products_Endpoint;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Init {

    public static function init(): void {
        add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public static function register_routes(): void {
        $namespace = Constants::WC_API_BASE_NAME . '/' . Constants::WC_API_VERSION . '/' . Constants::INTERNAL_API_BASE_NAME;

        Products_Endpoint::register_routes( $namespace );
        Variations_Endpoint::register_routes( $namespace );
    }
}
