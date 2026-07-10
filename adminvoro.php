<?php
/**
 * Plugin Name: Adminvoro Toolkit - Login, Redirects & Site Controls
 * Plugin URI: https://github.com/devatiq/adminvoro
 * Description: Manage custom login URLs, login branding, redirects, and essential admin/site controls from one lightweight toolkit.
 * Version: 1.0.0
 * Author: Nexiby LLC
 * Author URI: https://nexiby.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adminvoro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Adminvoro
 */

if (!defined('ABSPATH')) {
	exit;
}

define('ADMINVORO_VERSION', '1.0.0');
define('ADMINVORO_FILE', __FILE__);
define('ADMINVORO_PATH', plugin_dir_path(__FILE__));
define('ADMINVORO_URL', plugin_dir_url(__FILE__));
define('ADMINVORO_BASENAME', plugin_basename(__FILE__));
define('ADMINVORO_OPTION', 'adminvoro_options');
define('ADMINVORO_REDIRECTS_OPTION', 'adminvoro_redirects');

require_once ADMINVORO_PATH . 'includes/class-adminvoro.php';

register_activation_hook(__FILE__, array('Adminvoro', 'activate'));
register_deactivation_hook(__FILE__, array('Adminvoro', 'deactivate'));

/**
 * Start the plugin.
 *
 * @return void
 */
function adminvoro_run()
{
	Adminvoro::instance()->run();
}

adminvoro_run();
