<?php

namespace WooHive\Admin;

use WooHive\Internal\Tools;
use WooHive\WCApi\Client;
use WooHive\Config\Constants;
use WooHive\Internal\Crud;
use WooHive\Utils\Helpers;
use WooHive\WCApi\Check_Api;


/** Prevenir acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Page {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 15, 0 );

        // Comprobación de versión de WooCommerce.
        add_action( 'init', array( $this, 'version_check' ), 10, 0 );

        // Agregar la página de configuración a WooCommerce.
        add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_settings_page' ), 10, 1 );

        // Add links to the plugins page
        add_filter( 'plugin_action_links_' . Constants::$PLUGIN_BASENAME, array( $this, 'add_plugin_links' ), 10, 1 );

        // AJAX
        add_action( 'wp_ajax_' . Constants::PLUGIN_PREFIX . '_api_check', array( $this, 'check_api_access' ) );
        add_action( 'wp_ajax_' . Constants::PLUGIN_PREFIX . '_export_product', array( $this, 'export_product_ajax' ) );
        add_action( 'wp_ajax_' . Constants::PLUGIN_PREFIX . '_push_all', array( $this, 'push_all' ) );
        add_action( 'wp_ajax_' . Constants::PLUGIN_PREFIX . '_view_last_response', array( $this, 'view_last_response' ) );
        add_action( 'wp_ajax_' . Constants::PLUGIN_PREFIX . '_import_product', array( $this, 'import_product_ajax' ) );
        add_action( 'wp_ajax_' . Constants::PLUGIN_PREFIX . '_massive_import', array( $this, 'massive_import_ajax' ) );
    }

    /**
     * Comprobar la versión de WooCommerce.
     *
     * @return void
     */
    public function version_check(): void {
        if ( ! Helpers::woocommerce_version_check( Constants::MIN_WC_VERSION ) ) {
            WPFlashMessages::queue_flash_message(
                Constants::PLUGIN_NAME . __( 'requiere WooCommerce 6.0 o superior. Por favor, actualice WooCommerce.', Constants::TEXT_DOMAIN ),
                'error'
            );
        }
    }

    /**
     * Verificar acceso a la API.
     * Este método está pendiente de implementación.
     *
     * @return void
     */
    public function check_api_access(): void {
        check_ajax_referer( Constants::PLUGIN_PREFIX . '-api-check', 'security' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json( array( 'message' => 'Permission denied' ), 403 );
        }

        $type   = isset( $_POST['type'] ) ? trim( $_POST['type'] ) : '';
        $url    = isset( $_POST['url'] ) ? trim( $_POST['url'] ) : '';
        $key    = isset( $_POST['key'] ) ? trim( $_POST['key'] ) : '';
        $secret = isset( $_POST['secret'] ) ? trim( $_POST['secret'] ) : '';

        $check = new Check_Api( $url, $key, $secret );
        $check->check( $type );
    }

    /**
     * Agregar la página de configuración a WooCommerce.
     * Incluye el archivo de configuración y lo agrega al arreglo de páginas de configuración de WooCommerce.
     *
     * @param array $settings Las páginas de configuración actuales de WooCommerce.
     * @return array Las páginas de configuración actualizadas con la nueva página añadida.
     */
    public function add_settings_page( array $settings ): array {
        $settings[] = include_once Constants::$PLUGIN_DIR_PATH . 'includes/admin/class-wc-settings.php';

        return $settings;
    }

    public function enqueue_scripts(): void {
        wp_enqueue_style( Constants::PLUGIN_URL_SLUG . '-admin-css', Constants::$PLUGIN_DIR_URL . 'public/admin/css/woo-multisite-stock-sync-admin.css', array( 'woocommerce_admin_styles', 'wp-jquery-ui-dialog' ), Constants::VERSION );
        wp_enqueue_script( Constants::PLUGIN_URL_SLUG . '-admin-js', Constants::$PLUGIN_DIR_URL . 'public/admin/js/woo-multisite-stock-sync-admin.js', array( 'jquery', 'wc-enhanced-select', 'jquery-tiptip', 'jquery-ui-dialog' ), Constants::VERSION );

        $is_report_page   = isset( $_GET['page'] ) && $_GET['page'] === Constants::PLUGIN_URL_SLUG . '-report';
        $is_settings_page = isset( $_GET['page'], $_GET['tab'] ) && $_GET['page'] === 'wc-settings' && $_GET['tab'] === Constants::PLUGIN_SLUG;
        $is_log_page      = isset( $_GET['page'], $_GET['action'] ) && $_GET['page'] === Constants::PLUGIN_URL_SLUG . '-report' && $_GET['action'] === 'log';

        if ( $is_log_page ) {
            wp_enqueue_style( 'wc-admin-layout' );
        }

        if ( $is_report_page || $is_settings_page ) {
            // Enqueue Vue.js only here to avoid clashing with other plugins using Vue.js
            wp_enqueue_script( 'vue-js', Constants::$PLUGIN_DIR_URL . 'public/admin/js/vue.js', array( 'underscore' ), Constants::VERSION );
        }

        $nonces = array(
            'api_check'      => wp_create_nonce( Constants::PLUGIN_PREFIX . '-api-check' ),
            'export_product' => wp_create_nonce( Constants::PLUGIN_PREFIX . '-export-product' ),
            'push_all'       => wp_create_nonce( Constants::PLUGIN_PREFIX . '-push-all' ),
            'import_product' => wp_create_nonce( Constants::PLUGIN_PREFIX . '-import-product' ),
            'massive_import' => wp_create_nonce( Constants::PLUGIN_PREFIX . '-massive-import' ),
        );

        wp_localize_script(
            Constants::PLUGIN_URL_SLUG . '-admin-js',
            Constants::PLUGIN_SLUG,
            array(
                'ajax_urls' => self::ajax_urls( $nonces ),
                'nonces'    => $nonces,
            )
        );
    }

    /**
     * Get AJAX action URLs dynamically based on nonces.
     *
     * @param array $nonces Nonce array with keys representing actions.
     * @return array Generated URLs for each nonce.
     */
    private static function ajax_urls( array $nonces ): array {
        $urls   = array();
        $prefix = Constants::PLUGIN_PREFIX;

        foreach ( $nonces as $action => $nonce ) {
            $urls[ $action ] = add_query_arg(
                array(
                    'action' => $prefix . '_' . $action,
                ),
                admin_url( 'admin-ajax.php' )
            );
        }

        return $urls;
    }

    /**
     * Agrega un enlace de configuración al menú de plugins de WordPress.
     *
     * Esta función agrega un enlace de configuración en la página de plugins, que redirige a la página de configuración
     * del plugin dentro de WooCommerce, utilizando el slug del plugin.
     *
     * @param array $links Enlaces de configuración existentes en el menú de plugins.
     *
     * @return array El arreglo de enlaces con el enlace de configuración agregado al principio.
     */
    public function add_plugin_links( array $links ): array {
        $url   = admin_url( 'admin.php?page=wc-settings&tab=' . Constants::PLUGIN_SLUG );
        $link  = '<a href="' . $url . '">' . __( 'Settings' ) . '</a>';
        $links = array_merge( array( $link ), $links );

        return $links;
    }

    public function view_last_response(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json( array( 'message' => 'Permission denied' ), 403 );
        }

        $data    = get_option( Constants::PLUGIN_PREFIX . '_last_response', array() );
        $code    = isset( $data['code'] ) ? $data['code'] : 'N/A';
        $body    = isset( $data['body'] ) ? $data['body'] : 'N/A';
        $headers = isset( $data['headers'] ) ? $data['headers'] : false;

        include 'views/last-response.html.php';
        die;
    }

    public function export_product_ajax(): void {
        check_ajax_referer( Constants::PLUGIN_PREFIX . '-export-product', 'security' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json( array( 'message' => 'Permission denied' ), 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        $site_key   = isset( $_POST['site_key'] ) ? sanitize_text_field( $_POST['site_key'] ) : '';

        wp_send_json_success( __( 'El producto fue exportado exitosamente.', Constants::TEXT_DOMAIN ) );
    }

    public function import_product_ajax(): void {
        check_ajax_referer( Constants::PLUGIN_PREFIX . '-import-product', 'security' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json( array( 'message' => 'Permission denied' ), 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        $site_key   = isset( $_POST['site_key'] ) ? sanitize_text_field( $_POST['site_key'] ) : '';

        $site = Helpers::site_by_key( $site_key );
        if ( empty( $site ) ) {
            wp_send_json_error(
                __(
                    'El sitio especificado no pudo ser localizado. Por favor verifica la clave del sitio e intenta nuevamente.',
                    Constants::TEXT_DOMAIN
                )
            );
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

        $res = Tools::import_product( $client, $product_id );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( json_encode( $res ) );
        }

        wp_send_json_success( __( 'El producto fue importado exitosamente.', Constants::TEXT_DOMAIN ) );
    }

    public function push_all(): void {
        check_ajax_referer( Constants::PLUGIN_PREFIX . '-push-all', 'security' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json( array( 'message' => 'Permission denied' ), 403 );
        }

        // If we are completing the whole update, update timestamp
        if ( isset( $_POST['complete'] ) && $_POST['complete'] ) {
            update_option( Constants::PLUGIN_SLUG . '_last_updated', time() );
            wp_send_json( null, 200 );
        }

        $page  = intval( $_POST['page'] );
        $limit = intval( $_POST['limit'] );

        $site = Helpers::site_by_key( $_POST['site_key'] );
        if ( empty( $site ) ) {
            wp_send_json( array( 'message' => 'Sitio no encontrado' ), 404 );
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

        // Get products for this page
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'paged'          => $page,
            'fields'         => 'ids',
        );

        $product_ids = get_posts( $args );

        if ( empty( $product_ids ) ) {
            wp_send_json( array( 'status' => 'error', 'message' => 'No products found' ), 404 );
        }

        $errors  = array();
        $success = 0;

        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $sku = $product->get_sku();
            if ( empty( $sku ) ) {
                continue;
            }

            add_filter(
                Constants::PLUGIN_SLUG . '_exclude_skus_from_sync',
                function () use ( $sku ) {
                    return array( $sku );
                }
            );

            $response = $client->post( Constants::INTERNAL_API_BASE_NAME . "/products/{$sku}", array() );
            if ( $response->has_error() ) {
                $errors[] = array(
                    'sku'     => $sku,
                    'message' => $response->json_fmt(),
                );
            } else {
                $success++;
            }

            remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );
        }

        $total       = wp_count_posts( 'product' )->publish;
        $total_pages = ceil( $total / $limit );

        $response_data = array(
            'status'    => 'processed',
            'total'     => $total,
            'pages'     => $total_pages,
            'page'      => $page,
            'last_page' => $total_pages == $page,
            'count'     => $success,
        );

        // Add errors if any
        if ( ! empty( $errors ) ) {
            $response_data['errors'] = $errors;
        }

        wp_send_json( $response_data, 200 );
    }

    public function massive_import_ajax(): void {
        check_ajax_referer( Constants::PLUGIN_PREFIX . '-massive-import', 'security' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json( array( 'message' => 'Permission denied' ), 403 );
        }

        // If we are completing whole update (all sites has been processed),
        // just update timestamp
        if ( isset( $_POST['complete'] ) && $_POST['complete'] ) {
            update_option( Constants::PLUGIN_SLUG . '_last_updated', time() );
            wp_send_json( null, 200 );
        }

        $page  = intval( $_POST['page'] );
        $limit = intval( $_POST['limit'] );

        $site = Helpers::site_by_key( $_POST['site_key'] );
        if ( empty( $site ) ) {
            wp_send_json( array( 'message' => 'Sitio no encontrado' ), 404 );
        }

        $client = Client::create( $site['url'], $site['api_key'], $site['api_secret'] );

        $response = $client->products->pull_all(
            array(
                'per_page' => $limit,
                'page'     => $page,
                'context'  => 'edit',
            )
        );
        if ( $response->has_error() ) {
            wp_send_json(
                array(
                    'status'  => 'error',
                    'message' => __( 'Error mientras se intentaba recuperar productos de la API', Constants::TEXT_DOMAIN ),
                    'error'   => $response->json_fmt(),
                ),
                422
            );
        }

        $results           = $response->body();
        $filtered_products = array_values( array_filter( $results, fn( $product ) => ! empty( $product['sku'] ) ) );

        $total       = $response->headers()['x-wp-total'] ?? 0;
        $total_pages = $response->headers()['x-wp-totalpages'] ?? 0;

        $skus = array_column( $filtered_products, 'sku' );

        $category_ids = array();
        foreach ( $filtered_products as $product ) {
            if ( ! empty( $product['categories'] ) ) {
                foreach ( $product['categories'] as $category ) {
                    if ( isset( $category['id'] ) ) {
                        $category_ids[] = $category['id'];
                    }
                }
            }
        }

        $unique_category_ids = array_unique( $category_ids );
        if ( ! empty( $unique_category_ids ) ) {
            $categories_res = $client->product_categories->pull_all( array( 'include' => implode( ',', $unique_category_ids ) ) );
            if ( ! $categories_res->has_error() ) {
                $categories = $categories_res->body();
                $unused     = Crud\Categories::create_batch( $categories );
            }
        }

        add_filter(
            Constants::PLUGIN_SLUG . '_exclude_skus_from_sync',
            function () use ( $skus ) {
                return $skus;
            }
        );
        $imported_products = Crud\Products::create_or_update_batch( $filtered_products, array( 'skip_ids' => true ) );
        if ( $imported_products['error_count'] > 0 ) {
            remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );
            wp_send_json(
                array(
                    'status' => 'error',
                    'errors' => $imported_products,
                ),
                422
            );
        }

        foreach ( $filtered_products as $product ) {
            if ( $product && ! empty( $product['variations'] ) ) {
                $ids        = $product['variations'];
                $product_id = wc_get_product_id_by_sku( $product['sku'] );

                if ( ! $product_id || empty( $ids ) ) {
                    continue;
                }

                $variations_res = $client->product_variations->pull_all( $product['id'], array( 'include' => implode( ',', $ids ) ) );
                if ( ! $variations_res->has_error() ) {
                    $variations = $variations_res->body();
                    $unused     = Crud\Variations::create_or_update_batch( $product_id, $variations );
                }
            }
        }

        remove_filter( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', '__return_false' );
        wp_send_json(
            array(
                'status'    => 'processed',
                'total'     => $total,
                'pages'     => $total_pages,
                'page'      => $page,
                'last_page' => $total_pages == $page,
                'count'     => count( $results ),
            ),
            200
        );
    }
}
