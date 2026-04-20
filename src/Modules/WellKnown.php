<?php

/** 
 * WellKnown
 * 
 * Registers and serves all /.well-known/* endpoints for agent discovery.
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
 * WellKnown
 *
 * Registers and serves all /.well-known/* endpoints for agent discovery.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class WellKnown extends AbstractModule
{

    private const QUERY_VAR = 'kp_agent';

    /**
     * register
     *
     * Attaches rewrite rule registration, query var, and dispatch hooks.
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
        add_action('init',              [__CLASS__, 'registerRules']);
        add_filter('query_vars',        [$this, 'addQueryVar']);
        add_action('template_redirect', [$this, 'dispatch'], 1);
        add_filter('wpseo_whitelist_query_vars', [$this, 'addQueryVar']);
        add_filter('redirect_canonical', [$this, 'preventCanonicalRedirect']);
    }

    /**
     * registerRules
     *
     * Adds rewrite rules for all /.well-known/* endpoints.
     * Called on init and on plugin activation.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public static function registerRules(): void
    {
        $rules = [
            '^\.well-known/api-catalog/?$'              => 'api-catalog',
            '^\.well-known/agent-skills/index\.json$'   => 'agent-skills',
            '^\.well-known/mcp/server-card\.json$'      => 'mcp-server-card',
            '^\.well-known/openid-configuration$'       => 'oidc-config',
            '^\.well-known/oauth-authorization-server$' => 'oauth-config',
            '^\.well-known/oauth-protected-resource$'   => 'oauth-resource',
        ];

        foreach ($rules as $regex => $ep) {
            add_rewrite_rule($regex, 'index.php?' . self::QUERY_VAR . '=' . $ep, 'top');
        }
    }

    /**
     * addQueryVar
     *
     * Registers the kp_agent query variable with WordPress.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string[] $vars The current registered query vars
     *
     * @return string[] The query vars array with kp_agent appended
     *
     */
    public function addQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * dispatch
     *
     * Reads the kp_agent query var and routes to the appropriate
     * endpoint handler. No-ops if the var is not set.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function dispatch(): void
    {
        $ep = get_query_var(self::QUERY_VAR);
        if (! $ep) return;

        match ($ep) {
            'api-catalog'     => $this->serveApiCatalog(),
            'agent-skills'    => $this->serveAgentSkills(),
            'mcp-server-card' => $this->serveMcpCard(),
            'oidc-config'     => $this->serveOidcConfig(),
            'oauth-config'    => $this->serveOauthConfig(),
            'oauth-resource'  => $this->serveOauthResource(),
            default           => null,
        };
    }

    /**
     * preventCanonicalRedirect
     *
     * Prevents WordPress canonical redirects from firing on
     * well-known endpoint requests, which have no canonical URL.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $redirect_url The URL WordPress wants to redirect to
     *
     * @return string|false The redirect URL or false to cancel the redirect
     *
     */
    public function preventCanonicalRedirect(string $redirect_url): string|false
    {
        if (get_query_var(self::QUERY_VAR)) {
            return false;
        }

        return $redirect_url;
    }

    /**
     * serveApiCatalog
     *
     * Serves /.well-known/api-catalog as application/linkset+json per RFC 9727.
     * Builds entries from the api_catalog_entries repeater setting.
     * Falls back to a minimal valid catalog when no entries are configured.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return never Emits response and exits
     *
     */
    private function serveApiCatalog(): never
    {
        $entries = (array) $this->opt('api_catalog_entries', []);
        $linkset = [];

        foreach ($entries as $row) {
            $anchor = trim($row['anchor'] ?? '');
            if (! $anchor) continue;

            $item = ['anchor' => $anchor];

            if (! empty($row['service_desc'])) {
                $item['service-desc'] = [['href' => $row['service_desc']]];
            }
            // page_select overrides the manual URL when a page is chosen
            $service_doc = ! empty($row['service_doc_page'])
                ? get_permalink((int) $row['service_doc_page'])
                : ($row['service_doc'] ?? '');

            if ($service_doc) {
                $item['service-doc'] = [['href' => $service_doc]];
            }
            if (! empty($row['status'])) {
                $item['status'] = [['href' => $row['status']]];
            }

            $linkset[] = $item;
        }

        // Fallback — minimal valid catalog pointing to the site itself
        if (empty($linkset)) {
            $linkset[] = [
                'anchor'      => home_url('/'),
                'service-doc' => [['href' => home_url('/blog/')]],
                'describedby' => [['href' => home_url('/.well-known/agent-skills/index.json')]],
            ];
        }

        $this->respond(['linkset' => $linkset], 'application/linkset+json');
    }

    /**
     * serveAgentSkills
     *
     * Serves /.well-known/agent-skills/index.json per Agent Skills Discovery v0.2.0.
     * Applies the kp_agent_skills filter before output.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return never Emits response and exits
     *
     */
    private function serveAgentSkills(): never
    {
        $skills = apply_filters('kp_agent_skills', $this->buildSkills());

        $payload = [
            '$schema' => 'https://agentskills.io/schema/v0.2.0/index.schema.json',
            'skills'  => array_values($skills),
        ];

        $this->respond($payload);
    }

    /**
     * serveMcpCard
     *
     * Serves /.well-known/mcp/server-card.json per SEP-1649.
     * Builds the capabilities object from the mcp_capabilities repeater setting.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return never Emits response and exits
     *
     */
    private function serveMcpCard(): never
    {
        $capabilities_raw = (array) $this->opt('mcp_capabilities', []);
        $capabilities     = (object) [];

        foreach ($capabilities_raw as $row) {
            $key = trim($row['capability_key'] ?? '');
            if (! $key) continue;
            $capabilities->$key = (object) [];
        }

        $payload = [
            'serverInfo'   => [
                'name'    => $this->opt('mcp_name',    'kevinpirnie.com'),
                'version' => $this->opt('mcp_version', '1.0.0'),
            ],
            'transport'    => $this->opt('mcp_transport', null) ?: null,
            'capabilities' => $capabilities,
            'description'  => $this->opt('mcp_desc', 'Personal portfolio and blog of Kevin Pirnie — WordPress Developer & DevOps Engineer.'),
            'homepage'     => home_url('/'),
            'contact'      => home_url('/contact/'),
        ];

        $this->respond($payload);
    }

    /**
     * serveOidcConfig
     *
     * Serves /.well-known/openid-configuration per OpenID Connect Discovery 1.0.
     * Returns 404 if OAuth is disabled or the selected type is not 'oidc'.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return never Emits response and exits
     *
     */
    private function serveOidcConfig(): never
    {
        if (! $this->opt('oauth_enabled', false) || $this->opt('oauth_type', 'oidc') !== 'oidc') {
            status_header(404);
            exit;
        }

        $this->respond($this->buildOauthPayload());
    }

    /**
     * serveOauthConfig
     *
     * Serves /.well-known/oauth-authorization-server per RFC 8414.
     * Returns 404 if OAuth is disabled or the selected type is not 'oauth2'.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return never Emits response and exits
     *
     */
    private function serveOauthConfig(): never
    {
        if (! $this->opt('oauth_enabled', false) || $this->opt('oauth_type', 'oidc') !== 'oauth2') {
            status_header(404);
            exit;
        }

        $this->respond($this->buildOauthPayload());
    }

    /**
     * serveOauthResource
     *
     * Serves /.well-known/oauth-protected-resource per RFC 9728.
     * Returns 404 if the OAuth Protected Resource feature is disabled.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return never Emits response and exits
     *
     */
    private function serveOauthResource(): never
    {
        if (! $this->opt('opr_enabled', false)) {
            status_header(404);
            exit;
        }

        $auth_servers = array_filter(
            array_map(
                static fn($row) => trim($row['server_url'] ?? ''),
                (array) $this->opt('opr_auth_servers', [])
            )
        );

        $scopes = array_filter(
            array_map(
                static fn($row) => trim($row['scope'] ?? ''),
                (array) $this->opt('opr_scopes', [])
            )
        );

        $bearer_methods = array_values(array_filter((array) $this->opt('opr_bearer_methods', [])));

        $payload = array_filter([
            'resource'                => $this->opt('opr_resource', home_url('/')),
            'authorization_servers'   => array_values($auth_servers),
            'scopes_supported'        => array_values($scopes),
            'bearer_methods_supported' => $bearer_methods ?: null,
        ]);

        $this->respond($payload);
    }

    /**
     * buildOauthPayload
     *
     * Assembles the shared OAuth/OIDC discovery metadata payload
     * used by both serveOidcConfig() and serveOauthConfig().
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The discovery metadata array
     *
     */
    private function buildOauthPayload(): array
    {
        $grant_types    = array_values(array_filter((array) $this->opt('oauth_grant_types', [])));
        $response_types = array_values(array_filter((array) $this->opt('oauth_response_types', [])));
        $auth_methods   = array_values(array_filter((array) $this->opt('oauth_token_auth_methods', [])));

        $scopes = array_filter(
            array_map(
                static fn($row) => trim($row['scope'] ?? ''),
                (array) $this->opt('oauth_scopes', [])
            )
        );

        return array_filter([
            'issuer'                                => $this->opt('oauth_issuer', home_url('/')),
            'authorization_endpoint'                => $this->opt('oauth_auth_endpoint')    ?: null,
            'token_endpoint'                        => $this->opt('oauth_token_endpoint')   ?: null,
            'jwks_uri'                              => $this->opt('oauth_jwks_uri')          ?: null,
            'grant_types_supported'                 => $grant_types    ?: null,
            'response_types_supported'              => $response_types ?: null,
            'scopes_supported'                      => array_values($scopes) ?: null,
            'token_endpoint_auth_methods_supported' => $auth_methods   ?: null,
        ]);
    }

    /**
     * buildSkills
     *
     * Assembles the agent skills array from blog settings, enabled CPTs,
     * and manually defined custom skills from the repeater setting.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<int, array<string, string>> The assembled skills array
     *
     */
    private function buildSkills(): array
    {
        $skills = [];

        if ($this->opt('skill_blog_enabled', true)) {
            $skills[] = [
                'name'        => 'blog-search',
                'type'        => 'search',
                'description' => 'Search Kevin Pirnie\'s blog articles on WordPress, DevOps, and web development.',
                'url'         => home_url('/?s={query}'),
            ];
            $skills[] = [
                'name'        => 'blog-articles',
                'type'        => 'browse',
                'description' => 'Browse all blog articles published on kevinpirnie.com.',
                'url'         => home_url('/blog/'),
            ];
        }

        foreach ((array) $this->opt('skill_cpts', []) as $post_type) {
            $obj = get_post_type_object($post_type);
            if (! $obj || ! $obj->public) continue;

            $archive  = get_post_type_archive_link($post_type);
            $skills[] = [
                'name'        => sanitize_title($post_type),
                'type'        => 'browse',
                'description' => sprintf('Browse %s on kevinpirnie.com.', strtolower($obj->label)),
                'url'         => $archive ?: home_url("/?post_type={$post_type}"),
            ];
        }

        foreach ((array) $this->opt('skill_custom', []) as $row) {
            $name = trim($row['name'] ?? '');
            if (! $name) continue;

            // page_select overrides the manual URL when a page is chosen
            $url = ! empty($row['url_page'])
                ? get_permalink((int) $row['url_page'])
                : ($row['url'] ?? '');

            $skill = [
                'name'        => sanitize_title($name),
                'type'        => $row['type']        ?? 'browse',
                'description' => $row['description'] ?? '',
                'url'         => $url,
            ];

            // sha256 digest — optional, manually supplied
            if (! empty($row['sha256'])) {
                $skill['sha256'] = $row['sha256'];
            }

            $skills[] = $skill;
        }

        return $skills;
    }

    /**
     * respond
     *
     * Emits a JSON response with the appropriate Content-Type header and exits.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array<mixed> $payload      The data to encode as JSON
     * @param string       $content_type The Content-Type header value
     *
     * @return never Emits response and exits
     *
     */
    private function respond(array $payload, string $content_type = 'application/json'): never
    {
        status_header(200);
        header("Content-Type: {$content_type}; charset=UTF-8");
        header('Cache-Control: public, max-age=3600');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
