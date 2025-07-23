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
    global $wpdb;
    $table_name = $wpdb->prefix . 'short_urls';
    
    // Behandle URL-Erstellung direkt hier
    if ( isset($_POST['long_url']) && isset($_POST['_wpnonce']) && wp_verify_nonce( $_POST['_wpnonce'], 'create_url_nonce' ) ) {
        $long_url = esc_url_raw( $_POST['long_url'] );
        $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        $short_code = urlshorter_create_short_url( $long_url, $name );
        $success_message = 'URL wurde verkürzt: <strong>' . esc_html( $short_code ) . '</strong>';
    }
    
    // Behandle URL-Aktualisierung
    if ( isset($_POST['update_url']) && isset($_POST['edit_id']) ) {
        $edit_id = intval($_POST['edit_id']);
        if ( wp_verify_nonce( $_POST['_wpnonce'], 'update_url_nonce_' . $edit_id ) ) {
            $new_long_url = esc_url_raw( $_POST['long_url'] );
            $new_name     = sanitize_text_field( $_POST['name'] );
            $new_short_code = sanitize_text_field( $_POST['short_code'] );

            // Prüfen, ob der neue Short Code bereits existiert (aber nicht der aktuelle ist)
            $current_entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $edit_id ) );
            
            if ( $new_short_code !== $current_entry->short_code ) {
                $existing_entry = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table_name WHERE short_code = %s", $new_short_code ) );
                if ( $existing_entry ) {
                    $success_message = 'Fehler: Der Short Code "' . $new_short_code . '" ist bereits vergeben. Bitte wählen Sie einen anderen Short Code.';
                } else {
                    // Short Code ändern zusammen mit anderen Daten
                    $updated = $wpdb->update(
                        $table_name,
                        array(
                            'long_url'   => $new_long_url,
                            'name'       => $new_name,
                            'short_code' => $new_short_code,
                        ),
                        array( 'id' => $edit_id ),
                        array( '%s', '%s', '%s' ),
                        array( '%d' )
                    );
                    
                    if ( false !== $updated ) {
                        $success_message = 'Eintrag mit neuem Short Code "' . $new_short_code . '" erfolgreich aktualisiert.';
                        $edit_id = null; // Bearbeitungsmodus beenden
                    } else {
                        $success_message = 'Fehler: Short Code-Änderung fehlgeschlagen.';
                    }
                }
            } else {
                // Normale Aktualisierung ohne Short Code-Änderung
                $updated = $wpdb->update(
                    $table_name,
                    array(
                        'long_url' => $new_long_url,
                        'name'     => $new_name,
                    ),
                    array( 'id' => $edit_id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );

                if ( false !== $updated ) {
                    $success_message = 'Eintrag erfolgreich aktualisiert.';
                    $edit_id = null; // Bearbeitungsmodus beenden
                } else {
                    $success_message = 'Fehler: Aktualisierung fehlgeschlagen.';
                }
            }
        } else {
            $success_message = 'Fehler: Sicherheitsüberprüfung fehlgeschlagen.';
            $edit_id = null;
        }
    }
    // Behandle Klicks zurücksetzen
    if ( isset($_POST['reset_clicks']) && isset($_POST['reset_id']) ) {
        $reset_id = intval($_POST['reset_id']);
        if ( wp_verify_nonce( $_POST['_wpnonce'], 'reset_clicks_' . $reset_id ) ) {
            $updated = $wpdb->update(
                $table_name,
                array( 'click_count' => 0 ),
                array( 'id' => $reset_id ),
                array( '%d' ),
                array( '%d' )
            );
            
            if ( $updated !== false ) {
                $success_message = 'Klicks wurden erfolgreich zurückgesetzt.';
            } else {
                $success_message = 'Fehler: Klicks konnten nicht zurückgesetzt werden.';
            }
        } else {
            $success_message = 'Fehler: Sicherheitsüberprüfung fehlgeschlagen.';
        }
    }
    // Behandle URL-Löschung
    if ( isset($_POST['delete_url']) && isset($_POST['delete_id']) ) {
        $delete_id = intval($_POST['delete_id']);
        if ( wp_verify_nonce( $_POST['_wpnonce'], 'delete_url_' . $delete_id ) ) {
            $deleted = $wpdb->delete(
                $table_name,
                array( 'id' => $delete_id ),
                array( '%d' )
            );
            
            if ( $deleted !== false ) {
                $success_message = 'URL-Eintrag wurde erfolgreich gelöscht.';
            } else {
                $success_message = 'Fehler: URL-Eintrag konnte nicht gelöscht werden.';
            }
        } else {
            $success_message = 'Fehler: Sicherheitsüberprüfung fehlgeschlagen.';
        }
    }
    // Behandle Bearbeitungsanfrage
    elseif ( isset($_POST['start_edit']) && isset($_POST['edit_id']) ) {
        if ( wp_verify_nonce( $_POST['_wpnonce'], 'edit_url_' . intval($_POST['edit_id']) ) ) {
            $edit_id = intval($_POST['edit_id']);
        } else {
            echo '<div class="error"><p>Sicherheitsüberprüfung fehlgeschlagen.</p></div>';
            $edit_id = null;
        }
    } else {
        $edit_id = null;
    }
    
    // Debug: Zeige alle GET-Parameter an
    if ( isset($_GET['action']) ) {
        error_log( 'URL-Shorter Debug: Action = ' . $_GET['action'] . ', ID = ' . ( isset($_GET['id']) ? $_GET['id'] : 'nicht gesetzt' ) );
    }
    
    // QR-Code Download
    if ( isset($_GET['action']) && $_GET['action'] === 'download_qrcode' && isset($_GET['id']) ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }
        urlshorter_download_qrcode();
        return;
    }
    
    $urls = $wpdb->get_results( "SELECT * FROM $table_name" );
    ?>
    <div class="wrap">
        <h1>URL-Shorter Verwaltung</h1>
        
        <!-- Erfolgsmeldung für neue URL -->
        <?php if ( isset($success_message) ): ?>
            <div class="updated"><p><?php echo $success_message; ?></p></div>
        <?php endif; ?>
        
        <!-- Erfolgsmeldung aus GET-Parameter (Fallback) -->
        <?php if ( isset($_GET['created']) ): ?>
            <div class="updated"><p>URL wurde verkürzt: <strong><?php echo esc_html( urldecode($_GET['created']) ); ?></strong></p></div>
        <?php endif; ?>
        
        <!-- Bearbeitungsbereich falls edit_id gesetzt ist -->
        <?php if ( $edit_id ): ?>
            <?php urlshorter_show_edit_form( $edit_id ); ?>
        <?php endif; ?>
        
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
                    <td data-label="Short Code"><?php echo esc_html( $url->short_code ); ?></td>
                    <td data-label="Name"><?php echo esc_html( $url->name ); ?></td>
                    <td data-label="Original URL"><?php echo esc_html( $url->long_url ); ?></td>
                    <td data-label="Klicks"><?php echo esc_html( $url->click_count ); ?></td>
                    <td data-label="QR-Code">
                        <a href="<?php echo admin_url( 'admin.php?page=url-shorter&action=download_qrcode&id=' . $url->id ); ?>" class="button">QR-Code herunterladen</a>
                    </td>
                    <td data-label="Aktionen">
                        <form method="post" action="" style="display: inline;">
                            <?php wp_nonce_field( 'edit_url_' . $url->id ); ?>
                            <input type="hidden" name="edit_id" value="<?php echo esc_attr( $url->id ); ?>">
                            <input type="submit" name="start_edit" value="Bearbeiten" class="button">
                        </form>
                        <button type="button" class="button" onclick="copyToClipboard('<?php echo home_url( '/?s=' . $url->short_code ); ?>')">Link kopieren</button>
                        <form method="post" action="" style="display: inline;">
                            <?php wp_nonce_field( 'reset_clicks_' . $url->id ); ?>
                            <input type="hidden" name="reset_id" value="<?php echo esc_attr( $url->id ); ?>">
                            <input type="submit" name="reset_clicks" value="Klicks zurücksetzen" class="button" onclick="return confirm('Möchten Sie die Klicks wirklich zurücksetzen?');">
                        </form>
                        <form method="post" action="" style="display: inline;">
                            <?php wp_nonce_field( 'delete_url_' . $url->id ); ?>
                            <input type="hidden" name="delete_id" value="<?php echo esc_attr( $url->id ); ?>">
                            <input type="submit" name="delete_url" value="Löschen" class="button button-link-delete" onclick="return confirm('Sind Sie sicher, dass Sie diesen URL-Eintrag löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden!');">
                        </form>
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
    
    <script>
    function copyToClipboard(text) {
        // Erstelle ein temporäres Input-Element
        var tempInput = document.createElement("input");
        tempInput.style = "position: absolute; left: -1000px; top: -1000px";
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
        
        // Zeige eine kurze Bestätigung
        alert("Link wurde in die Zwischenablage kopiert: " + text);
    }
    </script>
    
    <style>
    /* Responsive Styles für mobile Geräte */
    @media screen and (max-width: 782px) {
        /* Tabelle für mobile Geräte optimieren */
        .wp-list-table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
            border: none;
        }
        
        .wp-list-table thead,
        .wp-list-table tbody,
        .wp-list-table th,
        .wp-list-table td,
        .wp-list-table tr {
            display: block;
        }
        
        .wp-list-table thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        
        .wp-list-table tr {
            border: 1px solid #ccc;
            margin-bottom: 10px;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .wp-list-table td {
            border: none;
            padding: 8px 0;
            position: relative;
            padding-left: 35%;
            white-space: normal;
            text-align: left;
        }
        
        .wp-list-table td:before {
            content: attr(data-label);
            position: absolute;
            left: 6px;
            width: 30%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: bold;
            color: #333;
        }
        
        /* Buttons für mobile optimieren */
        .wp-list-table td form {
            display: block !important;
            margin: 5px 0;
        }
        
        .wp-list-table .button {
            display: block;
            width: 100%;
            margin: 3px 0;
            text-align: center;
            padding: 8px 12px;
            box-sizing: border-box;
        }
        
        /* Form-Table für mobile optimieren */
        .form-table th,
        .form-table td {
            display: block;
            width: 100%;
            padding: 10px 0;
        }
        
        .form-table th {
            border-bottom: none;
            padding-bottom: 5px;
        }
        
        .form-table .regular-text {
            width: 100%;
            max-width: none;
        }
        
        /* Debug-Box für mobile anpassen */
        .urlshorter-debug-box {
            padding: 15px;
            margin: 15px 0;
            font-size: 14px;
        }
        
        /* Edit-Form für mobile optimieren */
        .urlshorter-edit-form {
            padding: 15px;
            margin: 15px 0;
        }
        
        .urlshorter-edit-form .button {
            display: block;
            width: 100%;
            margin: 5px 0;
            text-align: center;
        }
    }
    
    @media screen and (max-width: 480px) {
        /* Sehr kleine Bildschirme */
        .wp-list-table td {
            padding-left: 0;
            text-align: center;
        }
        
        .wp-list-table td:before {
            position: relative;
            display: block;
            width: 100%;
            text-align: center;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 5px;
        }
        
        .wrap h1 {
            font-size: 1.5em;
            text-align: center;
        }
        
        .wrap h2 {
            font-size: 1.3em;
            text-align: center;
        }
    }
    
    /* Lösch-Button Styling */
    .button-link-delete {
        background-color: #d63638 !important;
        border-color: #d63638 !important;
        color: #fff !important;
    }
    
    .button-link-delete:hover {
        background-color: #b32d2e !important;
        border-color: #b32d2e !important;
        color: #fff !important;
    }
    
    .button-link-delete:focus {
        background-color: #b32d2e !important;
        border-color: #b32d2e !important;
        color: #fff !important;
        box-shadow: 0 0 0 1px #fff, 0 0 0 3px #d63638;
    }
    </style>
    <?php
}


