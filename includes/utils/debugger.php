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

    private const LOG_NAME      = 'debugger.log';
    private const MAX_LOG_SIZE  = 9 * 1024;
    private const MAX_LOG_LINES = 9000;

    private const LEVELS = array(
        'ok'      => self::LEVEL_OK,
        'warning' => self::LEVEL_WARN,
        'error'   => self::LEVEL_ERROR,
        'debug'   => self::LEVEL_DEBUG,
    );

    private static $log_file;
    private static $enabled_levels = array( self::LEVEL_ERROR, self::LEVEL_WARN, self::LEVEL_OK, self::LEVEL_DEBUG );

    public static function init(): void {
        $directory = Constants::$PLUGIN_DIR_PATH . 'logs/';
        if ( ! file_exists( $directory ) ) {
            if ( is_writable( Constants::$PLUGIN_DIR_PATH ) ) {
                mkdir( $directory, 0755, true );
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
     * Write log entry to file.
     *
     * @param string $log_entry The content to be logged.
     */
    private static function write_log( string $log_entry ): void {
        if ( is_writable( self::$log_file ) ) {
            file_put_contents( self::$log_file, $log_entry, FILE_APPEND );
        }
    }

    /**
     * Set enabled logging levels.
     *
     * @param array $levels Enabled levels (e.g., [self::LEVEL_ERROR, self::LEVEL_WARN]).
     */
    public static function set_levels( array $levels ): void {
        self::$enabled_levels = $levels;
    }

    /**
     * Log a message with a specific level.
     *
     * @param int   $level  The log level (e.g., self::LEVEL_ERROR).
     * @param mixed ...$messages One or more messages to log.
     */
    private static function log( int $level, ...$messages ): void {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return; // Only log if WP_DEBUG is enabled
        }

        if ( ! in_array( $level, self::$enabled_levels ) ) {
            return; // Ignore disabled levels
        }

        $level_name = array_search( $level, self::LEVELS, true );
        $timestamp  = date( 'Y-m-d H:i:s' );

        foreach ( $messages as $message ) {
            $formatted_message = is_string( $message ) ? $message : var_export( $message, true );
            $log_entry         = "[$timestamp] [$level_name] $formatted_message" . PHP_EOL;

            // Check file size or line count before logging
            if ( file_exists( self::$log_file ) &&
                ( filesize( self::$log_file ) >= self::MAX_LOG_SIZE || self::get_line_count() >= self::MAX_LOG_LINES ) ) {
                self::rotate_log();
            }

            self::write_log( $log_entry );
        }
    }

    /**
     * Get the number of lines in the log file.
     *
     * @return int Number of lines in the log file.
     */
    private static function get_line_count(): int {
        $lines = file( self::$log_file );
        return count( $lines );
    }

    /**
     * Rotate the log file if it exceeds size or line limits.
     */
    private static function rotate_log(): void {
        $lines = file( self::$log_file );
        if ( count( $lines ) > self::MAX_LOG_LINES ) {
            $lines = array_slice( $lines, -self::MAX_LOG_LINES );
        }
        file_put_contents( self::$log_file, implode( '', $lines ) );
    }

    // Level-specific logging methods

    public static function ok( ...$messages ): void {
        self::log( self::LEVEL_OK, ...$messages );
    }

    public static function warning( ...$messages ): void {
        self::log( self::LEVEL_WARN, ...$messages );
    }

    public static function error( ...$messages ): void {
        self::log( self::LEVEL_ERROR, ...$messages );
    }

    public static function debug( ...$messages ): void {
        self::log( self::LEVEL_DEBUG, ...$messages );
    }

    /**
     * Clear the log file content.
     */
    public static function clear(): void {
        file_put_contents( self::$log_file, '' );
    }

    /**
     * Get currently enabled logging levels.
     *
     * @return array The enabled levels.
     */
    public static function get_levels(): array {
        return self::$enabled_levels;
    }

    /**
     * Get the current log file path.
     *
     * @return string Log file path.
     */
    public static function get_log_file(): string {
        return (string) self::$log_file;
    }
}
