<?php

declare(strict_types=1);

/**
 * @copyright 2026 Andy Fragen
 * @license   MIT
 *
 * @link      https://github.com/afragen/wp-readme-parser
 */

namespace Fragen\WP_Readme_Parser\Adapters;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Fragen\WP_Readme_Parser\Contracts\HtmlSanitizerInterface;

/**
 * HTML sanitizer implemented with PHP's native DOMDocument.
 *
 * Enforces the same element/attribute allowlist as the original WP.org
 * wp_kses() call — no third-party dependencies required.
 *
 * Disallowed elements are removed but their text content is preserved.
 * Disallowed attributes are silently stripped from allowed elements.
 */
class NativeHtmlSanitizerAdapter implements HtmlSanitizerInterface
{
    /**
     * Elements whose entire subtree (tag + content) must be removed.
     * These are never safe to expose as plain text.
     *
     * @var string[]
     */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea'];

    /**
     * Allowed elements and the attributes permitted on each.
     *
     * @var array<string, string[]>
     */
    private const ALLOWED = [
        'a'          => ['href', 'title', 'rel'],
        'blockquote' => ['cite'],
        'br'         => [],
        'p'          => [],
        'code'       => [],
        'pre'        => [],
        'em'         => [],
        'strong'     => [],
        'ul'         => [],
        'ol'         => [],
        'dl'         => [],
        'dt'         => ['id'],
        'dd'         => [],
        'li'         => [],
        'h3'         => [],
        'h4'         => [],
    ];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $prev = libxml_use_internal_errors(true);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
            LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $body = $doc->getElementsByTagName('body')->item(0);

        if (!($body instanceof DOMElement)) {
            return '';
        }

        $this->sanitizeElement($body);

        $result = '';

        foreach ($body->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    private function sanitizeElement(DOMElement $element): void
    {
        // Snapshot children before mutating the list.
        $children = [];

        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (!($child instanceof DOMElement)) {
                // Remove comments, processing instructions, etc.
                $element->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                // Dangerous element: drop it and all its content.
                $element->removeChild($child);
            } elseif (!array_key_exists($tag, self::ALLOWED)) {
                // Unknown element: sanitize its children first, then lift
                // them up to replace the element itself.
                $this->sanitizeElement($child);

                while ($child->firstChild instanceof DOMNode) {
                    $element->insertBefore($child->firstChild, $child);
                }

                $element->removeChild($child);
            } else {
                // Allowed element: strip any disallowed attributes, then recurse.
                $allowedAttrs  = self::ALLOWED[$tag];
                $attrsToRemove = [];

                foreach ($child->attributes as $attr) {
                    if (!in_array($attr->name, $allowedAttrs, true)) {
                        $attrsToRemove[] = $attr->name;
                    }
                }

                foreach ($attrsToRemove as $name) {
                    $child->removeAttribute($name);
                }

                $this->sanitizeElement($child);
            }
        }
    }
}
