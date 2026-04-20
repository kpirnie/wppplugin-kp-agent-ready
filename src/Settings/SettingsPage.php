<?php

namespace KP\AgentReady\Settings;


// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');



use KP\AgentReady\Plugin;
use KP\WPFieldFramework\Loader;

/**
 * SettingsPage
 *
 * Registers the plugin's tabbed admin settings page using KPT WP Field Framework.
 * All settings are stored under the option key defined in Plugin::OPTION_KEY.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class SettingsPage
{

    /** CPT slugs to exclude from the CPT skill selection. */
    private const EXCLUDED_CPTS = [
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
        'wp_font_family',
        'wp_font_face',
    ];

    /** @param array<string, mixed> $options */
    public function __construct(private array $options) {}

    /**
     * register
     *
     * Hooks init for settings registration and admin_menu for submenu links.
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
        // Priority 20 — after CPTs from other plugins are registered at 10
        add_action('init',       [$this, 'init'], 20);
        add_action('admin_menu', [$this, 'registerSubmenus'], 20);
    }

    /**
     * init
     *
     * Initialises the KPT WP Field Framework and registers the options page.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function init(): void
    {
        $framework = Loader::init();
        $framework->addOptionsPage($this->buildConfig());
    }

    /**
     * buildConfig
     *
     * Assembles the full options page configuration array passed to
     * the KPT WP Field Framework.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The complete options page configuration
     *
     */
    private function buildConfig(): array
    {
        $tabs = [
            'features'     => $this->tabFeatures(),
            'api_catalog'  => $this->tabApiCatalog(),
            'agent_skills' => $this->tabAgentSkills(),
            'mcp_card'     => $this->tabMcpCard(),
        ];

        if ($this->options['content_signals_enabled'] ?? true) {
            $tabs['content_signals'] = $this->tabContentSignals();
        }

        if ($this->options['oauth_enabled'] ?? false) {
            $tabs['oauth'] = $this->tabOAuth();
        }

        if ($this->options['opr_enabled'] ?? false) {
            $tabs['oauth_resource'] = $this->tabOAuthResource();
        }

        if ($this->options['webmcp_enabled'] ?? true) {
            $tabs['webmcp'] = $this->tabWebMcp();
        }

        return [
            'page_title'         => 'Agent Ready',
            'menu_title'         => 'Agent Ready',
            'menu_slug'          => Plugin::OPTION_KEY,
            'icon_url'           => 'dashicons-superhero-alt',
            'position'           => 30,
            'show_export_import' => true,
            'tabs'               => $tabs,
        ];
    }

    /**
     * registerSubmenus
     *
     * Registers each settings tab as a child submenu link under the
     * main Agent Ready menu item.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function registerSubmenus(): void
    {
        $tabs = [
            'features'     => 'Features',
            'api_catalog'  => 'API Catalog',
            'agent_skills' => 'Agent Skills',
            'mcp_card'     => 'MCP Server Card',
        ];

        if ($this->options['content_signals_enabled'] ?? true) {
            $tabs['content_signals'] = 'Content Signals';
        }

        if ($this->options['oauth_enabled'] ?? false) {
            $tabs['oauth'] = 'OAuth / OIDC';
        }

        if ($this->options['opr_enabled'] ?? false) {
            $tabs['oauth_resource'] = 'OAuth Resource';
        }

        if ($this->options['webmcp_enabled'] ?? true) {
            $tabs['webmcp'] = 'WebMCP';
        }

        foreach ($tabs as $tab => $label) {
            $url = add_query_arg('tab', $tab, 'admin.php?page=' . Plugin::OPTION_KEY);
            add_submenu_page(
                Plugin::OPTION_KEY,
                $label,
                $label,
                'manage_options',
                $url
            );
        }
    }

    /**
     * tabFeatures
     *
     * Builds the Features tab configuration — master toggles for all features.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The tab configuration array
     *
     */
    private function tabFeatures(): array
    {
        return [
            'title'    => 'Features',
            'sections' => [
                'toggles' => [
                    'title'  => 'Feature Toggles',
                    'fields' => [
                        [
                            'id'             => 'link_headers_enabled',
                            'type'           => 'switch',
                            'label'          => 'RFC 8288 Link Headers',
                            'checkbox_label' => 'Send Link response headers for agent discovery',
                            'default'        => true,
                        ],
                        [
                            'id'             => 'content_signals_enabled',
                            'type'           => 'switch',
                            'label'          => 'Content Signals',
                            'checkbox_label' => 'Append Content-Signal directives to robots.txt',
                            'default'        => true,
                        ],
                        [
                            'id'             => 'markdown_enabled',
                            'type'           => 'switch',
                            'label'          => 'Markdown Negotiation',
                            'checkbox_label' => 'Serve text/markdown when requested via Accept header',
                            'default'        => true,
                        ],
                        [
                            'id'             => 'webmcp_enabled',
                            'type'           => 'switch',
                            'label'          => 'WebMCP',
                            'checkbox_label' => 'Inject WebMCP tool definitions via navigator.modelContext',
                            'default'        => true,
                        ],
                        [
                            'id'             => 'oauth_enabled',
                            'type'           => 'switch',
                            'label'          => 'OAuth / OIDC Discovery',
                            'checkbox_label' => 'Serve OAuth or OpenID Connect discovery metadata',
                            'default'        => false,
                        ],
                        [
                            'id'             => 'opr_enabled',
                            'type'           => 'switch',
                            'label'          => 'OAuth Protected Resource',
                            'checkbox_label' => 'Serve OAuth Protected Resource Metadata (RFC 9728)',
                            'default'        => false,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * tabApiCatalog
     *
     * Builds the API Catalog tab configuration — repeater for RFC 9727 linkset entries.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The tab configuration array
     *
     */
    private function tabApiCatalog(): array
    {
        // Collect pages for the service-doc page selector
        return [
            'title'    => 'API Catalog',
            'sections' => [
                'entries' => [
                    'title'       => 'Linkset Entries',
                    'description' => 'Each entry is published in the <code>linkset</code> array at <code>/.well-known/api-catalog</code> (<a href="https://www.rfc-editor.org/rfc/rfc9727" target="_blank">RFC 9727</a>). Leave empty to use the auto-generated fallback.',
                    'fields'      => [
                        [
                            'id'           => 'api_catalog_entries',
                            'type'         => 'repeater',
                            'label'        => 'Entries',
                            'button_label' => 'Add Entry',
                            'collapsed'    => true,
                            'sortable'     => true,
                            'row_label'    => 'Entry',
                            'fields'       => [
                                [
                                    'id'          => 'anchor',
                                    'type'        => 'url',
                                    'label'       => 'Anchor URL',
                                    'sublabel'    => 'Base URL of the API this entry describes.',
                                    'placeholder' => home_url('/'),
                                    'required'    => true,
                                ],
                                [
                                    'id'          => 'service_desc',
                                    'type'        => 'url',
                                    'label'       => 'service-desc (OpenAPI spec URL)',
                                    'sublabel'    => 'Direct URL to the OpenAPI / Swagger specification file.',
                                    'placeholder' => 'https://example.com/openapi.json',
                                ],
                                [
                                    'id'       => 'service_doc',
                                    'type'     => 'url',
                                    'label'    => 'service-doc (Documentation URL)',
                                    'sublabel' => 'Human-readable API documentation page.',
                                ],
                                [
                                    'id'          => 'service_doc_page',
                                    'type'        => 'page_select',
                                    'label'       => 'service-doc — or select a page',
                                    'sublabel'    => 'Overrides the URL above if a page is selected.',
                                ],
                                [
                                    'id'          => 'status',
                                    'type'        => 'url',
                                    'label'       => 'status (Health endpoint URL)',
                                    'placeholder' => 'https://example.com/health',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * tabAgentSkills
     *
     * Builds the Agent Skills tab configuration — blog toggle, CPT checkboxes,
     * and custom skills repeater.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The tab configuration array
     *
     */
    private function tabAgentSkills(): array
    {
        return [
            'title'    => 'Agent Skills',
            'sections' => [
                'blog' => [
                    'title'  => 'Blog / Articles',
                    'fields' => [
                        [
                            'id'             => 'skill_blog_enabled',
                            'type'           => 'switch',
                            'label'          => 'Include Blog Articles',
                            'checkbox_label' => 'Expose blog search and article listing as agent skills',
                            'default'        => true,
                        ],
                    ],
                ],
                'cpts'          => $this->buildCptSection(),
                'custom_skills' => [
                    'title'       => 'Custom Skills',
                    'description' => 'Manually define additional entries in the agent skills index.',
                    'fields'      => [
                        [
                            'id'           => 'skill_custom',
                            'type'         => 'repeater',
                            'label'        => 'Custom Skills',
                            'button_label' => 'Add Skill',
                            'collapsed'    => true,
                            'sortable'     => true,
                            'row_label'    => 'Skill',
                            'fields'       => [
                                [
                                    'id'       => 'name',
                                    'type'     => 'text',
                                    'label'    => 'Name',
                                    'required' => true,
                                ],
                                [
                                    'id'      => 'type',
                                    'type'    => 'select',
                                    'label'   => 'Type',
                                    'options' => [
                                        'browse' => 'Browse',
                                        'search' => 'Search',
                                        'form'   => 'Form',
                                        'action' => 'Action',
                                        'api'    => 'API',
                                    ],
                                    'default' => 'browse',
                                ],
                                [
                                    'id'    => 'description',
                                    'type'  => 'textarea',
                                    'label' => 'Description',
                                    'rows'  => 3,
                                ],
                                [
                                    'id'   => 'url',
                                    'type' => 'url',
                                    'label' => 'URL',
                                ],
                                [
                                    'id'      => 'url_page',
                                    'type'    => 'page_select',
                                    'label'   => 'Or select a page',
                                    'sublabel' => 'Overrides the URL above if a page is selected.',
                                ],
                                [
                                    'id'       => 'sha256',
                                    'type'     => 'text',
                                    'label'    => 'sha256 Digest',
                                    'sublabel' => 'Optional. Hash of the skill file content per the Agent Skills RFC.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * tabContentSignals
     *
     * Builds the Content Signals tab configuration — ai-train, search,
     * and ai-input preference fields.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The tab configuration array
     *
     */
    private function tabContentSignals(): array
    {
        $yes_no = ['yes' => 'Yes', 'no' => 'No'];

        return [
            'title'    => 'Content Signals',
            'sections' => [
                'signals' => [
                    'title'       => 'AI Usage Preferences',
                    'description' => 'Declare AI content usage preferences published in <code>robots.txt</code> via the <a href="https://contentsignals.org/" target="_blank">Content Signals</a> spec.',
                    'fields'      => [
                        [
                            'id'       => 'cs_ai_train',
                            'type'     => 'radio',
                            'label'    => 'AI Training (ai-train)',
                            'sublabel' => 'Allow this content to be used to train AI models.',
                            'options'  => $yes_no,
                            'default'  => 'no',
                        ],
                        [
                            'id'       => 'cs_search',
                            'type'     => 'radio',
                            'label'    => 'Search Indexing (search)',
                            'sublabel' => 'Allow this content to be indexed by search engines.',
                            'options'  => $yes_no,
                            'default'  => 'yes',
                        ],
                        [
                            'id'       => 'cs_ai_input',
                            'type'     => 'radio',
                            'label'    => 'AI RAG Input (ai-input)',
                            'sublabel' => 'Allow this content to be used as input to AI retrieval / RAG systems.',
                            'options'  => $yes_no,
                            'default'  => 'no',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * tabMcpCard
     *
     * Builds the MCP Server Card tab configuration — server info fields
     * and capabilities repeater.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The tab configuration array
     *
     */
    private function tabMcpCard(): array
    {
        return [
            'title'    => 'MCP Server Card',
            'sections' => [
                'card' => [
                    'title'       => 'Server Card Info',
                    'description' => 'Configures <code>/.well-known/mcp/server-card.json</code> per <a href="https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127" target="_blank">SEP-1649</a>.',
                    'fields'      => [
                        [
                            'id'      => 'mcp_name',
                            'type'    => 'text',
                            'label'   => 'Server Name',
                            'default' => 'kevinpirnie.com',
                        ],
                        [
                            'id'      => 'mcp_version',
                            'type'    => 'text',
                            'label'   => 'Version',
                            'default' => '1.0.0',
                        ],
                        [
                            'id'      => 'mcp_desc',
                            'type'    => 'textarea',
                            'label'   => 'Description',
                            'rows'    => 3,
                            'default' => 'Personal portfolio and blog of Kevin Pirnie — WordPress Developer & DevOps Engineer.',
                        ],
                        [
                            'id'          => 'mcp_transport',
                            'type'        => 'url',
                            'label'       => 'Transport Endpoint URL',
                            'sublabel'    => 'Leave blank if no MCP server is currently running.',
                            'placeholder' => 'https://kevinpirnie.com/mcp',
                        ],
                    ],
                ],
                'capabilities' => [
                    'title'       => 'Capabilities',
                    'description' => 'Declare which MCP capabilities this server supports. Each entry adds a key to the <code>capabilities</code> object.',
                    'fields'      => [
                        [
                            'id'           => 'mcp_capabilities',
                            'type'         => 'repeater',
                            'label'        => 'Capabilities',
                            'button_label' => 'Add Capability',
                            'row_label'    => 'Capability',
                            'fields'       => [
                                [
                                    'id'          => 'capability_key',
                                    'type'        => 'text',
                                    'label'       => 'Capability Key',
                                    'placeholder' => 'e.g. tools, resources, prompts',
                                    'required'    => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * tabOAuth
     *
     * Builds the OAuth / OIDC tab configuration — protocol type, endpoints,
     * grant types, response types, token auth methods, and scopes.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The tab configuration array
     *
     */
    private function tabOAuth(): array
    {
        return [
            'title'    => 'OAuth / OIDC',
            'sections' => [
                'type' => [
                    'title'       => 'Discovery Type',
                    'description' => 'Enable the OAuth / OIDC feature toggle in the Features tab to activate these endpoints.',
                    'fields'      => [
                        [
                            'id'      => 'oauth_type',
                            'type'    => 'radio',
                            'label'   => 'Protocol',
                            'sublabel' => 'Determines which <code>/.well-known/</code> path is served.',
                            'options' => [
                                'oidc'   => 'OpenID Connect — <code>/.well-known/openid-configuration</code>',
                                'oauth2' => 'OAuth 2.0 only — <code>/.well-known/oauth-authorization-server</code>',
                            ],
                            'default' => 'oidc',
                        ],
                    ],
                ],
                'endpoints' => [
                    'title'  => 'Endpoints',
                    'fields' => [
                        [
                            'id'          => 'oauth_issuer',
                            'type'        => 'url',
                            'label'       => 'Issuer',
                            'sublabel'    => 'The canonical issuer identifier URL.',
                            'placeholder' => home_url('/'),
                        ],
                        [
                            'id'          => 'oauth_auth_endpoint',
                            'type'        => 'url',
                            'label'       => 'Authorization Endpoint',
                            'placeholder' => home_url('/oauth/authorize'),
                        ],
                        [
                            'id'          => 'oauth_token_endpoint',
                            'type'        => 'url',
                            'label'       => 'Token Endpoint',
                            'placeholder' => home_url('/oauth/token'),
                        ],
                        [
                            'id'          => 'oauth_jwks_uri',
                            'type'        => 'url',
                            'label'       => 'JWKS URI',
                            'placeholder' => home_url('/oauth/jwks.json'),
                        ],
                    ],
                ],
                'grant_types' => [
                    'title'  => 'Supported Grant Types',
                    'fields' => [
                        [
                            'id'      => 'oauth_grant_types',
                            'type'    => 'checkboxes',
                            'label'   => 'grant_types_supported',
                            'options' => [
                                'authorization_code' => 'authorization_code',
                                'client_credentials' => 'client_credentials',
                                'refresh_token'      => 'refresh_token',
                                'implicit'           => 'implicit',
                                'password'           => 'password',
                                'urn:ietf:params:oauth:grant-type:device_code' => 'device_code',
                            ],
                        ],
                    ],
                ],
                'response_types' => [
                    'title'  => 'Supported Response Types',
                    'fields' => [
                        [
                            'id'      => 'oauth_response_types',
                            'type'    => 'checkboxes',
                            'label'   => 'response_types_supported',
                            'options' => [
                                'code'             => 'code',
                                'token'            => 'token',
                                'id_token'         => 'id_token',
                                'code token'       => 'code token',
                                'code id_token'    => 'code id_token',
                                'token id_token'   => 'token id_token',
                                'code token id_token' => 'code token id_token',
                            ],
                        ],
                    ],
                ],
                'token_auth' => [
                    'title'  => 'Token Endpoint Auth Methods',
                    'fields' => [
                        [
                            'id'      => 'oauth_token_auth_methods',
                            'type'    => 'checkboxes',
                            'label'   => 'token_endpoint_auth_methods_supported',
                            'options' => [
                                'client_secret_basic' => 'client_secret_basic',
                                'client_secret_post'  => 'client_secret_post',
                                'client_secret_jwt'   => 'client_secret_jwt',
                                'private_key_jwt'     => 'private_key_jwt',
                                'none'                => 'none (PKCE)',
                            ],
                        ],
                    ],
                ],
                'scopes' => [
                    'title'       => 'Supported Scopes',
                    'description' => 'Each entry adds a scope string to <code>scopes_supported</code>.',
                    'fields'      => [
                        [
                            'id'           => 'oauth_scopes',
                            'type'         => 'repeater',
                            'label'        => 'Scopes',
                            'button_label' => 'Add Scope',
                            'row_label'    => 'Scope',
                            'fields'       => [
                                [
                                    'id'          => 'scope',
                                    'type'        => 'text',
                                    'label'       => 'Scope',
                                    'placeholder' => 'e.g. openid, profile, email, read:posts',
                                    'required'    => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * tabOAuthResource
     *
     * Builds the OAuth Protected Resource tab configuration — resource identifier,
     * authorization servers, bearer methods, and scopes.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The tab configuration array
     *
     */
    private function tabOAuthResource(): array
    {
        return [
            'title'    => 'OAuth Resource',
            'sections' => [
                'resource' => [
                    'title'       => 'Protected Resource Metadata',
                    'description' => 'Configures <code>/.well-known/oauth-protected-resource</code> per <a href="https://www.rfc-editor.org/rfc/rfc9728" target="_blank">RFC 9728</a>. Enable via the Features tab.',
                    'fields'      => [
                        [
                            'id'          => 'opr_resource',
                            'type'        => 'url',
                            'label'       => 'Resource Identifier',
                            'sublabel'    => 'Canonical URL identifying this protected resource.',
                            'placeholder' => home_url('/'),
                        ],
                    ],
                ],
                'auth_servers' => [
                    'title'       => 'Authorization Servers',
                    'description' => 'OAuth/OIDC issuer URLs that can issue tokens for this resource.',
                    'fields'      => [
                        [
                            'id'           => 'opr_auth_servers',
                            'type'         => 'repeater',
                            'label'        => 'Authorization Servers',
                            'button_label' => 'Add Server',
                            'row_label'    => 'Server',
                            'fields'       => [
                                [
                                    'id'          => 'server_url',
                                    'type'        => 'url',
                                    'label'       => 'Issuer URL',
                                    'placeholder' => 'https://auth.example.com',
                                    'required'    => true,
                                ],
                            ],
                        ],
                    ],
                ],
                'bearer_methods' => [
                    'title'  => 'Bearer Methods',
                    'fields' => [
                        [
                            'id'      => 'opr_bearer_methods',
                            'type'    => 'checkboxes',
                            'label'   => 'bearer_methods_supported',
                            'options' => [
                                'header' => 'header (Authorization: Bearer)',
                                'body'   => 'body (form parameter)',
                                'query'  => 'query (URL parameter)',
                            ],
                        ],
                    ],
                ],
                'scopes' => [
                    'title'       => 'Supported Scopes',
                    'description' => 'Scopes this resource accepts. Each entry adds a scope string to <code>scopes_supported</code>.',
                    'fields'      => [
                        [
                            'id'           => 'opr_scopes',
                            'type'         => 'repeater',
                            'label'        => 'Scopes',
                            'button_label' => 'Add Scope',
                            'row_label'    => 'Scope',
                            'fields'       => [
                                [
                                    'id'          => 'scope',
                                    'type'        => 'text',
                                    'label'       => 'Scope',
                                    'placeholder' => 'e.g. read:posts, write:comments',
                                    'required'    => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * tabWebMcp
     *
     * Builds the WebMCP tab configuration — built-in tool toggles.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The tab configuration array
     *
     */
    private function tabWebMcp(): array
    {
        return [
            'title'    => 'WebMCP',
            'sections' => [
                'tools' => [
                    'title'       => 'Built-in Tools',
                    'description' => 'Configure which tools are exposed to agents via <code>navigator.modelContext</code>. Requires the WebMCP feature toggle to be enabled.',
                    'fields'      => [
                        [
                            'id'             => 'webmcp_search',
                            'type'           => 'switch',
                            'label'          => 'Blog Search',
                            'checkbox_label' => 'Enable the blog search tool',
                            'default'        => true,
                            'conditional'    => ['field' => 'webmcp_enabled', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_search_desc',
                            'type'        => 'text',
                            'label'       => 'Search Tool Description',
                            'placeholder' => sprintf('Search %s', get_bloginfo('name')),
                            'conditional' => ['field' => 'webmcp_search', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'   => 'sep_portfolio',
                            'type' => 'separator',
                        ],
                        [
                            'id'             => 'webmcp_portfolio',
                            'type'           => 'switch',
                            'label'          => 'Portfolio Navigation',
                            'checkbox_label' => 'Enable the portfolio navigation tool',
                            'default'        => true,
                            'conditional'    => ['field' => 'webmcp_enabled', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_portfolio_desc',
                            'type'        => 'text',
                            'label'       => 'Portfolio Tool Description',
                            'placeholder' => sprintf('Browse the portfolio on %s', get_bloginfo('name')),
                            'conditional' => ['field' => 'webmcp_portfolio', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_portfolio_url',
                            'type'        => 'url',
                            'label'       => 'Portfolio URL',
                            'placeholder' => home_url('/portfolio/'),
                            'conditional' => ['field' => 'webmcp_portfolio', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'   => 'sep_contact',
                            'type' => 'separator',
                        ],
                        [
                            'id'             => 'webmcp_contact',
                            'type'           => 'switch',
                            'label'          => 'Contact Navigation',
                            'checkbox_label' => 'Enable the contact navigation tool',
                            'default'        => true,
                            'conditional'    => ['field' => 'webmcp_enabled', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_contact_desc',
                            'type'        => 'text',
                            'label'       => 'Contact Tool Description',
                            'placeholder' => sprintf('Contact %s', get_bloginfo('name')),
                            'conditional' => ['field' => 'webmcp_contact', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_contact_url',
                            'type'        => 'url',
                            'label'       => 'Contact URL',
                            'placeholder' => home_url('/contact/'),
                            'conditional' => ['field' => 'webmcp_contact', 'value' => true, 'condition' => '=='],
                        ],
                    ],
                ],
            ],
        ];
    }


    /**
     * buildCptSection
     *
     * Dynamically builds the Custom Post Types settings section from all
     * registered public CPTs, excluding built-in WordPress types.
     * Returns an info notice section if no qualifying CPTs are found.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, mixed> The section configuration array
     *
     */
    private function buildCptSection(): array
    {
        $all_cpts = get_post_types(['public' => true], 'objects');
        $opts     = [];

        foreach ($all_cpts as $slug => $obj) {
            if (in_array($slug, self::EXCLUDED_CPTS, true)) continue;
            if (in_array($slug, ['post', 'page'], true)) continue;
            $opts[$slug] = $obj->label;
        }

        if (empty($opts)) {
            return [
                'title'  => 'Custom Post Types',
                'fields' => [
                    [
                        'id'           => 'no_cpts_notice',
                        'type'         => 'message',
                        'message_type' => 'info',
                        'content'      => 'No custom post types are currently registered (other than built-ins).',
                    ],
                ],
            ];
        }

        return [
            'title'       => 'Custom Post Types',
            'description' => 'Each checked CPT generates a browsable skill entry pointing to its archive URL.',
            'fields'      => [
                [
                    'id'      => 'skill_cpts',
                    'type'    => 'checkboxes',
                    'label'   => 'Enabled CPTs',
                    'options' => $opts,
                ],
            ],
        ];
    }
}
