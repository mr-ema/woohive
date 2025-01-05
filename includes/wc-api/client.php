<?php

namespace WooHive\WCApi;

use WooHive\WCApi\Endpoints\Products;


/**
 * Prevenir acceso directo al script.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Client {

    private string $site_url;
    private array $auth_header;

    /** @var Products */
    public Products $products;

    /** @var Client_Products_Categories */
    public $products_categories;

    /** @var Client_Products_Attributes */
    public $products_attributes;

    /** @var Client_Products_Variations */
    public $products_variations;

    /**
     * Constructor para inicializar el cliente de la API.
     *
     * @param string $site_url   La URL base del sitio de WooCommerce.
     * @param string $api_key    La clave de consumidor de la API de WooCommerce.
     * @param string $api_secret El secreto de consumidor de la API de WooCommerce.
     */
    public function __construct( string $site_url, string $api_key, string $api_secret ) {
        $this->site_url    = trailingslashit( $site_url ) . 'wp-json/wc/v3/';
        $this->auth_header = array(
            'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $api_secret ),
            'Content-Type'  => 'application/json',
        );

        $this->products = new Products( $this );
        // $this->products_categories = new Client_Products_Categories( $this );
        // $this->products_attributes = new Client_Products_Attributes( $this );
        // $this->products_variations = new Client_Products_Variations( $this );
    }

    /**
     * Crear una nueva instancia del cliente API.
     *
     * @param string $site_url   La URL base del sitio de WooCommerce.
     * @param string $api_key    La clave de consumidor de la API de WooCommerce.
     * @param string $api_secret El secreto de consumidor de la API de WooCommerce.
     * @return Client La nueva instancia del cliente de API.
     */
    public static function create( string $site_url, string $api_key, string $api_secret ): Client {
        return new self( $site_url, $api_key, $api_secret );
    }

    /**
     * Realizar una solicitud HTTP a la API de WooCommerce.
     *
     * @param string     $method   El método HTTP (GET, POST, PUT).
     * @param string     $endpoint El endpoint de la API.
     * @param array|null $data Los datos que se enviarán en el cuerpo de la solicitud (si corresponde).
     * @param array|null $args Los argumentos adicionales que se agregarán a la solicitud (query params, headers, etc.).
     * @return Response La respuesta de la API.
     */
    public function request( string $method, string $endpoint, ?array $data = null, ?array $args = null ): Response {
        $url = $this->site_url . $endpoint;

        if ( ! empty( $args ) ) {
            $url = add_query_arg( $args, $url );
        }

        $request_args = array(
            'method'  => $method,
            'headers' => $this->auth_header,
        );

        if ( $data ) {
            $request_args['body'] = wp_json_encode( $data );
        }

        $response = wp_remote_request( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            if ( empty( $error_message ) ) {
                $error_message = __( 'Un error desconocido ha ocurrido.', 'woo-multisite-stock-sync' );
            }

            return new Response( 0, null, array(), $error_message );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );
        $headers     = wp_remote_retrieve_headers( $response )->getAll();

        return new Response( $status_code, $body, $headers );
    }

    /**
     * Realizar una solicitud GET a la API.
     *
     * @param string $endpoint El endpoint de la API.
     * @param array  $args Argumentos adicionales (query params).
     * @return Response La respuesta de la API.
     */
    public function get( string $endpoint, array $args = array() ): Response {
        return $this->request( 'GET', $endpoint, null, $args );
    }

    /**
     * Realizar una solicitud POST a la API.
     *
     * @param string $endpoint El endpoint de la API.
     * @param array  $data     Los datos que se enviarán en el cuerpo de la solicitud.
     * @param array  $args     Argumentos adicionales.
     * @return Response La respuesta de la API.
     */
    public function post( string $endpoint, array $data, array $args = array() ): Response {
        return $this->request( 'POST', $endpoint, $data, $args );
    }

    /**
     * Realizar una solicitud PUT a la API.
     *
     * @param string $endpoint El endpoint de la API.
     * @param array  $data     Los datos que se enviarán en el cuerpo de la solicitud.
     * @param array  $args     Argumentos adicionales.
     * @return Response La respuesta de la API.
     */
    public function put( string $endpoint, array $data, array $args = array() ): Response {
        return $this->request( 'PUT', $endpoint, $data, $args );
    }
}

class Response {

    private int $status_code;
    private mixed $body;
    private array $headers;
    private ?string $error_msg;

    /**
     * Constructor de la clase.
     *
     * @param int         $status_code El código de estado de la respuesta.
     * @param mixed       $body        El cuerpo de la respuesta.
     * @param array       $headers     Los encabezados de la respuesta.
     * @param string|null $error_msg   El mensaje de error, si lo hay.
     */
    public function __construct( int $status_code, mixed $body, array $headers = array(), ?string $error_msg = null ) {
        $this->status_code = $status_code;
        $this->body        = $body;
        $this->headers     = $headers;
        $this->error_msg   = $error_msg;
    }

    /**
     * Verifica si la respuesta contiene un error.
     *
     * @return bool Verdadero si hay un error, falso si no lo hay.
     */
    public function has_error(): bool {
        return ! is_null( $this->error_msg ) || $this->status_code < 200 || $this->status_code >= 300;
    }

    /**
     * Obtiene el código de estado de la respuesta.
     *
     * @return int El código de estado HTTP.
     */
    public function status_code(): int {
        return $this->status_code;
    }

    /**
     * Obtiene el cuerpo de la respuesta.
     *
     * @return mixed El cuerpo de la respuesta (generalmente un array o un objeto).
     */
    public function body(): mixed {
        return $this->body;
    }

    /**
     * Obtiene los encabezados de la respuesta.
     *
     * @return array Los encabezados de la respuesta.
     */
    public function headers(): array {
        return $this->headers;
    }

    /**
     * Obtener el mensaje de error de la respuesta si existe.
     *
     * @return string|null Mensaje de error o null si no hay error.
     */
    public function error_msg(): ?string {
        return $this->error_msg;
    }

    /**
     * Obtener el estado de la respuesta.
     *
     * @return string "success" o "error" dependiendo del estado de la respuesta.
     */
    public function status(): string {
        return $this->has_error() ? 'error' : 'success';
    }

    /**
     * Formatear el cuerpo de la respuesta como JSON.
     *
     * @return array|null El cuerpo de la respuesta como un array JSON, o null si no es válido.
     */
    public function json_fmt(): ?array {
        if ( is_array( $this->body ) ) {
            return $this->body;
        }

        if ( is_string( $this->body ) ) {
            $decoded = json_decode( $this->body, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $decoded;
            }
        }

        return null;
    }
}
