<?php
// Datei: includes/shorturl-functions.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Generiert einen zufälligen Shortcode
function urlshorter_generate_shortcode( $length = 6 ) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $short_code = '';
    for ( $i = 0; $i < $length; $i++ ) {
        $short_code .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
    }
    return $short_code;
}

// Erstellt einen neuen Eintrag für eine verkürzte URL
function urlshorter_create_short_url( $long_url, $name = '' ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'short_urls';

    // Prüfen, ob die URL bereits existiert
    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE long_url = %s", $long_url ) );
    if ( $existing ) {
        return $existing->short_code;
    }

    $short_code = urlshorter_generate_shortcode();
    // Sicherstellen, dass der Shortcode eindeutig ist
    while ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE short_code = %s", $short_code ) ) > 0 ) {
        $short_code = urlshorter_generate_shortcode();
    }

    $wpdb->insert(
        $table_name,
        array(
            'long_url'    => esc_url_raw( $long_url ),
            'short_code'  => $short_code,
            'click_count' => 0,
            'name'        => sanitize_text_field( $name )
        ),
        array(
            '%s',
            '%s',
            '%d',
            '%s'
        )
    );

    return $short_code;
}


// Behandelt die Weiterleitung anhand des Shortcodes und erhöht den Klick-Zähler
function urlshorter_redirect() {
    global $wpdb;
    
    // Beispiel: Aufruf http://deinedomain.de/?s=abc123
    if ( isset( $_GET['s'] ) ) {
        $short_code = sanitize_text_field( $_GET['s'] );
        $table_name = $wpdb->prefix . 'short_urls';
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE short_code = %s", $short_code ) );

        if ( $entry ) {
            // Klickzähler erhöhen
            $wpdb->update(
                $table_name,
                array( 'click_count' => $entry->click_count + 1 ),
                array( 'id' => $entry->id ),
                array( '%d' ),
                array( '%d' )
            );

            wp_redirect( esc_url_raw( $entry->long_url ) );
            exit;
        }
    }
}
