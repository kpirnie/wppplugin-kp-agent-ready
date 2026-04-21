<?php

/** 
 * MarkdownNegotiation
 * 
 * Intercepts requests for singular posts and pages when the client sends
 * an Accept header containing text/markdown, and returns a Markdown
 * version of the content instead of HTML.
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

// pull in the namespace
use KP\AgentReady\Helpers\HtmlToMarkdown;

/**
 * MarkdownNegotiation
 *
 * Intercepts requests for singular posts and pages when the client sends
 * an Accept header containing text/markdown, and returns a Markdown
 * version of the content instead of HTML.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class MarkdownNegotiation extends AbstractModule
{

    /**
     * register
     *
     * Attaches the maybeServe() method to template_redirect.
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
        add_action('template_redirect', [$this, 'maybeServe'], 2);
    }

    /**
     * maybeServe
     *
     * Checks whether the current request is singular and the Accept header
     * includes text/markdown. If both are true, serves a Markdown response
     * and exits. Otherwise returns normally.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function maybeServe(): void
    {
        // make sure we have this enabled, and we're on a single post
        if (! $this->opt('markdown_enabled', true)) return;
        if (! is_singular()) return;

        // make sure we accept the proper content type of what we want to serve up here
        $accept = sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (! $this->prefersMarkdown($accept)) {
            return;
        }

        // setup the post
        global $post;
        if (! $post instanceof \WP_Post) return;

        // setup the relevant post data
        $title = wp_strip_all_tags(get_the_title($post));
        $title = preg_replace('/([\\\\`*_{}\[\]()#+\-.!])/u', '\\\\$1', $title);
        $url   = esc_url(get_permalink($post));
        $content = apply_filters('the_content', $post->post_content);
        $md      = HtmlToMarkdown::convert($content);

        // setup the return markdown content
        status_header(200);
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Vary: Accept');
        header('X-Content-Type-Options: nosniff');

        // echo out the converted content, then exit so nothing further is output
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "# {$title}\n\n> {$url}\n\n{$md}";
        exit;
    }

    /**
     * prefersMarkdown
     *
     * Returns true only when the client explicitly lists text/markdown with
     * a non-zero q-value that is at least as high as text/html's. Wildcard
     * accepts (Accept: wildcards from curl, browsers, etc.) never trigger
     * markdown — the type has to be named.
     *
     * @since 1.1.2
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $accept The raw Accept header value
     *
     * @return bool Whether a Markdown response is the right answer
     *
     */
    private function prefersMarkdown(string $accept): bool
    {
        if ($accept === '') {
            return false;
        }

        $md   = $this->acceptQuality($accept, 'text/markdown');
        $html = $this->acceptQuality($accept, 'text/html');

        return $md > 0.0 && $md >= $html;
    }

    /**
     * acceptQuality
     *
     * Extracts the q-value associated with an exact media type in an Accept
     * header. Wildcard ranges (*\/* and text/*) are intentionally ignored —
     * this plugin only serves Markdown when the client asks for it by name.
     * Returns 0.0 when the type is absent or listed with q=0.
     *
     * @since 1.1.2
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $accept The raw Accept header value
     * @param string $type   Fully qualified media type (e.g. text/markdown)
     *
     * @return float The resolved quality factor in the range 0.0–1.0
     *
     */
    private function acceptQuality(string $accept, string $type): float
    {
        $type = strtolower($type);
        $best = 0.0;

        foreach (explode(',', $accept) as $entry) {
            $parts = array_map('trim', explode(';', trim($entry)));
            $media = strtolower((string) array_shift($parts));

            if ($media !== $type) {
                continue;
            }

            $q = 1.0;
            foreach ($parts as $param) {
                if (stripos($param, 'q=') === 0) {
                    $q = (float) substr($param, 2);
                    break;
                }
            }

            // Clamp to the valid RFC 9110 range
            $q = max(0.0, min(1.0, $q));

            if ($q > $best) {
                $best = $q;
            }
        }

        return $best;
    }
}
