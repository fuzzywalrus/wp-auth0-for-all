# Auth0 for All

A lightweight WordPress plugin that enables front-end authentication with Auth0 without creating WordPress user accounts.

## Description

Auth0 for All provides a seamless way to protect your WordPress front-end content using Auth0 authentication while keeping your WordPress admin separate. The official Auth0 plugin requires WordPress accounts and is designed for the admin panel. This integration stores user sessions separately from WordPress user accounts, making it ideal for membership sites, intranets, and client portals. This plugin is dependent on the Auth0 Login for WordPress plugin.

### Key Features

- **Front-end only authentication**: Protects public-facing content while leaving WordPress admin untouched
- **No WordPress user creation**: Stores Auth0 sessions independently from WordPress users
- **Customizable login experience**: Create your own login page HTML with a simple placeholder
- **Auto-redirect option**: Skip the intermediate login page and go directly to Auth0
- **Support for Auth0 custom domains**: Choose between standard Auth0 domain or your custom domain
- **Path exclusion**: Keep specific content public with wildcard support

### Perfect For

- Client portals that need simple authentication
- Membership sites that don't need WordPress user management
- Intranet sites with existing Auth0 implementation
- Projects where you want to separate WordPress administration from front-end users

## Installation

1. Upload the `auth0-for-all` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Auth0 For All to configure

## Configuration

### Use with Login by Auth0 Plugin

1. Install and configure the [Login by Auth0](https://wordpress.org/plugins/auth0/) plugin
2. Activate Auth0 for All
3. Go to Settings > Auth0 For All to configure the plugin options

### Available Settings

- **Use Custom Domain**: Choose between the standard Auth0 domain or your custom domain
- **Bypass for Logged-in Users**: Allow WordPress users to bypass Auth0 login
- **Auto-Redirect to Auth0**: Skip the intermediate login page and go directly to Auth0
- **Excluded Paths**: Specify paths that should remain public (supports wildcards)
- **Custom Login Page HTML**: Create your own login page using {{login_url}} as a placeholder

## Usage

Once configured, the plugin will:

1. Redirect unauthenticated users to a login page (or directly to Auth0 if auto-redirect is enabled)
2. After successful Auth0 authentication, redirect users back to their requested page
3. Add a logout link to your primary menu
4. Maintain user sessions using PHP $_SESSION

## Customizing the Login Page

You can now customize the login page by providing your own HTML in the plugin settings. Use the `{{login_url}}` placeholder where you want the Auth0 login link to appear.

Example:
```html
<div class="my-custom-login">
  <h2>Welcome to Our Secure Area</h2>
  <p>Please log in with your credentials to access the content.</p>
  <a href="{{login_url}}" class="login-button">Sign In</a>
</div>
```

## Frequently Asked Questions

## What is the login URL redirect this plugin generates?

The login URL uses a auth0_login=1 parameter that will need to be configured in Auth0's allowed callback URLs. The redirect_to parameter is the original page the user was trying to access.

https://your-site.com/?auth0_login=1&redirect_to=https%3A%2F%2Fyour-site.com%2Foriginal-page%2F

### Does this create WordPress users?

No. This plugin uses PHP sessions to track authenticated users without creating WordPress user accounts.

### How does this differ from the official Auth0 plugin?

The official Login by Auth0 plugin creates WordPress users for each authenticated user and focuses on WordPress admin authentication. This plugin is designed for front-end only authentication without user creation.

### Can I use my Auth0 custom domain?

Yes! You can choose between the standard Auth0 domain or your custom domain in the plugin settings.

### How do I exclude certain pages from requiring login?

In the plugin settings, add paths to the "Excluded Paths" field - one per line. You can use wildcards (*) to match multiple paths.

## Changelog

### 1.1
- Added support for Auth0 custom domains
- Added customizable login page HTML
- Added auto-redirect option to skip intermediate login page
- Improved integration with Auth0 Login plugin
- Renamed plugin from "Simple Auth0 Integration" to "Auth0 for All"
- Updated admin UI for better usability

### 1.0
- Initial release

## Credits

- Developed by Greg Gant for Audigy
- Built for WordPress
- Requires Auth0 account & Auth0 Login for WordPress

This was originally a functions.php integration that was converted to a plugin for easier deployment and management. Claude.ai was used to convert the functions.php to a plugin and add additional features.

## License

This project is licensed under the GPL v2 or later.