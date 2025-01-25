<?php

/**
 * WooCommerce Multisite Product Sync
 *
 * @package             PluginPackage
 * @author              Marco, Emanuel, Alexander
 * @license             GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:         WooCommerce Multisite Product Sync
 * Description:         Coordina productos entre páginas web que usan WooCommerce.
 * Version:             1.0.0
 * Requires at least:   6.7
 * Requires PHP:        8.0
 * Author:              Marco, Emanuel, Alexander
 * License:             GPL v2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins:    WooCommerce Multisite Stock Sync, WooCommerce
 * Text Domain:         woo-multisite-stock-sync
 */

namespace WooHive;

use WooHive\Config\Constants;
use WooHive\Internal\Demons;
use WooHive\Internal\Api;


/** Prevent Direct Access */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Load constants */
require_once __DIR__ . '/config/constants.php';

/** Load DB table file */
// require_once Constants::DIR_PATH . 'includes/db/init.php';

class Init {

    public function __construct() {}

    public static function init(): void {
        Constants::init( __FILE__ );

        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action(
                'admin_notices',
                function () {
                    echo '<div class="error"><p>'
                        . __( 'El plugin WooCommerce debe estar instalado y activado para usar el plugin "Multisite Stock Sync".', Constants::TEXT_DOMAIN )
                        . '</p></div>';
                }
            );

            add_action(
                'admin_init',
                function () {
                    deactivate_plugins( Constants::$PLUGIN_BASENAME );
                }
            );

            return;
        }

        self::includes();

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            \WooHive\Utils\Debugger::init();
        }

        Demons\Init::start();
        Api\Init::init();
    }

    public static function includes(): void {
        // self::load_class('includes/utils/logger.php');
        self::load_class( 'includes/utils/helpers.php' );
        self::load_class( 'includes/utils/json_fmt.php' );
        self::load_class( 'includes/utils/debugger.php' );

        self::load_class( 'includes/wc-api/check-api.php' );
        self::load_class( 'includes/wc-api/client.php' );
        self::load_class( 'includes/wc-api/endpoints/products.php' );
        self::load_class( 'includes/wc-api/endpoints/product_categories.php' );
        self::load_class( 'includes/wc-api/endpoints/product_variations.php' );

        self::load_class( 'includes/internal/crud/products.php' );
        self::load_class( 'includes/internal/crud/categories.php' );
        self::load_class( 'includes/internal/crud/global_attributes.php' );
        self::load_class( 'includes/internal/crud/attributes.php' );
        self::load_class( 'includes/internal/crud/variations.php' );

        self::load_class( 'includes/internal/tools.php' );

        self::load_class( 'includes/internal/demons/sync_stock.php' );
        self::load_class( 'includes/internal/demons/sync_product.php' );
        self::load_class( 'includes/internal/demons/sync_variation.php' );
        self::load_class( 'includes/internal/demons/transients.php' );
        self::load_class( 'includes/internal/demons/init.php' );

        self::load_class( 'includes/internal/api/endpoints/sync/products_endpoint.php' );
        self::load_class( 'includes/internal/api/endpoints/sync/variations_endpoint.php' );
        self::load_class( 'includes/internal/api/init.php' );

        if ( is_admin() ) {
            self::admin_includes();
        }

        self::load_class( 'includes/wp-flash-messages.php', false );
    }

    private static function admin_includes(): void {
        self::load_class( 'includes/admin/class-admin.php', __NAMESPACE__ . '\Admin\Admin_Page' );
        self::load_class( 'includes/admin/class-admin-ui.php', __NAMESPACE__ . '\Admin\UI_Page' );
    }

    /**
     * Carga un archivo de clase y, opcionalmente, instancia la clase.
     *
     * @param string      $filepath La ruta relativa del archivo de clase a incluir.
     *                              Debe ser relativa a la constante `WOO_MULTISITE_STOCK_SYNC_DIR_PATH`.
     * @param string|bool $class_name (Opcional) El nombre de la clase a instanciar.
     *                                Si se establece como `FALSE`, la clase no se instancia.
     *
     * @return object|bool Devuelve una instancia de la clase si se proporciona $class_name,
     *                     o `TRUE` si el archivo se carga sin instanciación.
     */
    private static function load_class( string $filepath, string|bool $class_name = false ): object|bool {
        require_once Constants::$PLUGIN_DIR_PATH . $filepath;

        if ( $class_name ) {
            return new $class_name();
        }

        return true;
    }
}

add_action( 'plugins_loaded', array( Init::class, 'init' ) );
