<?php

namespace WooHive\Admin;

use WooHive\Config\Constants;
use WooHive\Utils\Helpers;
use WooHive\WCApi\Client;


/**
 * Prevent direct access to the script.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ui_Page {

    public function __construct() {
        // Registrar admin menu
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
    }

    /**
     * Agregar pagina en submenu de WooCommerce
     */
    public function admin_menu(): void {
        add_submenu_page(
            'woocommerce',
            Constants::PLUGIN_NAME,
            Constants::PLUGIN_NAME,
            'manage_woocommerce',
            Constants::PLUGIN_URL_SLUG . '-report',
            array( $this, 'output' )
        );
    }

    public function output(): mixed {
        $action = isset( $_GET['action'] ) ? $_GET['action'] : '';

        switch ( $action ) {
            case 'massive_import':
                return self::massive_import_page();
            case 'push_all':
                return self::push_all_page();
            case 'tools':
                return self::tools_page();
            case 'edit_product':
                return self::edit_product_page();
            case 'log':
                // return $this->log();
        }

        return self::report_page();
    }

    /**
     * URLs to common tasks
     */
    private static function urls(): array {
        return array(
            'massive_import' => add_query_arg(
                array(
                    'page'   => Constants::PLUGIN_URL_SLUG . '-report',
                    'action' => 'massive_import',
                ),
                admin_url( 'admin.php' )
            ),
            'push_all'       => add_query_arg(
                array(
                    'page'   => Constants::PLUGIN_URL_SLUG . '-report',
                    'action' => 'push_all',
                ),
                admin_url( 'admin.php' )
            ),
            'report'         => add_query_arg(
                array(
                    'page'   => Constants::PLUGIN_URL_SLUG . '-report',
                    'action' => '',
                ),
                admin_url( 'admin.php' )
            ),
            'edit_product'   => admin_url( 'admin.php?page=' . Constants::PLUGIN_URL_SLUG . '-report&action=edit_product' ),
        );
    }

    /** @return array<string, mixed> */
    private static function tabs(): array {
        $action = isset( $_GET['action'] ) ? $_GET['action'] : '';

        $tabs = array();

        $tabs['report'] = array(
            'title'  => __( 'Productos', Constants::TEXT_DOMAIN ),
            'url'    => add_query_arg(
                array(
                    'page'   => Constants::PLUGIN_URL_SLUG . '-report',
                    'action' => '',
                ),
                admin_url( 'admin.php' )
            ),
            'active' => empty( $action ),
        );

        $tabs['tools'] = array(
            'title'  => __( 'Herramientas', Constants::TEXT_DOMAIN ),
            'url'    => add_query_arg(
                array(
                    'page'   => Constants::PLUGIN_URL_SLUG . '-report',
                    'action' => 'tools',
                ),
                admin_url( 'admin.php' )
            ),
            'active' => ( $action === 'tools' ),
        );

        $tabs['settings'] = array(
            'title'  => __( 'Ajustes', Constants::TEXT_DOMAIN ),
            'url'    => admin_url( 'admin.php?page=wc-settings&tab=' . Constants::PLUGIN_SLUG ),
            'active' => false,
        );

        return $tabs;
    }

    public static function massive_import_page(): void {
        $tabs                    = self::tabs();
        $urls                    = self::urls();
        $tabs['tools']['active'] = true;

        include __DIR__ . '/views/massive-import.html.php';
    }

    public static function push_all_page(): void {
        $tabs                    = self::tabs();
        $urls                    = self::urls();
        $tabs['tools']['active'] = true;

        include __DIR__ . '/views/push-all.html.php';
    }

    public static function report_page(): void {
        $tabs = self::tabs();
        $urls = self::urls();

        // Filters
        $search_term  = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
        $stock_status = isset( $_GET['stock_status'] ) ? sanitize_text_field( $_GET['stock_status'] ) : '';
        $sites        = Helpers::sites();

        // Pagination
        $products_per_page = 10;
        $paged             = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        $remote_sites = array();
        $total_count  = 0;
        $args         = array(
            'page'     => $paged,
            'per_page' => $products_per_page,
            'search'   => $search_term,
        );

        // Listar productos de otros sitios
        foreach ( $sites as $site ) {
            $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

            $res = $client->products->pull_all( $args );
            if ( ! $res->has_error() ) {
                $fmt_site     = array(
                    'site'     => $site,
                    'products' => $res->body(),
                );
                $remote_sites = array_merge( $remote_sites, array( $fmt_site ) );
                $headers      = $res->headers();
                $total        = $headers['x-wp-total'] ? (int) $headers['x-wp-total'] : 0;
                $total_count += $total;
            }
        }

        $total_pages = ( $total_count / $products_per_page );

        include __DIR__ . '/views/report.html.php';
    }

    public static function tools_page(): void {
        $tabs = self::tabs();
        $urls = self::urls();

        include __DIR__ . '/views/tools.html.php';
    }

    public static function edit_product_page(): void {
        $tabs = self::tabs();
        $urls = self::urls();

        $product_id = isset( $_GET['product_id'] ) ? sanitize_text_field( $_GET['product_id'] ) : '';
        $site_key   = isset( $_GET['site_key'] ) ? sanitize_text_field( $_GET['site_key'] ) : '';

        $site = Helpers::site_by_key( $site_key );
        if ( empty( $site ) ) {
            self::render_error_page(
                __( 'Sitio No Encontrado', Constants::TEXT_DOMAIN ),
                __( 'El sitio especificado no pudo ser localizado. Por favor verifica la clave del sitio e intenta nuevamente.', Constants::TEXT_DOMAIN )
            );
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );
        $res    = $client->get( "products/{$product_id}" );
        if ( $res->has_error() ) {
            self::render_error_page(
                __( 'Producto no encontrado', Constants::TEXT_DOMAIN ),
                __( 'El producto especificado no pudo ser localizado. Por favor verifica el ID del producto e intenta nuevamente.', Constants::TEXT_DOMAIN )
            );
        }

        $product = $res->body();

        // Form fields
        $fields = array(
            'name'              => array(
                'type'  => 'text',
                'label' => __( 'Nombre del producto', Constants::TEXT_DOMAIN ),
            ),
            'sku'               => array(
                'type'  => 'text',
                'label' => __( 'SKU', Constants::TEXT_DOMAIN ),
            ),
            'price'             => array(
                'type'  => 'number',
                'label' => __( 'Precio', Constants::TEXT_DOMAIN ),
                'step'  => '0.01',
            ),
            'description'       => array(
                'type'  => 'textarea',
                'label' => __( 'Descripcion', Constants::TEXT_DOMAIN ),
            ),
            'short_description' => array(
                'type'  => 'textarea',
                'label' => __( 'Descripcion corta', Constants::TEXT_DOMAIN ),
            ),
        );

        include __DIR__ . '/views/edit-product.html.php';
    }

    private static function render_error_page( string $title, string $message ): void {
        $error_title   = $title;
        $error_message = $message;
        $back_url      = admin_url( 'admin.php?page=' . Constants::PLUGIN_URL_SLUG . '-report' );

        include __DIR__ . '/views/custom-error.html.php';
        die; // stop processing
    }
}
