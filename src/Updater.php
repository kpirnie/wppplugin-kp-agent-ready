<?php

/**
 * Updater
 *
 * Hooks into the WordPress plugin update system to check for new
 * releases published on GitHub and surface them in the admin dashboard.
 *
 * @since 1.0.68
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 *
 */

namespace KP\AgentReady;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

/**
 * Updater
 *
 * Polls the GitHub Releases API for the plugin repository and injects
 * update metadata into WordPress's plugin update transient so that
 * updates appear and install through the standard admin UI.
 *
 * @since 1.1.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class Updater
{

    /** GitHub API endpoint for the latest release. */
    private const API_URL = 'https://api.github.com/repos/kpirnie/wppplugin-kp-agent-ready/releases/latest';

    /** Transient key used to cache the remote release data. */
    private const CACHE_KEY = 'kp_agent_ready_update_data';

    /** Cache lifetime in seconds (12 hours). */
    private const CACHE_TTL = 43200;

    /** Plugin slug as WordPress identifies it (folder/file). */
    private const PLUGIN_SLUG = 'kp-agent-ready/kp-agent-ready.php';

    /**
     * register
     *
     * Attaches the updater hooks to the WordPress plugin update lifecycle.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'injectUpdate']);
        add_filter('plugins_api',                           [$this, 'injectPluginInfo'], 10, 3);
        add_filter('upgrader_post_install',                 [$this, 'postInstall'],      10, 3);
    }

    /**
     * injectUpdate
     *
     * Checks the GitHub Releases API and, when a newer version exists,
     * inserts the update payload into the WordPress update_plugins transient.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \stdClass $transient The current update_plugins transient value
     *
     * @return \stdClass The (possibly modified) transient value
     *
     */
    public function injectUpdate(\stdClass $transient): \stdClass
    {
        // Bail if WordPress hasn't finished checking yet
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->fetchRelease();
        if (! $release) {
            return $transient;
        }

        // Only inject when the remote version is actually newer
        if (version_compare($release->version, KP_AGENT_READY_VERSION, '>')) {
            $transient->response[self::PLUGIN_SLUG] = $this->buildUpdatePayload($release);
        }

        return $transient;
    }

    /**
     * injectPluginInfo
     *
     * Populates the plugin information modal (the "View Details" overlay)
     * with data fetched from the GitHub release when WordPress requests it.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param false|\stdClass $result The current plugins_api result
     * @param string          $action The API action being requested
     * @param \stdClass       $args   The request arguments
     *
     * @return false|\stdClass The plugin info object or the original result
     *
     */
    public function injectPluginInfo(false|\stdClass $result, string $action, \stdClass $args): false|\stdClass
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (($args->slug ?? '') !== 'kp-agent-ready') {
            return $result;
        }

        $release = $this->fetchRelease();
        if (! $release) {
            return $result;
        }

        $info                = new \stdClass();
        $info->name          = __('KP Agent Ready', 'kp-agent-ready');
        $info->slug          = 'kp-agent-ready';
        $info->version       = $release->version;
        $info->author        = sprintf('<a href="https://kevinpirnie.com">%s</a>', __('Kevin Pirnie', 'kp-agent-ready'));
        $info->homepage      = 'https://github.com/kpirnie/wppplugin-kp-agent-ready';
        $info->requires      = '6.8';
        $info->requires_php  = '8.2';
        $info->download_link = $release->zip_url;
        $info->sections      = [
            'description' => __('Make your WordPress site discoverable and usable by AI agents.', 'kp-agent-ready'),
            'changelog'   => wp_kses_post($release->body),
        ];

        return $info;
    }

    /**
     * postInstall
     *
     * Renames the extracted upgrade directory back to the expected plugin
     * folder name after installation, since GitHub archives unzip with a
     * generated directory name rather than the plugin slug.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param bool  $response   Whether the install was successful
     * @param array $hook_extra Extra hook arguments including the plugin slug
     * @param array $result     Array of result data including the destination path
     *
     * @return array The (possibly modified) result array
     *
     */
    public function postInstall(bool $response, array $hook_extra, array $result): array
    {
        global $wp_filesystem;

        // Only act on our own plugin's upgrade
        if (($hook_extra['plugin'] ?? '') !== self::PLUGIN_SLUG) {
            return $result;
        }

        $target = WP_PLUGIN_DIR . '/kp-agent-ready';

        // Move the unpacked directory to the correct location
        $wp_filesystem->move($result['destination'], $target, true);
        $result['destination'] = $target;

        // Only reactivate if it was active before the upgrade
        if (is_plugin_active(self::PLUGIN_SLUG)) {
            activate_plugin(self::PLUGIN_SLUG);
        }

        return $result;
    }

    /**
     * fetchRelease
     *
     * Retrieves release metadata from the GitHub API, caching the result
     * in a transient to avoid hammering the API on every page load.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return \stdClass|null Normalised release object or null on failure
     *
     */
    private function fetchRelease(): ?\stdClass
    {
        // Return cached data when available
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get(self::API_URL, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'KP-Agent-Ready-Updater/' . KP_AGENT_READY_VERSION,
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['tag_name'])) {
            return null;
        }

        // Locate the zip asset, falling back to the auto-generated source archive
        $zip_url = $body['zipball_url'];
        foreach ($body['assets'] ?? [] as $asset) {
            if (str_ends_with($asset['name'], '.zip')) {
                $candidate     = $asset['browser_download_url'];
                $parsed_host   = wp_parse_url($candidate, PHP_URL_HOST);
                $allowed_hosts = ['api.github.com', 'github.com', 'codeload.github.com'];
                if (in_array($parsed_host, $allowed_hosts, true)) {
                    $zip_url = $candidate;
                }
                break;
            }
        }

        $release          = new \stdClass();
        $release->version = sanitize_text_field(ltrim($body['tag_name'], 'v'));
        $release->zip_url = $zip_url;
        $release->body    = $body['body'] ?? '';

        // Cache for TTL before hitting the API again
        set_transient(self::CACHE_KEY, $release, self::CACHE_TTL);

        return $release;
    }

    /**
     * buildUpdatePayload
     *
     * Constructs the stdClass object that WordPress expects to find inside
     * the update_plugins transient response array for a given plugin.
     *
     * @since 1.0.68
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \stdClass $release Normalised release object from fetchRelease()
     *
     * @return \stdClass The WordPress-compatible update payload
     *
     */
    private function buildUpdatePayload(\stdClass $release): \stdClass
    {
        $payload                = new \stdClass();
        $payload->slug          = 'kp-agent-ready';
        $payload->plugin        = self::PLUGIN_SLUG;
        $payload->new_version   = $release->version;
        $payload->tested        = '7.0';
        $payload->requires_php  = '8.2';
        $payload->url           = 'https://github.com/kpirnie/wppplugin-kp-agent-ready';
        // Only allow GitHub origins
        $zip_url = $release->zip_url;
        $allowed_hosts = ['api.github.com', 'github.com', 'codeload.github.com'];
        $parsed_host   = wp_parse_url($zip_url, PHP_URL_HOST);
        if (! in_array($parsed_host, $allowed_hosts, true)) {
            $zip_url = '';
        }
        $payload->package = $zip_url;

        // return the updater payload
        return $payload;
    }
}
