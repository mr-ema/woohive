<?php
    use WooHive\Config\Constants;
    use WooHive\Utils\Helpers;

    global $title;
?>

<div class="wrap" id="woo-multisite-stock-sync-tools">
    <h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
    <hr class="wp-header-end">

    <?php require 'tabs.html.php'; ?>

    <?php if ( Helpers::is_primary_site() ) { ?>

        <div style="padding: 1rem; margin: 2rem 0; border: 1px solid #2c3338; border-radius: 0.36rem;">
            <h2 style="margin-bottom: 2rem;"><?php esc_html_e( 'Herramientas', Constants::TEXT_DOMAIN ); ?></h2>

            <div class="tools">
                <div class="tool">
                    <div class="title"><?php _e( 'Exportacion masiva de productos', Constants::TEXT_DOMAIN ); ?></div>
                    <div class="desc"><?php _e( 'Exporta productos masivamente, exportanto desde el inventario principal.', Constants::TEXT_DOMAIN ); ?></div>
                    <div class="action">
                        <a href="<?php echo $urls['push_all']; ?>" class="button button-primary"><?php _e( 'Exportar', Constants::TEXT_DOMAIN ); ?></a>
                    </div>
                </div>

                <div class="tool">
                    <div class="title"><?php _e( 'Importacion masiva de productos', Constants::TEXT_DOMAIN ); ?></div>
                    <div class="desc"><?php _e( 'Importa productos masivamente, importando desde los inventarios segundarios.', Constants::TEXT_DOMAIN ); ?></div>
                    <div class="action">
                        <a href="<?php echo $urls['massive_import']; ?>" class="button button-primary"><?php _e( 'Importar', Constants::TEXT_DOMAIN ); ?></a>
                    </div>
                </div>
            </div>
        </div>

    <?php } else { ?>
        <p><?php printf( __( 'Please view report in <a href="%s" target="_blank">the Primary Inventory site.</a>', ), Helpers::primary_report_url() ); ?></p>
    <?php } ?>
</div>
