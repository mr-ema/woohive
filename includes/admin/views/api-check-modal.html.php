<?php
    use WooHive\Config\Constants;
?>

<div id="wmss-api-check-dialog" style="display:none;">
    <div id="wmss-api-check-app">

        <table class="wmss-credentials-table">
            <tr>
                <th><?php esc_html_e( 'URL', Constants::TEXT_DOMAIN ); ?></th>
                <td>{{ url }}</td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'API Key', Constants::TEXT_DOMAIN ); ?></th>
                <td>{{ apiKey }}</td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'API Secret', Constants::TEXT_DOMAIN ); ?></th>
                <td>{{ apiSecret }}</td>
            </tr>
        </table>

        <table class="wmss-checks-table">
            <template v-for="check in checks">
                <tr>
                    <th>{{ check.title }}</th>
                    <td><span class="wmss-check-status" :class="statuses[check.id]"></span></td>
                </tr>
                <tr v-if="errors[check.id].length > 0">
                    <td colspan="2" class="error">
                        <div v-for="error in errors[check.id]" v-html="error"></div>
                    </td>
                </tr>
            </template>
        </table>

        <p v-if="allGood" class="wmss-api-check-ok"><?php esc_html_e( 'All tests passed and API connection is good to go. Happy syncing!', Constants::TEXT_DOMAIN ); ?></p>
    </div>
</div>