// Neue Funktion zum Anzeigen des Bearbeitungsformulars
function urlshorter_show_edit_form( $id ) {
    global $wpdb;
    
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $table_name = $wpdb->prefix . 'short_urls';
    $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );

    if ( ! $entry ) {
        echo '<div class="error"><p>Eintrag nicht gefunden.</p></div>';
        return;
    }

    ?>
    <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 20px 0;" class="urlshorter-edit-form">
        <h2>URL bearbeiten</h2>
        
        <!-- Debug-Informationen -->
        <div style="background: #fffbf0; border: 1px solid #e6db55; padding: 10px; margin: 10px 0;" class="urlshorter-debug-box">
            <strong>Debug-Info:</strong><br>
            ID: <?php echo esc_html( $entry->id ); ?><br>
            Name: <?php echo esc_html( $entry->name ); ?><br>
            URL: <?php echo esc_html( $entry->long_url ); ?><br>
            Short Code: <?php echo esc_html( $entry->short_code ); ?>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'update_url_nonce_' . $id ); ?>
            <input type="hidden" name="edit_id" value="<?php echo esc_attr( $id ); ?>">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="short_code">Short Code</label></th>
                    <td>
                        <input name="short_code" type="text" id="short_code" value="<?php echo esc_attr( $entry->short_code ); ?>" class="regular-text" required>
                        <p class="description">Der Short Code für die kurze URL kann geändert werden, falls er noch nicht vergeben ist. Nur Buchstaben, Zahlen und Bindestriche erlaubt.</p>
                    </td>
                </tr>
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
            <a href="<?php echo admin_url( 'admin.php?page=url-shorter' ); ?>" class="button">Abbrechen</a>
        </form>
    </div>
    <?php
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


