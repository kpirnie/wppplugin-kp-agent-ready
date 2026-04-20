<?php

/** 
 * Uninstall
 * 
 * Removes all created settings when deleting the plugin from a site
 * 
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 * 
 */

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

// make sure we're actually supposed to be doing this
if (
    ! defined('WP_UNINSTALL_PLUGIN') || ! WP_UNINSTALL_PLUGIN ||
    dirname(WP_UNINSTALL_PLUGIN) != dirname(plugin_basename(__FILE__))
) {
    exit;
}

// remove our settings
unregister_setting('kp_agent_ready', 'kp_agent_ready');

// delete the option
delete_option('kp_agent_ready');
