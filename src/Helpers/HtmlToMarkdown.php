<?php

/**
 * HtmlToMarkdown
 *
 * Converts an HTML string to clean Markdown. Used by the MarkdownNegotiation
 * module when serving text/markdown responses to AI agents. Handles the full
 * range of HTML structures produced by WordPress, including Gutenberg blocks,
 * shortcodes, nested lists, multi-header tables with colspan/rowspan, and
 * fenced code blocks with language hints.
 *
 * For inputs exceeding the streaming threshold, falls back to a lightweight
 * XMLReader pass that strips markup and preserves text content only.
 *
 * @since 1.1.0
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Agent Ready
 *
 */

declare(strict_types=1);

// Setup the namespace
namespace KPAgentReady\Helpers;

// We don't want to allow direct access to this
defined('ABSPATH') || die('No direct script access allowed');

/**
 * HtmlToMarkdown
 *
 * Converts an HTML string to clean Markdown via DOMDocument (standard path)
 * or XMLReader (large-document streaming fallback).
 *
 * @since 1.1.0
 * @author Kevin Pirnie <iam@kevinpirnie.com>
 * @package KP Agent Ready
 *
 */
class HtmlToMarkdown
{

    /**
     * Byte length above which the streaming XMLReader path is used instead
     * of DOMDocument. 2 MB covers virtually all real WordPress content.
     */
    private const STREAMING_THRESHOLD = 2_000_000;

    /**
     * Tags whose entire subtree should be discarded, including text content.
     */
    private const DISCARD_TAGS = ['script', 'style', 'meta', 'link', 'head', 'noscript', 'iframe', 'object', 'embed'];

