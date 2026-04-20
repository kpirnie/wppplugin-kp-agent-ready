<?php

namespace KP\AgentReady\Modules;

use KP\AgentReady\Helpers\HtmlToMarkdown;

/**
 * Serves text/markdown responses for singular posts/pages when the
 * client sends an Accept header containing "text/markdown".
 */
class MarkdownNegotiation extends AbstractModule
{

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybeServe'], 2);
    }

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
