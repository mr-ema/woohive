<?php

namespace WooHive\Utils;

use WooHive\Config\Constants;


/**
 * Evitar acceso directo al script.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

class Debugger {

    private const LEVEL_OK    = 0;
    private const LEVEL_WARN  = 1;
    private const LEVEL_ERROR = 2;
    private const LEVEL_DEBUG = 3;

    private const LOG_NAME       = 'debugger.log';
    private const MAX_LOG_SIZE   = 3 * 1024;
    private const MAX_LOG_LINES  = 100;

    private const LEVELS = [
        'ok'      => self::LEVEL_OK,
        'warning' => self::LEVEL_WARN,
        'error'   => self::LEVEL_ERROR,
        'debug'   => self::LEVEL_DEBUG,
    ];

    private static $log_file;
    private static $enabled_levels = array( self::LEVEL_ERROR, self::LEVEL_WARN, self::LEVEL_OK, self::LEVEL_DEBUG );

    public static function init(): void {
        $directory = Constants::$PLUGIN_DIR_PATH . 'logs/';
        if ( ! file_exists( $directory ) ) {
            if ( is_writable( Constants::$PLUGIN_DIR_PATH ) ) {
                $unused = mkdir( $directory, 0755, true );
            }
        }

        self::$log_file = $directory . self::LOG_NAME;
        if ( ! file_exists( self::$log_file ) ) {
            if ( is_writable( $directory ) ) {
                file_put_contents( self::$log_file, '', FILE_APPEND );
            }
        }
    }

    /**
     * Función para escribir en el archivo de registro.
     * Esta función intentará escribir en el archivo, pero no hará nada si el archivo no es escribible.
     *
     * @param string $log_entry El contenido que se va a escribir en el archivo de registro.
     */
    public static function write_log( string $log_entry ): void {
        // Si el archivo no es escribible, simplemente no hacemos nada.
        if ( is_writable( self::$log_file ) ) {
            file_put_contents( self::$log_file, $log_entry, FILE_APPEND );
        }
    }

    /**
     * Configurar los niveles de registro habilitados.
     *
     * @param array $levels Array con los niveles habilitados (e.g., [self::LEVEL_ERROR, self::LEVEL_WARN]).
     */
    public static function set_levels( array $levels ): void {
        self::$enabled_levels = $levels;
    }

    /**
     * Registrar un mensaje con un nivel específico.
     *
     * @param int    $level   Nivel del mensaje (e.g., self::LEVEL_ERROR).
     * @param string $message Mensaje a registrar.
     */
    private static function log( int $level, string $message ): void {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return; // No hacer nada si la depuración no está habilitada
        }

        if ( ! in_array( $level, self::$enabled_levels ) ) {
            return; // Ignorar niveles no habilitados
        }

        $level_name = array_search( $level, self::LEVELS, true );
        $timestamp  = date( 'Y-m-d H:i:s' );
        $log_entry  = "[$timestamp] [$level_name] $message" . PHP_EOL;

        // Verificar el tamaño del archivo antes de agregar un nuevo registro
        if ( file_exists( self::$log_file ) && ( filesize( self::$log_file ) >= self::MAX_LOG_SIZE || self::get_line_count() >= self::MAX_LOG_LINES ) ) {
            self::rotate_log();
        }

        self::write_log( $log_entry );
    }

    /**
     * Obtener el número de líneas del archivo de registro.
     *
     * @return int El número de líneas en el archivo de registro.
     */
    private static function get_line_count(): int {
        $lines = file( self::$log_file );
        return count( $lines );
    }

    /**
     * Rotar el archivo de registro (limpiar o truncar el archivo).
     */
    private static function rotate_log(): void {
        $lines = file( self::$log_file );
        if ( count( $lines ) > self::MAX_LOG_LINES ) {
            $lines = array_slice( $lines, -self::MAX_LOG_LINES );
        }

        self::write_log( implode( '', $lines ) );
    }

    /**
     * Registrar un mensaje de nivel OK.
     *
     * @param string $message Mensaje de nivel OK.
     */
    public static function ok( string $message ): void {
        self::log( self::LEVEL_OK, $message );
    }

    /**
     * Registrar un mensaje de advertencia (warning).
     *
     * @param string $message Mensaje de advertencia.
     */
    public static function warning( string $message ): void {
        self::log( self::LEVEL_WARN, $message );
    }

    /**
     * Registrar un mensaje de error.
     *
     * @param string $message Mensaje de error.
     */
    public static function error( string $message ): void {
        self::log( self::LEVEL_ERROR, $message );
    }

    /**
     * Registrar un mensaje de depuración (debug).
     *
     * @param string $message Mensaje de depuración.
     */
    public static function debug( string $message ): void {
        self::log( self::LEVEL_DEBUG, $message );
    }

    /**
     * Limpiar el contenido del archivo de registro.
     */
    public static function clear(): void {
        file_put_contents( self::$log_file, '' );
    }

    /**
     * Obtener los niveles de registro habilitados actualmente.
     *
     * @return array Los niveles habilitados.
     */
    public static function get_levels(): array {
        return self::$enabled_levels;
    }

    /**
     * Obtener la ruta actual del archivo de registro.
     *
     * @return string Ruta del archivo de registro.
     */
    public static function get_log_file(): string {
        return ( (string)self::$log_file );
    }
}
