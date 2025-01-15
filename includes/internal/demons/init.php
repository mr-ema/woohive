<?php

namespace WooHive\Internal\Demons;


/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Init {

    public static function start(): void {
        Sync_Simple_Data::init();
        Sync_Variations::init();
        Sync_Stock::init();
    }
}
