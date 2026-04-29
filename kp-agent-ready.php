<?php

/**
 * Plugin Name:  KP Agent Ready
 * Plugin URI:   https://github.com/kpirnie/wppplugin-kp-agent-ready
 * Description:  Make your WordPress site discoverable and usable by AI agents. Implements the emerging suite of agent-readiness standards — all configurable from the WordPress admin.
 * Version:      1.1.24
 * Author:       Kevin Pirnie
 * Author URI:   https://kevinpirnie.com/
 * License:      GPL-2.0-or-later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  kp-agent-ready
 * Requires PHP: 8.2
 * Requires at least: 6.8
 */

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

// setup our plugin definitions
defined('KPAGRE_VERSION') || define('KPAGRE_VERSION', '1.1.24');
defined('KPAGRE_FILE') || define('KPAGRE_FILE',    __FILE__);
defined('KPAGRE_DIR') || define('KPAGRE_DIR',     plugin_dir_path(__FILE__));
defined('KPAGRE_URL') || define('KPAGRE_URL',     plugin_dir_url(__FILE__));

// Simple PSR-4 autoloader for KPAgentReady\
spl_autoload_register(static function (string $class): void {
    $prefix = 'KPAgentReady\\';
    $len    = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $file = KPAGRE_DIR . 'src/' . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $len)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// add our plugin in the proper wordpress action
add_action('plugins_loaded', [\KPAgentReady\Plugin::class, 'instance']);

// make sure to register our activation and deactivation too
register_activation_hook(__FILE__, [\KPAgentReady\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [\KPAgentReady\Plugin::class, 'deactivate']);

// Prevent activation on multisite network
defined('WPMU_PLUGIN_DIR') && add_action('admin_init', static function () {
    if (is_plugin_active_for_network(plugin_basename(__FILE__))) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(esc_html__('KP Agent Ready cannot be network activated. Please activate it on individual sites only.', 'kp-agent-ready'));
    }
});
