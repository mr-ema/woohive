<?php

/**
 * Plugin Name: WP Flash Messages
 * Plugin URI: http://webpresencepartners.com
 * Description: Easily Show Flash Messages in WP Admin
 * Version: 1
 * Author: Daniel Grundel, Web Presence Partners
 * Author URI: http://webpresencepartners.com
 */

if ( ! class_exists( 'WPFlashMessages' ) ) {

    class WPFlashMessages {

        /**
         * WPFlashMessages constructor.
         * Registers the show_flash_messages function to run on the 'admin_notices' action.
         */
        public function __construct() {
            add_action( 'admin_notices', array( $this, 'show_flash_messages' ) );
        }

        /**
         * Queues a flash message.
         *
         * @param string      $message The message to display.
         * @param string|null $class The CSS class to apply to the message. Defaults to 'updated'.
         *
         * @return void
         */
        public static function queue_flash_message( string $message, ?string $class = null ): void {
            $default_allowed_classes = array( 'error', 'updated' );
            $allowed_classes         = apply_filters( 'flash_messages_allowed_classes', $default_allowed_classes );
            $default_class           = apply_filters( 'flash_messages_default_class', 'updated' );

            // Use the default class if the provided class is not allowed
            if ( ! in_array( $class, $allowed_classes, true ) ) {
                $class = $default_class;
            }

            $flash_messages             = maybe_unserialize( get_option( 'wp_flash_messages', array() ) );
            $flash_messages[ $class ][] = $message;

            update_option( 'wp_flash_messages', $flash_messages );
        }

        /**
         * Displays the flash messages stored in the WordPress options.
         * It is hooked to 'admin_notices' to show messages in the admin dashboard.
         *
         * @return void
         */
        public static function show_flash_messages(): void {
            $flash_messages = maybe_unserialize( get_option( 'wp_flash_messages', '' ) );

            if ( is_array( $flash_messages ) ) {
                foreach ( $flash_messages as $class => $messages ) {
                    foreach ( $messages as $message ) {
                        ?>
                        <div class="<?php echo esc_attr( $class ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
                        <?php
                    }
                }
            }

            // Clear flash messages after they have been shown
            delete_option( 'wp_flash_messages' );
        }
    }

    new WPFlashMessages();

    if ( class_exists( 'WPFlashMessages' ) && ! function_exists( 'queue_flash_message' ) ) {
        /**
         * Convenience function for queuing flash messages.
         *
         * @param string      $message The message to display.
         * @param string|null $class The CSS class to apply to the message.
         */
        function queue_flash_message( string $message, ?string $class = null ): void {
            WPFlashMessages::queue_flash_message( $message, $class );
        }
    }
}
