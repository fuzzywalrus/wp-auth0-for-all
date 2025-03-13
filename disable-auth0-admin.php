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
<?php
/**
 * Plugin Name: Direct Admin Access
 * Description: Force direct access to wp-admin by completely bypassing Auth0 redirects
 * Version: 1.1
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
        
        // Check if we're on a password reset page - don't interfere with password resets
        $is_password_reset = (
            isset($_GET['action']) && ($_GET['action'] === 'lostpassword' || $_GET['action'] === 'rp' || $_GET['action'] === 'resetpass') ||
            isset($_POST['action']) && ($_POST['action'] === 'lostpassword' || $_POST['action'] === 'rp' || $_POST['action'] === 'resetpass')
        );
        
        // Only remove actions and add init hook if not on password reset
        if (!$is_password_reset) {
            // Remove login init but preserve standard login form functionality
            remove_all_actions('login_init', 10);
            
            // Check if redirect is happening and stop it
            add_action('init', 'prevent_auth0_init_redirect', -99999);
        }
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
    // Check for password reset pages
    $is_password_reset = (
        isset($_GET['action']) && ($_GET['action'] === 'lostpassword' || $_GET['action'] === 'rp' || $_GET['action'] === 'resetpass') ||
        isset($_POST['action']) && ($_POST['action'] === 'lostpassword' || $_POST['action'] === 'rp' || $_POST['action'] === 'resetpass')
    );
    
    // Don't disable plugins on password reset
    if ($is_password_reset) {
        return $plugins;
    }
    
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
    // Check for password reset pages
    $is_password_reset = (
        isset($_GET['action']) && ($_GET['action'] === 'lostpassword' || $_GET['action'] === 'rp' || $_GET['action'] === 'resetpass') ||
        isset($_POST['action']) && ($_POST['action'] === 'lostpassword' || $_POST['action'] === 'rp' || $_POST['action'] === 'resetpass')
    );
    
    // Don't block redirects on password reset
    if ($is_password_reset) {
        return $location;
    }
    
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
    // Check for password reset
    $is_password_reset = (
        isset($_GET['action']) && ($_GET['action'] === 'lostpassword' || $_GET['action'] === 'rp' || $_GET['action'] === 'resetpass') ||
        isset($_POST['action']) && ($_POST['action'] === 'lostpassword' || $_POST['action'] === 'rp' || $_POST['action'] === 'resetpass')
    );
    
    // Don't interfere with password reset
    if ($is_password_reset) {
        return;
    }
    
    // Check for any Auth0 redirects happening in init
    if (did_action('init')) {
        // Only remove redirect_canonical, not all redirects
        remove_all_filters('redirect_canonical');
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
    
    // Don't show on password reset pages
    $is_password_reset = (
        isset($_GET['action']) && ($_GET['action'] === 'lostpassword' || $_GET['action'] === 'rp' || $_GET['action'] === 'resetpass')
    );
    if ($is_password_reset) {
        return;
    }
    
    ?>
    <div class="notice notice-success is-dismissible">
        <p><strong>Direct Admin Access:</strong> Auth0 redirects are bypassed for WordPress admin. <a href="<?php echo admin_url('plugins.php'); ?>">Manage plugins</a></p>
    </div>
    <?php
}
add_action('admin_notices', 'admin_protection_notice');