    /**
     * Block-level tags that should be separated from surrounding content with
     * a trailing double newline.
     */
    private const BLOCK_TAGS = ['div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'figure', 'details'];

    /**
     * Tags whose text content is passed through without Markdown escaping.
     */
    private const PASS_TAGS = ['span', 'label', 'abbr', 'cite', 'mark', 'small', 'sub', 'sup', 'time', 'body', 'html', 'li', 'td', 'th', 'tr', 'thead', 'tbody', 'tfoot', 'summary'];

    /**
     * convert
     *
     * Converts an HTML string to Markdown. Chooses between DOMDocument and
     * streaming XMLReader based on document length.
     *
     * @since 1.1.0
     * @access public
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $html Raw HTML to convert
     *
     * @return string Converted Markdown string
     *
     */
    public static function convert(string $html): string
    {
        if (strlen($html) > self::STREAMING_THRESHOLD) {
            return self::convertStreaming($html);
        }

        return self::convertDOM($html);
    }

    /**
     * convertDOM
     *
     * Parses the HTML with DOMDocument and recursively renders each node
     * to its Markdown equivalent.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $html Raw HTML to convert
     *
     * @return string Normalised Markdown string
     *
     */
    private static function convertDOM(string $html): string
    {
        $doc = new \DOMDocument();

        libxml_use_internal_errors(true);

        $doc->loadHTML(
            '<?xml encoding="utf-8" ?>' . self::preprocessWordPress($html),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        return self::normalize(self::renderNode($doc));
    }

    /**
     * renderNode
     *
     * Recursively converts a single DOMNode and all its descendants to
     * Markdown. Dispatches to specialised renderers for complex elements
     * such as lists, tables, and code blocks.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \DOMNode $node The node to render
     *
     * @return string Markdown fragment for this node
     *
     */
    private static function renderNode(\DOMNode $node): string
    {
        // Plain text — escape Markdown metacharacters
        if ($node instanceof \DOMText) {
            return self::escape($node->nodeValue ?? '');
        }

        // Non-element nodes (DOMDocument, DOMComment, DOMProcessingInstruction…)
        if (!($node instanceof \DOMElement)) {
            $out = '';
            foreach ($node->childNodes as $child) {
                $out .= self::renderNode($child);
            }
            return $out;
        }

        $tag = strtolower($node->tagName);

        // ----- Discard -----
        if (in_array($tag, self::DISCARD_TAGS, true)) {
            return '';
        }

        // ----- Pre / fenced code block -----
        if ($tag === 'pre') {
            return self::renderPre($node);
        }

        // ----- Inline code — raw text, no child escaping -----
        if ($tag === 'code') {
            $raw = html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '`' . $raw . '`';
        }

        // ----- Lists -----
        if ($tag === 'ul') return self::renderList($node, false);
        if ($tag === 'ol') return self::renderList($node, true);

        // ----- Table -----
        if ($tag === 'table') return self::renderTable($node);

        // ----- Everything else: render children first -----
        $content = '';
        foreach ($node->childNodes as $child) {
            $content .= self::renderNode($child);
        }

        // Block-level containers
        if (in_array($tag, self::BLOCK_TAGS, true)) {
            return trim($content) !== '' ? trim($content) . "\n\n" : '';
        }

        // Pass-through inline / structural tags
        if (in_array($tag, self::PASS_TAGS, true)) {
            return $content;
        }

        return match ($tag) {
            'p'                  => trim($content) . "\n\n",
            'h1'                 => '# '      . trim($content) . "\n\n",
            'h2'                 => '## '     . trim($content) . "\n\n",
            'h3'                 => '### '    . trim($content) . "\n\n",
            'h4'                 => '#### '   . trim($content) . "\n\n",
            'h5'                 => '##### '  . trim($content) . "\n\n",
            'h6'                 => '###### ' . trim($content) . "\n\n",
            'blockquote'         => self::renderBlockquote(trim($content)),
            'hr'                 => "\n---\n\n",
            'strong', 'b'        => '**' . trim($content) . '**',
            'em', 'i'            => '*' . trim($content) . '*',
            'del', 's', 'strike' => '~~' . trim($content) . '~~',
            'a'                  => self::renderLink($node, trim($content)),
            'img'                => self::renderImage($node),
            'br'                 => "  \n",
            'figcaption'         => '_' . trim($content) . "_\n\n",
            default              => $content,
        };
    }

    /**
     * renderPre
     *
     * Renders a <pre> block as a fenced Markdown code block, optionally
     * extracting the language hint from a nested <code class="language-*">.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \DOMElement $node The <pre> element
     *
     * @return string Fenced code block Markdown
     *
     */
    private static function renderPre(\DOMElement $node): string
    {
        $lang = '';

        // Attempt to pull a language hint from <code class="language-php"> etc.
        $codeNodes = $node->getElementsByTagName('code');
        if ($codeNodes->length > 0) {
            $class = $codeNodes->item(0)->getAttribute('class');
            if (preg_match('/(?:language|lang)-(\S+)/i', $class, $m)) {
                $lang = $m[1];
            }
        }

        $raw = html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return "```{$lang}\n" . rtrim($raw) . "\n```\n\n";
    }

    /**
     * renderList
     *
     * Recursively renders <ul> and <ol> elements to Markdown bullet or
     * numbered lists, indenting nested lists correctly.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \DOMElement $node    The list element
     * @param bool        $ordered True for <ol>, false for <ul>
     * @param int         $depth   Current nesting depth (0 = top level)
     *
     * @return string Markdown list fragment
     *
     */
    private static function renderList(\DOMElement $node, bool $ordered, int $depth = 0): string
    {
        $out    = '';
        $index  = max(1, (int) ($node->getAttribute('start') ?: 1));
        $indent = str_repeat('  ', $depth);

        foreach ($node->childNodes as $li) {
            if (!($li instanceof \DOMElement) || $li->tagName !== 'li') {
                continue;
            }

            $prefix = $indent . ($ordered ? "{$index}. " : '- ');
            $text   = '';
            $nested = '';

            // Separate inline text from nested list children
            foreach ($li->childNodes as $child) {
                if ($child instanceof \DOMElement && in_array($child->tagName, ['ul', 'ol'], true)) {
                    $nested .= self::renderList($child, $child->tagName === 'ol', $depth + 1);
                } else {
                    $text .= self::renderNode($child);
                }
            }

            $out .= $prefix . trim($text) . "\n";

            if ($nested !== '') {
                $out .= $nested;
            }

            $index++;
        }

        // Only add trailing blank line at the outermost level
        return $out . ($depth === 0 ? "\n" : '');
    }

    /**
     * renderBlockquote
     *
     * Prefixes each line of the content with the Markdown `> ` marker.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $content Already-rendered inner content
     *
     * @return string Blockquote Markdown
     *
     */
    private static function renderBlockquote(string $content): string
    {
        $lines = explode("\n", rtrim($content));
        return implode("\n", array_map(static fn($l) => '> ' . $l, $lines)) . "\n\n";
    }

    /**
     * renderLink
     *
     * Renders an <a> element as a Markdown inline link, including the optional
     * title attribute.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \DOMElement $node    The anchor element
     * @param string      $content Already-rendered link text
     *
     * @return string Markdown link or bare content when href is absent
     *
     */
    private static function renderLink(\DOMElement $node, string $content): string
    {
        $href  = esc_url_raw($node->getAttribute('href'));
        $title = esc_attr($node->getAttribute('title'));

        // make sure we have a url
        if ($href === '') {
            return $content;
        }

        return $title !== ''
            ? "[{$content}]({$href} \"{$title}\")"
            : "[{$content}]({$href})";
    }

    /**
     * renderImage
     *
     * Renders an <img> element as a Markdown image tag, including the optional
     * title attribute.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \DOMElement $node The image element
     *
     * @return string Markdown image tag
     *
     */
    private static function renderImage(\DOMElement $node): string
    {
        $src   = esc_url_raw($node->getAttribute('src'));
        $alt   = esc_attr($node->getAttribute('alt'));
        $title = esc_attr($node->getAttribute('title'));

        // make sure we have a source
        if ($src === '') {
            return '';
        }

        return $title !== ''
            ? "![{$alt}]({$src} \"{$title}\")"
            : "![{$alt}]({$src})";
    }

    /**
     * renderTable
     *
     * Converts an HTML table to a GFM pipe table. Handles:
     * - <thead>/<tbody>/<tfoot> sections
     * - Multi-row headers collapsed into a single header row
     * - colspan/rowspan cell spanning via grid expansion
     * - Alignment detected from align attribute and inline CSS
     * - Numeric column auto right-alignment
     * - Optional <caption> rendered above the table
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \DOMElement $table The table element
     *
     * @return string GFM pipe table Markdown, or empty string for empty tables
     *
     */
    private static function renderTable(\DOMElement $table): string
    {
        // Optional caption rendered as bold text above the table
        $caption = '';
        $capEl   = $table->getElementsByTagName('caption')->item(0);
        if ($capEl) {
            $caption = '**' . trim($capEl->textContent) . "**\n\n";
        }

        // Collect <tr> nodes, tracking which belong to <thead>
        $rawRows    = [];
        $headerRows = 0;

        if ($thead = $table->getElementsByTagName('thead')->item(0)) {
            foreach ($thead->getElementsByTagName('tr') as $tr) {
                $rawRows[] = ['node' => $tr, 'header' => true];
                $headerRows++;
            }
        }

        foreach (['tbody', 'tfoot'] as $section) {
            $el = $table->getElementsByTagName($section)->item(0);
            if ($el) {
                foreach ($el->getElementsByTagName('tr') as $tr) {
                    $rawRows[] = ['node' => $tr, 'header' => false];
                }
            }
        }

        // Fallback: no explicit sections — treat first row as header
        if (empty($rawRows)) {
            foreach ($table->getElementsByTagName('tr') as $i => $tr) {
                $rawRows[] = ['node' => $tr, 'header' => $i === 0];
                if ($i === 0) {
                    $headerRows = 1;
                }
            }
        }

        if (empty($rawRows)) {
            return '';
        }

        // Build a 2-D grid respecting colspan and rowspan
        $grid = [];
        foreach ($rawRows as $ri => $meta) {
            $grid[$ri] ??= [];
            $ci = 0;

            foreach ($meta['node']->childNodes as $cell) {
                if (!($cell instanceof \DOMElement) || !in_array($cell->tagName, ['td', 'th'], true)) {
                    continue;
                }

                // Advance past cells already filled in by a previous rowspan
                while (isset($grid[$ri][$ci])) {
                    $ci++;
                }

                $text    = self::cleanCell(self::renderNode($cell));
                $colspan = max(1, (int) ($cell->getAttribute('colspan') ?: 1));
                $rowspan = max(1, (int) ($cell->getAttribute('rowspan') ?: 1));

                // Fill all spanned positions with the same text
                for ($r = 0; $r < $rowspan; $r++) {
                    for ($c = 0; $c < $colspan; $c++) {
                        $grid[$ri + $r][$ci + $c] = $text;
                    }
                }

                $ci += $colspan;
            }
        }

        // Normalise every row to the same column count
        $maxCols = max(array_map('count', $grid));
        foreach ($grid as &$row) {
            ksort($row);
            while (count($row) < $maxCols) {
                $row[] = '';
            }
            $row = array_values($row);
        }
        unset($row);

        // Detect column alignments from the first header row's cells
        $alignments = array_fill(0, $maxCols, 'default');
        if (!empty($rawRows[0])) {
            $ci = 0;
            foreach ($rawRows[0]['node']->childNodes as $cell) {
                if (!($cell instanceof \DOMElement) || !in_array($cell->tagName, ['td', 'th'], true)) {
                    continue;
                }
                if ($ci < $maxCols) {
                    $alignments[$ci] = self::detectAlignment($cell);
                }
                $ci++;
            }
        }

        // Right-align columns whose data rows are all numeric
        $alignments = self::inferNumericAlignment($grid, $alignments, $headerRows);

        // Collapse multi-row headers into one header row
        $headerCols = self::collapseHeaders($grid, $headerRows, $maxCols);

        // Compute column widths across all rows (minimum 3 for the separator)
        $widths = array_fill(0, $maxCols, 3);
        foreach ($headerCols as $i => $cell) {
            $widths[$i] = max($widths[$i], mb_strlen($cell));
        }
        foreach (array_slice($grid, $headerRows) as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 3, mb_strlen($cell));
            }
        }

        $output = $caption;

        // Header row
        $output .= '| ' . self::formatTableRow($headerCols, $widths) . " |\n";

        // Separator row with alignment markers
        $sep = [];
        foreach ($alignments as $i => $align) {
            $w     = $widths[$i];
            $sep[] = match ($align) {
                'left'   => ':' . str_repeat('-', $w - 1),
                'center' => ':' . str_repeat('-', max(1, $w - 2)) . ':',
                'right'  => str_repeat('-', $w - 1) . ':',
                default  => str_repeat('-', $w),
            };
        }
        $output .= '| ' . implode(' | ', $sep) . " |\n";

        // Data rows
        foreach (array_slice($grid, $headerRows) as $row) {
            $output .= '| ' . self::formatTableRow(array_values($row), $widths) . " |\n";
        }

        return $output . "\n";
    }

