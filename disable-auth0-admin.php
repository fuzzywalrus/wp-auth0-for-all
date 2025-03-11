<?php
/**
 * Plugin Name: Direct Admin Access
 * Description: Force direct access to wp-admin by completely bypassing Auth0 redirects
 * Version: 1.0
 * Author: Greg Gant
 * Author URI: https://www.greggant.com
 * Plugin URI: https://github.com/fuzzywalrus/wp-auth0-for-all
 * Text Domain: auth0-for-all
 */

// Execute as early as possible
add_action('muplugins_loaded', 'force_admin_access', -99999);
add_action('plugins_loaded', 'force_admin_access', -99999);

// Add a special handler for /wp-login without .php extension
add_action('init', 'maybe_handle_plain_wp_login', 1);

/**
 * Handle /wp-login URLs safely after WordPress is initialized
 */
function maybe_handle_plain_wp_login() {
    if (isset($_SERVER['REQUEST_URI']) && (
        $_SERVER['REQUEST_URI'] === '/wp-login' || 
        $_SERVER['REQUEST_URI'] === '/wp-login/' ||
        preg_match('#^/wp-login(\?|$)#', $_SERVER['REQUEST_URI'])
    )) {
        // Just include the standard login page file
        require_once(ABSPATH . 'wp-login.php');
        exit;
    }
}

/**
 * Force direct admin access by disabling Auth0 on admin pages
 */
function force_admin_access() {
    // Define constant to disable Auth0 for login URLs without .php extension
    if (isset($_SERVER['REQUEST_URI']) && (
        $_SERVER['REQUEST_URI'] === '/wp-login' || 
        $_SERVER['REQUEST_URI'] === '/wp-login/' ||
        preg_match('#^/wp-login(\?|$)#', $_SERVER['REQUEST_URI'])
    )) {
        if (!defined('DISABLE_AUTH0')) {
            define('DISABLE_AUTH0', true);
        }
    }
    
    // Check if we're on an admin page or login page
    if (is_admin_page()) {
        // Define constant to disable Auth0
        if (!defined('DISABLE_AUTH0')) {
            define('DISABLE_AUTH0', true);
        }
        
        // Disable Login by Auth0 plugin specifically for wp-admin
        add_filter('wp_auth0_is_ready', '__return_false', 999);
        add_filter('wp_auth0_should_redirect', '__return_false', 999);
        
        // Disable all plugins that contain "auth0" in their filename
        add_filter('option_active_plugins', 'disable_auth0_plugins', 999);
        
        // Block all redirects to Auth0
        add_filter('wp_redirect', 'block_auth0_redirects', 999, 2);
        
        // Force native WordPress login
        remove_all_actions('login_init');
        remove_all_filters('login_redirect');
        
        // Check if redirect is happening and stop it
        add_action('init', 'prevent_auth0_init_redirect', -99999);
    }
}

/**
 * Check if the current request is for an admin page
 */
function is_admin_page() {
    // More comprehensive check for admin and login pages
    return is_admin() || 
        strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false ||
        strpos($_SERVER['REQUEST_URI'], '/wp-login') !== false ||
        (isset($_SERVER['PHP_SELF']) && (
            strpos($_SERVER['PHP_SELF'], '/wp-admin/') !== false ||
            strpos($_SERVER['PHP_SELF'], 'wp-login.php') !== false ||
            strpos($_SERVER['PHP_SELF'], '/wp-login') !== false
        ));
}

/**
 * Filter out Auth0 plugins on admin pages
 */
function disable_auth0_plugins($plugins) {
    return array_filter($plugins, function($plugin) {
        // Skip this check on plugin management pages
        if (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], 'plugins.php') !== false) {
            return true;
        }
        
        // Allow the plugin on its own settings page
        if (isset($_GET['page']) && (
            $_GET['page'] === 'simple-auth0-settings' || 
            strpos($_GET['page'], 'auth0') !== false || 
            strpos($_GET['page'], 'wpa0') !== false
        )) {
            return true;
        }
        
        // Otherwise, filter out Auth0 plugins
        return strpos($plugin, 'auth0') === false && 
               strpos($plugin, 'wp-auth0') === false;
    });
}

/**
 * Block any redirects to Auth0
 */
function block_auth0_redirects($location, $status) {
    // Skip this filter when on plugin management pages
    if (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], 'plugins.php') !== false) {
        return $location;
    }
    
    // Allow redirects on Auth0 settings pages
    if (isset($_GET['page']) && (
        $_GET['page'] === 'simple-auth0-settings' || 
        strpos($_GET['page'], 'auth0') !== false ||
        strpos($_GET['page'], 'wpa0') !== false
    )) {
        return $location;
    }
    
    // Block redirects to Auth0 domains or with Auth0 parameters
    if (strpos($location, 'auth0.com') !== false || 
        strpos($location, '.auth0.com') !== false ||
        strpos($location, 'nexus-qa.formos.com') !== false ||  // Your custom domain
        strpos($location, 'formos.com') !== false ||  // Broader protection for your domain
        strpos($location, 'auth0_login') !== false ||
        strpos($location, '?auth0') !== false ||
        strpos($location, '&auth0') !== false) {
        
        // Log the blocked redirect
        error_log('Admin Access: Blocked redirect to ' . $location);
        
        // Return false to cancel the redirect
        return false;
    }
    
    return $location;
}

/**
 * Stop redirects during init
 */
function prevent_auth0_init_redirect() {
    // Check for any Auth0 redirects happening in init
    if (did_action('init')) {
        // Remove all remaining redirect_canonical filters
        remove_all_filters('redirect_canonical');
        
        // Disable all additional redirects
        add_filter('wp_redirect', '__return_false', 999999);
    }
}

/**
 * Add notification that admin protection is active
 */
function admin_protection_notice() {
    // Don't show on plugin pages
    if (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], 'plugins.php') !== false) {
        return;
    }
    
    // Don't show on Auth0 settings
    if (isset($_GET['page']) && (
        $_GET['page'] === 'simple-auth0-settings' || 
        strpos($_GET['page'], 'auth0') !== false ||
        strpos($_GET['page'], 'wpa0') !== false
    )) {
        return;
    }
    
    ?>
    <div class="notice notice-success is-dismissible">
        <p><strong>Direct Admin Access:</strong> Auth0 redirects are bypassed for WordPress admin. <a href="<?php echo admin_url('plugins.php'); ?>">Manage plugins</a></p>
    </div>
    <?php
}
add_action('admin_notices', 'admin_protection_notice');