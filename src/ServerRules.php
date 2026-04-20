<?php

/**
 * ServerRules
 *
 * Writes and removes the well-known rewrite rules needed to pass the
 * kp_agent query variable through to WordPress. Supports both Apache
 * (.htaccess via insert_with_markers) and Nginx (a standalone conf
 * snippet the server operator includes once). Duplicate detection is
 * handled by the marker system for Apache and by a checksum comment
 * for Nginx.
 *
 * @since 1.1.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 *
 */

declare(strict_types=1);

// setup the namespace
namespace KP\AgentReady;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

/**
 * ServerRules
 *
 * Manages server-level rewrite rules required for the well-known endpoints.
 *
 * @since 1.1.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class ServerRules
{

    /** Marker used by insert_with_markers() to wrap the Apache rules. */
    private const APACHE_MARKER = 'KP Agent Ready';

    /** Filename written to ABSPATH for the Nginx snippet. */
    private const NGINX_SNIPPET = 'kp-agent-ready-well-known.conf';

    /** Option key that records which server type we wrote rules for. */
    private const OPTION_KEY = 'kp_agent_ready_server_rules';

    /**
     * install
     *
     * Detects the active server software and writes the appropriate
     * rewrite rules. Called on plugin activation.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public static function install(): void
    {
        if (self::isNginx()) {
            self::writeNginxSnippet();
            update_option(self::OPTION_KEY, 'nginx');
        } else {
            self::writeApacheRules();
            update_option(self::OPTION_KEY, 'apache');
        }
    }

    /**
     * uninstall
     *
     * Removes whichever rules were written during activation.
     * Called on plugin deactivation.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public static function uninstall(): void
    {
        $server = get_option(self::OPTION_KEY, '');

        if ($server === 'nginx') {
            self::removeNginxSnippet();
        } else {
            self::removeApacheRules();
        }

        delete_option(self::OPTION_KEY);
    }

    /**
     * adminNotice
     *
     * Displays a one-time admin notice when a Nginx snippet has been
     * written, instructing the operator to include it in the site config.
     * The notice is dismissed permanently once acknowledged.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public static function adminNotice(): void
    {
        if (get_option(self::OPTION_KEY) !== 'nginx') {
            return;
        }

        // Bail if the notice has already been dismissed
        if (get_option('kp_agent_ready_nginx_notice_dismissed')) {
            return;
        }

        $snippet = ABSPATH . self::NGINX_SNIPPET;
        $action  = wp_nonce_url(
            add_query_arg('kp_agent_dismiss_nginx_notice', '1'),
            'kp_agent_dismiss_nginx_notice'
        );

        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>KP Agent Ready:</strong> ' .
                'A Nginx rewrite snippet has been written to <code>%s</code>. ' .
                'Add the following line inside your server block and reload Nginx:<br>' .
                '<code>include %s;</code></p>' .
                '<p><a href="%s">Dismiss this notice</a></p></div>',
            esc_html($snippet),
            esc_html($snippet),
            esc_url($action)
        );
    }

    /**
     * handleNoticeDismissal
     *
     * Listens for the dismissal query parameter and persists the
     * dismissed state so the notice does not reappear.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public static function handleNoticeDismissal(): void
    {
        if (
            isset($_GET['kp_agent_dismiss_nginx_notice']) &&
            wp_verify_nonce($_GET['_wpnonce'] ?? '', 'kp_agent_dismiss_nginx_notice')
        ) {
            update_option('kp_agent_ready_nginx_notice_dismissed', true);
            wp_safe_redirect(remove_query_arg(['kp_agent_dismiss_nginx_notice', '_wpnonce']));
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // Apache
    // -------------------------------------------------------------------------

    /**
     * writeApacheRules
     *
     * Inserts the well-known rewrite rules into .htaccess using
     * insert_with_markers(), which is idempotent — running it twice
     * replaces the existing block rather than duplicating it.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private static function writeApacheRules(): void
    {
        $htaccess = get_home_path() . '.htaccess';

        $rules = [
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            'RewriteRule ^\.well-known/api-catalog/?$              /index.php?kp_agent=api-catalog     [L,QSA]',
            'RewriteRule ^\.well-known/agent-skills/index\.json$   /index.php?kp_agent=agent-skills    [L,QSA]',
            'RewriteRule ^\.well-known/mcp/server-card\.json$      /index.php?kp_agent=mcp-server-card [L,QSA]',
            'RewriteRule ^\.well-known/openid-configuration$       /index.php?kp_agent=oidc-config     [L,QSA]',
            'RewriteRule ^\.well-known/oauth-authorization-server$ /index.php?kp_agent=oauth-config    [L,QSA]',
            'RewriteRule ^\.well-known/oauth-protected-resource$   /index.php?kp_agent=oauth-resource  [L,QSA]',
            '</IfModule>',
        ];

        insert_with_markers($htaccess, self::APACHE_MARKER, $rules);
    }

    /**
     * removeApacheRules
     *
     * Removes the plugin's marker block from .htaccess by replacing
     * it with an empty array.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private static function removeApacheRules(): void
    {
        $htaccess = get_home_path() . '.htaccess';

        if (file_exists($htaccess)) {
            insert_with_markers($htaccess, self::APACHE_MARKER, []);
        }
    }

    // -------------------------------------------------------------------------
    // Nginx
    // -------------------------------------------------------------------------

    /**
     * writeNginxSnippet
     *
     * Writes a standalone Nginx location block to ABSPATH. A checksum
     * comment at the top of the file is compared before writing so the
     * file is only replaced when the rules have changed.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private static function writeNginxSnippet(): void
    {
        $target  = ABSPATH . self::NGINX_SNIPPET;
        $content = self::nginxSnippetContent();
        $hash    = md5($content);

        // Skip writing if the file already exists with identical content
        if (file_exists($target) && md5_file($target) === $hash) {
            return;
        }

        file_put_contents($target, $content);
    }

    /**
     * removeNginxSnippet
     *
     * Deletes the Nginx snippet file from ABSPATH if it exists.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    private static function removeNginxSnippet(): void
    {
        $target = ABSPATH . self::NGINX_SNIPPET;

        if (file_exists($target)) {
            unlink($target);
        }
    }

    /**
     * nginxSnippetContent
     *
     * Returns the Nginx location block content as a string. The block
     * uses explicit rewrite rules for each well-known path so the
     * kp_agent query var is set before WordPress boots, and Let's
     * Encrypt's acme-challenge path is not matched.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return string The Nginx location block
     *
     */
    private static function nginxSnippetContent(): string
    {
        return <<<NGINX
# KP Agent Ready — well-known endpoint rewrites
# Generated by KP Agent Ready plugin. Do not edit manually.
# Include this file inside your server {} block:
#   include {$_SERVER['DOCUMENT_ROOT']}/../kp-agent-ready-well-known.conf;
#
# Note: /.well-known/acme-challenge/ is intentionally not matched
# so Let's Encrypt certificate renewals are unaffected.

location ~ ^/\.well-known/(api-catalog|agent-skills/index\.json|mcp/server-card\.json|openid-configuration|oauth-authorization-server|oauth-protected-resource) {
    auth_basic off;
    allow all;
    rewrite ^/\.well-known/api-catalog/?$                /index.php?kp_agent=api-catalog     last;
    rewrite ^/\.well-known/agent-skills/index\.json$     /index.php?kp_agent=agent-skills    last;
    rewrite ^/\.well-known/mcp/server-card\.json$        /index.php?kp_agent=mcp-server-card last;
    rewrite ^/\.well-known/openid-configuration$         /index.php?kp_agent=oidc-config     last;
    rewrite ^/\.well-known/oauth-authorization-server$   /index.php?kp_agent=oauth-config    last;
    rewrite ^/\.well-known/oauth-protected-resource$     /index.php?kp_agent=oauth-resource  last;
}
NGINX;
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    /**
     * isNginx
     *
     * Detects whether the current server software is Nginx by inspecting
     * the SERVER_SOFTWARE superglobal.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return bool True when running under Nginx
     *
     */
    private static function isNginx(): bool
    {
        return str_contains(strtolower($_SERVER['SERVER_SOFTWARE'] ?? ''), 'nginx');
    }
}
