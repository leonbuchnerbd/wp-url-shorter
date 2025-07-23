<?php
/**
 * Plugin Name: URL-Shorter
 * Plugin URI: https://www.buchner-leon.de/
 * Description: Ein Plugin zur Verkürzung von URLs mit Klicktracking und QR-Code Generierung.
 * Version: 3.0
 * Author: Leon Buchner
 * Author URI: https://www.buchner-leon.de/
 * Text Domain: url-shorter
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direkter Aufruf verhindern
}

// Konstanten definieren
define( 'URL_SHORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'URL_SHORTER_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once URL_SHORTER_PATH . 'includes/database-functions.php';
require_once URL_SHORTER_PATH . 'includes/shorturl-functions.php';
require_once URL_SHORTER_PATH . 'includes/helper-functions.php';
require_once URL_SHORTER_PATH . 'admin/admin-functions.php';

// Aktivierungshook: erstellt die Datenbanktabelle
register_activation_hook( __FILE__, 'urlshorter_activate' );

// Redirect-Handler für Kurz-URLs
add_action( 'template_redirect', 'urlshorter_redirect' );

// Admin-Menü hinzufügen
add_action( 'admin_menu', 'urlshorter_admin_menu' );
