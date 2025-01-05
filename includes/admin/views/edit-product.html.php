<?php
    use WooHive\Config\Constants;

    global $title;
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
    <hr class="wp-header-end">

    <?php require 'tabs.html.php'; ?>
    <h1><?php echo __( 'Editar Producto', Constants::TEXT_DOMAIN ); ?></h1>

    <form id="edit-product-form" method="post" action="#">
        <?php foreach ( $fields as $field_name => $field_data ) : ?>
            <div class="form-field">
                <label for="<?php echo esc_attr( $field_name ); ?>">
                    <?php echo esc_html( $field_data['label'] ); ?>
                </label>

                <?php if ( $field_data['type'] === 'textarea' ) : ?>
                    <textarea name="<?php echo esc_attr( $field_name ); ?>"
                                id="<?php echo esc_attr( $field_name ); ?>">
                                            <?php
                                                echo esc_textarea( $product[ $field_name ] ?? '' );
                                            ?>
                                            </textarea>
                <?php else : ?>
                    <input type="<?php echo esc_attr( $field_data['type'] ); ?>"
                            name="<?php echo esc_attr( $field_name ); ?>"
                            id="<?php echo esc_attr( $field_name ); ?>"
                            value="<?php echo esc_attr( $product[ $field_name ] ?? '' ); ?>"
                            <?php
                            if ( isset( $field_data['step'] ) ) {
                                echo 'step="' . esc_attr( $field_data['step'] ) . '"';}
                            ?>
                            />
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="form-field">
            <button type="submit" class="button button-primary" id="save-product"><?php echo __( 'Save Product', 'woo-multisite-stock-sync' ); ?></button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#edit-product-form').on('submit', function(e) {
        e.preventDefault();

        // Collect form data into a JSON object
        var formData = {
            product_id: $('#product_id').val(),
            security: $('#security').val(),
            product_name: $('#product_name').val(),
            product_sku: $('#product_sku').val(),
            product_price: $('#product_price').val(),
            product_description: $('#product_description').val(),
            product_short_description: $('#product_short_description').val(),
            product_categories: $('#product_categories').val(),
            product_attributes: $('#product_attributes').val()
        };

        // Perform AJAX request to update the product
        $.ajax({
            url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
            method: 'POST',
            data: {
                action: 'wmss_update_product',
                product_data: JSON.stringify(formData)  // Send the data as JSON
            },
            success: function(response) {
                if (response.success) {
                    alert('Product updated successfully!');
                } else {
                    alert('Failed to update product: ' + response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('Error: ' + textStatus);
            }
        });
    });
});
</script>
