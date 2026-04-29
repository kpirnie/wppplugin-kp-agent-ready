<?php

/** 
 * WebMCP
 * 
 * Registers and injects the WebMCP tool context via WordPress's script API.
 * Tool data is passed via wp_localize_script; execute functions are attached
 * via wp_add_inline_script so no raw script tags are ever echoed directly.
 * 
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 * 
 */

// setup the namespace
namespace KPAgentReady\Modules;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

/**
 * WebMCP
 *
 * Registers and injects the WebMCP tool context via WordPress's script API.
 * Tool data is passed via wp_localize_script; execute functions are attached
 * via wp_add_inline_script so no raw script tags are ever echoed directly.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 * @see https://webmachinelearning.github.io/webmcp/
 *
 */
class WebMCP extends AbstractModule
{

    /** Script handle used for registration and inline attachment. */
    private const HANDLE = 'kp-agent-ready-webmcp';

    /**
     * register
     *
     * Attaches the enqueue() method to wp_enqueue_scripts if WebMCP is enabled.
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
        if ($this->opt('webmcp_enabled', true)) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        }
    }

    /**
     * enqueue
     *
     * Registers a script handle with no source file, localizes the tool
     * configuration data, and attaches the WebMCP bootstrap as inline JS.
     * Bails early if no tools are configured.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function enqueue(): void
    {
        $config = $this->buildConfig();

        if (empty($config['tools'])) {
            return;
        }

        wp_register_script(self::HANDLE, false, [], KPAGRE_VERSION, ['in_footer' => true]);
        wp_enqueue_script(self::HANDLE);
        wp_localize_script(self::HANDLE, 'kpagreWebMcp', $config);
        wp_add_inline_script(self::HANDLE, $this->getScript());
    }

    /**
     * buildConfig
     *
     * Assembles the tool configuration array passed to wp_localize_script.
     * Each tool carries its name, type, description, url, and inputSchema.
     * Applies the kpagre_webmcp_tools filter before returning.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> Config array containing homeUrl and tools
     *
     */
    private function buildConfig(): array
    {
        $site_name = get_bloginfo('name');
        $tools     = [];

        if ($this->opt('webmcp_search', true)) {
            $tools[] = [
                'name'        => 'search_blog',
                'type'        => 'search',
                // translators: %s is the site name
                'description' => $this->opt('webmcp_search_desc', sprintf(__('Search %s', 'kp-agent-ready'), $site_name)),
                'url' => home_url('/?s={query}'),
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => ['query' => ['type' => 'string', 'description' => __('Search terms', 'kp-agent-ready')]],
                    'required'   => ['query'],
                ],
            ];
        }

        if ($this->opt('webmcp_portfolio', true)) {
            $tools[] = [
                'name'        => 'go_to_portfolio',
                'type'        => 'navigate',
                // translators: %s is the site name
                'description' => $this->opt('webmcp_portfolio_desc', sprintf(__('Browse the portfolio on %s', 'kp-agent-ready'), $site_name)),
                'url'         => esc_url_raw($this->opt('webmcp_portfolio_url', home_url('/portfolio/'))),
                'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ];
        }

        if ($this->opt('webmcp_contact', true)) {
            $tools[] = [
                'name'        => 'go_to_contact',
                'type'        => 'navigate',
                // translators: %s is the site name
                'description' => $this->opt('webmcp_contact_desc', sprintf(__('Contact %s', 'kp-agent-ready'), $site_name)),
                'url'         => esc_url_raw($this->opt('webmcp_contact_url', home_url('/contact/'))),
                'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ];
        }

        return [
            'homeUrl' => home_url(),
            'tools'   => apply_filters('kpagre_webmcp_tools', $tools),
        ];
    }

    /**
     * getScript
     *
     * Returns the WebMCP bootstrap JavaScript string. Reads from the
     * kpagreWebMcp global set by wp_localize_script, maps each tool config
     * to an execute function based on its type, and calls provideContext().
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return string The JavaScript bootstrap string
     *
     */
    private function getScript(): string
    {
        return '( function () {
    if ( ! navigator.modelContext || typeof navigator.modelContext.provideContext !== \'function\' ) return;
    if ( ! window.kpagreWebMcp || ! Array.isArray( window.kpagreWebMcp.tools ) || ! window.kpagreWebMcp.tools.length ) return;

    var tools = window.kpagreWebMcp.tools.map( function ( tool ) {
        var execute;

        if ( tool.type === \'search\' ) {
            execute = function ( input ) {
                window.location.href = window.kpagreWebMcp.homeUrl + \'/?s=\' + encodeURIComponent( ( input && input.query ) ? input.query : \'\' );
            };
        } else {
            execute = function () {
                if ( tool.url && /^https?:\/\//i.test( tool.url ) ) {
                    window.location.href = tool.url;
                }
            };
        }

        return {
            name        : tool.name,
            description : tool.description,
            inputSchema : tool.inputSchema,
            execute     : execute,
        };
    } );

    navigator.modelContext.provideContext( { tools : tools } );
} )();';
    }
}