    /**
     * collapseHeaders
     *
     * Merges multiple header rows into a single array of column labels by
     * concatenating unique non-empty values found across the header rows.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array $grid       The full table grid
     * @param int   $headerRows Number of rows that form the header section
     * @param int   $maxCols    Total column count
     *
     * @return array Single-row array of header strings
     *
     */
    private static function collapseHeaders(array $grid, int $headerRows, int $maxCols): array
    {
        if ($headerRows === 0 || empty($grid)) {
            return array_fill(0, $maxCols, '');
        }

        $result = [];

        for ($c = 0; $c < $maxCols; $c++) {
            $parts = [];
            for ($r = 0; $r < min($headerRows, count($grid)); $r++) {
                $v = trim($grid[$r][$c] ?? '');
                // Only add unique, non-empty segments
                if ($v !== '' && !in_array($v, $parts, true)) {
                    $parts[] = $v;
                }
            }
            $result[] = implode(' ', $parts);
        }

        return $result;
    }

    /**
     * detectAlignment
     *
     * Reads the `align` attribute or inline `text-align` CSS to determine
     * the Markdown alignment marker for a table cell's column.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param \DOMElement $cell A <td> or <th> element
     *
     * @return string One of 'left', 'center', 'right', or 'default'
     *
     */
    private static function detectAlignment(\DOMElement $cell): string
    {
        $attr = strtolower(trim($cell->getAttribute('align')));
        if (in_array($attr, ['left', 'center', 'right'], true)) {
            return $attr;
        }

        if (preg_match('/text-align\s*:\s*(left|center|right)/i', $cell->getAttribute('style'), $m)) {
            return strtolower($m[1]);
        }

        return 'default';
    }

