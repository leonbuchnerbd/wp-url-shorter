<?php
// Datei: admin/admin-functions.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Fügt das Admin-Menü hinzu
function urlshorter_admin_menu() {
    add_menu_page(
        'URL-Shorter Verwaltung',
        'URL-Shorter',
        'manage_options',
        'url-shorter',
        'urlshorter_admin_page'
    );
}

// Anzeige der Admin-Seite
function urlshorter_admin_page() {
    if ( isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) ) {
        urlshorter_edit_url();
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'short_urls';
    $urls = $wpdb->get_results( "SELECT * FROM $table_name" );
    ?>
    <div class="wrap">
        <h1>URL-Shorter Verwaltung</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Short Code</th>
                    <th>Name</th>
                    <th>Original URL</th>
                    <th>Klicks</th>
                    <th>QR-Code</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $urls as $url ) : ?>
                <tr>
                    <td><?php echo esc_html( $url->short_code ); ?></td>
                    <td><?php echo esc_html( $url->name ); ?></td>
                    <td><?php echo esc_html( $url->long_url ); ?></td>
                    <td><?php echo esc_html( $url->click_count ); ?></td>
                    <td>
                        <a href="<?php echo admin_url( 'admin.php?page=url-shorter&action=download_qrcode&id=' . $url->id ); ?>" class="button">QR-Code herunterladen</a>
                    </td>
                    <td>
                        <a href="<?php echo admin_url( 'admin.php?page=url-shorter&action=edit&id=' . $url->id ); ?>" class="button">Bearbeiten</a>
                        <a href="<?php echo admin_url( 'admin.php?page=url-shorter&action=reset_clicks&id=' . $url->id ); ?>" class="button">Klicks zurücksetzen</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <hr>
        <h2>Neue URL verkürzen</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'create_url_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="name">Name / Beschriftung</label></th>
                    <td><input name="name" type="text" id="name" value="" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="long_url">Lange URL</label></th>
                    <td><input name="long_url" type="url" id="long_url" value="" class="regular-text" required></td>
                </tr>
            </table>
            <?php submit_button( 'URL verkürzen' ); ?>
        </form>
    </div>
    <?php
}


// Formularverarbeitung: Neue URL anlegen oder bestehende bearbeiten
add_action( 'admin_init', 'urlshorter_handle_form_submission' );
function urlshorter_handle_form_submission() {
    if ( isset( $_POST['long_url'] ) && check_admin_referer( 'create_url_nonce' ) ) {
        $long_url = esc_url_raw( $_POST['long_url'] );
        $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $short_code = urlshorter_create_short_url( $long_url, $name );
        add_action( 'admin_notices', function() use ( $short_code ) {
            echo '<div class="updated"><p>URL wurde verkürzt: <strong>' . esc_html( $short_code ) . '</strong></p></div>';
        });
    }

    // Hier könntest du auch die Bearbeitung vorhandener URLs behandeln
}

// QR-Code Download Action
if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_qrcode' && isset( $_GET['id'] ) ) {
    urlshorter_download_qrcode();
}

// Funktion, die den QR-Code generiert und zum Download anbietet
function urlshorter_download_qrcode() {
    global $wpdb;
    $id = intval( $_GET['id'] );
    $table_name = $wpdb->prefix . 'short_urls';
    $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );

    if ( $entry ) {
        require_once URL_SHORTER_PATH . 'phpqrcode/qrlib.php';
        // Erstelle die vollständige Short-URL mit der Domain
        $short_url = home_url( '/?s=' . $entry->short_code );
        
        header( 'Content-Type: image/png' );
        header( 'Content-Disposition: attachment; filename="qrcode_' . $id . '.png"' );
        // Generiere den QR-Code basierend auf der Short-URL
        QRcode::png( $short_url, false, QR_ECLEVEL_H, 8 );
        exit;
    }
}


function urlshorter_edit_url() {
    global $wpdb;
    $id = intval($_GET['id']);
    $table_name = $wpdb->prefix . 'short_urls';
    $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );

    if ( ! $entry ) {
        echo '<div class="error"><p>Eintrag nicht gefunden.</p></div>';
        return;
    }

    // Formularverarbeitung: Daten aktualisieren
    if ( isset( $_POST['update_url'] ) ) {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update_url_nonce' ) ) {
            wp_die( 'Sicherheitsüberprüfung fehlgeschlagen.' );
        }
        $new_long_url = esc_url_raw( $_POST['long_url'] );
        $new_name     = sanitize_text_field( $_POST['name'] );

        $updated = $wpdb->update(
            $table_name,
            array(
                'long_url' => $new_long_url,
                'name'     => $new_name,
            ),
            array( 'id' => $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false !== $updated ) {
            echo '<div class="updated"><p>Eintrag aktualisiert.</p></div>';
            // Aktualisierte Werte neu laden
            $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
        } else {
            echo '<div class="error"><p>Aktualisierung fehlgeschlagen.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>URL bearbeiten</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'update_url_nonce' ); ?>
            <input type="hidden" name="id" value="<?php echo esc_attr( $entry->id ); ?>">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="name">Name / Beschriftung</label></th>
                    <td>
                        <input name="name" type="text" id="name" value="<?php echo esc_attr( $entry->name ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="long_url">Lange URL</label></th>
                    <td>
                        <input name="long_url" type="url" id="long_url" value="<?php echo esc_url( $entry->long_url ); ?>" class="regular-text" required>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Eintrag aktualisieren', 'primary', 'update_url' ); ?>
        </form>
        <p><a href="<?php echo admin_url( 'admin.php?page=url-shorter' ); ?>">&laquo; Zurück zur Übersicht</a></p>
    </div>
    <?php
}



