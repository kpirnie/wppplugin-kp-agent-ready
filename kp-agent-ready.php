<?php

/**
 * Plugin Name:  KP Agent Ready
 * Plugin URI:   https://github.com/kpirnie/wppplugin-kp-agent-ready
 * Description:  RFC 8288 Link headers, /.well-known endpoints, markdown negotiation, Content Signals, and WebMCP
 * Version:      1.0.68
 * Author:       Kevin Pirnie
 * Author URI:   https://kevinpirnie.com
 * License:      MIT
 * Text Domain:  kp-agent-ready
 * Requires PHP: 8.2
 * Requires at least: 6.8
 */

defined('ABSPATH') || exit;

define('KP_AGENT_READY_VERSION', '1.0.68');
define('KP_AGENT_READY_FILE',    __FILE__);
define('KP_AGENT_READY_DIR',     plugin_dir_path(__FILE__));
define('KP_AGENT_READY_URL',     plugin_dir_url(__FILE__));

if (file_exists(KP_AGENT_READY_DIR . 'vendor/autoload.php')) {
    require_once KP_AGENT_READY_DIR . 'vendor/autoload.php';
}

add_action('plugins_loaded', [KP\AgentReady\Plugin::class, 'instance']);

register_activation_hook(__FILE__, [KP\AgentReady\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [KP\AgentReady\Plugin::class, 'deactivate']);
