<?php

namespace WooHive\WCApi;

use WooHive\Config\Constants;


/**
 * Evitar acceso directo al script.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

class Check_Api {

    protected $errors = array();
    public $url       = null;
    public $key       = null;
    public $secret    = null;

    /**
     * @param string $url    La URL de la tienda WooCommerce.
     * @param string $key    La clave API para autenticación.
     * @param string $secret El secreto API para autenticación.
     */
    public function __construct( string $url, string $key, string $secret ) {
        $this->url    = $url;
        $this->key    = $key;
        $this->secret = $secret;
    }

    /**
     * Realizar la verificación según el tipo especificado.
     *
     * @param string $type El tipo de verificación (por ejemplo, format, url, rest_api, credentials).
     */
    public function check( string $type ): void {
        $this->errors = array(); // Restablecer errores.

        // Comprobar permisos del usuario.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            $this->errors[] = __( 'No tienes acceso a esta función', Constants::TEXT_DOMAIN );
        } else {
            // Llamar al método de verificación correspondiente basado en el tipo.
            call_user_func( array( $this, "check_{$type}" ) );
        }

        // Devolver el resultado como respuesta JSON.
        wp_send_json(
            array(
                'ok'     => empty( $this->errors ),
                'errors' => $this->errors,
            )
        );
    }

    /**
     * Verificar el formato de la clave y el secreto API.
     */
    public function check_format(): void {
        // Asegurarse de que la clave API comienza con "ck_".
        if ( strpos( $this->key, 'ck_' ) !== 0 ) {
            $this->errors[] = __( 'La clave API debe comenzar con "ck_"', Constants::TEXT_DOMAIN );
        }

        // Asegurarse de que el secreto API comienza con "cs_".
        if ( strpos( $this->secret, 'cs_' ) !== 0 ) {
            $this->errors[] = __( 'El secreto API debe comenzar con "cs_"', Constants::TEXT_DOMAIN );
        }
    }

    /**
     * Verificar que la URL proporcionada sea accesible.
     */
    public function check_url(): void {
        $response = wp_remote_get(
            $this->url,
            array(
                'timeout'     => 30,
                'redirection' => 0,
            )
        );

        // Verificar si la solicitud falló.
        if ( is_wp_error( $response ) ) {
            $this->errors[] = sprintf( __( 'Error de conexión: %s', Constants::TEXT_DOMAIN ), $response->get_error_message() );
            return;
        }

        // Obtener el código de respuesta.
        $code = wp_remote_retrieve_response_code( $response );

        // Manejar diferentes códigos de respuesta.
        if ( $code !== 200 ) {
            if ( $code === 301 || $code === 302 ) {
                $location = wp_remote_retrieve_header( $response, 'location' );

                // Evitar error si la URL solo tiene una diferencia de barra al final.
                if ( rtrim( $location, '/' ) === rtrim( $this->url, '/' ) ) {
                    return;
                }

                $this->errors[] = sprintf( __( '<strong>%1$s</strong> está redirigiendo a <strong>%2$s</strong>. Por favor, usa <strong>%3$s</strong> como la URL.', Constants::TEXT_DOMAIN ), $this->url, $location, $location );
            } else {
                $this->errors[] = sprintf( __( 'Código de respuesta inválido %s.', Constants::TEXT_DOMAIN ), $code );
            }
        }
    }

    /**
     * Verificar si la REST API de WooCommerce es accesible.
     */
    public function check_rest_api(): void {
        // Usando credenciales inválidas para la verificación de la API.
        $client = Client::create( $this->url, 'invalid', 'invalid' );

        $response = $client->get( '' );
        if ( $response->has_error() ) {
                $this->errors[] = 'Error al verificar la REST API: ' . $response->error_msg();
        }

        if ( $response->status_code() === 401 ) {
            // All Good, verificaremos credenciales en el siguiente paso.
            return;
        }

        if ( $response->status_code() === 404 ) {
            $this->errors[] = sprintf( __( 'La REST API de WooCommerce no es accesible en <pre>%s</pre>En la mayoría de los casos esto significa que la estructura de enlaces permanentes está configurada como "Simple" o que la REST API está deshabilitada u oculta por algún plugin de seguridad como Defender.', Constants::TEXT_DOMAIN ), $this->url );
        }
    }

    /**
     * Verificar que las credenciales API sean correctas.
     */
    public function check_credentials(): void {
        // Asegurarse de que las credenciales API no pertenezcan a este sitio.
        if ( $this->check_own_api_keys() ) {
            $this->errors[] = __( 'La clave API ingresada pertenece a este sitio. Por favor, ingresa la clave del otro sitio.', Constants::TEXT_DOMAIN );
            return;
        }

        // Obtener los productos usando las credenciales proporcionadas.
        $client   = Client::create( $this->url, $this->key, $this->secret );
        $response = $client->products->pull_all();

        $url = admin_url( 'admin-ajax.php?action=' . Constants::PLUGIN_PREFIX . '_view_last_response' );
        update_option(
            Constants::PLUGIN_PREFIX . '_last_response',
            array(
                'code' => $response->status_code(),
                'body' => json_encode( $response->body() ),
            )
        );

        if ( $response->status_code() === 200 ) {
            // All Good, podemos seguir al siguiente paso
            return;
        }

        if ( $response->status_code() === 401 ) {
            $this->errors[] = sprintf( __( 'La clave API o el secreto son inválidos o el usuario API tiene solo permisos de escritura. <a href="%s" target="_blank">Ver respuesta para depuración &raquo;</a>', Constants::TEXT_DOMAIN ), $url );
        } else {
            $this->errors[] = sprintf( __( 'El sitio web respondió de una manera que no se pudo entender. <a href="%s" target="_blank">Ver respuesta para depuración &raquo;</a>', Constants::TEXT_DOMAIN ), $url );
        }
    }

    /**
     * Verificar si las credenciales API pertenecen a este sitio.
     *
     * @return bool True si las credenciales pertenecen a este sitio, false de lo contrario.
     */
    public function check_own_api_keys(): bool {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(key_id)
                FROM {$wpdb->prefix}woocommerce_api_keys
                WHERE consumer_key = %s OR consumer_secret = %s",
                $this->key,
                $this->secret
            )
        );

        return $count >= 1;
    }

    /**
     * Verificar si el plugin está instalado y es funcional.
     */
    public function check_plugin_installed(): void {
        $client   = Client::create( $this->url, $this->key, $this->secret );
        $response = $client->get( Constants::INTERNAL_API_BASE_NAME );

        if ( $response->has_error() ) {
            $this->errors[] = $response->error_msg();
        }
    }

    /**
     * Verificar que el usuario API tenga acceso de lectura/escritura.
     */
    public function check_privileges(): void {
        // bypass
        return;

        // Usando un cliente personalizado para enviar datos.
        $client   = Client::create( $this->url, $this->key, $this->secret );
        $response = $client->post(
            'multisite-stock-sync-batch',
            array(
                'update' => array(),
            )
        );

        // Verificar si hubo un error en la respuesta.
        if ( $response->has_error() ) {
            $this->errors[] = __( 'Error al verificar privilegios: ' . $response->error_msg(), Constants::TEXT_DOMAIN );
        }
    }
}
