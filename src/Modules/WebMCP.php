<?php

namespace KP\AgentReady\Modules;

/**
 * Injects WebMCP tool definitions via navigator.modelContext.provideContext().
 *
 * @see https://webmachinelearning.github.io/webmcp/
 */
class WebMCP extends AbstractModule
{

    public function register(): void
    {
        if ($this->opt('webmcp_enabled', true)) {
            add_action('wp_footer', [$this, 'inject']);
        }
    }

    public function inject(): void
    {
?>
        <script>
            /* WebMCP — agent tool discovery (https://webmachinelearning.github.io/webmcp/) */
            (function() {
                if (!navigator.modelContext || typeof navigator.modelContext.provideContext !== 'function') return;

                const tools = [];

                <?php if ($this->opt('webmcp_search', true)) : ?>
                    tools.push({
                        name: 'search_blog',
                        description: <?php echo wp_json_encode("Search Kevin Pirnie's blog for WordPress, DevOps, and web development articles."); ?>,
                        inputSchema: {
                            type: 'object',
                            properties: {
                                query: {
                                    type: 'string',
                                    description: 'Search terms'
                                }
                            },
                            required: ['query'],
                        },
                        execute: ({
                            query
                        }) => {
                            window.location.href = `/?s=${ encodeURIComponent( query ) }`;
                        },
                    });
                <?php endif; ?>

                <?php if ($this->opt('webmcp_portfolio', true)) : ?>
                    tools.push({
                        name: 'go_to_portfolio',
                        description: <?php echo wp_json_encode("Navigate to Kevin Pirnie's development portfolio."); ?>,
                        inputSchema: {
                            type: 'object',
                            properties: {},
                            required: []
                        },
                        execute: () => {
                            window.location.href = '/portfolio/';
                        },
                    });
                <?php endif; ?>

                <?php if ($this->opt('webmcp_contact', true)) : ?>
                    tools.push({
                        name: 'go_to_contact',
                        description: <?php echo wp_json_encode('Navigate to the contact page on kevinpirnie.com.'); ?>,
                        inputSchema: {
                            type: 'object',
                            properties: {},
                            required: []
                        },
                        execute: () => {
                            window.location.href = '/contact/';
                        },
                    });
                <?php endif; ?>

                if (tools.length) {
                    navigator.modelContext.provideContext({
                        tools
                    });
                }
            })();
        </script>
<?php
    }
}
