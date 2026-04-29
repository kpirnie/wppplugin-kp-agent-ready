<?php

/**
 * SettingsPage
 *
 * Registers the plugin's tabbed admin settings page using the native
 * WordPress Settings API. All settings are stored under the option
 * key defined in Plugin::OPTION_KEY.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 *
 */

// setup the namespace
namespace KPAgentReady\Settings;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

// pull in our namespace
use KPAgentReady\Plugin;

/**
 * SettingsPage
 *
 * Registers the plugin's tabbed admin settings page using the native
 * WordPress Settings API. All settings are stored under the option
 * key defined in Plugin::OPTION_KEY.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class SettingsPage
{

    /** CPT slugs excluded from the CPT skill selection. */
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

    /**
     * Maps each tab slug to the option field IDs it owns.
     * Used by the sanitizer to merge only the submitted tab's fields.
     */
    private const TAB_FIELDS = [
        'features' => [
            'link_headers_enabled',
            'content_signals_enabled',
            'markdown_enabled',
            'webmcp_enabled',
            'oauth_enabled',
            'opr_enabled',
        ],
        'api_catalog'     => ['api_catalog_entries'],
        'agent_skills'    => ['skill_blog_enabled', 'skill_cpts', 'skill_custom'],
        'content_signals' => ['cs_ai_train', 'cs_search', 'cs_ai_input'],
        'mcp_card'        => ['mcp_name', 'mcp_version', 'mcp_desc', 'mcp_transport', 'mcp_capabilities'],
        'oauth'           => [
            'oauth_type',
            'oauth_issuer',
            'oauth_auth_endpoint',
            'oauth_token_endpoint',
            'oauth_jwks_uri',
            'oauth_grant_types',
            'oauth_response_types',
            'oauth_token_auth_methods',
            'oauth_scopes',
        ],
        'oauth_resource' => ['opr_resource', 'opr_auth_servers', 'opr_bearer_methods', 'opr_scopes'],
        'webmcp'         => [
            'webmcp_search',
            'webmcp_search_desc',
            'webmcp_portfolio',
            'webmcp_portfolio_desc',
            'webmcp_portfolio_url',
            'webmcp_contact',
            'webmcp_contact_desc',
            'webmcp_contact_url',
        ],
    ];

    /** Boolean (switch) fields — explicitly set to false when unchecked. */
    private const BOOL_FIELDS = [
        'link_headers_enabled',
        'content_signals_enabled',
        'markdown_enabled',
        'webmcp_enabled',
        'oauth_enabled',
        'opr_enabled',
        'skill_blog_enabled',
        'webmcp_search',
        'webmcp_portfolio',
        'webmcp_contact',
    ];

    /** Array fields — default to [] when absent from POST. */
    private const ARRAY_FIELDS = [
        'api_catalog_entries',
        'skill_cpts',
        'skill_custom',
        'mcp_capabilities',
        'oauth_grant_types',
        'oauth_response_types',
        'oauth_token_auth_methods',
        'oauth_scopes',
        'opr_auth_servers',
        'opr_bearer_methods',
        'opr_scopes',
    ];

    /** URL scalar fields — sanitized with esc_url_raw. */
    private const URL_FIELDS = [
        'oauth_issuer',
        'oauth_auth_endpoint',
        'oauth_token_endpoint',
        'oauth_jwks_uri',
        'mcp_transport',
        'webmcp_portfolio_url',
        'webmcp_contact_url',
        'opr_resource',
    ];

    /** @param array<string, mixed> $options */
    public function __construct(private array $options) {}

    /**
     * register
     *
     * Attaches all hooks required to run the settings page.
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
        add_action('admin_menu',             [$this, 'registerMenus'],   20);
        add_action('admin_init',             [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts',  [$this, 'enqueueAssets']);
    }

    /**
     * registerMenus
     *
     * Registers the top-level menu page and all submenu tab links.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function registerMenus(): void
    {
        add_menu_page(
            __('Agent Ready', 'kp-agent-ready'),
            __('Agent Ready', 'kp-agent-ready'),
            'manage_options',
            Plugin::OPTION_KEY,
            [$this, 'renderPage'],
            'dashicons-superhero-alt',
            30
        );

        $this->registerSubmenus();
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
        foreach ($this->getEnabledTabs() as $tab => $label) {
            $url = add_query_arg('tab', $tab, 'admin.php?page=' . Plugin::OPTION_KEY);
            add_submenu_page(Plugin::OPTION_KEY, $label, $label, 'manage_options', $url);
        }
    }

    /**
     * registerSettings
     *
     * Registers the single option key with the WordPress Settings API.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function registerSettings(): void
    {
        register_setting(Plugin::OPTION_KEY, Plugin::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default'           => [],
        ]);
    }

    /**
     * enqueueAssets
     *
     * Enqueues inline admin styles and the repeater script on plugin pages only.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $hook The current admin page hook suffix
     *
     * @return void This method does not return anything
     *
     */
    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, Plugin::OPTION_KEY) === false) {
            return;
        }

        wp_enqueue_style(
            'kp-agent-ready-admin',
            KPAGRE_URL . 'assets/admin.css',
            ['wp-admin'],
            KPAGRE_VERSION
        );

        wp_enqueue_script(
            'kp-agent-ready-admin',
            KPAGRE_URL . 'assets/admin.js',
            [],
            KPAGRE_VERSION,
            ['in_footer' => true]
        );
    }

    // =========================================================================
    // Sanitization
    // =========================================================================

    /**
     * sanitize
     *
     * Sanitizes submitted settings. When saving from the tabbed form the
     * current tab's fields are merged over the existing stored values so
     * other tabs' data is preserved. Programmatic updates (no _kp_tab key)
     * sanitize and return the full input directly.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param mixed $input Raw submitted option data
     *
     * @return array Sanitized merged options
     *
     */
    public function sanitize(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        // Programmatic update path (export/import, etc.) — no tab indicator
        if (!isset($input['_kp_tab'])) {
            return $this->sanitizeFull($input);
        }

        $current_tab = sanitize_key($input['_kp_tab']);
        unset($input['_kp_tab']);

        // Preserve existing values for all other tabs
        $existing   = (array) get_option(Plugin::OPTION_KEY, []);
        $merged     = $existing;
        $tab_fields = self::TAB_FIELDS[$current_tab] ?? [];

        foreach ($tab_fields as $field) {
            if (in_array($field, self::BOOL_FIELDS, true)) {
                // Unchecked switches are absent from POST — must be explicit false
                $merged[$field] = !empty($input[$field]);
            } elseif (in_array($field, self::ARRAY_FIELDS, true)) {
                $merged[$field] = $this->sanitizeArrayField($field, $input[$field] ?? []);
            } else {
                $merged[$field] = $this->sanitizeScalarField($field, $input[$field] ?? '');
            }
        }

        return $merged;
    }

    /**
     * sanitizeFull
     *
     * Sanitizes a complete options array for programmatic saves.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array $input Raw input array
     *
     * @return array Sanitized options
     *
     */
    private function sanitizeFull(array $input): array
    {
        $all_fields = array_merge(...array_values(self::TAB_FIELDS));
        $merged     = [];

        foreach ($all_fields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            if (in_array($field, self::BOOL_FIELDS, true)) {
                $merged[$field] = (bool) $input[$field];
            } elseif (in_array($field, self::ARRAY_FIELDS, true)) {
                $merged[$field] = $this->sanitizeArrayField($field, (array) $input[$field]);
            } else {
                $merged[$field] = $this->sanitizeScalarField($field, $input[$field]);
            }
        }

        return $merged;
    }

    /**
     * sanitizeArrayField
     *
     * Dispatches array field sanitization by field ID.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $field Field ID
     * @param mixed  $value Raw value
     *
     * @return array Sanitized array
     *
     */
    private function sanitizeArrayField(string $field, mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return match ($field) {
            'api_catalog_entries'      => $this->sanitizeApiCatalogEntries($value),
            'skill_custom'             => $this->sanitizeCustomSkills($value),
            'mcp_capabilities'         => $this->sanitizeMcpCapabilities($value),
            'oauth_scopes', 'opr_scopes' => $this->sanitizeScopes($value),
            'opr_auth_servers'         => $this->sanitizeAuthServers($value),
            // response types can contain spaces ("code token"), so use sanitize_text_field
            'oauth_response_types'     => array_values(array_filter(array_map('sanitize_text_field', $value))),
            default                    => array_values(array_filter(array_map('sanitize_key', $value))),
        };
    }

    /**
     * sanitizeScalarField
     *
     * Sanitizes a scalar field based on its ID.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $field Field ID
     * @param mixed  $value Raw value
     *
     * @return string Sanitized string value
     *
     */
    private function sanitizeScalarField(string $field, mixed $value): string
    {
        if (in_array($field, self::URL_FIELDS, true)) {
            return esc_url_raw(sanitize_text_field((string) $value));
        }

        if ($field === 'mcp_desc') {
            return sanitize_textarea_field((string) $value);
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * sanitizeApiCatalogEntries
     *
     * Sanitizes the API catalog repeater rows.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array $rows Raw repeater rows
     *
     * @return array Sanitized rows
     *
     */
    private function sanitizeApiCatalogEntries(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            if (!is_array($row) || empty(trim($row['anchor'] ?? ''))) {
                continue;
            }

            $clean[] = [
                'anchor'           => esc_url_raw($row['anchor']),
                'service_desc'     => esc_url_raw($row['service_desc'] ?? ''),
                'service_doc'      => esc_url_raw($row['service_doc'] ?? ''),
                'service_doc_page' => absint($row['service_doc_page'] ?? 0),
                'status'           => esc_url_raw($row['status'] ?? ''),
            ];
        }

        return $clean;
    }

    /**
     * sanitizeCustomSkills
     *
     * Sanitizes the custom skills repeater rows.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array $rows Raw repeater rows
     *
     * @return array Sanitized rows
     *
     */
    private function sanitizeCustomSkills(array $rows): array
    {
        $allowed_types = ['browse', 'search', 'form', 'action', 'api'];
        $clean         = [];

        foreach ($rows as $row) {
            if (!is_array($row) || empty(trim($row['name'] ?? ''))) {
                continue;
            }

            $clean[] = [
                'name'        => sanitize_text_field($row['name']),
                'type'        => in_array($row['type'] ?? 'browse', $allowed_types, true) ? $row['type'] : 'browse',
                'description' => sanitize_textarea_field($row['description'] ?? ''),
                'url'         => esc_url_raw($row['url'] ?? ''),
                'url_page'    => absint($row['url_page'] ?? 0),
                'sha256'      => sanitize_text_field($row['sha256'] ?? ''),
            ];
        }

        return $clean;
    }

    /**
     * sanitizeMcpCapabilities
     *
     * Sanitizes the MCP capabilities repeater rows.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array $rows Raw repeater rows
     *
     * @return array Sanitized rows
     *
     */
    private function sanitizeMcpCapabilities(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $key = sanitize_key(trim($row['capability_key'] ?? ''));
            if ($key) {
                $clean[] = ['capability_key' => $key];
            }
        }

        return $clean;
    }

    /**
     * sanitizeScopes
     *
     * Sanitizes an OAuth/OIDC scopes repeater.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array $rows Raw repeater rows
     *
     * @return array Sanitized rows
     *
     */
    private function sanitizeScopes(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $scope = sanitize_text_field(trim($row['scope'] ?? ''));
            if ($scope) {
                $clean[] = ['scope' => $scope];
            }
        }

        return $clean;
    }

    /**
     * sanitizeAuthServers
     *
     * Sanitizes the OAuth resource authorization servers repeater.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array $rows Raw repeater rows
     *
     * @return array Sanitized rows
     *
     */
    private function sanitizeAuthServers(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $url = esc_url_raw(trim($row['server_url'] ?? ''));
            if ($url) {
                $clean[] = ['server_url' => $url];
            }
        }

        return $clean;
    }

    // =========================================================================
    // Page rendering
    // =========================================================================

    /**
     * renderPage
     *
     * Renders the main settings page with vertical tab navigation.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tabs        = $this->getEnabledTabs();
        $current_tab = sanitize_key($_GET['tab'] ?? array_key_first($tabs)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // Fall back to first tab if the requested one is no longer enabled
        if (!array_key_exists($current_tab, $tabs)) {
            $current_tab = array_key_first($tabs);
        }

        if (isset($_GET['settings-updated'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            add_settings_error(
                Plugin::OPTION_KEY . '_messages',
                'saved',
                __('Settings saved.', 'kp-agent-ready'),
                'updated'
            );
        }

?>
        <div class="wrap kp-ar-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors(Plugin::OPTION_KEY . '_messages'); ?>
            <div class="kp-ar-layout">
                <nav class="kp-ar-tab-nav" aria-label="<?php esc_attr_e('Settings tabs', 'kp-agent-ready'); ?>">
                    <?php foreach ($tabs as $tab_id => $label): ?>
                        <a href="<?php echo esc_url(add_query_arg('tab', $tab_id)); ?>"
                            class="kp-ar-tab<?php echo $current_tab === $tab_id ? ' active' : ''; ?>">
                            <?php echo esc_html($label); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="kp-ar-tab-content">
                    <form action="options.php" method="post">
                        <?php settings_fields(Plugin::OPTION_KEY); ?>
                        <input type="hidden"
                            name="<?php echo esc_attr(Plugin::OPTION_KEY); ?>[_kp_tab]"
                            value="<?php echo esc_attr($current_tab); ?>">
                        <?php $this->renderTab($current_tab); ?>
                        <?php submit_button(__('Save Your Settings', 'kp-agent-ready')); ?>
                    </form>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * renderTab
     *
     * Dispatches rendering to the correct tab method.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $tab Current tab slug
     *
     * @return void This method does not return anything
     *
     */
    private function renderTab(string $tab): void
    {
        match ($tab) {
            'features'        => $this->renderFeaturesTab(),
            'api_catalog'     => $this->renderApiCatalogTab(),
            'agent_skills'    => $this->renderAgentSkillsTab(),
            'content_signals' => $this->renderContentSignalsTab(),
            'mcp_card'        => $this->renderMcpCardTab(),
            'oauth'           => $this->renderOAuthTab(),
            'oauth_resource'  => $this->renderOAuthResourceTab(),
            'webmcp'          => $this->renderWebMcpTab(),
            default           => null,
        };
    }

    // =========================================================================
    // Tab renderers
    // =========================================================================

    /**
     * renderFeaturesTab
     *
     * Renders the Features tab — master toggles for all features.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function renderFeaturesTab(): void
    {
        $this->sectionOpen(__('Feature Toggles', 'kp-agent-ready'));
        $this->fieldSwitch('link_headers_enabled',    __('RFC 8288 Link Headers',   'kp-agent-ready'), __('Send Link response headers for agent discovery',                  'kp-agent-ready'), true);
        $this->fieldSwitch('content_signals_enabled', __('Content Signals',          'kp-agent-ready'), __('Append Content-Signal directives to robots.txt',                 'kp-agent-ready'), true);
        $this->fieldSwitch('markdown_enabled',        __('Markdown Negotiation',     'kp-agent-ready'), __('Serve text/markdown when requested via Accept header',            'kp-agent-ready'), true);
        $this->fieldSwitch('webmcp_enabled',          __('WebMCP',                   'kp-agent-ready'), __('Inject WebMCP tool definitions via navigator.modelContext',       'kp-agent-ready'), true);
        $this->fieldSwitch('oauth_enabled',           __('OAuth / OIDC Discovery',   'kp-agent-ready'), __('Serve OAuth or OpenID Connect discovery metadata',               'kp-agent-ready'), false);
        $this->fieldSwitch('opr_enabled',             __('OAuth Protected Resource', 'kp-agent-ready'), __('Serve OAuth Protected Resource Metadata (RFC 9728)',             'kp-agent-ready'), false);
        $this->sectionClose();
    }

    /**
     * renderApiCatalogTab
     *
     * Renders the API Catalog tab — RFC 9727 linkset entries repeater.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function renderApiCatalogTab(): void
    {
        $this->sectionOpen(
            __('Linkset Entries', 'kp-agent-ready'),
            __('Each entry is published in the <code>linkset</code> array at <code>/.well-known/api-catalog</code> (<a href="https://www.rfc-editor.org/rfc/rfc9727" target="_blank">RFC 9727</a>). Leave empty to use the auto-generated fallback.', 'kp-agent-ready')
        );

        $page_opts = $this->getPageOptions();

        $this->fieldRepeater('api_catalog_entries', __('Add Entry', 'kp-agent-ready'), [
            ['id' => 'anchor',           'type' => 'url',    'label' => __('Anchor URL',                      'kp-agent-ready'), 'placeholder' => home_url('/'),                      'sublabel' => __('Base URL of the API this entry describes.', 'kp-agent-ready')],
            ['id' => 'service_desc',     'type' => 'url',    'label' => __('service-desc (OpenAPI spec URL)', 'kp-agent-ready'), 'placeholder' => 'https://example.com/openapi.json', 'sublabel' => __('Direct URL to the OpenAPI / Swagger specification file.', 'kp-agent-ready')],
            ['id' => 'service_doc',      'type' => 'url',    'label' => __('service-doc (Documentation URL)', 'kp-agent-ready')],
            ['id' => 'service_doc_page', 'type' => 'select', 'label' => __('service-doc — or select a page', 'kp-agent-ready'),  'options' => $page_opts, 'sublabel' => __('Overrides the URL above if a page is selected.', 'kp-agent-ready')],
            ['id' => 'status',           'type' => 'url',    'label' => __('status (Health endpoint URL)',    'kp-agent-ready'), 'placeholder' => 'https://example.com/health'],
        ]);

        $this->sectionClose();
    }

    /**
     * renderAgentSkillsTab
     *
     * Renders the Agent Skills tab — blog toggle, CPT multiselect, custom skills repeater.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function renderAgentSkillsTab(): void
    {
        // Blog / articles section
        $this->sectionOpen(__('Blog / Articles', 'kp-agent-ready'));
        $this->fieldSwitch('skill_blog_enabled', __('Include Blog Articles', 'kp-agent-ready'), __('Expose blog search and article listing as agent skills', 'kp-agent-ready'), true);
        $this->sectionClose();

        // Custom post types section
        $all_cpts = get_post_types(['public' => true], 'objects');
        $cpt_opts = [];

        foreach ($all_cpts as $slug => $obj) {
            if (in_array($slug, self::EXCLUDED_CPTS, true) || in_array($slug, ['post', 'page'], true)) {
                continue;
            }
            $cpt_opts[$slug] = $obj->label;
        }

        $this->sectionOpen(__('Custom Post Types', 'kp-agent-ready'), __('Each checked CPT generates a browsable skill entry pointing to its archive URL.', 'kp-agent-ready'));

        if (empty($cpt_opts)) {
            echo '<p class="description">' . esc_html__('No custom post types are currently registered (other than built-ins).', 'kp-agent-ready') . '</p>';
        } else {
            $this->fieldCheckboxes('skill_cpts', __('Enabled CPTs', 'kp-agent-ready'), $cpt_opts);
        }

        $this->sectionClose();

        // Custom skills repeater
        $page_opts = $this->getPageOptions();

        $this->sectionOpen(__('Custom Skills', 'kp-agent-ready'), __('Manually define additional entries in the agent skills index.', 'kp-agent-ready'));
        $this->fieldRepeater('skill_custom', __('Add Skill', 'kp-agent-ready'), [
            ['id' => 'name',        'type' => 'text',     'label' => __('Name',              'kp-agent-ready'), 'required' => true],
            ['id' => 'type',        'type' => 'select',   'label' => __('Type',              'kp-agent-ready'), 'options' => ['browse' => __('Browse', 'kp-agent-ready'), 'search' => __('Search', 'kp-agent-ready'), 'form' => __('Form', 'kp-agent-ready'), 'action' => __('Action', 'kp-agent-ready'), 'api' => __('API', 'kp-agent-ready')]],
            ['id' => 'description', 'type' => 'textarea', 'label' => __('Description',       'kp-agent-ready'), 'rows' => 2],
            ['id' => 'url',         'type' => 'url',      'label' => __('URL',               'kp-agent-ready')],
            ['id' => 'url_page',    'type' => 'select',   'label' => __('Or select a page',  'kp-agent-ready'), 'options' => $page_opts, 'sublabel' => __('Overrides the URL above if a page is selected.', 'kp-agent-ready')],
            ['id' => 'sha256',      'type' => 'text',     'label' => __('sha256 Digest',     'kp-agent-ready'), 'sublabel' => __('Optional. Hash of the skill file content per the Agent Skills RFC.', 'kp-agent-ready')],
        ]);
        $this->sectionClose();
    }

    /**
     * renderContentSignalsTab
     *
     * Renders the Content Signals tab — AI usage preference radio fields.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function renderContentSignalsTab(): void
    {
        $yes_no = ['yes' => __('Yes', 'kp-agent-ready'), 'no' => __('No', 'kp-agent-ready')];

        $this->sectionOpen(
            __('AI Usage Preferences', 'kp-agent-ready'),
            __('Declare AI content usage preferences published in <code>robots.txt</code> via the <a href="https://contentsignals.org/" target="_blank">Content Signals</a> spec.', 'kp-agent-ready')
        );
        $this->fieldRadio('cs_ai_train', __('AI Training (ai-train)',   'kp-agent-ready'), $yes_no, 'no',  __('Allow this content to be used to train AI models.',                       'kp-agent-ready'));
        $this->fieldRadio('cs_search',   __('Search Indexing (search)', 'kp-agent-ready'), $yes_no, 'yes', __('Allow this content to be indexed by search engines.',                     'kp-agent-ready'));
        $this->fieldRadio('cs_ai_input', __('AI RAG Input (ai-input)',  'kp-agent-ready'), $yes_no, 'no',  __('Allow this content to be used as input to AI retrieval / RAG systems.',  'kp-agent-ready'));
        $this->sectionClose();
    }

    /**
     * renderMcpCardTab
     *
     * Renders the MCP Server Card tab — server info fields and capabilities repeater.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function renderMcpCardTab(): void
    {
        $this->sectionOpen(
            __('Server Card Info', 'kp-agent-ready'),
            __('Configures <code>/.well-known/mcp/server-card.json</code> per <a href="https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127" target="_blank">SEP-1649</a>.', 'kp-agent-ready')
        );
        $this->fieldText('mcp_name',     __('Server Name',            'kp-agent-ready'), get_bloginfo('name'));
        $this->fieldText('mcp_version',  __('Version',                'kp-agent-ready'), '1.0.0');
        $this->fieldTextarea('mcp_desc', __('Description',            'kp-agent-ready'), 3);
        $this->fieldUrl('mcp_transport', __('Transport Endpoint URL', 'kp-agent-ready'), home_url('/mcp'), __('Leave blank if no MCP server is currently running.', 'kp-agent-ready'));
        $this->sectionClose();

        $this->sectionOpen(
            __('Capabilities', 'kp-agent-ready'),
            __('Declare which MCP capabilities this server supports. Each entry adds a key to the <code>capabilities</code> object.', 'kp-agent-ready')
        );
        $this->fieldRepeater('mcp_capabilities', __('Add Capability', 'kp-agent-ready'), [
            ['id' => 'capability_key', 'type' => 'text', 'label' => __('Capability Key', 'kp-agent-ready'), 'placeholder' => __('e.g. tools, resources, prompts', 'kp-agent-ready'), 'required' => true],
        ]);
        $this->sectionClose();
    }

    /**
     * renderOAuthTab
     *
     * Renders the OAuth / OIDC tab — protocol, endpoints, grant types, scopes.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function renderOAuthTab(): void
    {
        $this->sectionOpen(
            __('Discovery Type', 'kp-agent-ready'),
            __('Enable the OAuth / OIDC feature toggle in the Features tab to activate these endpoints.', 'kp-agent-ready')
        );
        $this->fieldRadio('oauth_type', __('Protocol', 'kp-agent-ready'), [
            'oidc'   => __('OpenID Connect — <code>/.well-known/openid-configuration</code>',      'kp-agent-ready'),
            'oauth2' => __('OAuth 2.0 only — <code>/.well-known/oauth-authorization-server</code>', 'kp-agent-ready'),
        ], 'oidc', __('Determines which <code>/.well-known/</code> path is served.', 'kp-agent-ready'));
        $this->sectionClose();

        $this->sectionOpen(__('Endpoints', 'kp-agent-ready'));
        $this->fieldUrl('oauth_issuer',         __('Issuer',                 'kp-agent-ready'), home_url('/'),                 __('The canonical issuer identifier URL.', 'kp-agent-ready'));
        $this->fieldUrl('oauth_auth_endpoint',  __('Authorization Endpoint', 'kp-agent-ready'), home_url('/oauth/authorize'));
        $this->fieldUrl('oauth_token_endpoint', __('Token Endpoint',         'kp-agent-ready'), home_url('/oauth/token'));
        $this->fieldUrl('oauth_jwks_uri',       __('JWKS URI',               'kp-agent-ready'), home_url('/oauth/jwks.json'));
        $this->sectionClose();

        $this->sectionOpen(__('Supported Grant Types', 'kp-agent-ready'));
        $this->fieldCheckboxes('oauth_grant_types', __('Grant Types Supported', 'kp-agent-ready'), [
            'authorization_code' => __('Auth. Code',         'kp-agent-ready'),
            'client_credentials' => __('Client Credentials', 'kp-agent-ready'),
            'refresh_token'      => __('Refresh Token',      'kp-agent-ready'),
            'implicit'           => __('Implicit',           'kp-agent-ready'),
            'password'           => __('Password',           'kp-agent-ready'),
            'urn:ietf:params:oauth:grant-type:device_code' => __('Device Code', 'kp-agent-ready'),
        ]);
        $this->sectionClose();

        $this->sectionOpen(__('Supported Response Types', 'kp-agent-ready'));
        $this->fieldCheckboxes('oauth_response_types', __('Response Types Supported', 'kp-agent-ready'), [
            'code'                => __('Code',               'kp-agent-ready'),
            'token'               => __('Token',              'kp-agent-ready'),
            'id_token'            => __('ID token',           'kp-agent-ready'),
            'code token'          => __('Code Token',         'kp-agent-ready'),
            'code id_token'       => __('Code ID Token',      'kp-agent-ready'),
            'token id_token'      => __('Token ID Token',     'kp-agent-ready'),
            'code token id_token' => __('Code Token ID Token', 'kp-agent-ready'),
        ]);
        $this->sectionClose();

        $this->sectionOpen(__('Token Endpoint Auth Methods', 'kp-agent-ready'));
        $this->fieldCheckboxes('oauth_token_auth_methods', __('Endpoint Auth Methods', 'kp-agent-ready'), [
            'client_secret_basic' => __('Client Secret Basic', 'kp-agent-ready'),
            'client_secret_post'  => __('Client Secret Post',  'kp-agent-ready'),
            'client_secret_jwt'   => __('Client Secret JWT',   'kp-agent-ready'),
            'private_key_jwt'     => __('Private Key JWT',     'kp-agent-ready'),
            'none'                => __('none (PKCE)',          'kp-agent-ready'),
        ]);
        $this->sectionClose();

        $this->sectionOpen(__('Supported Scopes', 'kp-agent-ready'), __('Each entry adds a scope string to <code>scopes_supported</code>.', 'kp-agent-ready'));
        $this->fieldRepeater('oauth_scopes', __('Add Scope', 'kp-agent-ready'), [
            ['id' => 'scope', 'type' => 'text', 'label' => __('Scope', 'kp-agent-ready'), 'placeholder' => __('e.g. openid, profile, email, read:posts', 'kp-agent-ready'), 'required' => true],
        ]);
        $this->sectionClose();
    }

    /**
     * renderOAuthResourceTab
     *
     * Renders the OAuth Protected Resource tab.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function renderOAuthResourceTab(): void
    {
        $this->sectionOpen(
            __('Protected Resource Metadata', 'kp-agent-ready'),
            __('Configures <code>/.well-known/oauth-protected-resource</code> per <a href="https://www.rfc-editor.org/rfc/rfc9728" target="_blank">RFC 9728</a>. Enable via the Features tab.', 'kp-agent-ready')
        );
        $this->fieldUrl('opr_resource', __('Resource Identifier', 'kp-agent-ready'), home_url('/'), __('Canonical URL identifying this protected resource.', 'kp-agent-ready'));
        $this->sectionClose();

        $this->sectionOpen(__('Authorization Servers', 'kp-agent-ready'), __('OAuth/OIDC issuer URLs that can issue tokens for this resource.', 'kp-agent-ready'));
        $this->fieldRepeater('opr_auth_servers', __('Add Server', 'kp-agent-ready'), [
            ['id' => 'server_url', 'type' => 'url', 'label' => __('Issuer URL', 'kp-agent-ready'), 'placeholder' => 'https://auth.example.com', 'required' => true],
        ]);
        $this->sectionClose();

        $this->sectionOpen(__('Bearer Methods', 'kp-agent-ready'));
        $this->fieldCheckboxes('opr_bearer_methods', __('Bearer Methods Supported', 'kp-agent-ready'), [
            'header' => __('header (Authorization: Bearer)', 'kp-agent-ready'),
            'body'   => __('body (form parameter)',          'kp-agent-ready'),
            'query'  => __('query (URL parameter)',          'kp-agent-ready'),
        ]);
        $this->sectionClose();

        $this->sectionOpen(__('Supported Scopes', 'kp-agent-ready'), __('Scopes this resource accepts. Each entry adds a scope string to <code>scopes_supported</code>.', 'kp-agent-ready'));
        $this->fieldRepeater('opr_scopes', __('Add Scope', 'kp-agent-ready'), [
            ['id' => 'scope', 'type' => 'text', 'label' => __('Scope', 'kp-agent-ready'), 'placeholder' => __('e.g. read:posts, write:comments', 'kp-agent-ready'), 'required' => true],
        ]);
        $this->sectionClose();
    }

    /**
     * renderWebMcpTab
     *
     * Renders the WebMCP tab — built-in tool toggles, descriptions, and URLs.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function renderWebMcpTab(): void
    {
        $site = get_bloginfo('name');

        $this->sectionOpen(
            __('Built-in Tools', 'kp-agent-ready'),
            __('Configure which tools are exposed to agents via <code>navigator.modelContext</code>. Requires the WebMCP feature toggle to be enabled.', 'kp-agent-ready')
        );

        $this->fieldSwitch('webmcp_search',       __('Blog Search',            'kp-agent-ready'), __('Enable the blog search tool',          'kp-agent-ready'), true);
        // translators: %s is the site name
        $this->fieldText('webmcp_search_desc',    __('Search Tool Description', 'kp-agent-ready'), sprintf(__('Search %s', 'kp-agent-ready'), $site));

        echo '<hr style="margin:16px 0 12px;">';

        $this->fieldSwitch('webmcp_portfolio',    __('Portfolio Navigation',       'kp-agent-ready'), __('Enable the portfolio navigation tool', 'kp-agent-ready'), true);
        // translators: %s is the site name
        $this->fieldText('webmcp_portfolio_desc', __('Portfolio Tool Description', 'kp-agent-ready'), sprintf(__('Browse the portfolio on %s', 'kp-agent-ready'), $site));
        $this->fieldUrl('webmcp_portfolio_url',   __('Portfolio URL',              'kp-agent-ready'), home_url('/portfolio/'));

        echo '<hr style="margin:16px 0 12px;">';

        $this->fieldSwitch('webmcp_contact',      __('Contact Navigation',       'kp-agent-ready'), __('Enable the contact navigation tool', 'kp-agent-ready'), true);
        // translators: %s is the site name
        $this->fieldText('webmcp_contact_desc',   __('Contact Tool Description', 'kp-agent-ready'), sprintf(__('Contact %s', 'kp-agent-ready'), $site));
        $this->fieldUrl('webmcp_contact_url',     __('Contact URL',              'kp-agent-ready'), home_url('/contact/'));

        $this->sectionClose();
    }

    // =========================================================================
    // Field helpers
    // =========================================================================

    /**
     * sectionOpen
     *
     * Outputs an opening settings section wrapper with an optional description.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $title       Section heading text
     * @param string $description Optional description — may contain safe HTML
     *
     * @return void This method does not return anything
     *
     */
    private function sectionOpen(string $title, string $description = ''): void
    {
        echo '<div class="kp-ar-section">';
        echo '<h2 class="kp-ar-section-title">' . esc_html($title) . '</h2>';

        if ($description) {
            echo '<p class="kp-ar-section-desc description">' . wp_kses_post($description) . '</p>';
        }
    }

    /**
     * sectionClose
     *
     * Outputs the closing tag for a settings section wrapper.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private function sectionClose(): void
    {
        echo '</div>';
    }

    /**
     * fieldRow
     *
     * Wraps any rendered field HTML in a labelled two-column row.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $id       Option field ID (used to build the HTML id attribute)
     * @param string $label    Field label text
     * @param string $sublabel Optional sub-label below the label
     * @param string $content  Rendered field HTML
     *
     * @return void This method does not return anything
     *
     */
    private function fieldRow(string $id, string $label, string $sublabel, string $content): void
    {
        $html_id = Plugin::OPTION_KEY . '_' . $id;

        // Allowed tags for constructed form field HTML
        $allowed = [
            'input'    => ['type' => true, 'id' => true, 'name' => true, 'value' => true, 'placeholder' => true, 'class' => true, 'checked' => true, 'required' => true, 'rows' => true, 'aria-label' => true],
            'select'   => ['id' => true, 'name' => true, 'class' => true],
            'option'   => ['value' => true, 'selected' => true],
            'textarea' => ['id' => true, 'name' => true, 'rows' => true, 'class' => true],
            'fieldset' => [],
            'label'    => ['for' => true, 'class' => true],
            'span'     => ['class' => true, 'aria-hidden' => true],
            'strong'   => [],
            'br'       => [],
        ];

        echo '<div class="kp-ar-field">';
        echo '<div class="kp-ar-field-label">';
        printf('<label for="%s">%s</label>', esc_attr($html_id), esc_html($label));

        if ($sublabel) {
            echo '<span class="kp-ar-sublabel description">' . esc_html($sublabel) . '</span>';
        }

        echo '</div>';
        echo '<div class="kp-ar-field-input">' . wp_kses($content, $allowed) . '</div>';
        echo '</div>';
    }

    /**
     * fieldSwitch
     *
     * Renders a toggle-switch field.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $id       Option key
     * @param string $label    Field label
     * @param string $sublabel Switch inline label / sub-label
     * @param bool   $default  Default value when the option is not yet set
     *
     * @return void This method does not return anything
     *
     */
    private function fieldSwitch(string $id, string $label, string $sublabel, bool $default = true): void
    {
        $val     = (bool) ($this->options[$id] ?? $default);
        $name    = Plugin::OPTION_KEY . '[' . $id . ']';
        $html_id = Plugin::OPTION_KEY . '_' . $id;

        $html  = '<label class="kp-ar-switch">';
        $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="0">';
        $html .= '<input type="checkbox" id="' . esc_attr($html_id) . '" name="' . esc_attr($name) . '" value="1"' . checked($val, true, false) . '>';
        $html .= '<span class="kp-ar-slider"></span>';
        $html .= '</label>';
        $html .= ' <span class="description kp-ar-switch-label">' . esc_html($sublabel) . '</span>';

        $this->fieldRow($id, $label, '', $html);
    }

    /**
     * fieldText
     *
     * Renders a plain text input field.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $id          Option key
     * @param string $label       Field label
     * @param string $placeholder Placeholder text
     * @param string $sublabel    Optional sub-label
     *
     * @return void This method does not return anything
     *
     */
    private function fieldText(string $id, string $label, string $placeholder = '', string $sublabel = ''): void
    {
        $val     = sanitize_text_field((string) ($this->options[$id] ?? ''));
        $name    = Plugin::OPTION_KEY . '[' . $id . ']';
        $html_id = Plugin::OPTION_KEY . '_' . $id;

        $html = sprintf(
            '<input type="text" id="%s" name="%s" value="%s" placeholder="%s" class="regular-text">',
            esc_attr($html_id),
            esc_attr($name),
            esc_attr($val),
            esc_attr($placeholder)
        );

        $this->fieldRow($id, $label, $sublabel, $html);
    }

    /**
     * fieldUrl
     *
     * Renders a URL input field.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $id          Option key
     * @param string $label       Field label
     * @param string $placeholder Placeholder URL
     * @param string $sublabel    Optional sub-label
     *
     * @return void This method does not return anything
     *
     */
    private function fieldUrl(string $id, string $label, string $placeholder = '', string $sublabel = ''): void
    {
        $val     = esc_url((string) ($this->options[$id] ?? ''));
        $name    = Plugin::OPTION_KEY . '[' . $id . ']';
        $html_id = Plugin::OPTION_KEY . '_' . $id;

        $html = sprintf(
            '<input type="url" id="%s" name="%s" value="%s" placeholder="%s" class="regular-text code">',
            esc_attr($html_id),
            esc_attr($name),
            esc_attr($val),
            esc_attr($placeholder)
        );

        $this->fieldRow($id, $label, $sublabel, $html);
    }

    /**
     * fieldTextarea
     *
     * Renders a textarea field.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $id    Option key
     * @param string $label Field label
     * @param int    $rows  Visible row count
     *
     * @return void This method does not return anything
     *
     */
    private function fieldTextarea(string $id, string $label, int $rows = 4): void
    {
        $val     = esc_textarea((string) ($this->options[$id] ?? ''));
        $name    = Plugin::OPTION_KEY . '[' . $id . ']';
        $html_id = Plugin::OPTION_KEY . '_' . $id;

        $html = sprintf(
            '<textarea id="%s" name="%s" rows="%d" class="large-text">%s</textarea>',
            esc_attr($html_id),
            esc_attr($name),
            $rows,
            $val
        );

        $this->fieldRow($id, $label, '', $html);
    }

    /**
     * fieldRadio
     *
     * Renders a group of radio buttons.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $id       Option key
     * @param string $label    Field label
     * @param array  $options  Value => Label pairs
     * @param string $default  Default selected value
     * @param string $sublabel Optional sub-label
     *
     * @return void This method does not return anything
     *
     */
    private function fieldRadio(string $id, string $label, array $options, string $default = '', string $sublabel = ''): void
    {
        $current = sanitize_text_field((string) ($this->options[$id] ?? $default));
        $name    = Plugin::OPTION_KEY . '[' . $id . ']';

        $html = '<fieldset>';

        foreach ($options as $val => $opt_label) {
            $rid  = Plugin::OPTION_KEY . '_' . $id . '_' . sanitize_key((string) $val);
            $html .= sprintf(
                '<label for="%s"><input type="radio" id="%s" name="%s" value="%s"%s> %s</label><br>',
                esc_attr($rid),
                esc_attr($rid),
                esc_attr($name),
                esc_attr((string) $val),
                checked($current, (string) $val, false),
                wp_kses($opt_label, ['code' => []])
            );
        }

        $html .= '</fieldset>';

        $this->fieldRow($id, $label, $sublabel, $html);
    }

    /**
     * fieldCheckboxes
     *
     * Renders a group of checkboxes.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $id      Option key
     * @param string $label   Field label
     * @param array  $options Value => Label pairs
     *
     * @return void This method does not return anything
     *
     */
    private function fieldCheckboxes(string $id, string $label, array $options): void
    {
        $current = (array) ($this->options[$id] ?? []);
        $name    = Plugin::OPTION_KEY . '[' . $id . '][]';

        $html = '<fieldset>';

        foreach ($options as $val => $opt_label) {
            // Sanitize the value for use in HTML attributes (spaces, colons, slashes)
            $safe_val = str_replace([':', ' ', '/'], '_', (string) $val);
            $cid      = Plugin::OPTION_KEY . '_' . $id . '_' . sanitize_key($safe_val);
            $checked  = in_array((string) $val, $current, true) ? ' checked' : '';

            $html .= sprintf(
                '<label for="%s"><input type="checkbox" id="%s" name="%s" value="%s"%s> %s</label><br>',
                esc_attr($cid),
                esc_attr($cid),
                esc_attr($name),
                esc_attr((string) $val),
                $checked,
                esc_html($opt_label)
            );
        }

        $html .= '</fieldset>';

        $this->fieldRow($id, $label, '', $html);
    }

    /**
     * fieldRepeater
     *
     * Renders a repeater field with add/remove row functionality powered by
     * the inline JavaScript registered in enqueueAssets().
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $id           Option key for this repeater
     * @param string $button_label Label for the Add Row button
     * @param array  $sub_fields   Field definitions — each entry needs 'id', 'type', 'label'
     *
     * @return void This method does not return anything
     *
     */
    private function fieldRepeater(string $id, string $button_label, array $sub_fields): void
    {
        $rows    = (array) ($this->options[$id] ?? []);
        $opt_key = Plugin::OPTION_KEY;

        echo '<div class="kp-ar-repeater" data-id="' . esc_attr($id) . '">';
        echo '<div class="kp-ar-repeater-rows">';

        foreach ($rows as $i => $row) {
            $this->renderRepeaterRow($opt_key, $id, $sub_fields, $i, is_array($row) ? $row : []);
        }

        echo '</div>';

        printf(
            '<button type="button" class="button kp-ar-add-row" data-repeater="%s">&#43; %s</button>',
            esc_attr($id),
            esc_html($button_label)
        );

        // Hidden template row — cloned by JS when Add is clicked
        echo '<template class="kp-ar-row-tpl" data-repeater="' . esc_attr($id) . '">';
        $this->renderRepeaterRow($opt_key, $id, $sub_fields, '__INDEX__', []);
        echo '</template>';

        echo '</div>';
    }

    /**
     * renderRepeaterRow
     *
     * Renders a single repeater row with its header controls and sub-fields.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string     $opt_key    Top-level WP option key
     * @param string     $id         Repeater field ID
     * @param array      $sub_fields Sub-field definitions
     * @param int|string $index      Row index, or '__INDEX__' for the JS template row
     * @param array      $values     Stored values for this row
     *
     * @return void This method does not return anything
     *
     */
    private function renderRepeaterRow(
        string $opt_key,
        string $id,
        array $sub_fields,
        int|string $index,
        array $values
    ): void {
        $display = is_int($index) ? ($index + 1) : '#';

        echo '<div class="kp-ar-repeater-row" data-row-index="' . esc_attr((string) $index) . '">';
        echo '<div class="kp-ar-row-header">';
        echo '<span class="dashicons dashicons-menu kp-ar-drag-handle" aria-hidden="true"></span>';
        echo '<strong class="kp-ar-row-num">' . esc_html((string) $display) . '</strong>';
        printf(
            '<button type="button" class="button-link kp-ar-remove-row" aria-label="%s"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>',
            esc_attr__('Remove row', 'kp-agent-ready')
        );
        echo '</div>';

        echo '<div class="kp-ar-row-fields">';

        foreach ($sub_fields as $field) {
            $fid   = $field['id'];
            $fname = sprintf('%s[%s][%s][%s]', $opt_key, $id, $index, $fid);
            $ffid  = sprintf('%s_%s_%s_%s',    $opt_key, $id, $index, $fid);
            $fval  = $values[$fid] ?? ($field['default'] ?? '');
            $ftype = $field['type'] ?? 'text';

            echo '<div class="kp-ar-row-field">';

            if (!empty($field['label'])) {
                printf('<label for="%s">%s</label>', esc_attr($ffid), esc_html($field['label']));
            }

            if (!empty($field['sublabel'])) {
                echo '<span class="kp-ar-sublabel description">' . esc_html($field['sublabel']) . '</span>';
            }

            switch ($ftype) {
                case 'url':
                    printf(
                        '<input type="url" id="%s" name="%s" value="%s" placeholder="%s" class="widefat code">',
                        esc_attr($ffid),
                        esc_attr($fname),
                        esc_attr(esc_url((string) $fval)),
                        esc_attr($field['placeholder'] ?? '')
                    );
                    break;

                case 'textarea':
                    printf(
                        '<textarea id="%s" name="%s" rows="%d" class="widefat">%s</textarea>',
                        esc_attr($ffid),
                        esc_attr($fname),
                        (int) ($field['rows'] ?? 2),
                        esc_textarea((string) $fval)
                    );
                    break;

                case 'select':
                    echo '<select id="' . esc_attr($ffid) . '" name="' . esc_attr($fname) . '" class="widefat">';
                    foreach ($field['options'] ?? [] as $oval => $olabel) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr((string) $oval),
                            selected((string) $fval, (string) $oval, false),
                            esc_html($olabel)
                        );
                    }
                    echo '</select>';
                    break;

                default: // text
                    printf(
                        '<input type="text" id="%s" name="%s" value="%s" placeholder="%s" class="widefat">',
                        esc_attr($ffid),
                        esc_attr($fname),
                        esc_attr(sanitize_text_field((string) $fval)),
                        esc_attr($field['placeholder'] ?? '')
                    );
            }

            echo '</div>'; // .kp-ar-row-field
        }

        echo '</div>'; // .kp-ar-row-fields
        echo '</div>'; // .kp-ar-repeater-row
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * getEnabledTabs
     *
     * Returns the tab slugs and labels that should currently be displayed,
     * based on the stored feature-toggle values.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string, string> Tab slug => translated label
     *
     */
    private function getEnabledTabs(): array
    {
        $tabs = [
            'features'     => __('Features',        'kp-agent-ready'),
            'api_catalog'  => __('API Catalog',     'kp-agent-ready'),
            'agent_skills' => __('Agent Skills',    'kp-agent-ready'),
            'mcp_card'     => __('MCP Server Card', 'kp-agent-ready'),
        ];

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

        return $tabs;
    }

    /**
     * getPageOptions
     *
     * Returns an array of published page IDs => titles for use in select fields,
     * with an empty "None" option prepended.
     *
     * @since 1.0.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<int|string, string> Page select options
     *
     */
    private function getPageOptions(): array
    {
        $opts = ['' => __('— None —', 'kp-agent-ready')];

        foreach (get_pages(['post_status' => 'publish']) as $page) {
            $opts[$page->ID] = get_the_title($page);
        }

        return $opts;
    }
}
