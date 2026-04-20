<?php

/** 
 * Plugin
 * 
 * Main plugin orchestrator — bootstraps and coordinates all modules.
 * 
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 * 
 */

// setup the namespace
namespace KP\AgentReady;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

// Pull in the rest of our namespaces
use KP\AgentReady\Modules\LinkHeaders;
use KP\AgentReady\Modules\MarkdownNegotiation;
use KP\AgentReady\Modules\RobotsTxt;
use KP\AgentReady\Modules\WebMCP;
use KP\AgentReady\Modules\WellKnown;
use KP\AgentReady\Settings\SettingsPage;

/**
 * Plugin
 *
 * Main plugin orchestrator — bootstraps and coordinates all modules.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
final class Plugin
{

    /** Option key used for all plugin settings. */
    public const OPTION_KEY = 'kp_agent_ready';

    // hold the instance
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $options = [];

    // fire us up!
    private function __construct()
    {
        $this->options = (array) get_option(self::OPTION_KEY, []);
        $this->bootstrap();
    }

    /**
     * instance
     *
     * Returns or creates the single Plugin instance.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return self The singleton instance
     *
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * bootstrap
     *
     * Instantiates and registers all modules.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function bootstrap(): void
    {
        if (is_admin()) {
            (new SettingsPage($this->options))->register();
        }

        (new LinkHeaders($this->options))->register();
        (new RobotsTxt($this->options))->register();
        (new WellKnown($this->options))->register();
        (new MarkdownNegotiation($this->options))->register();
        (new WebMCP($this->options))->register();
    }

    /**
     * activate
     *
     * Runs on plugin activation — registers rewrite rules and flushes.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public static function activate(): void
    {
        WellKnown::registerRules();
        flush_rewrite_rules();
    }

    /**
     * deactivate
     *
     * Runs on plugin deactivation — flushes rewrite rules.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