// Funktion zum Zurücksetzen der Klicks
function urlshorter_reset_clicks() {
    global $wpdb;
    $id = intval($_GET['id']);
    
    // Berechtigung prüfen
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }
    
    $table_name = $wpdb->prefix . 'short_urls';
    
    // Klicks auf 0 zurücksetzen
    $updated = $wpdb->update(
        $table_name,
        array( 'click_count' => 0 ),
        array( 'id' => $id ),
        array( '%d' ),
        array( '%d' )
    );
    
    if ( $updated !== false ) {
        // Erfolgsmeldung anzeigen und zurück zur Hauptseite
        add_action( 'admin_notices', function() {
            echo '<div class="updated"><p>Klicks wurden erfolgreich zurückgesetzt.</p></div>';
        });
    } else {
        // Fehlermeldung anzeigen
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>Fehler beim Zurücksetzen der Klicks.</p></div>';
        });
    }
    
    // Zurück zur Hauptseite leiten
    wp_redirect( admin_url( 'admin.php?page=url-shorter' ) );
    exit;
}


function urlshorter_edit_url() {
    global $wpdb;
    $id = intval($_GET['id']);
    
    // Debug-Output
    error_log( 'URL-Shorter Debug: Edit-Funktion aufgerufen für ID: ' . $id );
    
    // Berechtigung prüfen statt Nonce für GET-Request
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }
    
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
        $new_id       = intval( $_POST['entry_id'] );

        // Prüfen, ob die neue ID bereits existiert (aber nicht die aktuelle ID ist)
        if ( $new_id !== $id ) {
            $existing_entry = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table_name WHERE id = %d", $new_id ) );
            if ( $existing_entry ) {
                echo '<div class="error"><p>Die ID ' . $new_id . ' ist bereits vergeben. Bitte wählen Sie eine andere ID.</p></div>';
            } else {
                // ID ändern: Zuerst neuen Eintrag erstellen, dann alten löschen
                $wpdb->query( $wpdb->prepare( "SET foreign_key_checks = 0" ) );
                $inserted = $wpdb->insert(
                    $table_name,
                    array(
                        'id'       => $new_id,
                        'long_url' => $new_long_url,
                        'name'     => $new_name,
                        'short_code' => $entry->short_code,
                        'click_count' => $entry->click_count,
                        'created_at' => $entry->created_at
                    ),
                    array( '%d', '%s', '%s', '%s', '%d', '%s' )
                );
                
                if ( $inserted ) {
                    $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );
                    $wpdb->query( $wpdb->prepare( "SET foreign_key_checks = 1" ) );
                    echo '<div class="updated"><p>Eintrag mit neuer ID ' . $new_id . ' aktualisiert.</p></div>';
                    $id = $new_id; // ID für die weitere Verwendung aktualisieren
                    $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
                } else {
                    $wpdb->query( $wpdb->prepare( "SET foreign_key_checks = 1" ) );
                    echo '<div class="error"><p>ID-Änderung fehlgeschlagen.</p></div>';
                }
            }
        } else {
            // Normale Aktualisierung ohne ID-Änderung
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
    }
    ?>
    <div class="wrap">
        <h1>URL bearbeiten</h1>
        
        <!-- Debug-Informationen -->
        <div style="background: #fffbf0; border: 1px solid #e6db55; padding: 10px; margin: 10px 0;">
            <strong>Debug-Info:</strong><br>
            ID: <?php echo esc_html( $entry->id ); ?><br>
            Name: <?php echo esc_html( $entry->name ); ?><br>
            URL: <?php echo esc_html( $entry->long_url ); ?><br>
            Short Code: <?php echo esc_html( $entry->short_code ); ?>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'update_url_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="entry_id">ID</label></th>
                    <td>
                        <input name="entry_id" type="number" id="entry_id" value="<?php echo esc_attr( $entry->id ); ?>" class="regular-text" min="1" required>
                        <p class="description">Die eindeutige ID des Eintrags kann geändert werden, falls sie noch nicht vergeben ist.</p>
                    </td>
                </tr>
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



