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
        if (! $this->opt('markdown_enabled', true)) return;
        if (! is_singular()) return;

        $accept = sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (! str_contains($accept, 'text/markdown')) return;

        global $post;
        setup_postdata($post);

        $title   = get_the_title($post);
        $url     = get_permalink($post);
        $content = apply_filters('the_content', $post->post_content);
        $md      = HtmlToMarkdown::convert($content);

        status_header(200);
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Vary: Accept');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "# {$title}\n\n> {$url}\n\n{$md}";
        exit;
    }
}
