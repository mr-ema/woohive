<?php

namespace WooHive\Utils;

use WooHive\Config\Constants;

use \WC_Product;
use \WC_Product_Query;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Helpers {

    /**
     * Obtener las claves de los sitios registrados.
     *
     * @return array Claves de los sitios registrados.
     */
    public static function get_site_keys(): array {
        return array_map(
            fn( $site ) => $site['key'],
            self::sites()
        );
    }

    /**
     * Busca un sitio en la lista de sitios sincronizados mediante una clave proporcionada.
     *
     * Este método recorre todos los sitios disponibles y compara la clave de cada uno con la clave
     * proporcionada como parámetro. Si se encuentra un sitio con la clave correspondiente,
     * se devuelve dicho sitio.
     *
     * @param string $key La clave del sitio que se desea buscar.
     * @return array|null El sitio encontrado en caso de éxito, o null si no se encuentra el sitio.
     */
    public static function site_by_key( string $key ): ?array {
        $sites = self::sites();

        foreach ( $sites as $site ) {
            if ( $site['key'] === $key ) {
                return $site;
            }
        }

        return null;
    }

    /**
     * Obtener la lista de sitios configurados.
     *
     * Esta función genera un arreglo de sitios iterando a través de las credenciales
     * de la API configuradas y extrae detalles como URL, clave de API y secreto de API.
     *
     * @return array|string Lista de sitios con detalles, o un arreglo vacío si no hay sitios.
     */
    public static function sites(): array|string {
        $sites     = array();
        $max_sites = apply_filters( Constants::PLUGIN_SLUG . '_supported_api_credentials', Constants::MAX_SITES );

        for ( $i = 0; $i < $max_sites; $i++ ) {
            $url           = self::api_credentials_field_value( Constants::PLUGIN_SLUG . '_url', $i );
            $formatted_url = self::format_site_url( $url );
            $api_key       = self::api_credentials_field_value( Constants::PLUGIN_SLUG . '_api_key', $i );
            $api_secret    = self::api_credentials_field_value( Constants::PLUGIN_SLUG . '_api_secret', $i );

            if ( ! empty( $url ) && ! empty( $api_key ) && ! empty( $api_secret ) ) {
                $sites[ $i ] = array(
                    'i'             => $i,
                    'key'           => sanitize_key( $url ),
                    'url'           => $url,
                    'formatted_url' => $formatted_url,
                    'letter'        => strtoupper( substr( $formatted_url, 0, 1 ) ),
                    'api_key'       => $api_key,
                    'api_secret'    => $api_secret,
                );
            }
        }

        return $sites;
    }

    /**
     * Formatear una URL para devolver un título limpio o un enlace HTML.
     *
     * Esta función elimina los prefijos "https://" y "http://" de la URL proporcionada,
     * devolviendo un título simplificado. Si el parámetro `$link` es `true`,
     * genera un enlace HTML con el título como texto visible.
     *
     * @param string $url   URL que se desea formatear.
     * @param bool   $link  Opcional. Si es `true`, devuelve un enlace HTML. Predeterminado es `false`.
     *
     * @return string Título limpio o el enlace HTML generado.
     */
    public static function format_site_url( string $url, bool $link = false ): string {
        $title = str_replace( array( 'https://', 'http://' ), '', $url );

        if ( $link ) {
            return sprintf( '<a href="%s" target="_blank">%s</a>', $url, $title );
        }

        return $title;
    }

    /**
     * Verificar si WooCommerce 6.0 o superior está activo.
     *
     * @param string $version Versión que se desea verificar. Predeterminado es '6.0'.
     * @return bool `true` si WooCommerce 6.0 o superior está activo, `false` en caso contrario.
     */
    public static function woocommerce_version_check( string $version = '6.0' ): bool {
        if ( class_exists( 'WooCommerce' ) ) {
            global $woocommerce;

            if ( version_compare( $woocommerce->version, $version, '>=' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar si el sitio es el principal.
     *
     * @return bool `true` si el sitio es el principal, `false` en caso contrario.
     */
    public static function is_primary_site(): bool {
        return self::get_role() === 'primary';
    }

    /**
     * Comprobar si el sitio es el segundario.
     *
     * @return bool True si el sitio es principal, False en caso contrario.
     */
    public static function is_secondary_site(): bool {
        return self::get_role() === 'secondary';
    }

    /**
     * Obtener el rol del sitio.
     *
     * @return string Rol del sitio, 'primary' por defecto.
     */
    public static function get_role(): string {
        return get_option( 'woo_multisite_stock_sync_role', 'primary' );
    }

    /**
     * Get request source role (Primary / Inventory)
     */
    public static function request_role(): bool {
        return isset( $GLOBALS['woo_multisite_stock_sync_request_role'] ) ? $GLOBALS['woo_multisite_stock_sync_request_role'] : false;
    }

    /**
     * Formatear el nombre del campo de las credenciales de la API.
     *
     * @param string $name Nombre base del campo.
     * @param int    $i Índice del campo (si es mayor que 0).
     *
     * @return string Nombre del campo formateado.
     */
    public static function api_credentials_field_name( string $name, int $i ): string {
        if ( $i == 0 ) {
            return $name;
        }

        return sprintf( '%s_%d', $name, $i );
    }

    /**
     * Obtener el valor del campo de las credenciales de la API.
     *
     * @param string $name Nombre base del campo.
     * @param int    $i Índice del campo (si es mayor que 0).
     * @param string $default Valor por defecto si no se encuentra la opción.
     *
     * @return string Valor del campo, o el valor por defecto.
     */
    public static function api_credentials_field_value( string $name, int $i, string $default = '' ): string {
        if ( $i == 0 ) {
            $value_key = $name;
        } else {
            $value_key = sprintf( '%s_%d', $name, $i );
        }

        return get_option( $value_key, $default );
    }

    /**
     * Generar la URL para el reporte principal de inventario.
     *
     * Basado en los sitios configurados en la sincronización de inventario.
     *
     * @param string $action Acción opcional que se incluirá como parámetro en la URL.
     * @return string URL generada para el reporte principal, o cadena vacía si no hay sitios configurados.
     */
    public static function primary_report_url( string $action = '' ): string {
        $sites = self::sites();

        if ( ! empty( $sites ) ) {
            $site = reset( $sites );

            return add_query_arg(
                array(
                    'page'   => Constants::PLUGIN_URL_SLUG . '-report',
                    'action' => $action,
                ),
                $site['url'] . '/wp-admin/admin.php'
            );
        }

        return '';
    }

    /**
     * Busca el sitio principal en este caso el primer sitio en sites si existe
     *
     * @return array|null El sitio encontrado en caso de éxito, o null si no se encuentra el sitio.
     */
    public static function primary_site(): ?array {
        $sites = self::sites();
        if ( empty( $sites ) ) {
            return null;
        }

        $site = reset( $sites );
        return $site;
    }

    public static function status_filter_options(): array {
        return array(
            'all'      => __( 'Todos los productos', Constants::TEXT_DOMAIN ),
            'mismatch' => __( 'Stock distinto', Constants::TEXT_DOMAIN ),
        );
    }

    /**
     * Obtener la consulta de productos.
     *
     * Esta función configura y devuelve un objeto de consulta de productos (`WC_Product_Query`)
     * con filtros predefinidos y la posibilidad de añadir parámetros personalizados.
     *
     * @param array $params Opcional. Parámetros adicionales para personalizar la consulta.
     *
     * @return WC_Product_Query Objeto de consulta configurado.
     */
    public static function product_query( array $params = array() ): WC_Product_Query {
        $query = new WC_Product_Query();

        $query->set( 'status', array( 'publish', 'private' ) );
        $query->set( 'type', self::product_types() );
        $query->set( 'order', 'ASC' );
        $query->set( 'orderby', 'ID' );

        foreach ( $params as $key => $value ) {
            $query->set( $key, $value );
        }

        return $query;
    }

    /**
     * Obtener los tipos de productos.
     *
     * Devuelve un arreglo de tipos de productos compatibles, incluyendo los valores predeterminados
     * y los tipos adicionales proporcionados en el parámetro `$incl`.
     *
     * @param array $incl Opcional. Tipos adicionales a incluir en el resultado.
     *
     * @return array Tipos de productos resultantes.
     */
    public static function product_types( array $incl = array() ): array {
        $types = apply_filters(
            Constants::PLUGIN_SLUG . '_product_types',
            array(
                'simple',
                'variable',
                'product-part',
                'variable-product-part',
                'bundle',
            )
        );

        return array_merge( $types, $incl );
    }

    /**
     * Verificar si debe proceder con la sincronización de inventario.
     *
     * @param WC_Product $product El objeto del producto de WooCommerce.
     * @return bool Si debe sincronizarse el inventario o no.
     */
    public static function should_sync_stock( WC_Product $product ): bool {
        // Si se está utilizando una versión de WooCommerce no compatible, abortar
        if ( ! self::woocommerce_version_check() ) {
            return false;
        }

        // Si la sincronización de inventario no está habilitada
        if ( get_option( Constants::PLUGIN_SLUG . '_enabled', 'yes' ) !== 'yes' ) {
            return false;
        }

        // Si el cambio de inventario proviene del Inventario Principal, no crear un nuevo trabajo
        if ( self::request_role() === 'primary' ) {
            return false;
        }

        // Si es un Inventario Secundario y el cambio fue desencadenado por una solicitud de Stock Sync, no crear un nuevo trabajo
        if ( self::is_secondary_site() && self::is_self_request() ) {
            return false;
        }

        // Si el producto no está gestionando inventario
        if ( ! $product->managing_stock() ) {
            return false;
        }

        // Permitir que los plugins de terceros determinen si deben sincronizarse el stock
        if ( ! apply_filters( Constants::PLUGIN_SLUG . '_should_sync', true, $product, 'stock_qty' ) ) {
            return false;
        }

        $exclude_skus = apply_filters( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', [] );
        if ( is_array( $exclude_skus ) ) {
            $should_exclude = (function() use ( $product, $exclude_skus ) {
                $sku = $product->get_sku();
                if ( empty( $sku ) ) return true;

                if ( $product instanceof WC_Product_Variation ) {
                    $parent = wc_get_product( $product->get_parent_id() );

                    $sku = $parent->get_sku();
                    if ( empty( $sku ) ) return true;
                }

                return in_array( $sku, $exclude_skus );
            })();

            if ( $should_exclude ) return false;
        }

        return true;
    }

    /**
     * Verificar si debe proceder con la sincronización de inventario.
     *
     * @param WC_Product $product El objeto del producto de WooCommerce.
     * @return bool Si debe sincronizarse el inventario o no.
     */
    public static function should_sync( WC_Product $product ): bool {
        // Si se está utilizando una versión de WooCommerce no compatible, abortar
        if ( ! self::woocommerce_version_check() ) {
            return false;
        }

        // Si la sincronización de inventario no está habilitada
        if ( get_option( Constants::PLUGIN_SLUG . '_enabled', 'yes' ) !== 'yes' ) {
            return false;
        }

        // Si el cambio de inventario proviene del Inventario Principal, no crear un nuevo trabajo
        if ( self::request_role() === 'primary' ) {
            return false;
        }

        // Si es un Inventario Secundario y el cambio fue desencadenado por una solicitud de Stock Sync, no crear un nuevo trabajo
        if ( self::is_secondary_site() && self::is_self_request() ) {
            return false;
        }

        $exclude_skus = apply_filters( Constants::PLUGIN_SLUG . '_exclude_skus_from_sync', [] );
        if ( is_array( $exclude_skus ) ) {
            $should_exclude = (function() use ( $product, $exclude_skus ) {
                $sku = $product->get_sku();
                if ( empty( $sku ) ) return true;

                if ( $product instanceof WC_Product_Variation ) {
                    $parent = wc_get_product( $product->get_parent_id() );

                    $sku = $parent->get_sku();
                    if ( empty( $sku ) ) return true;
                }

                return in_array( $sku, $exclude_skus );
            })();

            if ( $should_exclude ) return false;
        }

        return true;
    }

    /**
     * Check if request is Woo Multisite Stock Sync request
     */
    public static function is_self_request(): bool {
        return isset( $GLOBALS[ Constants::PLUGIN_SLUG . '_request' ] ) && $GLOBALS[ Constants::PLUGIN_SLUG . '_request' ];
    }

    /**
     * Get batch size
     */
    public static function get_batch_size( string $operation = '' ): int {
        $size = intval( get_option( Constants::PLUGIN_SLUG . '_batch_size', 10 ) );

        return apply_filters( Constants::PLUGIN_SLUG . '_batch_size', $size, $operation );
    }
}
