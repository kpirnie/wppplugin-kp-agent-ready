<?php

/** 
 * LinkHeaders
 * 
 * Sends RFC 8288 Link response headers on every request so agents
 * can locate the site's discovery documents.
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
 * LinkHeaders
 *
 * Sends RFC 8288 Link response headers on every request so agents
 * can locate the site's discovery documents.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class LinkHeaders extends AbstractModule
{

    /**
     * register
     *
     * Attaches the send() method to the send_headers action.
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
        add_action('send_headers', [$this, 'send']);
    }

    /**
     * send
     *
     * Emits a Link header for each discovery endpoint.
     * Skipped entirely if the feature is disabled in settings.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function send(): void
    {
        if (! $this->opt('link_headers_enabled', true)) {
            return;
        }

        $links = [
            'api-catalog'  => '/.well-known/api-catalog',
            'describedby'  => '/.well-known/agent-skills/index.json',
            'service-desc' => '/.well-known/mcp/server-card.json',
        ];

        foreach ($links as $rel => $href) {
            header(sprintf('Link: <%s>; rel="%s"', $href, $rel), false);
        }
    }
}
