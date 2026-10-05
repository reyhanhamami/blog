<?php

namespace App\Services\Content;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    private const TAGS = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'strong', 'em', 'u', 's', 'del', 'blockquote', 'pre', 'code', 'a', 'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'br', 'hr'];

    private const DISCARD = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math', 'template', 'noscript'];

    public function clean(?string $html): string
    {
        if (! $html) {
            return '';
        }
        if (! class_exists(DOMDocument::class)) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);

                continue;
            }
            if (! $node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if ($tag === 'span') {
                $style = $node->getAttribute('style');
                $this->sanitizeChildren($node);
                $bold = (bool) preg_match('/font-weight\s*:\s*(?:bold|[6-9]00)/i', $style);
                $italic = (bool) preg_match('/font-style\s*:\s*italic/i', $style);
                if ($bold || $italic) {
                    $replacement = $node->ownerDocument->createElement($bold ? 'strong' : 'em');
                    if ($bold && $italic) {
                        $inner = $node->ownerDocument->createElement('em');
                        $replacement->appendChild($inner);
                        $replacement = $inner;
                    }
                    while ($node->firstChild) {
                        $replacement->appendChild($node->firstChild);
                    }
                    $parent->replaceChild($bold && $italic ? $replacement->parentNode : $replacement, $node);
                } else {
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                }

                continue;
            }
            if (in_array($tag, ['b', 'i', 'strike'], true)) {
                $replacement = $node->ownerDocument->createElement(['b' => 'strong', 'i' => 'em', 'strike' => 's'][$tag]);
                while ($node->firstChild) {
                    $replacement->appendChild($node->firstChild);
                }
                $parent->replaceChild($replacement, $node);
                $this->sanitizeChildren($replacement);

                continue;
            }
            if (in_array($tag, self::DISCARD, true)) {
                $parent->removeChild($node);

                continue;
            }
            if (! in_array($tag, self::TAGS, true)) {
                $this->sanitizeChildren($node);
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                $allowed = match ($tag) {
                    'a' => in_array($name, ['href', 'title', 'target', 'rel'], true) && ($name !== 'href' || $this->safeUrl($value)) && ($name !== 'target' || $value === '_blank'),
                    'img' => in_array($name, ['src', 'alt', 'width', 'height'], true) && ($name !== 'src' || $this->safeUrl($value)) && (! in_array($name, ['width', 'height'], true) || preg_match('/^[1-9][0-9]{0,3}$/', $value)),
                    'code' => $name === 'class' && preg_match('/^language-(?:php|javascript|typescript|html|css|bash|json|sql|python|go|text)$/', $value),
                    'figure' => $name === 'class' && $this->safeFigureClass($value),
                    'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote' => $name === 'class' && preg_match('/^article-align-(?:left|center|right|justify)$/', $value),
                    default => false,
                };
                if (! $allowed) {
                    $node->removeAttributeNode($attribute);
                }
            }
            if ($tag === 'img' && ! $node->hasAttribute('src')) {
                $parent->removeChild($node);

                continue;
            }
            if ($tag === 'a') {
                if (! $node->hasAttribute('href')) {
                    $node->removeAttribute('target');
                    $node->removeAttribute('rel');
                } elseif ($node->getAttribute('target') === '_blank') {
                    $node->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $node->removeAttribute('rel');
                }
            }
            $this->sanitizeChildren($node);
        }
    }

    private function safeFigureClass(string $class): bool
    {
        return (bool) preg_match('/^article-image article-image--(?:small|medium|large|full) article-image--(?:left|center|right)$/', $class);
    }

    private function safeUrl(string $url): bool
    {
        return (str_starts_with($url, '/') && ! str_starts_with($url, '//')) || preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $url) || (bool) preg_match('~^https?://[^\s<>"\']+$~i', $url);
    }
}
