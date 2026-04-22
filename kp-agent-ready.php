<?php

/**
 * Plugin Name:  KP Agent Ready
 * Plugin URI:   https://github.com/kpirnie/wppplugin-kp-agent-ready
 * Description:  Make your WordPress site discoverable and usable by AI agents. Implements the emerging suite of agent-readiness standards — all configurable from the WordPress admin.
 * Version:      1.1.21
 * Author:       Kevin Pirnie
 * Author URI:   https://kevinpirnie.com
 * License:      MIT
 * Text Domain:  kp-agent-ready
 * Requires PHP: 8.2
 * Requires at least: 6.8
 */

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

// setup our plugin definitions
defined('KP_AGENT_READY_VERSION') || define('KP_AGENT_READY_VERSION', '1.1.21');
defined('KP_AGENT_READY_FILE') || define('KP_AGENT_READY_FILE',    __FILE__);
defined('KP_AGENT_READY_DIR') || define('KP_AGENT_READY_DIR',     plugin_dir_path(__FILE__));
defined('KP_AGENT_READY_URL') || define('KP_AGENT_READY_URL',     plugin_dir_url(__FILE__));

// Simple PSR-4 autoloader for KP\AgentReady\
spl_autoload_register(static function (string $class): void {
    $prefix = 'KP\\AgentReady\\';
    $len    = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $file = KP_AGENT_READY_DIR . 'src/' . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $len)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// add our plugin in the proper wordpress action
add_action('plugins_loaded', [KP\AgentReady\Plugin::class, 'instance']);

// make sure to register our activation and deactivation too
register_activation_hook(__FILE__, [KP\AgentReady\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [KP\AgentReady\Plugin::class, 'deactivate']);
