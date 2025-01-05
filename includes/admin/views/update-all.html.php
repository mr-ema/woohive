<?php
    use WooHive\Config\Constants;
    use WooHive\Utils\Helpers;

    global $title;
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $title; ?></h1>
    <hr class="wp-header-end">

    <?php require 'tabs.html.php'; ?>

    <div id="wmss-update-app" class="wmss-app">
    <div>
        <div class="header">
        <div v-if="status === 'process'" class="processing">
            <h2><?php _e( 'Actualización en progreso', Constants::TEXT_DOMAIN ); ?></h2>
            <p><?php _e( 'La actualización del estado de sincronización puede tardar un poco. Por favor, no cierre su navegador ni actualice esta página hasta que el proceso se haya completado.', Constants::TEXT_DOMAIN ); ?></p>
        </div>

        <div v-if="status === 'completed'" class="completed">
            <h2><?php _e( '¡Actualización completada!', Constants::TEXT_DOMAIN ); ?></h2>
            <p><?php _e( 'La actualización se ha completado con éxito.', Constants::TEXT_DOMAIN ); ?></p>
            <p><a href="<?php echo $urls['report']; ?>" class="button button-primary"><?php _e( 'Ver reporte &raquo;', Constants::TEXT_DOMAIN ); ?></a></p>
        </div>

        <div v-if="status === 'pending'" class="pending">
            <h2><?php _e( 'Reporte de actualización', Constants::TEXT_DOMAIN ); ?></h2>
            <p><?php _e( 'Reporte de actualización obteniendo SKUs y cantidades de stock desde los inventarios secundarios.', Constants::TEXT_DOMAIN ); ?></p>
            <p><button v-on:click.prevent="startProcess" v-if="status === 'pending'" class="button button-primary"><?php _e( 'Iniciar actualización', Constants::TEXT_DOMAIN ); ?></button></p>
        </div>
        </div>

        <div class="sites">
        <div class="site" v-for="site in sites">
            <div class="site-header">
            <div class="title">{{ site.formatted_url }}</div>
            <div class="status" v-bind:class="site.status"><span class="icon"></span></div>
            </div>

            <div class="content">
            <table class="progress">
                <tr>
                <th><?php _e( 'Tiempo transcurrido', Constants::TEXT_DOMAIN ); ?></th>
                <td><span v-if="site.status != 'pending'">{{ site.timeElapsed }}</td>
                </tr>

                <tr>
                <th><?php _e( 'Procesados', Constants::TEXT_DOMAIN ); ?></th>
                <td>{{ site.processedRecords }} / {{ totalRecords }}</td>
                </tr>
            </table>
            </div>
        </div>
        </div>
    </div>
    </div>

    <?php require 'debugger.html.php'; ?>
</div>

<script>
Vue.prototype.GlobalAjax = window["<?php echo Constants::PLUGIN_SLUG; ?>"];

var app = new Vue( {
    el: '#wmss-update-app',
    data: {
    status: 'pending',
    sites: <?php echo json_encode( array_values( Helpers::sites() ) ); ?>,
    totalRecords: 0, // Total number of products
    },
    methods: {
    /**
     * Start syncing process
     */
    startProcess: function() {
        if ( this.status !== 'pending' ) {
        return;
        }

        Vue.set( this, 'status', 'process' );

        this.processSite( 0, 1 );
        this.runTimers();
    },
    /**
     * Complete whole update
     */
    completeUpdate: function() {
        jQuery.ajax( {
        type: 'post',
        url: this.GlobalAjax.ajax_urls.update_all,
        data: {
            complete: '1',
            security: this.GlobalAjax.nonces.update_all,
            action: 'wmss_update_all',
        },
        dataType: 'json',
        beforeSend: function() {
        },
        success: function( response ) {
        },
        error: function( jqXHR, textStatus, errorThrown ) {
            Debugger.error( jqXHR.status + " " + jqXHR.responseText + " " + textStatus + " " + errorThrown );
            console.error( jqXHR, textStatus, errorThrown );
        },
        complete: function() {
        }
        } );
    },
    /**
     * Process single site
     */
    processSite: function( siteIndex, page ) {
        var self = this;

        var site = this.sites[siteIndex];
        site.status = 'process';
        site.processEnded = false;

        if ( ! site.processStarted ) {
        site.processStarted = new Date();
        }

        var limit = <?php echo Helpers::get_batch_size( 'update' ); ?>;
        var data = {
        page: page,
        site_key: site.key,
        limit: limit,
        action: 'wmss_update_all',
        security: this.GlobalAjax.nonces.update_all,
        };

        jQuery.ajax( {
        type: 'post',
        url: this.GlobalAjax.ajax_urls.update_all,
        data: data,
        dataType: 'json',
        beforeSend: function() {
        },
        success: function( response ) {
            if ( response.status === 'processed' ) {
            if ( ! self.totalRecords ) {
                self.totalRecords = response.total;
            }

            site.processedRecords += response.count;

            if ( response.last_page ) {
                site.status = 'completed';
                site.processEnded = new Date();

                if ( ( siteIndex + 1 ) < self.sites.length ) {
                self.processSite( ( siteIndex + 1 ), 1 );
                } else {
                self.status = 'completed';
                self.completeUpdate();
                }
            } else {
                self.processSite( siteIndex, page + 1 );
            }
            } else if ( response.status === 'error' ) {
            Debugger.error( response.errors + "\nAborting..." );
            self.status = 'pending';
            site.status = 'pending';
            } else {
            Debugger.error( 'Invalid response: ' + response );
            }
        },
        error: function( jqXHR, textStatus, errorThrown ) {
            console.log( jqXHR, textStatus, errorThrown );
            Debugger.error( jqXHR.status + " " + jqXHR.responseText + " " + textStatus + " " + errorThrown );
        },
        complete: function() {
        }
        } );
    },
    /**
     * Run timers
     */
    runTimers: function() {
        var self = this;

        setInterval( function() {
        _.each( self.sites, function( site, i ) {
            if ( ! site.processEnded && site.status == 'process' ) {
            var endTime = new Date();
            var timeDiff = endTime - site.processStarted;
            var formattedTime = new Date( timeDiff ).toISOString().substr( 11, 8 );

            site.timeElapsed = formattedTime;
            }
        } );
        }, 1000 );
    },
    },
    created: function() {
    // Initialize values
    _.each( this.sites, function( site ) {
        Vue.set( site, 'status', 'pending' );
        Vue.set( site, 'processEnded', false );
        Vue.set( site, 'processedRecords', 0 );
        Vue.set( site, 'timeElapsed', '00:00:00' );
    } );
    },
    mounted: function() {
    Debugger.init();
    //this.startProcess();
    }
} );
</script>
