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

    /**
     * Map of path regex => endpoint slug. Matched against the portion of
     * REQUEST_URI that follows the WordPress home path, with leading and
     * trailing slashes trimmed.
     */
    private const ROUTES = [
        '#^\.well-known/api-catalog/?$#'              => 'api-catalog',
        '#^\.well-known/agent-skills/index\.json$#'   => 'agent-skills',
        '#^\.well-known/mcp/server-card\.json$#'      => 'mcp-server-card',
        '#^\.well-known/openid-configuration$#'       => 'oidc-config',
        '#^\.well-known/oauth-authorization-server$#' => 'oauth-config',
        '#^\.well-known/oauth-protected-resource$#'   => 'oauth-resource',
    ];

    /**
     * register
     *
     * Attaches the request interception hook. No rewrite rules or query
     * vars are registered — dispatch is driven entirely by REQUEST_URI
     * inspection, so no Apache or Nginx configuration is required.
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
        add_action('parse_request', [$this, 'interceptRequest'], PHP_INT_MIN);
    }

    /**
     * interceptRequest
     *
     * Fires as soon as WordPress begins parsing the request. Inspects
     * REQUEST_URI directly and, when it matches one of the well-known
     * endpoints, emits the response and exits. This bypasses the
     * rewrite-rule pipeline entirely, so the endpoints work without
     * any .htaccess or Nginx configuration — the only requirement is
     * that pretty permalinks route unknown paths to index.php, which
     * WordPress already handles on every supported server.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function interceptRequest(): void
    {

        $path = $this->resolveRequestPath();

        if ($path === '') {
            return;
        }

        foreach (self::ROUTES as $regex => $ep) {
            if (preg_match($regex, $path)) {
                match ($ep) {
                    'api-catalog'     => $this->serveApiCatalog(),
                    'agent-skills'    => $this->serveAgentSkills(),
                    'mcp-server-card' => $this->serveMcpCard(),
                    'oidc-config'     => $this->serveOidcConfig(),
                    'oauth-config'    => $this->serveOauthConfig(),
                    'oauth-resource'  => $this->serveOauthResource(),
                    default => null
                };
                return; // unreachable — serve* methods exit
            }
        }
    }

    /**
     * resolveRequestPath
     *
     * Returns REQUEST_URI reduced to a path that is relative to the
     * WordPress home URL, with any leading slash removed. Subdirectory
     * installs are handled by stripping the home path prefix so that a
     * request to /blog/.well-known/api-catalog on a WordPress install
     * at /blog/ resolves to '.well-known/api-catalog'.
     *
     * @since 1.1.1
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return string The normalised request path, or '' when unavailable
     *
     */
    private function resolveRequestPath(): string
    {
        // this is a workaround because some servers cannot pass through
        // the request_uri without intervention
        $uri = $_GET['__kp_wk'] ?? $_SERVER['REQUEST_URI'] ?? '';
        if ($uri === '') {
            return '';
        }

        // sanitize and parsel the url
        $uri  = sanitize_text_field(wp_unslash($uri));
        $path = parse_url($uri, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return '';
        }

        // fix the path
        $home_path = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
        $path      = ltrim($path, '/');

        // now clean out the home path
        if ($home_path !== '' && str_starts_with($path, $home_path . '/')) {
            $path = substr($path, strlen($home_path) + 1);
        }

        // return it
        return $path;
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

            $item = ['anchor' => esc_url_raw($anchor)];

            if (! empty($row['service_desc'])) {
                $item['service-desc'] = [['href' => esc_url_raw($row['service_desc'])]];
            }

            $service_doc = ! empty($row['service_doc_page'])
                ? get_permalink((int) $row['service_doc_page'])
                : ($row['service_doc'] ?? '');

            if ($service_doc) {
                $item['service-doc'] = [['href' => esc_url_raw($service_doc)]];
            }

            if (! empty($row['status'])) {
                $item['status'] = [['href' => esc_url_raw($row['status'])]];
            }

            $linkset[] = $item;
        }

        // Fallback — minimal valid catalog pointing to the site itself
        if (empty($linkset)) {
            $blog_url = get_option('page_for_posts')
                ? get_permalink((int) get_option('page_for_posts'))
                : home_url('/blog/');

            $linkset[] = [
                'anchor'      => home_url('/'),
                'service-doc' => [['href' => esc_url_raw($blog_url)]],
                'describedby' => [['href' => esc_url_raw(home_url('/.well-known/agent-skills/index.json'))]],
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
        $skills = (array) apply_filters('kp_agent_skills', $this->buildSkills());

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
            $key = sanitize_key(trim($row['capability_key'] ?? ''));
            if (! $key) continue;
            $capabilities->$key = (object) [];
        }

        $payload = [
            'serverInfo'   => [
                'name'    => sanitize_text_field($this->opt('mcp_name',    get_bloginfo('name'))),
                'version' => sanitize_text_field($this->opt('mcp_version', '1.0.0')),
            ],
            'transport'    => $this->opt('mcp_transport', null) ? esc_url_raw($this->opt('mcp_transport')) : null,
            'capabilities' => $capabilities,
            'description'  => sanitize_text_field($this->opt('mcp_desc', get_bloginfo('description'))),
            'homepage'     => esc_url_raw(home_url('/')),
            'contact'      => $this->opt('webmcp_contact_url', home_url('/contact/')),
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
            http_response_code(404);
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
            http_response_code(404);
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
            http_response_code(404);
            exit;
        }

        $auth_servers = array_filter(
            array_map(
                static fn($row) => esc_url_raw(trim($row['server_url'] ?? '')),
                (array) $this->opt('opr_auth_servers', [])
            )
        );

        $scopes = array_filter(
            array_map(
                static fn($row) => sanitize_text_field(trim($row['scope'] ?? '')),
                (array) $this->opt('opr_scopes', [])
            )
        );

        $bearer_methods = array_values(array_filter(
            array_map('sanitize_text_field', (array) $this->opt('opr_bearer_methods', []))
        ));

        $payload = array_filter([
            'resource'                 => esc_url_raw($this->opt('opr_resource', home_url('/'))),
            'authorization_servers'    => array_values($auth_servers),
            'scopes_supported'         => array_values($scopes),
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
        $grant_types    = array_values(array_filter(array_map('sanitize_text_field', (array) $this->opt('oauth_grant_types', []))));
        $response_types = array_values(array_filter(array_map('sanitize_text_field', (array) $this->opt('oauth_response_types', []))));
        $auth_methods   = array_values(array_filter(array_map('sanitize_text_field', (array) $this->opt('oauth_token_auth_methods', []))));

        $scopes = array_filter(
            array_map(
                static fn($row) => sanitize_text_field(trim($row['scope'] ?? '')),
                (array) $this->opt('oauth_scopes', [])
            )
        );

        return array_filter([
            'issuer'                                => esc_url_raw($this->opt('oauth_issuer', home_url('/'))),
            'authorization_endpoint'                => $this->opt('oauth_auth_endpoint')  ? esc_url_raw($this->opt('oauth_auth_endpoint'))  : null,
            'token_endpoint'                        => $this->opt('oauth_token_endpoint') ? esc_url_raw($this->opt('oauth_token_endpoint')) : null,
            'jwks_uri'                              => $this->opt('oauth_jwks_uri')        ? esc_url_raw($this->opt('oauth_jwks_uri'))        : null,
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
        $skills    = [];
        $site_name = get_bloginfo('name');

        if ($this->opt('skill_blog_enabled', true)) {
            // Use the configured Posts page, fall back to /blog/
            $blog_url = get_option('page_for_posts')
                ? get_permalink((int) get_option('page_for_posts'))
                : home_url('/blog/');
            $skills[] = [
                'name'        => 'blog-search',
                'type'        => 'search',
                'description' => sprintf('Search %s blog articles.', $site_name),
                'url'         => home_url('/?s={query}'),
            ];
            $skills[] = [
                'name'        => 'blog-articles',
                'type'        => 'browse',
                'description' => sprintf('Browse all blog articles published on %s.', home_url()),
                'url'         => esc_url_raw($blog_url),
            ];
        }

        foreach ((array) $this->opt('skill_cpts', []) as $post_type) {
            $obj = get_post_type_object($post_type);
            if (! $obj || ! $obj->public) continue;

            $archive  = get_post_type_archive_link($post_type);
            $skills[] = [
                'name'        => sanitize_title($post_type),
                'type'        => 'browse',
                'description' => sprintf('Browse %s on %s.', strtolower($obj->label), home_url()),
                'url'         => $archive ?: home_url("/?post_type={$post_type}"),
            ];
        }

        foreach ((array) $this->opt('skill_custom', []) as $row) {
            $name = trim($row['name'] ?? '');
            if (! $name) continue;

            // page_select overrides the manual URL when a page is chosen
            $url = ! empty($row['url_page'])
                ? get_permalink((int) $row['url_page'])
                : esc_url_raw($row['url'] ?? '');

            $allowed_types = ['browse', 'search', 'form', 'action', 'api'];
            $type          = in_array($row['type'] ?? 'browse', $allowed_types, true) ? $row['type'] : 'browse';

            $skill = [
                'name'        => sanitize_title($name),
                'type'        => $type,
                'description' => sanitize_text_field($row['description'] ?? ''),
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
     * Emits a JSON response using raw PHP headers and exits. Raw headers
     * are used intentionally so that no WordPress redirect logic can
     * intercept or override the response.
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
        // setup the json response
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            http_response_code(500);
            exit;
        }

        // Raw PHP headers — intentionally bypassing WordPress header functions
        http_response_code(200);
        header("Content-Type: {$content_type}; charset=UTF-8");
        header('Cache-Control: public, max-age=3600');
        header('X-Robots-Tag: noindex');

        // echo our json return and exit
        echo $json;
        exit;
    }
}
