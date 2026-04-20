<?php

namespace KP\AgentReady\Modules;

/**
 * Sends RFC 8288 Link response headers for agent discovery.
 */
class LinkHeaders extends AbstractModule
{

    public function register(): void
    {
        add_action('send_headers', [$this, 'send']);
    }

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