    /**
     * inferNumericAlignment
     *
     * Scans data rows to right-align columns whose non-empty values are all
     * numeric (integers, decimals, or common formatted numbers with commas,
     * currency symbols, or percentage signs).
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array $grid        Full table grid
     * @param array $alignments  Current alignment array
     * @param int   $headerRows  Number of header rows to skip
     *
     * @return array Updated alignments array
     *
     */
    private static function inferNumericAlignment(array $grid, array $alignments, int $headerRows): array
    {
        $dataRows = array_slice($grid, $headerRows);
        $cols     = count($grid[0] ?? []);

        for ($c = 0; $c < $cols; $c++) {
            // Only infer when no explicit alignment was found
            if ($alignments[$c] !== 'default') {
                continue;
            }

            $hasData    = false;
            $allNumeric = true;

            foreach ($dataRows as $row) {
                $raw = trim($row[$c] ?? '');
                if ($raw === '') {
                    continue;
                }

                $hasData = true;

                // Strip common numeric formatting before testing
                $clean = str_replace([',', ' ', '$', '%', '€', '£', '¥'], '', $raw);
                if (!is_numeric($clean)) {
                    $allNumeric = false;
                    break;
                }
            }

            if ($hasData && $allNumeric) {
                $alignments[$c] = 'right';
            }
        }

        return $alignments;
    }

