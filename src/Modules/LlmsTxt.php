<?php

/**
 * LlmsTxt
 *
 * Generates and writes physical /llms.txt and /llms-full.txt files to the
 * WordPress root directory per the llmstxt.org specification. Files are
 * regenerated automatically on post save/status change and when plugin
 * settings are saved. A manual regenerate action is also provided.
 *
 * If WP_Filesystem cannot write to ABSPATH, the generated content is stored
 * in a transient so the admin can copy/paste it manually from the settings tab.
 *
 * @since 1.2.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 *
 */

declare(strict_types=1);

// Setup the namespace
namespace KP\AgentReady\Modules;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

// Pull in the markdown converter
use KP\AgentReady\Helpers\HtmlToMarkdown;
use KP\AgentReady\Plugin;

/**
 * LlmsTxt
 *
 * Writes /llms.txt and /llms-full.txt to the WordPress web root and keeps
 * them in sync with content and settings changes.
 *
 * @since 1.2.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class LlmsTxt extends AbstractModule
{

    /** Filename for the slim file in the web root. */
    private const FILE_SLIM = 'llms.txt';

    /** Filename for the full file in the web root. */
    private const FILE_FULL = 'llms-full.txt';

    /** Transient key for write-failure content (slim). */
    private const TRANSIENT_SLIM = 'kp_agent_ready_llms_slim_content';

    /** Transient key for write-failure content (full). */
    private const TRANSIENT_FULL = 'kp_agent_ready_llms_full_content';

    /** Transient key recording which files failed on the last run. */
    private const TRANSIENT_ERRORS = 'kp_agent_ready_llms_write_errors';

    /** AJAX action handle for the manual regenerate button. */
    private const AJAX_ACTION = 'kp_agent_ready_regenerate_llms';

    /**
     * register
     *
     * Attaches all hooks required to keep the files current.
     *
     * @since 1.2.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function register(): void
    {
        if (! $this->opt('llms_enabled', false)) {
            return;
        }

        // Regenerate when a relevant post is saved or its status changes
        add_action('save_post',              [$this, 'onPostSave'],      20, 2);
        add_action('transition_post_status', [$this, 'onStatusChange'],  20, 3);

        // Regenerate when our own settings are saved
        add_action('update_option_' . Plugin::OPTION_KEY, [$this, 'onSettingsSaved'], 20);

        // AJAX handler
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajaxRegenerate']);

        // Admin notice for write failures
        add_action('admin_notices', [$this, 'maybeShowFailureNotice']);
    }

    // -------------------------------------------------------------------------
    // Hook callbacks
    // -------------------------------------------------------------------------

    /**
     * onPostSave
     *
     * Triggers a regeneration when a post in an included type is saved,
     * provided it is published and not an autosave or revision.
     *
     * @since 1.2.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param int      $post_id The saved post ID
     * @param \WP_Post $post    The post object
     *
     * @return void This method does not return anything
     *
     */
    public function onPostSave(int $post_id, \WP_Post $post): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        if (! $this->isIncludedPostType($post->post_type)) {
            return;
        }

        $this->generate();
    }

    /**
     * onStatusChange
     *
     * Triggers a regeneration when a post transitions to or from published
     * status, catching publish, unpublish, and trash operations.
     *
     * @since 1.2.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string   $new_status Incoming post status
     * @param string   $old_status Previous post status
     * @param \WP_Post $post       The post object
     *
     * @return void This method does not return anything
     *
     */
    public function onStatusChange(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($new_status === $old_status) {
            return;
        }

        if ($new_status !== 'publish' && $old_status !== 'publish') {
            return;
        }

        if (! $this->isIncludedPostType($post->post_type)) {
            return;
        }

        $this->generate();
    }

    /**
     * onSettingsSaved
     *
     * Triggers a regeneration whenever the plugin option is updated in the
     * database, ensuring file content always reflects current settings.
     *
     * @since 1.2.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function onSettingsSaved(): void
    {
        // Re-read options from DB since $this->options was loaded at boot
        $this->options = (array) get_option(Plugin::OPTION_KEY, []);
        $this->generate();
    }

    /**
     * ajaxRegenerate
     *
     * AJAX handler for the manual Regenerate button in the settings tab.
     * Verifies the nonce and capability before running.
     *
     * @since 1.2.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return never Sends JSON and exits
     *
     */
    public function ajaxRegenerate(): never
    {
        check_ajax_referer(self::AJAX_ACTION, 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'kp-agent-ready')]);
        }

        $this->options = (array) get_option(Plugin::OPTION_KEY, []);
        $errors        = $this->generate();

        if (empty($errors)) {
            wp_send_json_success([
                'message'   => __('Files regenerated successfully.', 'kp-agent-ready'),
                'timestamp' => current_time('mysql'),
            ]);
        }

        wp_send_json_error([
            'message' => implode(' ', $errors),
        ]);
    }

    /**
     * maybeShowFailureNotice
     *
     * Displays an admin notice with copyable file content whenever the last
     * write attempt failed and the admin is on our settings page.
     *
     * @since 1.2.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public function maybeShowFailureNotice(): void
    {
        $errors = get_transient(self::TRANSIENT_ERRORS);
        if (empty($errors)) {
            return;
        }

        // Only show on our settings page
        $screen = get_current_screen();
        if (! $screen || strpos($screen->id, Plugin::OPTION_KEY) === false) {
            return;
        }

        $slim = get_transient(self::TRANSIENT_SLIM) ?: '';
        $full = get_transient(self::TRANSIENT_FULL)  ?: '';

        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . esc_html__('KP Agent Ready — llms.txt write failed', 'kp-agent-ready') . '</strong></p>';
        echo '<p>' . esc_html__('Your hosting environment prevented automatic file creation. Copy the content below and paste it into the matching file in the root of your WordPress installation manually.', 'kp-agent-ready') . '</p>';

        if ($slim) {
            echo '<p><strong>llms.txt</strong> &rarr; paste into <code>' . esc_html(ABSPATH . 'llms.txt') . '</code></p>';
            echo '<textarea readonly rows="10" style="width:100%;font-family:monospace;font-size:12px;">' . esc_textarea($slim) . '</textarea>';
        }

        if ($full) {
            echo '<p><strong>llms-full.txt</strong> &rarr; paste into <code>' . esc_html(ABSPATH . 'llms-full.txt') . '</code></p>';
            echo '<textarea readonly rows="10" style="width:100%;font-family:monospace;font-size:12px;">' . esc_textarea($full) . '</textarea>';
        }

        echo '</div>';
    }

    // -------------------------------------------------------------------------
    // Generation
    // -------------------------------------------------------------------------

    /**
     * generate
     *
     * Builds content for both files and attempts to write them to disk via
     * WP_Filesystem. Stores generated content in transients as a fallback
     * for hosts where direct filesystem writes are not permitted.
     *
     * @since 1.2.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string> Any error messages produced during the write attempt
     *
     */
    public function generate(): array
    {
        $slim = $this->buildSlim();
        $full = $this->buildFull();

        // Always persist so the copy/paste fallback is current
        set_transient(self::TRANSIENT_SLIM, $slim, DAY_IN_SECONDS * 7);
        set_transient(self::TRANSIENT_FULL, $full, DAY_IN_SECONDS * 7);

        $errors = [];

        if (! $this->writeFile(self::FILE_SLIM, $slim)) {
            $errors[] = sprintf(
                /* translators: %s: filename */
                __('Could not write %s.', 'kp-agent-ready'),
                ABSPATH . self::FILE_SLIM
            );
        }

        if (! $this->writeFile(self::FILE_FULL, $full)) {
            $errors[] = sprintf(
                /* translators: %s: filename */
                __('Could not write %s.', 'kp-agent-ready'),
                ABSPATH . self::FILE_FULL
            );
        }

        if (! empty($errors)) {
            set_transient(self::TRANSIENT_ERRORS, $errors, HOUR_IN_SECONDS * 6);
        } else {
            delete_transient(self::TRANSIENT_ERRORS);
        }

        return $errors;
    }

    /**
     * buildSlim
     *
     * Constructs the llms.txt content — site header, optional intro block,
     * and one link line per resource with no inline description.
     *
     * @since 1.2.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return string The complete llms.txt content
     *
     */
    private function buildSlim(): string
    {
        $lines = $this->buildHeader();

        // Optional custom intro block
        $intro = trim($this->opt('llms_intro', ''));
        if ($intro !== '') {
            $lines[] = '';
            $lines[] = $intro;
        }

        // Pages
        if ($this->opt('llms_include_pages', true)) {
            $section = $this->buildPostSection('page', __('Pages', 'kp-agent-ready'), false);
            if ($section !== '') {
                $lines[] = '';
                $lines[] = $section;
            }
        }

        // Posts
        if ($this->opt('llms_include_posts', true)) {
            $section = $this->buildPostSection('post', __('Blog Posts', 'kp-agent-ready'), false);
            if ($section !== '') {
                $lines[] = '';
                $lines[] = $section;
            }
        }

        // Enabled CPTs
        foreach ((array) $this->opt('llms_cpts', []) as $post_type) {
            $obj = get_post_type_object($post_type);
            if (! $obj || ! $obj->public) {
                continue;
            }

            $section = $this->buildPostSection($post_type, $obj->label, false);
            if ($section !== '') {
                $lines[] = '';
                $lines[] = $section;
            }
        }

        // Optional links section
        $optional = $this->buildOptionalSection(false);
        if ($optional !== '') {
            $lines[] = '';
            $lines[] = $optional;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * buildFull
     *
     * Constructs the llms-full.txt content — identical structure to llms.txt
     * but each entry is followed by its excerpt or truncated post content.
     *
     * @since 1.2.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return string The complete llms-full.txt content
     *
     */
    private function buildFull(): string
    {
        $lines = $this->buildHeader();

        // Optional custom intro block
        $intro = trim($this->opt('llms_intro', ''));
        if ($intro !== '') {
            $lines[] = '';
            $lines[] = $intro;
        }

        // Pages
        if ($this->opt('llms_include_pages', true)) {
            $section = $this->buildPostSection('page', __('Pages', 'kp-agent-ready'), true);
            if ($section !== '') {
                $lines[] = '';
                $lines[] = $section;
            }
        }

        // Posts
        if ($this->opt('llms_include_posts', true)) {
            $section = $this->buildPostSection('post', __('Blog Posts', 'kp-agent-ready'), true);
            if ($section !== '') {
                $lines[] = '';
                $lines[] = $section;
            }
        }

        // Enabled CPTs
        foreach ((array) $this->opt('llms_cpts', []) as $post_type) {
            $obj = get_post_type_object($post_type);
            if (! $obj || ! $obj->public) {
                continue;
            }

            $section = $this->buildPostSection($post_type, $obj->label, true);
            if ($section !== '') {
                $lines[] = '';
                $lines[] = $section;
            }
        }

        // Optional links section
        $optional = $this->buildOptionalSection(true);
        if ($optional !== '') {
            $lines[] = '';
            $lines[] = $optional;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * buildHeader
     *
     * Returns the opening lines shared by both files: H1 site name,
     * blockquote tagline, and a blank separator.
     *
     * @since 1.2.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return array<string> Header lines
     *
     */
    private function buildHeader(): array
    {
        $name    = get_bloginfo('name');
        $tagline = get_bloginfo('description');

        $lines   = [];
        $lines[] = '# ' . $name;

        if ($tagline !== '') {
            $lines[] = '';
            $lines[] = '> ' . $tagline;
        }

        return $lines;
    }

    /**
     * buildPostSection
     *
     * Queries published posts of a given type and returns a Markdown H2
     * section string with one entry per post. In slim mode only the link
     * is output; in full mode the excerpt is appended as a content block.
     *
     * @since 1.2.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $post_type WordPress post type slug
     * @param string $label     Section heading label
     * @param bool   $full      When true, appends excerpt/content block per entry
     *
     * @return string Markdown section block, or empty string if no posts found
     *
     */
    private function buildPostSection(string $post_type, string $label, bool $full): string
    {
        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        if (empty($posts)) {
            return '';
        }

        $lines   = [];
        $lines[] = '## ' . $label;

        foreach ($posts as $post) {
            $url   = get_permalink($post);
            $title = wp_strip_all_tags(get_the_title($post));

            // Slim: link only
            $lines[] = '- [' . $title . '](' . $url . ')';

            // Full: excerpt block beneath the link
            if ($full) {
                $description = $this->getExcerpt($post);
                $lines[]     = '';
                $lines[]     = '  ' . $description;
                $lines[]     = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * buildOptionalSection
     *
     * Renders the manually defined optional links from the llms_custom_links
     * repeater setting under a single ## Optional heading.
     *
     * @since 1.2.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param bool $full When true, includes the description body beneath each entry
     *
     * @return string Markdown section block, or empty string if no links defined
     *
     */
    private function buildOptionalSection(bool $full): string
    {
        $links = (array) ($this->opt('llms_custom_links', []) ?: []);

        if (empty($links)) {
            return '';
        }

        $lines   = [];
        $lines[] = '## Optional';

        foreach ($links as $row) {
            $label       = trim($row['label'] ?? '');
            $url         = trim($row['url'] ?? '');
            $description = trim($row['description'] ?? '');

            if ($label === '') {
                continue;
            }

            $entry   = $url !== '' ? '- [' . $label . '](' . $url . ')' : '- ' . $label;
            $lines[] = $entry;

            if ($full && $description !== '') {
                $lines[] = '';
                $lines[] = '  ' . $description;
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * getExcerpt
     *
     * Returns a plain-text excerpt for a post. Uses the manual post_excerpt
     * when set; otherwise strips HTML from post_content and trims to the
     * configured word limit. When $word_limit is 0 the configured setting
     * value is used.
     *
     * @since 1.2.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \WP_Post $post       The post to excerpt
     * @param int      $word_limit Override word limit; 0 uses the setting value
     *
     * @return string Plain-text excerpt
     *
     */
    private function getExcerpt(\WP_Post $post, int $word_limit = 0): string
    {
        // Use manually written excerpt when available
        if (! empty($post->post_excerpt)) {
            return wp_strip_all_tags($post->post_excerpt);
        }

        $limit   = $word_limit > 0 ? $word_limit : max(1, (int) $this->opt('llms_excerpt_words', 200));
        $content = apply_filters('the_content', $post->post_content);
        $text    = wp_strip_all_tags(HtmlToMarkdown::convert($content));
        $text    = preg_replace('/\s+/', ' ', trim($text));
        $words   = explode(' ', $text);

        if (count($words) <= $limit) {
            return $text;
        }

        return implode(' ', array_slice($words, 0, $limit)) . ' …';
    }

    // -------------------------------------------------------------------------
    // Filesystem
    // -------------------------------------------------------------------------

    /**
     * writeFile
     *
     * Writes content to a file in ABSPATH using WP_Filesystem.
     *
     * @since 1.2.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $filename Filename relative to ABSPATH (no leading slash)
     * @param string $content  Content to write
     *
     * @return bool True on success, false on failure
     *
     */
    private function writeFile(string $filename, string $content): bool
    {
        global $wp_filesystem;

        if (! function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (! WP_Filesystem()) {
            return false;
        }

        $path = rtrim(ABSPATH, '/') . '/' . ltrim($filename, '/');

        return (bool) $wp_filesystem->put_contents($path, $content, 0644);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * isIncludedPostType
     *
     * Returns true when the given post type is enabled for llms.txt output
     * based on current settings.
     *
     * @since 1.2.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $post_type Post type slug to check
     *
     * @return bool Whether this post type is included
     *
     */
    private function isIncludedPostType(string $post_type): bool
    {
        if ($post_type === 'page' && $this->opt('llms_include_pages', true)) {
            return true;
        }

        if ($post_type === 'post' && $this->opt('llms_include_posts', true)) {
            return true;
        }

        return in_array($post_type, (array) $this->opt('llms_cpts', []), true);
    }

    /**
     * deleteFiles
     *
     * Removes both generated files and their associated transients from disk.
     * Called on plugin uninstall.
     *
     * @since 1.2.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return void This method does not return anything
     *
     */
    public static function deleteFiles(): void
    {
        foreach ([self::FILE_SLIM, self::FILE_FULL] as $file) {
            $path = rtrim(ABSPATH, '/') . '/' . $file;
            if (file_exists($path)) {
                wp_delete_file($path);
            }
        }

        delete_transient(self::TRANSIENT_SLIM);
        delete_transient(self::TRANSIENT_FULL);
        delete_transient(self::TRANSIENT_ERRORS);
    }

    /**
     * getLastModified
     *
     * Returns a formatted datetime string for when llms.txt was last written,
     * or an em-dash when the file does not exist.
     *
     * @since 1.2.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @return string Formatted date/time or '—'
     *
     */
    public static function getLastModified(): string
    {
        $path = rtrim(ABSPATH, '/') . '/' . self::FILE_SLIM;

        if (! file_exists($path)) {
            return '—';
        }

        return date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            filemtime($path)
        );
    }
}
