<?php

namespace WooHive\Internal\Demons;

/** Prevenir el acceso directo al script. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Init {

    public static function start(): void {
        Sync_Stock::init();
        Sync_Price::init();
        Sync_Variation::init();
        Sync_Product::init();
    }
}
