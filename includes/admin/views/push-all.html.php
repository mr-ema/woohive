<?php global $title; ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $title; ?></h1>
    <hr class="wp-header-end">

    <?php require 'tabs.html.php'; ?>

    <div id="wmss-push-app" class="wmss-app">
        <div>
            <div class="header">
                <div v-if="status === 'process'" class="processing">
                    <h2><?php _e( 'Sincronizacion en progreso', 'woo-multisite-stock-sync' ); ?></h2>
                    <p><?php _e( 'El proceso de empujar cantidades de stock puede tomar algún tiempo. Por favor, no cierre su navegador ni actualice esta página hasta que el proceso haya finalizado.', 'woo-multisite-stock-sync' ); ?></p>
                </div>

                <div v-if="status === 'completed'" class="completed">
                    <h2><?php _e( '¡Sincronizacion completada!', 'woo-multisite-stock-sync' ); ?></h2>
                    <p><?php _e( 'El proceso de empujar cantidades de stock se ha completado con éxito.', 'woo-multisite-stock-sync' ); ?></p>
                    <p><a href="<?php echo $urls['report']; ?>" class="button button-primary"><?php _e( 'Ver informe &raquo;', 'woo-multisite-stock-sync' ); ?></a></p>
                </div>

                <div v-if="status === 'pending'" class="pending">
                    <h2><?php _e( 'Sincronizar todo', 'woo-multisite-stock-sync' ); ?></h2>
                    <p>
                        <?php _e( 'Sincronizar todos los productos a los inventarios secundarios. Solo se empujarán productos con SKUs coincidentes.', 'woo-multisite-stock-sync' ); ?><br>
                    </p>
                    <p><button v-on:click.prevent="startProcess" class="button button-primary"><?php _e( 'Iniciar empuje', 'woo-multisite-stock-sync' ); ?></button></p>
                </div>
            </div>

            <div class="sites">
                <div class="site" v-for="site in sites">
                    <div class="site-header">
                        <div class="title">{{ site.url }}</div>
                        <div class="status" v-bind:class="site.status"><span class="icon"></span></div>
                    </div>

                    <div class="content">
                        <table class="progress">
                            <tr>
                                <th><?php _e( 'Tiempo transcurrido', 'woo-multisite-stock-sync' ); ?></th>
                                <td><span v-if="site.status != 'pending'">{{ site.timeElapsed }}</td>
                            </tr>

                            <tr>
                                <th><?php _e( 'Procesado', 'woo-multisite-stock-sync' ); ?></th>
                                <td>{{ site.processedRecords }} / {{ totalRecords }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="wmss-error-dialog" style="display:none;">
            <p>
                <?php printf( __( 'The process was aborted by the server. Try to decrease batch size if the problem persists. <a href="%s">Go to settings &raquo;</a>', 'woo-multisite-stock-sync' ), admin_url( 'admin.php?page=wc-settings&tab=woo_multisite_stock_sync#woo_multisite_stock_sync_batch_size' ) ); ?>
            </p>

            <div class="wmss-actions">
                <p>
                    <button v-on:click.prevent="retryProcess" class="button button-primary"><?php _e( 'Retry', 'woo-multisite-stock-sync' ); ?></button>
                </p>
            </div>

            <div class="wmss-error-details">
                <h3><?php esc_html_e( 'Error Details', 'woo-multisite-stock-sync' ); ?></h3>
                <table class="wmss-error-data-table">
                    <tr>
                        <th><?php esc_html_e( 'Error', 'woo-multisite-stock-sync' ); ?></th>
                        <td>{{ errorMsg }}</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Code', 'woo-multisite-stock-sync' ); ?></th>
                        <td>{{ errorCode }}</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Headers', 'woo-multisite-stock-sync' ); ?></th>
                        <td>
                            <div v-for="(header, headerCode) in errorHeaders">
                                <span>{{ headerCode }}: {{ header }}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Body', 'woo-multisite-stock-sync' ); ?></th>
                        <td>
                            <pre>{{ errorBody }}</pre>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <?php require 'debugger.html.php'; ?>
</div>

<script>
    var app = new Vue({
        el: '#wmss-push-app',
        data: {
            status: 'pending',
            sites: <?php echo json_encode( array_values( Woo_Multisite_Stock_Sync_Utils::sites() ) ); ?>,
            totalRecords: 0, // Total number of products
            errorMsg: '',
            errorCode: '',
            errorBody: '',
            errorHeaders: [],
        },
        methods: {
            /**
             * Start syncing process
             */
            startProcess: function() {
                if (this.status !== 'pending') {
                    return;
                }

                Vue.set(this, 'status', 'process');

                this.processSite(0, 1);
                this.runTimers();
            },
            /**
             * Reset error data
             */
            resetErrorData: function() {
                this.errorMsg = '';
                this.errorCode = '';
                this.errorBody = '';
                this.errorHeaders = '';
            },
            /**
             * Display error dialog
             */
            displayErrorDialog: function() {
                var dialog = jQuery('#wmss-error-dialog').dialog({
                    width: "50%",
                    maxWidth: "800px",
                    dialogClass: 'wmss-error-dialog-container',
                    title: 'Error',
                    closeOnEscape: true,
                    resizable: false,
                    draggable: false,
                    modal: true,
                    open: function(event, ui) {
                        jQuery('.ui-widget-overlay').bind('click', function() {
                            jQuery("#wmss-error-dialog").dialog('close');
                        });
                    }
                });
            },
            /**
             * Retry process
             */
            retryProcess: function() {
                Vue.set(this, 'status', 'process');

                this.initSiteValues();
                this.processSite(0, 1);
                this.runTimers();

                jQuery('#wmss-error-dialog').dialog('close');
            },
            /**
             * Complete whole update
             */
            completeUpdate: function() {
                jQuery.ajax({
                    type: 'post',
                    url: woo_multisite_stock_sync.ajax_urls.update,
                    data: {
                        complete: '1',
                        action: 'wmss_update',
                        security: woo_multisite_stock_sync.nonces.update,
                    },
                    dataType: 'json',
                    beforeSend: function() {},
                    success: function(response) {},
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.log(jqXHR, textStatus, errorThrown);
                        Debugger.error(jqXHR.status + " " + jqXHR.responseText + " " + textStatus + " " + errorThrown);
                    },
                    complete: function() {}
                });
            },
            /**
             * Process single site
             */
            processSite: function(siteIndex, page) {
                var self = this;

                var site = this.sites[siteIndex];
                site.status = 'process';
                site.processEnded = false;

                this.resetErrorData();

                if (!site.processStarted) {
                    site.processStarted = new Date();
                }

                var limit = <?php echo Woo_Multisite_Stock_Sync_Utils::get_batch_size( 'push' ); ?>;
                var data = {
                    page: page,
                    site_key: site.key,
                    limit: limit,
                    action: 'wmss_push_all',
                    security: woo_multisite_stock_sync.nonces.push_all,
                };

                jQuery.ajax({
                    type: 'post',
                    url: woo_multisite_stock_sync.ajax_urls.push_all,
                    data: data,
                    dataType: 'json',
                    beforeSend: function() {},
                    success: function(response) {
                        if (response.status === 'processed') {
                            if (!self.totalRecords) {
                                self.totalRecords = response.total;
                            }

                            site.processedRecords += response.count;

                            if (response.last_page) {
                                site.status = 'completed';
                                site.processEnded = new Date();

                                if ((siteIndex + 1) < self.sites.length) {
                                    self.processSite((siteIndex + 1), 1);
                                } else {
                                    self.status = 'completed';
                                    self.completeUpdate();
                                }
                            } else {
                                self.processSite(siteIndex, page + 1);
                            }
                        } else if (response.status === 'error') {
                            self.errorMsg = response.errors;
                            self.errorCode = response.error_data.code;
                            self.errorHeaders = response.error_data.headers;
                            self.errorBody = response.error_data.body;
                            self.status = 'pending';
                            self.displayErrorDialog();
                        } else {
                            Debugger.error('Invalid response');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.log(jqXHR, textStatus, errorThrown);
                        Debugger.error(jqXHR.status + " " + jqXHR.responseText + " " + textStatus + " " + errorThrown);
                    },
                    complete: function() {}
                });
            },
            /**
             * Run timers
             */
            runTimers: function() {
                var self = this;

                setInterval(function() {
                    _.each(self.sites, function(site, i) {
                        if (!site.processEnded && site.status == 'process') {
                            var endTime = new Date();
                            var timeDiff = endTime - site.processStarted;
                            var formattedTime = new Date(timeDiff).toISOString().substr(11, 8);

                            site.timeElapsed = formattedTime;
                        }
                    });
                }, 1000);
            },
            /**
             * Initialize site values
             */
            initSiteValues: function() {
                // Initialize values
                _.each(this.sites, function(site) {
                    Vue.set(site, 'status', 'pending');
                    Vue.set(site, 'processStarted', false);
                    Vue.set(site, 'processEnded', false);
                    Vue.set(site, 'processedRecords', 0);
                    Vue.set(site, 'timeElapsed', '00:00:00');
                });
            },
        },
        created: function() {
            this.initSiteValues();
        },
        mounted: function() {
            Debugger.init();
            //this.startProcess();
        }
    });
</script>
