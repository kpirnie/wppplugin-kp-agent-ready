<?php

namespace KP\AgentReady\Modules;

/**
 * Registers and serves all /.well-known/* endpoints for agent discovery.
 *
 * Endpoints:
 *   /.well-known/api-catalog                RFC 9727  — application/linkset+json
 *   /.well-known/agent-skills/index.json    Agent Skills Discovery v0.2.0
 *   /.well-known/mcp/server-card.json       SEP-1649
 *   /.well-known/openid-configuration       OpenID Connect Discovery 1.0
 *   /.well-known/oauth-authorization-server RFC 8414
 *   /.well-known/oauth-protected-resource   RFC 9728
 */
class WellKnown extends AbstractModule
{

    private const QUERY_VAR = 'kp_agent';

    public function register(): void
    {
        add_action('init',              [__CLASS__, 'registerRules']);
        add_filter('query_vars',        [$this, 'addQueryVar']);
        add_action('template_redirect', [$this, 'dispatch'], 1);
    }

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

    /** @param string[] $vars */
    public function addQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

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

    // -------------------------------------------------------------------------
    // /.well-known/api-catalog  (RFC 9727)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // /.well-known/agent-skills/index.json  (Agent Skills Discovery v0.2.0)
    // -------------------------------------------------------------------------

    private function serveAgentSkills(): never
    {
        $skills = apply_filters('kp_agent_skills', $this->buildSkills());

        $payload = [
            '$schema' => 'https://agentskills.io/schema/v0.2.0/index.schema.json',
            'skills'  => array_values($skills),
        ];

        $this->respond($payload);
    }

    // -------------------------------------------------------------------------
    // /.well-known/mcp/server-card.json  (SEP-1649)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // /.well-known/openid-configuration  (OpenID Connect Discovery 1.0)
    // -------------------------------------------------------------------------

    private function serveOidcConfig(): never
    {
        if (! $this->opt('oauth_enabled', false) || $this->opt('oauth_type', 'oidc') !== 'oidc') {
            status_header(404);
            exit;
        }

        $this->respond($this->buildOauthPayload());
    }

    // -------------------------------------------------------------------------
    // /.well-known/oauth-authorization-server  (RFC 8414)
    // -------------------------------------------------------------------------

    private function serveOauthConfig(): never
    {
        if (! $this->opt('oauth_enabled', false) || $this->opt('oauth_type', 'oidc') !== 'oauth2') {
            status_header(404);
            exit;
        }

        $this->respond($this->buildOauthPayload());
    }

    // -------------------------------------------------------------------------
    // /.well-known/oauth-protected-resource  (RFC 9728)
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Builders
    // -------------------------------------------------------------------------

    /**
     * Shared OAuth/OIDC discovery payload used by both endpoint types.
     *
     * @return array<string, mixed>
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
     * Assembles the agent skills array from:
     *   1. Blog / articles (if enabled)
     *   2. Configured custom post types
     *   3. Manually defined custom skills (repeater)
     *
     * @return array<int, array<string, string>>
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

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /** @param array<mixed> $payload */
    private function respond(array $payload, string $content_type = 'application/json'): never
    {
        status_header(200);
        header("Content-Type: {$content_type}; charset=UTF-8");
        header('Cache-Control: public, max-age=3600');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
