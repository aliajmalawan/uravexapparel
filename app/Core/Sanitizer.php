<?php

declare(strict_types=1);

namespace App\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitizer for admin-authored rich text (product descriptions,
 * content pages). Anything not explicitly allowed is removed: scripts, event
 * handlers, styles, iframes, javascript:/data: URLs.
 */
final class Sanitizer
{
    private const TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [], 'hr' => [], 'a' => ['href', 'title'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'span' => [],
    ];

    public static function html(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $input . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root');
        if (!$root) {
            return e(strip_tags($input));
        }
        self::clean($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function clean(DOMNode $node): void
    {
        // Iterate over a static copy because we mutate the tree.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'base'], true)) {
                $node->removeChild($child);
                continue;
            }
            if (!array_key_exists($tag, self::TAGS)) {
                // Unwrap unknown elements but keep their (cleaned) children.
                self::clean($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, self::TAGS[$tag], true)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if ($href !== '' && !preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $href)) {
                    $child->removeAttribute('href');
                }
                if (preg_match('#^https?://#i', $href)) {
                    $child->setAttribute('rel', 'noopener noreferrer nofollow');
                }
            }
            self::clean($child);
        }
    }

    /** Plain text with paragraphs → safe HTML (for textarea fields). */
    public static function paragraphs(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $blocks = preg_split("/\R{2,}/", $text) ?: [];
        return implode('', array_map(static fn ($b) => '<p>' . nl2br(e(trim($b))) . '</p>', $blocks));
    }
}
