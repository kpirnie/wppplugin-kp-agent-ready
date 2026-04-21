<?php

/** 
 * SettingsPage
 * 
 * Registers the plugin's tabbed admin settings page using KPT WP Field Framework.
 * All settings are stored under the option key defined in Plugin::OPTION_KEY.
 * 
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 * 
 */

// setup the namespace
namespace KP\AgentReady\Settings;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

// pull in our namespaces
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
        // setup the default tabs
        $tabs = [
            'features'     => $this->tabFeatures(),
            'api_catalog'  => $this->tabApiCatalog(),
            'agent_skills' => $this->tabAgentSkills(),
            'mcp_card'     => $this->tabMcpCard(),
        ];

        // make sure they are enabled to pull them in
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

        // return the full config
        return [
            'page_title'         => __('Agent Ready', 'kp-agent-ready'),
            'menu_title'         => __('Agent Ready', 'kp-agent-ready'),
            'menu_slug'          => Plugin::OPTION_KEY,
            'icon_url'           => 'dashicons-superhero-alt',
            'position'           => 30,
            'show_export_import' => true,
            'tabs'               => $tabs,
            'tab_layout'         => 'vertical',
            'save_button'        => __('Save Your Settings', 'kp-agent-ready'),
            'autoload'           => true,
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
        // setup the default tabs
        $tabs = [
            'features'     => __('Features', 'kp-agent-ready'),
            'api_catalog'  => __('API Catalog', 'kp-agent-ready'),
            'agent_skills' => __('Agent Skills', 'kp-agent-ready'),
            'mcp_card'     => __('MCP Server Card', 'kp-agent-ready'),
        ];

        // make sure they are enabled
        if ($this->options['content_signals_enabled'] ?? true) {
            $tabs['content_signals'] = __('Content Signals', 'kp-agent-ready');
        }
        if ($this->options['oauth_enabled'] ?? false) {
            $tabs['oauth'] = __('OAuth / OIDC', 'kp-agent-ready');
        }
        if ($this->options['opr_enabled'] ?? false) {
            $tabs['oauth_resource'] = __('OAuth Resource', 'kp-agent-ready');
        }
        if ($this->options['webmcp_enabled'] ?? true) {
            $tabs['webmcp'] = __('WebMCP', 'kp-agent-ready');
        }

        // loop over the tabs and add them as submenu items
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
        // setup the tabs settings
        return [
            'title'    => __('Features', 'kp-agent-ready'),
            'sections' => [
                'toggles' => [
                    'title'  => __('Feature Toggles', 'kp-agent-ready'),
                    'fields' => [
                        [
                            'id'             => 'link_headers_enabled',
                            'type'           => 'switch',
                            'label'          => __('RFC 8288 Link Headers', 'kp-agent-ready'),
                            'checkbox_label' => __('Send Link response headers for agent discovery', 'kp-agent-ready'),
                            'default'        => true,
                        ],
                        [
                            'id'             => 'content_signals_enabled',
                            'type'           => 'switch',
                            'label'          => __('Content Signals', 'kp-agent-ready'),
                            'checkbox_label' => __('Append Content-Signal directives to robots.txt', 'kp-agent-ready'),
                            'default'        => true,
                        ],
                        [
                            'id'             => 'markdown_enabled',
                            'type'           => 'switch',
                            'label'          => __('Markdown Negotiation', 'kp-agent-ready'),
                            'checkbox_label' => __('Serve text/markdown when requested via Accept header', 'kp-agent-ready'),
                            'default'        => true,
                        ],
                        [
                            'id'             => 'webmcp_enabled',
                            'type'           => 'switch',
                            'label'          => __('WebMCP', 'kp-agent-ready'),
                            'checkbox_label' => __('Inject WebMCP tool definitions via navigator.modelContext', 'kp-agent-ready'),
                            'default'        => true,
                        ],
                        [
                            'id'             => 'oauth_enabled',
                            'type'           => 'switch',
                            'label'          => __('OAuth / OIDC Discovery', 'kp-agent-ready'),
                            'checkbox_label' => __('Serve OAuth or OpenID Connect discovery metadata', 'kp-agent-ready'),
                            'default'        => false,
                        ],
                        [
                            'id'             => 'opr_enabled',
                            'type'           => 'switch',
                            'label'          => __('OAuth Protected Resource', 'kp-agent-ready'),
                            'checkbox_label' => __('Serve OAuth Protected Resource Metadata (RFC 9728)', 'kp-agent-ready'),
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
        // setup the tabs settings
        return [
            'title'    => __('API Catalog', 'kp-agent-ready'),
            'sections' => [
                'entries' => [
                    'title'       => __('Linkset Entries', 'kp-agent-ready'),
                    'description' => __('Each entry is published in the <code>linkset</code> array at <code>/.well-known/api-catalog</code> (<a href="https://www.rfc-editor.org/rfc/rfc9727" target="_blank">RFC 9727</a>). Leave empty to use the auto-generated fallback.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'           => 'api_catalog_entries',
                            'type'         => 'repeater',
                            'label'        => __('Entries', 'kp-agent-ready'),
                            'button_label' => __('Add Entry', 'kp-agent-ready'),
                            'collapsed'    => true,
                            'sortable'     => true,
                            'row_label'    => __('Entry', 'kp-agent-ready'),
                            'fields'       => [
                                [
                                    'id'          => 'anchor',
                                    'type'        => 'url',
                                    'label'       => __('Anchor URL', 'kp-agent-ready'),
                                    'sublabel'    => __('Base URL of the API this entry describes.', 'kp-agent-ready'),
                                    'placeholder' => home_url('/'),
                                    'required'    => true,
                                ],
                                [
                                    'id'          => 'service_desc',
                                    'type'        => 'url',
                                    'label'       => __('service-desc (OpenAPI spec URL)', 'kp-agent-ready'),
                                    'sublabel'    => __('Direct URL to the OpenAPI / Swagger specification file.', 'kp-agent-ready'),
                                    'placeholder' => 'https://example.com/openapi.json',
                                ],
                                [
                                    'id'       => 'service_doc',
                                    'type'     => 'url',
                                    'label'    => __('service-doc (Documentation URL)', 'kp-agent-ready'),
                                    'sublabel' => __('Human-readable API documentation page.', 'kp-agent-ready'),
                                ],
                                [
                                    'id'          => 'service_doc_page',
                                    'type'        => 'page_select',
                                    'label'       => __('service-doc — or select a page', 'kp-agent-ready'),
                                    'sublabel'    => __('Overrides the URL above if a page is selected.', 'kp-agent-ready'),
                                ],
                                [
                                    'id'          => 'status',
                                    'type'        => 'url',
                                    'label'       => __('status (Health endpoint URL)', 'kp-agent-ready'),
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
        // setup the tabs settings
        return [
            'title'    => __('Agent Skills', 'kp-agent-ready'),
            'sections' => [
                'blog' => [
                    'title'  => __('Blog / Articles', 'kp-agent-ready'),
                    'fields' => [
                        [
                            'id'             => 'skill_blog_enabled',
                            'type'           => 'switch',
                            'label'          => __('Include Blog Articles', 'kp-agent-ready'),
                            'checkbox_label' => __('Expose blog search and article listing as agent skills', 'kp-agent-ready'),
                            'default'        => true,
                        ],
                    ],
                ],
                'cpts'          => $this->buildCptSection(),
                'custom_skills' => [
                    'title'       => __('Custom Skills', 'kp-agent-ready'),
                    'description' => __('Manually define additional entries in the agent skills index.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'           => 'skill_custom',
                            'type'         => 'repeater',
                            'label'        => __('Custom Skills', 'kp-agent-ready'),
                            'button_label' => __('Add Skill', 'kp-agent-ready'),
                            'collapsed'    => true,
                            'sortable'     => true,
                            'row_label'    => __('Skill', 'kp-agent-ready'),
                            'fields'       => [
                                [
                                    'id'       => 'name',
                                    'type'     => 'text',
                                    'label'    => __('Name', 'kp-agent-ready'),
                                    'required' => true,
                                ],
                                [
                                    'id'      => 'type',
                                    'type'    => 'select',
                                    'label'   => __('Type', 'kp-agent-ready'),
                                    'options' => [
                                        'browse' => __('Browse', 'kp-agent-ready'),
                                        'search' => __('Search', 'kp-agent-ready'),
                                        'form'   => __('Form', 'kp-agent-ready'),
                                        'action' => __('Action', 'kp-agent-ready'),
                                        'api'    => __('API', 'kp-agent-ready'),
                                    ],
                                    'default' => 'browse',
                                ],
                                [
                                    'id'    => 'description',
                                    'type'  => 'textarea',
                                    'label' => __('Description', 'kp-agent-ready'),
                                    'rows'  => 3,
                                ],
                                [
                                    'id'   => 'url',
                                    'type' => 'url',
                                    'label' => __('URL', 'kp-agent-ready'),
                                ],
                                [
                                    'id'      => 'url_page',
                                    'type'    => 'page_select',
                                    'label'   => __('Or select a page', 'kp-agent-ready'),
                                    'sublabel' => __('Overrides the URL above if a page is selected.', 'kp-agent-ready'),
                                ],
                                [
                                    'id'       => 'sha256',
                                    'type'     => 'text',
                                    'label'    => __('sha256 Digest', 'kp-agent-ready'),
                                    'sublabel' => __('Optional. Hash of the skill file content per the Agent Skills RFC.', 'kp-agent-ready'),
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
        // yes? no?
        $yes_no = ['yes' => __('Yes', 'kp-agent-ready'), 'no' => __('No', 'kp-agent-ready')];

        // setup the tabs settings
        return [
            'title'    => __('Content Signals', 'kp-agent-ready'),
            'sections' => [
                'signals' => [
                    'title'       => __('AI Usage Preferences', 'kp-agent-ready'),
                    'description' => __('Declare AI content usage preferences published in <code>robots.txt</code> via the <a href="https://contentsignals.org/" target="_blank">Content Signals</a> spec.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'       => 'cs_ai_train',
                            'type'     => 'radio',
                            'label'    => __('AI Training (ai-train)', 'kp-agent-ready'),
                            'sublabel' => __('Allow this content to be used to train AI models.', 'kp-agent-ready'),
                            'options'  => $yes_no,
                            'default'  => 'no',
                        ],
                        [
                            'id'       => 'cs_search',
                            'type'     => 'radio',
                            'label'    => __('Search Indexing (search)', 'kp-agent-ready'),
                            'sublabel' => __('Allow this content to be indexed by search engines.', 'kp-agent-ready'),
                            'options'  => $yes_no,
                            'default'  => 'yes',
                        ],
                        [
                            'id'       => 'cs_ai_input',
                            'type'     => 'radio',
                            'label'    => __('AI RAG Input (ai-input)', 'kp-agent-ready'),
                            'sublabel' => __('Allow this content to be used as input to AI retrieval / RAG systems.', 'kp-agent-ready'),
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
        // setup the tabs settings
        return [
            'title'    => __('MCP Server Card', 'kp-agent-ready'),
            'sections' => [
                'card' => [
                    'title'       => __('Server Card Info', 'kp-agent-ready'),
                    'description' => __('Configures <code>/.well-known/mcp/server-card.json</code> per <a href="https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127" target="_blank">SEP-1649</a>.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'      => 'mcp_name',
                            'type'    => 'text',
                            'label'   => __('Server Name', 'kp-agent-ready'),
                            'default' => get_bloginfo('name'),
                        ],
                        [
                            'id'      => 'mcp_version',
                            'type'    => 'text',
                            'label'   => __('Version', 'kp-agent-ready'),
                            'default' => __('1.0.0', 'kp-agent-ready'),
                        ],
                        [
                            'id'      => 'mcp_desc',
                            'type'    => 'textarea',
                            'label'   => __('Description', 'kp-agent-ready'),
                            'rows'    => 3,
                            'default' => get_bloginfo('description'),
                        ],
                        [
                            'id'          => 'mcp_transport',
                            'type'        => 'url',
                            'label'       => __('Transport Endpoint URL', 'kp-agent-ready'),
                            'sublabel'    => __('Leave blank if no MCP server is currently running.', 'kp-agent-ready'),
                            'placeholder' => home_url('/mcp'),
                        ],
                    ],
                ],
                'capabilities' => [
                    'title'       => __('Capabilities', 'kp-agent-ready'),
                    'description' => __('Declare which MCP capabilities this server supports. Each entry adds a key to the <code>capabilities</code> object.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'           => 'mcp_capabilities',
                            'type'         => 'repeater',
                            'label'        => __('Capabilities', 'kp-agent-ready'),
                            'button_label' => __('Add Capability', 'kp-agent-ready'),
                            'row_label'    => __('Capability', 'kp-agent-ready'),
                            'fields'       => [
                                [
                                    'id'          => 'capability_key',
                                    'type'        => 'text',
                                    'label'       => __('Capability Key', 'kp-agent-ready'),
                                    'placeholder' => __('e.g. tools, resources, prompts', 'kp-agent-ready'),
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
        // setup the tabs settings
        return [
            'title'    => __('OAuth / OIDC', 'kp-agent-ready'),
            'sections' => [
                'type' => [
                    'title'       => __('Discovery Type', 'kp-agent-ready'),
                    'description' => __('Enable the OAuth / OIDC feature toggle in the Features tab to activate these endpoints.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'      => 'oauth_type',
                            'type'    => 'radio',
                            'label'   => __('Protocol', 'kp-agent-ready'),
                            'sublabel' => __('Determines which <code>/.well-known/</code> path is served.', 'kp-agent-ready'),
                            'options' => [
                                'oidc'   => __('OpenID Connect — <code>/.well-known/openid-configuration</code>', 'kp-agent-ready'),
                                'oauth2' => __('OAuth 2.0 only — <code>/.well-known/oauth-authorization-server</code>', 'kp-agent-ready'),
                            ],
                            'default' => 'oidc',
                        ],
                    ],
                ],
                'endpoints' => [
                    'title'  => __('Endpoints', 'kp-agent-ready'),
                    'fields' => [
                        [
                            'id'          => 'oauth_issuer',
                            'type'        => 'url',
                            'label'       => __('Issuer', 'kp-agent-ready'),
                            'sublabel'    => __('The canonical issuer identifier URL.', 'kp-agent-ready'),
                            'placeholder' => home_url('/'),
                        ],
                        [
                            'id'          => 'oauth_auth_endpoint',
                            'type'        => 'url',
                            'label'       => __('Authorization Endpoint', 'kp-agent-ready'),
                            'placeholder' => home_url('/oauth/authorize'),
                        ],
                        [
                            'id'          => 'oauth_token_endpoint',
                            'type'        => 'url',
                            'label'       => __('Token Endpoint', 'kp-agent-ready'),
                            'placeholder' => home_url('/oauth/token'),
                        ],
                        [
                            'id'          => 'oauth_jwks_uri',
                            'type'        => 'url',
                            'label'       => __('JWKS URI', 'kp-agent-ready'),
                            'placeholder' => home_url('/oauth/jwks.json'),
                        ],
                    ],
                ],
                'grant_types' => [
                    'title'  => __('Supported Grant Types', 'kp-agent-ready'),
                    'fields' => [
                        [
                            'id'      => 'oauth_grant_types',
                            'type'    => 'checkboxes',
                            'label'   => __('Grant Types Supported', 'kp-agent-ready'),
                            'options' => [
                                'authorization_code' => __('Auth. Code', 'kp-agent-ready'),
                                'client_credentials' => __('Client Credentials', 'kp-agent-ready'),
                                'refresh_token'      => __('Refresh Token', 'kp-agent-ready'),
                                'implicit'           => __('Implicit', 'kp-agent-ready'),
                                'password'           => __('Password', 'kp-agent-ready'),
                                'urn:ietf:params:oauth:grant-type:device_code' => __('Device Code', 'kp-agent-ready'),
                            ],
                        ],
                    ],
                ],
                'response_types' => [
                    'title'  => __('Supported Response Types', 'kp-agent-ready'),
                    'fields' => [
                        [
                            'id'      => 'oauth_response_types',
                            'type'    => 'checkboxes',
                            'label'   => __('Response Types Supported', 'kp-agent-ready'),
                            'options' => [
                                'code'             => __('Code', 'kp-agent-ready'),
                                'token'            => __('Token', 'kp-agent-ready'),
                                'id_token'         => __('ID token', 'kp-agent-ready'),
                                'code token'       => __('Code Token', 'kp-agent-ready'),
                                'code id_token'    => __('Code ID Token', 'kp-agent-ready'),
                                'token id_token'   => __('Token ID Token', 'kp-agent-ready'),
                                'code token id_token' => __('Code Token ID Token', 'kp-agent-ready'),
                            ],
                        ],
                    ],
                ],
                'token_auth' => [
                    'title'  => __('Token Endpoint Auth Methods', 'kp-agent-ready'),
                    'fields' => [
                        [
                            'id'      => 'oauth_token_auth_methods',
                            'type'    => 'checkboxes',
                            'label'   => __('Endpoint Auth Methods', 'kp-agent-ready'),
                            'options' => [
                                'client_secret_basic' => __('Client Secret Basic', 'kp-agent-ready'),
                                'client_secret_post'  => __('Client Secret Post', 'kp-agent-ready'),
                                'client_secret_jwt'   => __('Client Secret JWT', 'kp-agent-ready'),
                                'private_key_jwt'     => __('Private Key JWT', 'kp-agent-ready'),
                                'none'                => __('none (PKCE)', 'kp-agent-ready'),
                            ],
                        ],
                    ],
                ],
                'scopes' => [
                    'title'       => __('Supported Scopes', 'kp-agent-ready'),
                    'description' => __('Each entry adds a scope string to <code>scopes_supported</code>.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'           => 'oauth_scopes',
                            'type'         => 'repeater',
                            'label'        => __('Scopes', 'kp-agent-ready'),
                            'button_label' => __('Add Scope', 'kp-agent-ready'),
                            'row_label'    => __('Scope', 'kp-agent-ready'),
                            'fields'       => [
                                [
                                    'id'          => 'scope',
                                    'type'        => 'text',
                                    'label'       => __('Scope', 'kp-agent-ready'),
                                    'placeholder' => __('e.g. openid, profile, email, read:posts', 'kp-agent-ready'),
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
            'title'    => __('OAuth Resource', 'kp-agent-ready'),
            'sections' => [
                'resource' => [
                    'title'       => __('Protected Resource Metadata', 'kp-agent-ready'),
                    'description' => __('Configures <code>/.well-known/oauth-protected-resource</code> per <a href="https://www.rfc-editor.org/rfc/rfc9728" target="_blank">RFC 9728</a>. Enable via the Features tab.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'          => 'opr_resource',
                            'type'        => 'url',
                            'label'       => __('Resource Identifier', 'kp-agent-ready'),
                            'sublabel'    => __('Canonical URL identifying this protected resource.', 'kp-agent-ready'),
                            'placeholder' => home_url('/'),
                        ],
                    ],
                ],
                'auth_servers' => [
                    'title'       => __('Authorization Servers', 'kp-agent-ready'),
                    'description' => __('OAuth/OIDC issuer URLs that can issue tokens for this resource.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'           => 'opr_auth_servers',
                            'type'         => 'repeater',
                            'label'        => __('Authorization Servers', 'kp-agent-ready'),
                            'button_label' => __('Add Server', 'kp-agent-ready'),
                            'row_label'    => __('Server', 'kp-agent-ready'),
                            'fields'       => [
                                [
                                    'id'          => 'server_url',
                                    'type'        => 'url',
                                    'label'       => __('Issuer URL', 'kp-agent-ready'),
                                    'placeholder' => 'https://auth.example.com',
                                    'required'    => true,
                                ],
                            ],
                        ],
                    ],
                ],
                'bearer_methods' => [
                    'title'  => __('Bearer Methods', 'kp-agent-ready'),
                    'fields' => [
                        [
                            'id'      => 'opr_bearer_methods',
                            'type'    => 'checkboxes',
                            'label'   => __('Bearer Methods Supported', 'kp-agent-ready'),
                            'options' => [
                                'header' => __('header (Authorization: Bearer)', 'kp-agent-ready'),
                                'body'   => __('body (form parameter)', 'kp-agent-ready'),
                                'query'  => __('query (URL parameter)', 'kp-agent-ready'),
                            ],
                        ],
                    ],
                ],
                'scopes' => [
                    'title'       => __('Supported Scopes', 'kp-agent-ready'),
                    'description' => __('Scopes this resource accepts. Each entry adds a scope string to <code>scopes_supported</code>.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'           => 'opr_scopes',
                            'type'         => 'repeater',
                            'label'        => __('Scopes', 'kp-agent-ready'),
                            'button_label' => __('Add Scope', 'kp-agent-ready'),
                            'row_label'    => __('Scope', 'kp-agent-ready'),
                            'fields'       => [
                                [
                                    'id'          => 'scope',
                                    'type'        => 'text',
                                    'label'       => __('Scope', 'kp-agent-ready'),
                                    'placeholder' => __('e.g. read:posts, write:comments', 'kp-agent-ready'),
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
            'title'    => __('WebMCP', 'kp-agent-ready'),
            'sections' => [
                'tools' => [
                    'title'       => __('Built-in Tools', 'kp-agent-ready'),
                    'description' => __('Configure which tools are exposed to agents via <code>navigator.modelContext</code>. Requires the WebMCP feature toggle to be enabled.', 'kp-agent-ready'),
                    'fields'      => [
                        [
                            'id'             => 'webmcp_search',
                            'type'           => 'switch',
                            'label'          => __('Blog Search', 'kp-agent-ready'),
                            'checkbox_label' => __('Enable the blog search tool', 'kp-agent-ready'),
                            'default'        => true,
                            'conditional'    => ['field' => 'webmcp_enabled', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_search_desc',
                            'type'        => 'text',
                            'label'       => __('Search Tool Description', 'kp-agent-ready'),
                            // translators: %s is the site name
                            'placeholder' => sprintf(__('Search %s', 'kp-agent-ready'), get_bloginfo('name')),
                            'conditional' => ['field' => 'webmcp_search', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'   => 'sep_portfolio',
                            'type' => 'separator',
                        ],
                        [
                            'id'             => 'webmcp_portfolio',
                            'type'           => 'switch',
                            'label'          => __('Portfolio Navigation', 'kp-agent-ready'),
                            'checkbox_label' => __('Enable the portfolio navigation tool', 'kp-agent-ready'),
                            'default'        => true,
                            'conditional'    => ['field' => 'webmcp_enabled', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_portfolio_desc',
                            'type'        => 'text',
                            'label'       => __('Portfolio Tool Description', 'kp-agent-ready'),
                            // translators: %s is the site name
                            'placeholder' => sprintf(__('Browse the portfolio on %s', 'kp-agent-ready'), get_bloginfo('name')),
                            'conditional' => ['field' => 'webmcp_portfolio', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_portfolio_url',
                            'type'        => 'url',
                            'label'       => __('Portfolio URL', 'kp-agent-ready'),
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
                            'label'          => __('Contact Navigation', 'kp-agent-ready'),
                            'checkbox_label' => __('Enable the contact navigation tool', 'kp-agent-ready'),
                            'default'        => true,
                            'conditional'    => ['field' => 'webmcp_enabled', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_contact_desc',
                            'type'        => 'text',
                            'label'       => __('Contact Tool Description', 'kp-agent-ready'),
                            // translators: %s is the site name
                            'placeholder' => sprintf(__('Contact %s', 'kp-agent-ready'), get_bloginfo('name')),
                            'conditional' => ['field' => 'webmcp_contact', 'value' => true, 'condition' => '=='],
                        ],
                        [
                            'id'          => 'webmcp_contact_url',
                            'type'        => 'url',
                            'label'       => __('Contact URL', 'kp-agent-ready'),
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
                'title'  => __('Custom Post Types', 'kp-agent-ready'),
                'fields' => [
                    [
                        'id'           => 'no_cpts_notice',
                        'type'         => 'message',
                        'message_type' => 'info',
                        'content'      => __('No custom post types are currently registered (other than built-ins).', 'kp-agent-ready'),
                    ],
                ],
            ];
        }

        return [
            'title'       => __('Custom Post Types', 'kp-agent-ready'),
            'description' => __('Each checked CPT generates a browsable skill entry pointing to its archive URL.', 'kp-agent-ready'),
            'fields'      => [
                [
                    'id'      => 'skill_cpts',
                    'type'    => 'multiselect',
                    'label'   => __('Enabled CPTs', 'kp-agent-ready'),
                    'options' => $opts,
                ],
            ],
        ];
    }
}