    /**
     * formatTableRow
     *
     * Pads each cell to the column width and joins them with pipe separators.
     * Uses mb_strlen for correct multibyte character handling.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param array $cells  Cell values for one row
     * @param array $widths Per-column minimum widths
     *
     * @return string Pipe-separated padded row string (without outer pipes)
     *
     */
    private static function formatTableRow(array $cells, array $widths): string
    {
        $out = [];

        foreach ($cells as $i => $cell) {
            $w      = $widths[$i] ?? mb_strlen($cell);
            $padLen = $w - mb_strlen($cell);
            // Pad with spaces on the right (left-flush; separator controls display alignment)
            $out[] = $padLen > 0 ? $cell . str_repeat(' ', $padLen) : $cell;
        }

        return implode(' | ', $out);
    }

    /**
     * cleanCell
     *
     * Strips any remaining HTML tags and collapses whitespace in a table
     * cell value so it does not break the pipe table layout.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $text Raw cell content
     *
     * @return string Single-line plain-text cell value
     *
     */
    private static function cleanCell(string $text): string
    {
        // Replace pipes inside cells to avoid breaking the table structure
        return str_replace('|', '&#124;', trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($text))));
    }

    /**
     * preprocessWordPress
     *
     * Removes Gutenberg block comments and strips unprocessed shortcodes from
     * the HTML before DOM parsing so they do not appear as garbage text.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $html Raw HTML from WordPress
     *
     * @return string Cleaned HTML ready for DOM parsing
     *
     */
    private static function preprocessWordPress(string $html): string
    {
        // Remove Gutenberg block comment delimiters (both opening and closing)
        $html = preg_replace('/<!--\s*\/?wp:[^>]*-->/s', '', $html);

        // Convert [embed] shortcodes to linked anchors before stripping
        $html = preg_replace_callback(
            '/\[embed\](.*?)\[\/embed\]/s',
            static fn($m) => '<p><a href="' . trim($m[1]) . '">' . trim($m[1]) . '</a></p>',
            $html
        );

        // Strip any remaining unprocessed shortcodes
        $html = preg_replace('/\[\/?\w[\w-]*(?:\s[^\]]*?)?\]/', '', $html);

        return $html;
    }

    /**
     * convertStreaming
     *
     * Uses XMLReader for a forward-only text extraction pass on very large
     * HTML documents. Produces plain-text Markdown with no structural
     * formatting — headings, lists, and tables are not preserved.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $html Raw HTML to convert
     *
     * @return string Normalised plain-text Markdown
     *
     */
    private static function convertStreaming(string $html): string
    {
        $reader = new \XMLReader();

        libxml_use_internal_errors(true);

        $reader->XML(
            '<root>' . self::preprocessWordPress($html) . '</root>',
            null,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        $out  = '';
        $skip = 0; // depth counter for discarded subtrees

        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT) {
                if (in_array(strtolower($reader->localName), ['script', 'style'], true)) {
                    $skip++;
                }
            }

            if ($reader->nodeType === \XMLReader::END_ELEMENT) {
                if (in_array(strtolower($reader->localName), ['script', 'style'], true)) {
                    $skip = max(0, $skip - 1);
                }
            }

            // Only collect text outside discarded subtrees
            if ($reader->nodeType === \XMLReader::TEXT && $skip === 0) {
                $out .= self::escape($reader->value);
            }
        }

        libxml_clear_errors();

        return self::normalize($out);
    }

    /**
     * escape
     *
     * Escapes Markdown inline metacharacters in plain-text content so they
     * are rendered literally rather than triggering formatting.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $text Plain text to escape
     *
     * @return string Text with metacharacters backslash-escaped
     *
     */
    private static function escape(string $text): string
    {
        // Escape the characters that trigger Markdown inline formatting
        return preg_replace('/([*_`~])/u', '\\\\$1', $text);
    }

    /**
     * normalize
     *
     * Collapses runs of more than two consecutive blank lines, strips trailing
     * whitespace from every line, and ensures the output ends with a single
     * newline.
     *
     * @since 1.1.0
     * @access private
     * @author Kevin Pirnie <iam@kevinpirnie.com>
     * @package KP Agent Ready
     *
     * @param string $text Raw Markdown output before normalisation
     *
     * @return string Clean, normalised Markdown
     *
     */
    private static function normalize(string $text): string
    {
        // Collapse 3+ consecutive blank lines down to 2
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        // Strip trailing spaces and tabs from every line
        $text = preg_replace('/[ \t]+$/m', '', $text);

        return trim($text) . "\n";
    }
}
