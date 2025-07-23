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
        
        // Admin-Notices für Update-Informationen
        add_action('admin_notices', array($this, 'update_notice'));
        
        // Custom Update-Handling
        add_action('wp_ajax_url_shorter_update', array($this, 'handle_update'));
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Cache für 12 Stunden
        $cache_key = 'url_shorter_version_check';
        $remote_version = get_transient($cache_key);

        if ($remote_version === false) {
            $remote_version = $this->get_remote_version();
            if ($remote_version) {
                set_transient($cache_key, $remote_version, 12 * HOUR_IN_SECONDS);
            }
        }

        if ($remote_version && version_compare($this->version, $remote_version['new_version'], '<')) {
            $transient->response[$this->plugin_basename] = (object) array(
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_basename,
                'new_version' => $remote_version['new_version'],
                'url' => $remote_version['details_url'],
                'package' => $remote_version['download_url']
            );
        }

        return $transient;
    }

    private function get_remote_version() {
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
            'download_url' => $data['zipball_url'],
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
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>URL-Shorter Update verfügbar:</strong> 
                    Version <?php echo esc_html($remote_version['new_version']); ?> ist verfügbar. 
                    <a href="<?php echo esc_url($remote_version['details_url']); ?>" target="_blank">Details anzeigen</a>
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

        // Download und Installation hier implementieren
        // Für einfacheren Ansatz: Weiterleitung zur manuellen Installation
        wp_redirect(admin_url('plugin-install.php?tab=upload'));
        exit;
    }
}
