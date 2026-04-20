<?php

/** 
 * RobotsTxt
 * 
 * Appends Content Signals directives to the WordPress-generated robots.txt,
 * declaring the site's AI content usage preferences.
 * 
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 * 
 */

// setup the namespace
namespace KP\AgentReady\Modules;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

/**
 * RobotsTxt
 *
 * Appends Content Signals directives to the WordPress-generated robots.txt,
 * declaring the site's AI content usage preferences.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 * @see https://contentsignals.org/
 *
 */
class RobotsTxt extends AbstractModule
{

    /**
     * register
     *
     * Attaches the append() method to the robots_txt filter.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function register(): void
    {
        add_filter('robots_txt', [$this, 'append']);
    }

    /**
     * append
     *
     * Appends the Content-Signal directive to the robots.txt output.
     * Skipped if the feature is disabled in settings.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $output The current robots.txt content
     *
     * @return string The robots.txt content with Content-Signal appended
     *
     */
    public function append(string $output): string
    {
        if (! $this->opt('content_signals_enabled', true)) {
            return $output;
        }

        $ai_train = $this->opt('cs_ai_train', 'no');
        $search   = $this->opt('cs_search',   'yes');
        $ai_input = $this->opt('cs_ai_input', 'no');

        $output .= "\n# Content Signals (https://contentsignals.org/)\n";
        $output .= "Content-Signal: ai-train={$ai_train}, search={$search}, ai-input={$ai_input}\n";

        return $output;
    }
}
