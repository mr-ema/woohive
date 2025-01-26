<?php

namespace WooHive\Internal\Demons;

/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Init {

    public static function start(): void {
        Sync_Product::init();
        Sync_Stock::init();
        Sync_Variation::init();
    }
}
