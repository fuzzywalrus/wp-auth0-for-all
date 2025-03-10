<?php
/**
 * Plugin Name: Auth0 for All
 * Description: Custom Auth0 integration that works alongside Login by Auth0 plugin, forcing login for front-end pages without creating WordPress users. Uses client ID and domain declared in the plugin.
 * Version: 1.0
 * Author: Greg Gant
 * Author URI: https://www.greggant.com
 * Plugin URI: https://github.com/fuzzywalrus/wp-auth0-for-all
 * Text Domain: auth0-for-all
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Simple_Auth0_Integration {
    /**
     * The single instance of this class
     */
    private static $instance = null;

    /**
     * Plugin settings
     */
    private $settings = [];

    /**
     * Get the single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load settings
        $this->settings = get_option('simple_auth0_settings', [
            'use_auth0_settings' => 'yes',
            'client_id' => '',
            'domain' => '',
            'bypass_for_logged_in' => 'yes',
            'excluded_paths' => []
        ]);

        // Initialize the plugin
        add_action('plugins_loaded', [$this, 'init']);
        
        // Admin settings
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_settings_page']);
            add_action('admin_init', [$this, 'register_settings']);
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Do nothing if Auth0 should be disabled
        if (defined('DISABLE_AUTH0') && DISABLE_AUTH0) {
            return;
        }

        // Start session
        add_action('init', [$this, 'start_auth0_session'], 1);
        
        // These actions should not run in admin area
        if (!is_admin() && !wp_doing_ajax()) {
            // Handle Auth0 callback - this must run early
            add_action('init', [$this, 'handle_auth0_callback'], 5);
            
            // Add login button
            add_action('template_redirect', [$this, 'add_auth0_login_button'], 3);
            
            // Force login for front-end pages
            add_action('template_redirect', [$this, 'force_auth0_login'], 10);
            
            // Logout function
            add_action('template_redirect', [$this, 'auth0_logout'], 5);
            
            // Add logout link to menu
            add_filter('wp_nav_menu_items', [$this, 'add_auth0_logout_link'], 10, 2);
        }
    }

    /**
     * Start session if not already started
     */
    public function start_auth0_session() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Should we bypass Auth0 for this request?
     */
    private function should_bypass_auth0() {
        // Skip for admin URLs
        if (
            is_admin() || 
            strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false ||
            strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false
        ) {
            return true;
        }
        
        // Skip for login page and Auth0 callback
        if (
            is_page('login') || 
            isset($_GET['auth0']) ||
            isset($_GET['auth0_login']) ||
            isset($_GET['code']) ||
            isset($_GET['callback']) ||
            (defined('DOING_AJAX') && DOING_AJAX) ||
            wp_doing_ajax()
        ) {
            return true;
        }

        // Skip for WordPress logged-in users if that setting is enabled
        if (
            isset($this->settings['bypass_for_logged_in']) && 
            $this->settings['bypass_for_logged_in'] === 'yes' && 
            is_user_logged_in()
        ) {
            return true;
        }

        // Skip if we already have an Auth0 session
        if (isset($_SESSION['auth0_user']) && $_SESSION['auth0_user']['logged_in'] === true) {
            // Optionally check session age here if needed
            return true;
        }

        // Check excluded paths
        if (!empty($this->settings['excluded_paths'])) {
            $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            $excluded_paths = array_map('trim', explode("\n", $this->settings['excluded_paths']));
            
            foreach ($excluded_paths as $excluded_path) {
                if (empty($excluded_path)) continue;
                
                // Check for exact match or wildcard match
                if ($current_path === $excluded_path || 
                    (strpos($excluded_path, '*') !== false && 
                     fnmatch($excluded_path, $current_path))) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Force Auth0 login for front-end pages
     */
    public function force_auth0_login() {
        // Exit early if this is a login-related URL or admin area
        if (
            is_admin() || 
            strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false ||
            strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false ||
            isset($_GET['auth0_login']) || 
            isset($_GET['callback']) || 
            isset($_GET['code'])
        ) {
            return;
        }
        
        // Check if we should bypass Auth0 for this request
        if ($this->should_bypass_auth0()) {
            return;
        }
        
        // We need to redirect to Auth0 login
        $current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $encoded_url = urlencode($current_url);
        
        // Redirect to our login handler
        wp_redirect(home_url('/?auth0_login=1&redirect_to=' . $encoded_url));
        exit;
    }

    /**
     * Handle Auth0 callback
     */
    public function handle_auth0_callback() {
        // Don't run this in admin areas
        if (is_admin() || strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false) {
            return;
        }
        
        // Check for callback in URL (this is the return from Auth0)
        if (isset($_GET['callback']) && $_GET['callback'] == '1') {
            // Process Auth0 code exchange
            if (isset($_GET['code']) && isset($_GET['state'])) {
                // Set a session variable to track Auth0 login
                $_SESSION['auth0_user'] = [
                    'logged_in' => true,
                    'time' => time()
                ];
                
                // Get the redirect URL from state parameter or go to homepage
                $redirect_url = isset($_GET['state']) && $_GET['state'] !== 'RANDOM_STATE' 
                    ? urldecode($_GET['state']) 
                    : home_url();
                
                // Clear any auth0 parameters from the URL before redirecting
                wp_redirect($redirect_url);
                exit;
            }
        }
    }

    /**
     * Add login button to page
     * You can customize this as needed.
     */
    public function add_auth0_login_button() {
        if (isset($_GET['auth0_login'])) {
            // Get the redirect_to parameter (if any)
            $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : home_url();
            
            // Get Auth0 settings either from the Login by Auth0 plugin or our settings
            $client_id = $this->get_auth0_client_id();
            $domain = $this->get_auth0_domain();
            
            if (empty($client_id) || empty($domain)) {
                wp_die(__('Auth0 is not properly configured. Please check the plugin settings.', 'simple-auth0'));
            }
            
            // Make sure we have a properly structured callback URL
            $callback_url = add_query_arg(['callback' => '1'], home_url('/'));
            
            // Construct the Auth0 login URL
            $auth0_login_url = "https://{$domain}/authorize?" . http_build_query([
                'client_id' => $client_id,
                'redirect_uri' => $callback_url,
                'response_type' => 'code',
                'scope' => 'openid profile email',
                'state' => $redirect_to
            ]);
            
            echo '<div style="text-align:center; padding:50px;">';
            echo '<h2>' . __('Login Required', 'simple-auth0') . '</h2>';
            echo '<p>' . __('Please login to access this content.', 'simple-auth0') . '</p>';
            echo '<a href="' . esc_url($auth0_login_url) . '" style="display:inline-block; background:rgb(92, 45, 131); color:white; padding:10px 20px; text-decoration:none; border-radius:4px;">' . __('Login', 'simple-auth0') . '</a>';
            echo '</div>';
            exit;
        }
    }

    /**
     * Logout function
     */
    public function auth0_logout() {
        if (isset($_GET['auth0_logout'])) {
            // Clear Auth0 session
            if (isset($_SESSION['auth0_user'])) {
                unset($_SESSION['auth0_user']);
                // For complete session cleanup if needed
                //session_destroy();
                //session_start();
            }
            
            // Get Auth0 settings
            $client_id = $this->get_auth0_client_id();
            $domain = $this->get_auth0_domain();
            
            if (empty($client_id) || empty($domain)) {
                wp_redirect(home_url());
                exit;
            }
            
            // Ensure we have a properly formatted return URL
            $return_url = home_url('/');
            
            // Construct the Auth0 logout URL
            $auth0_logout_url = "https://{$domain}/v2/logout?" . http_build_query([
                'client_id' => $client_id,
                'returnTo' => $return_url
            ]);
            
            wp_redirect($auth0_logout_url);
            exit;
        }
    }

    /**
     * Add logout link to the site menu
     */
    public function add_auth0_logout_link($items, $args) {
        if (isset($_SESSION['auth0_user']) && isset($args->theme_location) && $args->theme_location == 'primary') {
            $items .= '<li><a href="' . home_url('/?auth0_logout=1') . '">' . __('Logout', 'simple-auth0') . '</a></li>';
        }
        return $items;
    }

    /**
     * Get Auth0 client ID from Login by Auth0 plugin or our settings
     */
    private function get_auth0_client_id() {
        if (isset($this->settings['use_auth0_settings']) && $this->settings['use_auth0_settings'] === 'yes') {
            // Try to get from Login by Auth0 plugin
            $auth0_options = get_option('wp_auth0_settings');
            if (!empty($auth0_options) && !empty($auth0_options['client_id'])) {
                return $auth0_options['client_id'];
            }
        }
        
        // Fall back to our own settings
        return !empty($this->settings['client_id']) ? $this->settings['client_id'] : '';
    }

    /**
     * Get Auth0 domain from Login by Auth0 plugin or our settings
     */
    private function get_auth0_domain() {
        if (isset($this->settings['use_auth0_settings']) && $this->settings['use_auth0_settings'] === 'yes') {
            // Try to get from Login by Auth0 plugin
            $auth0_options = get_option('wp_auth0_settings');
            if (!empty($auth0_options) && !empty($auth0_options['domain'])) {
                return $auth0_options['domain'];
            }
        }
        
        // Fall back to our own settings
        return !empty($this->settings['domain']) ? $this->settings['domain'] : '';
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page(
            __('Simple Auth0 Integration', 'simple-auth0'),
            __('Simple Auth0 Integration', 'simple-auth0'),
            'manage_options',
            'simple-auth0-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('simple_auth0_settings', 'simple_auth0_settings', [$this, 'sanitize_settings']);
        
        add_settings_section(
            'simple_auth0_main_section',
            __('Main Settings', 'simple-auth0'),
            [$this, 'render_main_section'],
            'simple-auth0-settings'
        );
        
        add_settings_field(
            'use_auth0_settings',
            __('Use Login by Auth0 Settings', 'simple-auth0'),
            [$this, 'render_use_auth0_settings_field'],
            'simple-auth0-settings',
            'simple_auth0_main_section'
        );
        
        add_settings_field(
            'client_id',
            __('Auth0 Client ID', 'simple-auth0'),
            [$this, 'render_client_id_field'],
            'simple-auth0-settings',
            'simple_auth0_main_section'
        );
        
        add_settings_field(
            'domain',
            __('Auth0 Domain', 'simple-auth0'),
            [$this, 'render_domain_field'],
            'simple-auth0-settings',
            'simple_auth0_main_section'
        );
        
        add_settings_field(
            'bypass_for_logged_in',
            __('Bypass for Logged-in Users', 'simple-auth0'),
            [$this, 'render_bypass_for_logged_in_field'],
            'simple-auth0-settings',
            'simple_auth0_main_section'
        );
        
        add_settings_field(
            'excluded_paths',
            __('Excluded Paths', 'simple-auth0'),
            [$this, 'render_excluded_paths_field'],
            'simple-auth0-settings',
            'simple_auth0_main_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        $sanitized['use_auth0_settings'] = isset($input['use_auth0_settings']) ? 'yes' : 'no';
        $sanitized['client_id'] = sanitize_text_field($input['client_id']);
        $sanitized['domain'] = sanitize_text_field($input['domain']);
        $sanitized['bypass_for_logged_in'] = isset($input['bypass_for_logged_in']) ? 'yes' : 'no';
        $sanitized['excluded_paths'] = sanitize_textarea_field($input['excluded_paths']);
        
        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('simple_auth0_settings');
                do_settings_sections('simple-auth0-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render main section description
     */
    public function render_main_section() {
        echo '<p>' . __('Configure your Auth0 integration settings here.', 'simple-auth0') . '</p>';
    }

    /**
     * Render use Auth0 settings field
     */
    public function render_use_auth0_settings_field() {
        $use_auth0_settings = isset($this->settings['use_auth0_settings']) ? $this->settings['use_auth0_settings'] : 'yes';
        ?>
        <label>
            <input type="checkbox" name="simple_auth0_settings[use_auth0_settings]" value="yes" <?php checked($use_auth0_settings, 'yes'); ?>>
            <?php _e('Use settings from the Login by Auth0 plugin if available', 'simple-auth0'); ?>
        </label>
        <?php
    }

    /**
     * Render client ID field
     */
    public function render_client_id_field() {
        $client_id = isset($this->settings['client_id']) ? $this->settings['client_id'] : '';
        $use_auth0_settings = isset($this->settings['use_auth0_settings']) ? $this->settings['use_auth0_settings'] : 'yes';
        $auth0_client_id = '';
        
        // Check if we can get this from the Auth0 plugin
        $auth0_options = get_option('wp_auth0_settings');
        if (!empty($auth0_options) && !empty($auth0_options['client_id'])) {
            $auth0_client_id = $auth0_options['client_id'];
        }
        
        ?>
        <input type="text" name="simple_auth0_settings[client_id]" value="<?php echo esc_attr($client_id); ?>" class="regular-text">
        <?php if ($use_auth0_settings === 'yes' && !empty($auth0_client_id)): ?>
            <p class="description">
                <?php printf(__('Currently using "%s" from Login by Auth0 plugin', 'simple-auth0'), esc_html($auth0_client_id)); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render domain field
     */
    public function render_domain_field() {
        $domain = isset($this->settings['domain']) ? $this->settings['domain'] : '';
        $use_auth0_settings = isset($this->settings['use_auth0_settings']) ? $this->settings['use_auth0_settings'] : 'yes';
        $auth0_domain = '';
        
        // Check if we can get this from the Auth0 plugin
        $auth0_options = get_option('wp_auth0_settings');
        if (!empty($auth0_options) && !empty($auth0_options['domain'])) {
            $auth0_domain = $auth0_options['domain'];
        }
        
        ?>
        <input type="text" name="simple_auth0_settings[domain]" value="<?php echo esc_attr($domain); ?>" class="regular-text">
        <?php if ($use_auth0_settings === 'yes' && !empty($auth0_domain)): ?>
            <p class="description">
                <?php printf(__('Currently using "%s" from Login by Auth0 plugin', 'simple-auth0'), esc_html($auth0_domain)); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render bypass for logged-in users field
     */
    public function render_bypass_for_logged_in_field() {
        $bypass_for_logged_in = isset($this->settings['bypass_for_logged_in']) ? $this->settings['bypass_for_logged_in'] : 'yes';
        ?>
        <label>
            <input type="checkbox" name="simple_auth0_settings[bypass_for_logged_in]" value="yes" <?php checked($bypass_for_logged_in, 'yes'); ?>>
            <?php _e('Skip Auth0 authentication for users already logged into WordPress', 'simple-auth0'); ?>
        </label>
        <?php
    }

    /**
     * Render excluded paths field
     */
    public function render_excluded_paths_field() {
        $excluded_paths = isset($this->settings['excluded_paths']) ? $this->settings['excluded_paths'] : '';
        ?>
        <textarea name="simple_auth0_settings[excluded_paths]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($excluded_paths); ?></textarea>
        <p class="description">
            <?php _e('Enter one path per line. These paths will not require Auth0 login. You can use * as a wildcard.', 'simple-auth0'); ?>
            <br>
            <?php _e('Examples: about, blog/*, products/category-*', 'simple-auth0'); ?>
        </p>
        <?php
    }
}

// Initialize the plugin
function simple_auth0_integration_init() {
    Simple_Auth0_Integration::get_instance();
}
simple_auth0_integration_init();

// Add an deactivation hook to clean up settings if needed
register_deactivation_hook(__FILE__, 'simple_auth0_integration_deactivate');
function simple_auth0_integration_deactivate() {
    // Uncomment to delete settings on plugin deactivation
    // delete_option('simple_auth0_settings');
}