<?php
// Datei: includes/database-functions.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function urlshorter_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'short_urls';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        long_url text NOT NULL,
        short_code varchar(100) NOT NULL,
        click_count mediumint(9) DEFAULT 0 NOT NULL,
        name varchar(255) DEFAULT '' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY short_code (short_code)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

