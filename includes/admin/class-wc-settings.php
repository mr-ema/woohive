<?php

use WooHive\Config\Constants;
use WooHive\Utils\Helpers;


/** Prevenir acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Setting_Woo_Hive_Page extends WC_Settings_Page {

    public function __construct() {
        $this->id    = Constants::PLUGIN_SLUG;
        $this->label = Constants::PLUGIN_NAME;

        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );

        add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
        add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
        add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );

        // Custom handler for outputting API credential table
        add_action( 'woocommerce_admin_field_' . $this->id . '_credentials_table', array( $this, 'credentials_table' ), 10, 1 );
    }

    /**
     * Get sections.
     *
     * @return array
     */
    public function get_sections(): array {
        $sections = array(
            '' => __( 'Settings', Constants::TEXT_DOMAIN ),
        );

        return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
    }

    /**
     * Get settings array.
     *
     * @return array
     */
    public function get_settings(): array {
        global $current_section;

        $settings = $this->get_general_settings();

        $settings = apply_filters( 'woocommerce_' . $this->id . '_settings', $settings );

        return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
    }

    /**
     * Get general settings
     *
     * @return array<<missing>,array<string,mixed>>
     */
    private function get_general_settings(): mixed {
        $settings = array(
            array(
                'title' => Constants::PLUGIN_NAME,
                'type'  => 'title',
                'id'    => $this->id . '_page_options',
            ),
        );

        $settings[ $this->id . '_enabled' ] = array(
            'title'   => __( 'Activo', Constants::TEXT_DOMAIN ),
            'type'    => 'checkbox',
            'id'      => $this->id . '_enabled',
            'default' => 'yes',
        );

        $settings[ $this->id . '_sync_stock' ] = array(
            'title'   => __( 'Sincronizar stock', Constants::TEXT_DOMAIN ),
            'type'    => 'checkbox',
            'id'      => $this->id . '_sync_stock',
            'default' => 'yes',
        );

        $settings[ $this->id . '_sync_product_data' ] = array(
            'title'   => __( 'Sincronizar datos de productos', Constants::TEXT_DOMAIN ),
            'type'    => 'checkbox',
            'id'      => $this->id . '_sync_product_data',
            'default' => 'yes',
        );

        if ( Helpers::is_secondary_site() ) {
            $settings[ $this->id . '_sync_to_primary' ] = array(
                'title'   => __( 'Permite sincronizar desde este sitio al sitio primario', Constants::TEXT_DOMAIN ),
                'type'    => 'checkbox',
                'id'      => $this->id . '_sync_to_primary',
                'default' => 'yes',
            );
        }

        $settings[ $this->id . '_create_products_in_site' ] = array(
            'title'   => __( 'Permitir crear productos en este sitio', Constants::TEXT_DOMAIN ),
            'type'    => 'checkbox',
            'id'      => $this->id . '_create_products_in_site',
            'default' => 'yes',
        );

        $settings[ $this->id . '_role' ] = array(
            'title'   => __( 'Rol de este sitio', Constants::TEXT_DOMAIN ),
            'type'    => 'select',
            'id'      => $this->id . '_role',
            'default' => 'primary',
            'options' => array(
                'primary'   => __( 'Inventario Primario', Constants::TEXT_DOMAIN ),
                'secondary' => __( 'Inventario secundario', Constants::TEXT_DOMAIN ),
            ),
            'desc'    => __( '<strong>Inventario Primario</strong> es el inventario principal que se utiliza para gestionar las cantidades de stock en los Inventarios Secundarios. Solo puedes tener un Inventario Primario.<br><strong>Los Inventarios Secundarios</strong> envían cambios de stock (edición por administrador, compras y devoluciones) al Inventario Primario, pero no cuentan con capacidades de registro ni herramientas.', Constants::TEXT_DOMAIN ),
        );

        $settings[ $this->id . '_batch_size' ] = array(
            'title'             => __( 'Tamaño del lote', Constants::TEXT_DOMAIN ),
            'type'              => 'number',
            'id'                => $this->id . '_batch_size',
            'default'           => '10',
            'desc'              => __( 'Cantidad de productos procesados a la vez en las herramientas de Empujar Todo y Actualizar Todo. Aumenta el número para procesar más productos en un lote o disminúyelo si tienes problemas con el tiempo de espera o los límites de memoria. Predeterminado: 10', Constants::TEXT_DOMAIN ),
            'desc_tip'          => true,
            'custom_attributes' => array(
                'step' => '1',
                'min'  => '1',
                'max'  => '100',
            ),
        );

        if ( Helpers::is_primary_site() ) {
            $title                     = __( 'Credenciales de API - Inventarios Segundarios', Constants::TEXT_DOMAIN );
            $supported_api_credentials = apply_filters( Constants::PLUGIN_SLUG . '_supported_api_credentials', Constants::MAX_SITES );
        } else {
            $title                     = __( 'Credenciales de API de inventario primario', Constants::TEXT_DOMAIN );
            $supported_api_credentials = Constants::MAX_SITES;
        }

        // Add hidden fields for API credentials so they get processed in WC_Admin_Settings
        // Hidden fields dont contain real data, instead fields are outputted in '_credentials_table
        // which wouldn't get saved without this
        for ( $i = 0; $i < $supported_api_credentials; $i++ ) {
            $fields = array( Constants::PLUGIN_SLUG . '_url', Constants::PLUGIN_SLUG . '_api_key', Constants::PLUGIN_SLUG . '_api_secret' );
            foreach ( $fields as $field ) {
                $settings[ $this->id . '_api_credentials_hidden_' . $field . '_' . $i ] = array(
                    'type' => 'hidden',
                    'id'   => Helpers::api_credentials_field_name( $field, $i ),
                );
            }
        }

        $settings[ $this->id . '_api_credentials' ] = array(
            'title'   => $title,
            'type'    => $this->id . '_credentials_table',
            'id'      => $this->id . '_api_credentials',
            'default' => '',
            'sites'   => $supported_api_credentials,
        );

        $settings[ $this->id . '_page_options_end' ] = array(
            'type' => 'sectionend',
            'id'   => $this->id . '_page_options',
        );

        return $settings;
    }

    /**
     * Save settings
     */
    public function save(): void {
        parent::save();
    }

    /**
     * Generar la tabla de credenciales.
     *
     * @param array $value Configuración actual de la tabla de credenciales.
     */
    public function credentials_table( array $value ): void {
        $sites = array();
        for ( $i = 0; $i < $value['sites']; $i++ ) {
            $sites[ $i ] = array(
                'url'        => array(
                    'name'  => Helpers::api_credentials_field_name( Constants::PLUGIN_SLUG . '_url', $i ),
                    'value' => Helpers::api_credentials_field_value( Constants::PLUGIN_SLUG . '_url', $i ),
                ),
                'api_key'    => array(
                    'name'  => Helpers::api_credentials_field_name( Constants::PLUGIN_SLUG . '_api_key', $i ),
                    'value' => Helpers::api_credentials_field_value( Constants::PLUGIN_SLUG . '_api_key', $i ),
                ),
                'api_secret' => array(
                    'name'  => Helpers::api_credentials_field_name( Constants::PLUGIN_SLUG . '_api_secret', $i ),
                    'value' => Helpers::api_credentials_field_value( Constants::PLUGIN_SLUG . '_api_secret', $i ),
                ),
            );

            // Hide unused fields
            $sites[ $i ]['hide_row'] = true;
            foreach ( $sites[ $i ] as $attrs ) {
                if ( ! empty( $attrs['value'] ) ) {
                    $sites[ $i ]['hide_row'] = false;
                    break;
                }
            }
        }

        include 'views/credentials-table.html.php';
    }

    /**
     * Output settings
     */
    public function output(): void {
        parent::output();

        ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    let syncToPrimary = $('#<?php echo $this->id; ?>_sync_to_primary');
                    let syncStock = $('#<?php echo $this->id; ?>_sync_stock');

                    function toggleSyncStock() {
                        if (!syncToPrimary.is(':checked')) {
                            syncStock.prop('checked', false).prop('disabled', true); // Desmarcar y deshabilitar
                        } else {
                            syncStock.prop('disabled', false); // Habilitar nuevamente
                        }
                    }

                    // Ejecutar al cargar la página
                    toggleSyncStock();

                    // Ejecutar cuando cambia el checkbox
                    syncToPrimary.on('change', toggleSyncStock);
                });
            </script>
        <?php

        include 'views/api-check-modal.html.php';
    }
}

return new WC_Setting_Woo_Hive_Page();
