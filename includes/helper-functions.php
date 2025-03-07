<?php
// Datei: includes/helper-functions.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Beispiel-Hilfsfunktion: Gibt die Plugin-URL zurück
function urlshorter_get_plugin_url() {
    return plugin_dir_url( __FILE__ );
}
