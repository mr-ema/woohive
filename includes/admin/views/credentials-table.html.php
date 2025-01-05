<?php
    use WooHive\Config\Constants;
    use WooHive\Utils\Helpers;
?>

<tr valign="top">
    <th scope="row" class="titledesc">
    <label><?php echo $value['title']; ?></label>
    </th>
    <td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
    <table class="form-table wp-list-table widefat fixed wmss-credentials-table">
        <thead>
        <tr>
            <th class="min">#</th>
            <th><?php _e( 'URL', Constants::TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'API Key', Constants::TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'API Secret', Constants::TEXT_DOMAIN ); ?></th>
            <th><?php _e( 'Check', Constants::TEXT_DOMAIN ); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ( $sites as $i => $site ) { ?>
            <tr <?php echo ( $site['hide_row'] && $i > 0 ) ? 'class="hidden"' : ''; ?>>
            <td class="min">
                <?php echo $i + 1; ?>
            </td>
            <td>
                <input
                type="text"
                class="woo-multisite-stock-sync-url"
                name="<?php echo esc_attr( $site['url']['name'] ); ?>"
                value="<?php echo esc_attr( $site['url']['value'] ); ?>"
                />
            </td>
            <td>
                <input
                type="text"
                class="woo-multisite-stock-sync-api-key"
                name="<?php echo esc_attr( $site['api_key']['name'] ); ?>"
                value="<?php echo esc_attr( $site['api_key']['value'] ); ?>"
                />
            </td>
            <td>
                <input
                type="text"
                class="woo-multisite-stock-sync-api-secret"
                name="<?php echo esc_attr( $site['api_secret']['name'] ); ?>"
                value="<?php echo esc_attr( $site['api_secret']['value'] ); ?>"
                />
            </td>
            <td>
                <a href="#" class="woo-multisite-stock-sync-check-credentials button"><?php _e( 'Check API', Constants::TEXT_DOMAIN ); ?></a>
            </td>
            </tr>
        <?php } ?>
        </tbody>
        <?php if ( Helpers::is_primary_site() ) { ?>
        <tfoot>
            <tr>
            <td colspan="5">
                <a href="#" class="wmss-add-site button"><?php _e( 'Add site', Constants::TEXT_DOMAIN ); ?></a>
            </td>
            </tr>
        </tfoot>
        <?php } ?>
    </table>
    </td>
</tr>
