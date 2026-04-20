<?php

/** 
 * HtmlToMarkdown
 * 
 * Provides a basic HTML to Markdown conversion used by the
 * MarkdownNegotiation module when serving text/markdown responses.
 * 
 * @since 1.0.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 * 
 */

// setup the namespace
namespace KP\AgentReady\Helpers;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

/**
 * HtmlToMarkdown
 *
 * Provides a basic HTML to Markdown conversion used by the
 * MarkdownNegotiation module when serving text/markdown responses.
 *
 * @since 1.0.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class HtmlToMarkdown
{

    /**
     * convert
     *
     * Converts an HTML string to a Markdown string. Handles headings,
     * inline formatting, links, images, lists, paragraphs, code blocks,
     * blockquotes, and horizontal rules. Strips any remaining tags after
     * conversion and normalises whitespace.
     *
     * @since 1.0.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $html The HTML string to convert
     *
     * @return string The converted Markdown string
     *
     */
    public static function convert(string $html): string
    {
        // Strip scripts and styles first
        $html = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/si', '', $html);

        // Pre/code blocks before inline code to avoid double-processing
        $html = preg_replace('/<pre[^>]*>\s*<code[^>]*>(.*?)<\/code>\s*<\/pre>/si', "```\n$1\n```\n\n", $html);

        $map = [
            '/<h1[^>]*>(.*?)<\/h1>/si'                                           => "# $1\n\n",
            '/<h2[^>]*>(.*?)<\/h2>/si'                                           => "## $1\n\n",
            '/<h3[^>]*>(.*?)<\/h3>/si'                                           => "### $1\n\n",
            '/<h4[^>]*>(.*?)<\/h4>/si'                                           => "#### $1\n\n",
            '/<h5[^>]*>(.*?)<\/h5>/si'                                           => "##### $1\n\n",
            '/<h6[^>]*>(.*?)<\/h6>/si'                                           => "###### $1\n\n",
            '/<blockquote[^>]*>(.*?)<\/blockquote>/si'                           => "> $1\n\n",
            '/<(strong|b)[^>]*>(.*?)<\/\1>/si'                                   => "**$2**",
            '/<(em|i)[^>]*>(.*?)<\/\1>/si'                                       => "_$2_",
            '/<code[^>]*>(.*?)<\/code>/si'                                       => '`$1`',
            '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si'                => '[$2]($1)',
            '/<img[^>]+src=["\']([^"\']+)["\'][^>]+alt=["\']([^"\']*)["\'][^>]*\/?>/si' => '![$2]($1)',
            '/<li[^>]*>(.*?)<\/li>/si'                                           => "- $1\n",
            '/<p[^>]*>(.*?)<\/p>/si'                                             => "$1\n\n",
            '/<br\s*\/?>/si'                                                     => "\n",
            '/<hr\s*\/?>/si'                                                     => "\n---\n\n",
        ];

        foreach ($map as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html);
        }

        $html = wp_strip_all_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/\n{3,}/', "\n\n", $html);

        return trim($html);
    }
}
