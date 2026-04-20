<?php

namespace KP\AgentReady;

use KP\AgentReady\Modules\LinkHeaders;
use KP\AgentReady\Modules\MarkdownNegotiation;
use KP\AgentReady\Modules\RobotsTxt;
use KP\AgentReady\Modules\WebMCP;
use KP\AgentReady\Modules\WellKnown;
use KP\AgentReady\Settings\SettingsPage;

/**
 * Main plugin class — bootstraps and coordinates all modules.
 */
final class Plugin
{

    /** Option key used for all plugin settings. */
    public const OPTION_KEY = 'kp_agent_ready';

    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $options = [];

    private function __construct()
    {
        $this->options = (array) get_option(self::OPTION_KEY, []);
        $this->bootstrap();
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

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

    public static function activate(): void
    {
        WellKnown::registerRules();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
