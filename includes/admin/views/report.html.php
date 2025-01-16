<?php
    use WooHive\Config\Constants;
    use WooHive\Utils\Helpers;

    global $title;
?>

<style>
    .stock-in {
        color: var(--wc-green, --wmss-color-success) !important;
        font-weight: bold;
    }

    .stock-out {
        color: var(--wc-red, --wmss-color-error) !important;
        font-weight: bold;
    }

    /* Styling for the search form */
    form {
        margin: 2rem 0;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        flex-direction: column-reverse;
        justify-content: start;
        align-items: start;

        div:has( :not( .form-field ) ) {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            flex-direction: row;
            justify-content: space-between;
            align-items: start;
            width: 100%;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        input[type="text"],
        select {
            padding: 0.5rem !important;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 0.36rem !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        label {
            font-weight: bold;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        button {
            padding: 0.3rem 0.8rem !important;
            font-size: 1rem;
            font-weight: bold;
            border-radius: 0.36rem !important;
            align-self: end;
            min-width: 200px;
        }
    }

    /* Styling for product table */
    .pagination {
        margin: 2rem 0;
        display: flex;
        justify-content: end;
    }

    .page-numbers {
        background-color: var(--wmss-color-bg-secondary, #0073aa);
        color: var(--wmss-color-primary, #FFF);
        padding: 0.5rem 1rem;
        border-radius: 0;
        text-decoration: none;
    }

    .page-numbers.current {
        background-color: var(--wmss-color-primary, #005177);
        color: var(--wmss-color-bg-primary, #FFF);
    }

    .page-numbers:hover {
        opacity: 0.86;
    }
</style>

<div class="wrap" id="woo-multisite-stock-sync-report">
    <h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
    <hr class="wp-header-end">

    <?php require 'tabs.html.php'; ?>

    <?php if ( Helpers::is_primary_site() ) { ?>

        <!-- Search and Filter Form -->
        <form method="get" action="">
            <input type="hidden" name="page" value="woo-multisite-stock-sync-report" />
            <div>
                <div class="form-field">
                    <label for="search"><?php esc_html_e( 'Buscar Productos:', Constants::TEXT_DOMAIN ); ?></label>
                    <input type="text" name="search" id="search" placeholder="<?php esc_html_e( 'Buscar por nombre, sku, etc.', Constants::TEXT_DOMAIN ); ?>" value="<?php echo esc_attr( $search_term ); ?>" />
                </div>
                <div class="form-field">
                    <label for="stock_status"><?php esc_html_e( 'Filtrar por Stock Status', Constants::TEXT_DOMAIN ); ?></label>
                    <select name="stock_status" id="stock_status">
                        <option value=""><?php esc_html_e( 'All', Constants::TEXT_DOMAIN ); ?></option>
                        <option value="instock" <?php selected( $stock_status, 'instock' ); ?>><?php esc_html_e( 'In Stock', Constants::TEXT_DOMAIN ); ?></option>
                        <option value="outofstock" <?php selected( $stock_status, 'outofstock' ); ?>><?php esc_html_e( 'Out of Stock', Constants::TEXT_DOMAIN ); ?></option>
                    </select>
                </div>
            </div>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Buscar', Constants::TEXT_DOMAIN ); ?></button>
        </form>

        <!-- Pagination -->
        <?php if ( isset( $total_pages ) && $total_pages >= 2 ) : ?>
            <div class="pagination">
                <?php
                $pagination_args = array(
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'total'     => $total_pages,
                    'current'   => $paged ?? 0,
                    'type'      => 'plain',
                    'prev_text' => __( '« Previous', Constants::TEXT_DOMAIN ),
                    'next_text' => __( 'Next »', Constants::TEXT_DOMAIN ),
                );
                echo paginate_links( $pagination_args );
                ?>
            </div>
        <?php endif; ?>

        <!-- Product Table -->
        <table class="wmss-table wp-list-table widefat fixed striped products">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Nombre', Constants::TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'SKU', Constants::TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Stock', Constants::TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Estado De Stock', Constants::TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Accion', Constants::TEXT_DOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Site', Constants::TEXT_DOMAIN ); ?></th>
                </tr>
            </thead>

            <tbody>
            <?php if ( ! empty( $remote_sites ) ) : ?>
                <?php foreach ( $remote_sites as $remote_site ) : ?>
                    <?php
                        $site     = $remote_site['site'] ?? '-'; // Ensure site is defined
                        $products = $remote_site['products'] ?? array(); // Default to empty array if 'products' is not set
                    ?>

                    <?php if ( ! empty( $products ) ) : // Only display the site if products exist ?>
                        <?php foreach ( $products as $product ) : ?>
                            <tr>
                                <td><?php echo esc_html( $product['name'] ?? '-' ); ?></td> <!-- Fallback if name is missing -->
                                <td><?php echo esc_html( $product['sku'] ?? '-' ); ?></td> <!-- Fallback if sku is missing -->
                                <td><?php echo esc_html( $product['stock_quantity'] ?? '-' ); ?></td> <!-- Fallback if stock_quantity is missing -->
                                <td class="<?php echo esc_attr( $product['stock_status'] === 'instock' ? 'stock-in' : 'stock-out' ); ?>">
                                    <?php echo esc_html( $product['stock_status'] === 'instock' ? 'In Stock' : 'Out of Stock' ); ?>
                                </td>
                                <td>

                                <button
                                    type="button"
                                    class="button button-secondary import-product-btn"
                                    data-product-id="<?php echo esc_attr( $product['id'] ?? '' ); ?>"
                                    data-site-key="<?php echo esc_attr( $site['key'] ?? '' ); ?>"
                                >
                                    <?php _e( 'Importar', Constants::TEXT_DOMAIN ); ?>
                                </button>

                                </td>
                                <td><?php echo esc_html( $site['formatted_url'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="6">No products found for <?php echo esc_html( $site['formatted_url'] ); ?>.</td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="6">No sites available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <?php require 'debugger.html.php'; ?>

    <?php } else { ?>
        <p><?php printf( __( 'Please view report in <a href="%s" target="_blank">the Primary Inventory site.</a>', Constants::TEXT_DOMAIN ), Helpers::primary_report_url() ); ?></p>
    <?php } ?>
</div>

<script>
jQuery(document).ready(function($) {
    var GlobalAjax = window["<?php echo Constants::PLUGIN_SLUG; ?>"];
    var pluginPrefix = "<?php echo Constants::PLUGIN_PREFIX; ?>";

    $('.import-product-btn').on('click', function() {
        var productId = $(this).data('product-id');
        var siteKey = $(this).data('site-key');
        var button = $(this);

        // Deshabilitar el botón mientras se procesa
        button.prop('disabled', true).text('Importando...');
        Debugger.header("Intentando importar el producto con ID: " + productId);

        $.ajax({
            url: GlobalAjax.ajax_urls.import_product,
            method: 'POST',
            data: {
                action: pluginPrefix + '_import_product',
                product_id: productId,
                site_key: siteKey,
                security: GlobalAjax.nonces.import_product,
            },
            success: function(response) {
                if (response.success) {
                    Debugger.success(response.data || 'Operación exitosa.');
                } else {
                    Debugger.error(response.data || 'Error desconocido.');
                }
            },
            error: function(error) {
                Debugger.error('Error AJAX: ');
                Debugger.error(error);
            },
            complete: function() {
                // Rehabilitar el botón
                button.prop('disabled', false).text('Importar');
                Debugger.footer();
            }
        });
    });
});
</script>
