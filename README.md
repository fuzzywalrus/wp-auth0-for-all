# wp-auth0-for-all

A lightweight WordPress plugin that enables front-end authentication with Auth0 without creating WordPress user accounts.


## Description

wp-auth0-for-all provides a seamless way to protect your WordPress front-end content using Auth0 authentication while keeping your WordPress admin separate.  The official Auth0 plugin requires Wordpress accounts and is designed for the admin panel. This integration stores user sessions separately from WordPress user accounts, making it ideal for membership sites, intranets, and client portals. This is dependant on the Auth0 Login for Wordpress plugin.

### Key Features

- **Front-end only authentication**: Protects public-facing content while leaving WordPress admin untouched
- **No WordPress user creation**: Stores Auth0 sessions independently from WordPress users
- **Customizable login experience**: Simple login screen with Auth0 Universal Login integration

### Perfect For

- Client portals that need simple authentication
- Membership sites that don't need WordPress user management
- Intranet sites with existing Auth0 implementation
- Projects where you want to separate WordPress administration from front-end users

## Installation

1. Upload the `wp-auth0-for-all` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Auth0 For All to configure

## Configuration

###  Use with Login by Auth0 Plugin

1. Install and configure the [Login by Auth0](https://wordpress.org/plugins/auth0/) plugin
2. Activate wp-auth0-for-all
3. In Settings > Auth0 For All, check "Use settings from the Login by Auth0 plugin"

### Additional Settings

- **Bypass for Logged-in Users**: Allow WordPress users to bypass Auth0 login
- **Excluded Paths**: Specify paths that should remain public (supports wildcards)

## Usage

Once configured, the plugin will:

1. Redirect unauthenticated users to a login page
2. After successful Auth0 authentication, redirect users back to their requested page
3. Add a logout link to your primary menu
4. Maintain user sessions using PHP $_SESSION


## Frequently Asked Questions

### Does this create WordPress users?

No. This plugin uses PHP sessions to track authenticated users without creating WordPress user accounts.

### How does this differ from the official Auth0 plugin?

The official Login by Auth0 plugin creates WordPress users for each authenticated user and focuses on WordPress admin authentication. This plugin is designed for front-end only authentication without user creation.


### How do I customize the login page?

Currently, the plugin provides a simple login page. For advanced customization, you can modify the plugin code or use CSS to style the login button. See the `add_auth0_login_button()` function in `wp-auth0-for-all.php` for customization.

## Changelog

### 1.0
- Initial release

## Credits

- Developed by Greg Gant for Audigy.
- Built for WordPress
- Requires Auth0 account & Auth0 Login for Wordpress

This was originally a functions.php integration that was converted to a plugin for easier deployment and management, Claude.ai was used to convert the functions.php to a plugin.

## License

This project is licensed under the GPL v2 or later. 