<?php
/**
 * Zentrale Versionsverwaltung für URL-Shorter Plugin
 * 
 * Hier wird die Version nur einmal definiert und von allen anderen Dateien verwendet.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direkter Aufruf verhindern
}

// Plugin Version - nur hier ändern!
define( 'URL_SHORTER_VERSION', '3.8' );

/**
 * Hilfsfunktion um die aktuelle Plugin-Version zu erhalten
 * 
 * @return string Die aktuelle Plugin-Version
 */
function urlshorter_get_version() {
    return URL_SHORTER_VERSION;
}
