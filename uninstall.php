<?php

/**
 * Uninstall
 *
 * Removes all plugin data when the plugin is deleted from a site.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 *
 */

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

// Make sure we're actually supposed to be doing this
if (
    ! defined('WP_UNINSTALL_PLUGIN') || ! WP_UNINSTALL_PLUGIN ||
    dirname(WP_UNINSTALL_PLUGIN) != dirname(plugin_basename(__FILE__))
) {
    exit;
}

// Register the autoloader — composer is unavailable in uninstall context
spl_autoload_register(static function (string $class): void {
    $prefix = 'KP\\AgentReady\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Remove generated llms.txt files if they exist
\KP\AgentReady\Modules\LlmsTxt::deleteFiles();

// Delete plugin options
delete_option('kp_agent_ready');

// Delete transients
delete_transient('kp_agent_ready_update_data');
