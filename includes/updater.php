<?php
/**
 * Kostenloser GitHub Auto-Updater für URL-Shorter Plugin
 * Basiert auf WordPress Core Update-Mechanismus
 */

if (!defined('ABSPATH')) {
    exit;
}

class URLShorterFreeUpdater {
    private $plugin_basename;
    private $plugin_slug;
    private $version;
    private $github_repo;
    private $plugin_file;

    public function __construct($plugin_file, $github_repo, $version) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        $this->version = $version;
        $this->github_repo = $github_repo;

        add_action('init', array($this, 'init'));
    }

    public function init() {
        // Hook in den WordPress Update-Checker
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        
        // Custom Update-Handler für automatische Updates
        add_filter('upgrader_pre_download', array($this, 'download_package'), 10, 4);
        add_filter('upgrader_source_selection', array($this, 'source_selection'), 10, 4);
        
        // Automatische Updates ermöglichen
        add_filter('auto_update_plugin', array($this, 'enable_auto_update'), 10, 2);
        
        // Admin-Notices für Update-Informationen
        add_action('admin_notices', array($this, 'update_notice'));
        
        // Update-Check bei Admin-Seitenaufruf triggern
        add_action('admin_init', array($this, 'force_update_check'));
        
        // Custom Update-Handling
        add_action('wp_ajax_url_shorter_update', array($this, 'handle_update'));
        
        // Einstellungen registrieren
        add_action('admin_init', array($this, 'register_settings'));
        
        // Debug-Action hinzufügen
        add_action('wp_ajax_url_shorter_debug', array($this, 'debug_update_check'));
    }

    /**
     * Debug-Funktion für Update-Prüfung
     */
    public function debug_update_check() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }

        echo '<div style="background: white; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
        echo '<h2>URL-Shorter Update Debug</h2>';
        
        echo '<h3>Plugin-Informationen:</h3>';
        echo '<p><strong>Plugin-Datei:</strong> ' . esc_html($this->plugin_file) . '</p>';
        echo '<p><strong>Plugin-Basename:</strong> ' . esc_html($this->plugin_basename) . '</p>';
        echo '<p><strong>Plugin-Slug:</strong> ' . esc_html($this->plugin_slug) . '</p>';
        echo '<p><strong>Aktuelle Version:</strong> ' . esc_html($this->version) . '</p>';
        echo '<p><strong>GitHub Repository:</strong> ' . esc_html($this->github_repo) . '</p>';
        
        echo '<h3>GitHub API Test:</h3>';
        $remote_version = $this->get_remote_version();
        
        if ($remote_version) {
            echo '<p style="color: green;"><strong>✅ GitHub API erfolgreich:</strong></p>';
            echo '<p><strong>Neueste Version:</strong> ' . esc_html($remote_version['new_version']) . '</p>';
            echo '<p><strong>Download URL:</strong> ' . esc_html($remote_version['download_url']) . '</p>';
            echo '<p><strong>Details URL:</strong> ' . esc_html($remote_version['details_url']) . '</p>';
            
            $version_compare = version_compare($this->version, $remote_version['new_version'], '<');
            echo '<p><strong>Update verfügbar:</strong> ' . ($version_compare ? 'JA' : 'NEIN') . '</p>';
        } else {
            echo '<p style="color: red;"><strong>❌ GitHub API Fehler</strong></p>';
            echo '<p>Mögliche Ursachen:</p>';
            echo '<ul>';
            echo '<li>Keine Internetverbindung</li>';
            echo '<li>GitHub API nicht erreichbar</li>';
            echo '<li>Repository nicht gefunden</li>';
            echo '<li>Keine Releases im Repository</li>';
            echo '</ul>';
        }
        
        echo '<h3>WordPress Update-System:</h3>';
        $update_plugins = get_site_transient('update_plugins');
        if (isset($update_plugins->response[$this->plugin_basename])) {
            echo '<p style="color: green;"><strong>✅ Plugin ist im WordPress Update-System registriert</strong></p>';
            $plugin_update = $update_plugins->response[$this->plugin_basename];
            echo '<p><strong>Update-Version:</strong> ' . esc_html($plugin_update->new_version) . '</p>';
        } else {
            echo '<p style="color: orange;"><strong>⚠️ Plugin nicht im WordPress Update-System gefunden</strong></p>';
        }
        
        echo '<h3>Auto-Update Einstellungen:</h3>';
        $auto_update_enabled = get_option('url_shorter_auto_update', false);
        echo '<p><strong>Auto-Update aktiviert:</strong> ' . ($auto_update_enabled ? 'JA' : 'NEIN') . '</p>';
        
        echo '</div>';
        wp_die();
    }

    /**
     * Update-Check bei Admin-Aufrufen erzwingen
     */
    public function force_update_check() {
        // Nur bei Plugin-Seiten und nur einmal pro Sitzung
        $current_screen = get_current_screen();
        if ($current_screen && $current_screen->id === 'plugins' && !get_transient('url_shorter_force_checked')) {
            // Update-Check erzwingen durch Löschen der Transients
            delete_site_transient('update_plugins');
            delete_transient('url_shorter_version_check');
            
            // Flag setzen, damit es nicht zu oft passiert
            set_transient('url_shorter_force_checked', true, 300); // 5 Minuten
        }
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Cache für 12 Stunden - aber bei Versionswechseln cache leeren
        $cache_key = 'url_shorter_version_check_' . $this->version;
        $remote_version = get_transient($cache_key);

        if ($remote_version === false) {
            // Alte Cache-Einträge löschen
            delete_transient('url_shorter_version_check');
            
            $remote_version = $this->get_remote_version();
            if ($remote_version) {
                set_transient($cache_key, $remote_version, 12 * HOUR_IN_SECONDS);
            }
        }

        // WICHTIG: Auch wenn keine neuere Version verfügbar ist, Plugin als "checked" markieren
        if ($remote_version) {
            if (version_compare($this->version, $remote_version['new_version'], '<')) {
                // Update verfügbar - in response hinzufügen
                $transient->response[$this->plugin_basename] = (object) array(
                    'slug' => $this->plugin_slug,
                    'plugin' => $this->plugin_basename,
                    'new_version' => $remote_version['new_version'],
                    'url' => $remote_version['details_url'],
                    'package' => $remote_version['download_url']
                );
            } else {
                // Kein Update verfügbar - aber Plugin als "checked" markieren
                if (!isset($transient->no_update)) {
                    $transient->no_update = array();
                }
                $transient->no_update[$this->plugin_basename] = (object) array(
                    'slug' => $this->plugin_slug,
                    'plugin' => $this->plugin_basename,
                    'new_version' => $this->version,
                    'url' => $remote_version['details_url'],
                    'package' => ''
                );
            }
        }

        return $transient;
    }

    public function get_remote_version() {
        // GitHub API für öffentliche Repositories
        $api_url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['tag_name'])) {
            return false;
        }

        return array(
            'new_version' => ltrim($data['tag_name'], 'v'),
            'details_url' => $data['html_url'],
            'download_url' => isset($data['assets'][0]['browser_download_url']) 
                ? $data['assets'][0]['browser_download_url'] 
                : $data['zipball_url'],
            'changelog' => isset($data['body']) ? $data['body'] : 'Siehe GitHub für Details.'
        );
    }

    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information' || $response->slug !== $this->plugin_slug) {
            return $false;
        }

        $remote_version = $this->get_remote_version();

        if (!$remote_version) {
            return $false;
        }

        $response = new stdClass();
        $response->name = 'URL-Shorter';
        $response->slug = $this->plugin_slug;
        $response->plugin_name = 'URL-Shorter';
        $response->version = $remote_version['new_version'];
        $response->author = '<a href="https://www.buchner-leon.de/">Leon Buchner</a>';
        $response->homepage = 'https://www.buchner-leon.de/';
        $response->requires = '5.0';
        $response->tested = '6.3';
        $response->requires_php = '7.4';
        $response->download_link = $remote_version['download_url'];
        
        $response->sections = array(
            'description' => 'Ein Plugin zur Verkürzung von URLs mit Klicktracking und QR-Code Generierung.',
            'installation' => 'Laden Sie das Plugin herunter und installieren Sie es über das WordPress Admin-Panel.',
            'changelog' => $remote_version['changelog'],
            'faq' => 'Bei Fragen besuchen Sie: https://www.buchner-leon.de/'
        );

        $response->banners = array(
            'low' => '',
            'high' => ''
        );

        return $response;
    }

    public function update_notice() {
        $current_screen = get_current_screen();
        if ($current_screen->id !== 'plugins') {
            return;
        }

        $remote_version = get_transient('url_shorter_version_check');
        if (!$remote_version) {
            $remote_version = $this->get_remote_version();
        }

        if ($remote_version && version_compare($this->version, $remote_version['new_version'], '<')) {
            $update_url = wp_nonce_url(
                admin_url('admin-ajax.php?action=url_shorter_update'),
                'url_shorter_update'
            );
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>URL-Shorter Update verfügbar:</strong> 
                    Version <?php echo esc_html($remote_version['new_version']); ?> ist verfügbar. 
                    <em>(Aktuell installiert: <?php echo esc_html($this->version); ?>)</em>
                    <br>
                    <a href="<?php echo esc_url($remote_version['details_url']); ?>" target="_blank">Details anzeigen</a>
                    |
                    <a href="<?php echo esc_url($update_url); ?>" class="button button-primary" 
                       onclick="return confirm('Möchten Sie das Plugin jetzt aktualisieren?');">
                        Jetzt aktualisieren
                    </a>
                </p>
            </div>
            <?php
        }
    }

    public function handle_update() {
        if (!current_user_can('update_plugins')) {
            wp_die('Keine Berechtigung.');
        }

        check_admin_referer('url_shorter_update');

        $remote_version = $this->get_remote_version();
        if (!$remote_version) {
            wp_die('Update-Informationen konnten nicht abgerufen werden.');
        }

        // Automatisches Update durchführen
        $this->perform_update($remote_version);
    }

    /**
     * Custom Download-Handler für GitHub ZIP-Downloads
     */
    public function download_package($reply, $package, $upgrader, $hook_extra = null) {
        // Nur für unser Plugin aktiv werden
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            return $this->download_github_package($package);
        }
        return $reply;
    }

    /**
     * Source-Selection für GitHub ZIP-Struktur
     */
    public function source_selection($source, $remote_source, $upgrader, $hook_extra = null) {
        // Nur für unser Plugin aktiv werden
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            return $this->fix_github_source($source, $remote_source);
        }
        return $source;
    }

    /**
     * GitHub Package herunterladen
     */
    private function download_github_package($package_url) {
        // Temporäres Verzeichnis erstellen
        $temp_file = download_url($package_url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        return $temp_file;
    }

    /**
     * GitHub ZIP-Struktur korrigieren
     */
    private function fix_github_source($source, $remote_source) {
        global $wp_filesystem;

        // GitHub ZIP-Archive haben einen zusätzlichen Ordner mit Repository-Name und Commit-Hash
        $source_dirs = array_keys($wp_filesystem->dirlist($remote_source));
        
        if (count($source_dirs) === 1) {
            $source_dir = trailingslashit($remote_source) . $source_dirs[0];
            
            // Prüfen ob das der GitHub-Ordner ist
            if ($wp_filesystem->is_dir($source_dir)) {
                return $source_dir;
            }
        }

        return $source;
    }

    /**
     * Update durchführen
     */
    private function perform_update($remote_version) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        // Plugin Upgrader initialisieren
        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        
        // Update durchführen
        $result = $upgrader->upgrade($this->plugin_basename);

        if (is_wp_error($result)) {
            wp_die('Update fehlgeschlagen: ' . $result->get_error_message());
        } elseif ($result === false) {
            wp_die('Update fehlgeschlagen: Unbekannter Fehler.');
        } else {
            // Cache leeren
            delete_transient('url_shorter_version_check');
            
            wp_redirect(admin_url('plugins.php?updated=true'));
            exit;
        }
    }

    /**
     * Automatische Updates für dieses Plugin ermöglichen
     */
    public function enable_auto_update($update, $item) {
        if (isset($item->plugin) && $item->plugin === $this->plugin_basename) {
            // Prüfen ob automatische Updates aktiviert sind
            $auto_update_enabled = get_option('url_shorter_auto_update', false);
            return $auto_update_enabled;
        }
        return $update;
    }

    /**
     * Update-Einstellungen registrieren
     */
    public function register_settings() {
        register_setting('url_shorter_settings', 'url_shorter_auto_update');
    }
}
