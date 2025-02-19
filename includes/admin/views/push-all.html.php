<?php
use WooHive\Config\Constants;
use WooHive\Utils\Helpers;

global $title;
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $title; ?></h1>
    <hr class="wp-header-end">

    <?php require 'tabs.html.php'; ?>

    <div id="wmss-push-all-app" class="wmss-app">
        <div>
            <div class="header">
                <div v-if="status === 'process'" class="processing">
                    <h2><?php _e( 'Exportación en progreso', Constants::TEXT_DOMAIN ); ?></h2>
                    <p><?php _e( 'La exportación puede tardar un poco. Por favor, no cierre su navegador ni actualice esta página hasta que el proceso se haya completado.', Constants::TEXT_DOMAIN ); ?></p>
                </div>

                <div v-if="status === 'completed'" class="completed">
                    <h2><?php _e( '¡Exportación completada!', Constants::TEXT_DOMAIN ); ?></h2>
                    <p><?php _e( 'La exportación se ha completado con éxito.', Constants::TEXT_DOMAIN ); ?></p>
                    <p><a href="<?php echo $urls['report']; ?>" class="button button-primary"><?php _e( 'Ver reporte &raquo;', Constants::TEXT_DOMAIN ); ?></a></p>
                </div>

                <div v-if="status === 'pending'" class="pending">
                    <h2><?php _e( 'Reporte de exportación', Constants::TEXT_DOMAIN ); ?></h2>
                    <p><?php _e( 'Haga clic en un sitio para iniciar la exportación o utilice el botón para exportar todos los sitios.', Constants::TEXT_DOMAIN ); ?></p>

                    <div v-if="status === 'pending'">
                        <button @click.prevent="startExportAll" class="button button-primary">
                            <?php _e( 'Exportar a todos los sitios', Constants::TEXT_DOMAIN ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="sites">
                <div class="site" v-for="site in sites" :key="site.key"  style="padding: 0.69rem;">
                    <div class="site-header">
                        <div class="title">{{ site.formatted_url }}</div>
                        <div class="status" :class="site.status"><span class="icon"></span></div>
                    </div>

                    <div class="content">
                        <table class="progress">
                            <tr>
                                <th><?php _e( 'Tiempo transcurrido', Constants::TEXT_DOMAIN ); ?></th>
                                <td><span v-if="site.status !== 'pending'">{{ site.timeElapsed }}</span></td>
                            </tr>

                            <tr>
                                <th><?php _e( 'Exportados', Constants::TEXT_DOMAIN ); ?></th>
                                <td>{{ site.processedRecords }} / {{ site.totalRecords }}</td>
                            </tr>
                        </table>
                    </div>

                    <div v-if="site.status === 'pending'" style="display: flex; flex-direction: column;">
                        <button @click.prevent="startExport(site)" class="button button-secondary" style="align-self: end;">
                            <?php _e( 'Exportar todo a este sitio', Constants::TEXT_DOMAIN ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require 'debugger.html.php'; ?>
</div>

<script>
Vue.prototype.GlobalAjax = window["<?php echo Constants::PLUGIN_SLUG; ?>"];
Vue.prototype.PluginPrefix = "<?php echo Constants::PLUGIN_PREFIX; ?>";

var app = new Vue({
    el: '#wmss-push-all-app',
    data: {
        status: 'pending',
        sites: <?php echo json_encode( array_values( Helpers::sites() ) ); ?>,
    },
    methods: {
        startExport: function(site) {
            if (site.status !== 'pending') return;

            Debugger.header("Starting export for site: " + site.formatted_url);
            site.status = 'process';
            this.processSite(site, 1);
            this.runTimers();
        },

        startExportAll: function() {
            Debugger.header("Starting export for all sites...");
            let self = this;
            let sitesToExport = this.sites.filter(site => site.status === 'pending');

            function exportNextSite(index) {
                if (index >= sitesToExport.length) {
                    self.status = 'completed';
                    Debugger.success("All sites exported successfully.");
                    return;
                }

                let site = sitesToExport[index];
                site.status = 'process';
                self.processSite(site, 1, function() {
                    Debugger.info("Site exported: " + site.formatted_url);
                    exportNextSite(index + 1);
                });
            }

            exportNextSite(0);
            this.status = 'process';
            this.runTimers();
        },

        processSite: function(site, page, callback) {
            var self = this;
            site.processEnded = false;
            if (!site.processStarted) {
                site.processStarted = new Date();
            }

            var limit = <?php echo Helpers::get_batch_size( 'push_all' ); ?>;
            var data = {
                page: page,
                site_key: site.key,
                limit: limit,
                action: this.PluginPrefix + '_push_all',
                security: this.GlobalAjax.nonces.push_all,
            };

            jQuery.ajax({
                type: 'post',
                url: this.GlobalAjax.ajax_urls.push_all,
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'processed') {
                        if (!site.totalRecords) {
                            site.totalRecords = response.total;
                        }
                        site.processedRecords += response.count;

                        if (response.last_page) {
                            site.status = 'completed';
                            site.processEnded = new Date();
                            Debugger.success("Processing completed for site: " + site.formatted_url);
                            if (callback) callback();
                        } else {
                            self.processSite(site, page + 1, callback);
                        }
                    } else if (response.status === 'error') {
                        Debugger.error("Error processing site: " + site.formatted_url);
                        Debugger.error(response);
                        site.status = 'pending';
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    Debugger.error("AJAX error on site: " + site.formatted_url + " | " + textStatus);
                    Debugger.error(jqXHR);
                    site.status = 'pending';
                }
            });
        },

        runTimers: function() {
            var self = this;

            setInterval(function() {
                self.sites.forEach(function(site) {
                    if (!site.processEnded && site.status === 'process') {
                        var endTime = new Date();
                        var timeDiff = endTime - site.processStarted;
                        var formattedTime = new Date(timeDiff).toISOString().substr(11, 8);
                        site.timeElapsed = formattedTime;
                    }
                });
            }, 1000);
        }
    },
    created: function() {
        Debugger.init();
        this.sites.forEach(function(site) {
            Vue.set(site, 'status', 'pending');
            Vue.set(site, 'processEnded', false);
            Vue.set(site, 'processedRecords', 0);
            Vue.set(site, 'timeElapsed', '00:00:00');
            Vue.set(site, 'totalRecords', 0);
        });
    }
});
</script>
