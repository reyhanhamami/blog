<?php

namespace App\Services\Content;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    private const TAGS = ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'u', 'blockquote', 'pre', 'code', 'a', 'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'br', 'hr'];

    public function clean(?string $html): string
    {
        if (! $html) {
            return '';
        }
        if (! class_exists(DOMDocument::class)) {
            return strip_tags($html, '<p><h2><h3><h4><ul><ol><li><strong><em><u><blockquote><pre><code><br><hr>');
        }
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="content-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $root = $doc->getElementById('content-root');
        if ($root) {
            $this->sanitizeChildren($root);
        }
        $output = '';
        if ($root) {
            foreach ($root->childNodes as $node) {
                $output .= $doc->saveHTML($node);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $output;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (! in_array($tag, self::TAGS, true)) {
                $parent->removeChild($node);

                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                $allowed = match ($tag) {
                    'a' => in_array($name, ['href', 'title'], true) && ($name !== 'href' || $this->safeUrl($value)),
                    'img' => in_array($name, ['src', 'alt', 'width', 'height'], true) && ($name !== 'src' || $this->safeUrl($value)),
                    'code' => $name === 'class' && preg_match('/^language-[a-z0-9+#-]+$/i', $value),
                    default => false,
                };
                if (! $allowed) {
                    $node->removeAttributeNode($attribute);
                }
            }
            $this->sanitizeChildren($node);
        }
    }

    private function safeUrl(string $url): bool
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') || (bool) preg_match('~^https?://~i', $url);
    }
}
