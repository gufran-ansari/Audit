<?php

namespace App\Services;

/**
 * HTML Parser Utility
 * 
 * Centralizes the repeated DOM parsing operations found across all
 * audit processors. Eliminates duplicated DOMDocument setup,
 * libxml error suppression, encoding handling, and tag extraction.
 * 
 * All processors previously duplicated these patterns independently.
 */
class HtmlParser
{
    private ?\DOMDocument $dom = null;

    /**
     * Parse HTML string into a DOMDocument.
     * Handles encoding, suppresses libxml warnings.
     */
    public function parse(string $html): self
    {
        libxml_use_internal_errors(true);

        $this->dom = new \DOMDocument();
        $this->dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        libxml_clear_errors();

        return $this;
    }

    /**
     * Get the parsed DOMDocument (for advanced usage).
     */
    public function getDom(): ?\DOMDocument
    {
        return $this->dom;
    }

    /**
     * Extract text content from all elements of a tag name.
     * Returns array of trimmed non-empty text values.
     */
    public function getTagTexts(string $tagName): array
    {
        if (!$this->dom) return [];

        $texts = [];
        $elements = $this->dom->getElementsByTagName($tagName);

        foreach ($elements as $el) {
            $text = trim($el->textContent);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * Get the first text content of a tag, or null.
     */
    public function getFirstTagText(string $tagName): ?string
    {
        $texts = $this->getTagTexts($tagName);
        return $texts[0] ?? null;
    }

    /**
     * Get a meta tag content by name attribute.
     */
    public function getMetaContent(string $name): ?string
    {
        if (!$this->dom) return null;

        $metas = $this->dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if (strtolower($meta->getAttribute('name')) === strtolower($name)) {
                $content = trim($meta->getAttribute('content'));
                return $content !== '' ? $content : null;
            }
        }
        return null;
    }

    /**
     * Get a meta tag content by property attribute (e.g., og:image).
     */
    public function getMetaProperty(string $property): ?string
    {
        if (!$this->dom) return null;

        $metas = $this->dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if ($meta->getAttribute('property') === $property) {
                $content = trim($meta->getAttribute('content'));
                return $content !== '' ? $content : null;
            }
        }
        return null;
    }

    /**
     * Extract visible body text with script/style/noscript removed.
     * Normalizes whitespace.
     */
    public function getBodyText(): string
    {
        if (!$this->dom) return '';

        // Remove script/style/noscript nodes
        foreach (['script', 'style', 'noscript'] as $tagName) {
            $nodes = $this->dom->getElementsByTagName($tagName);
            $toRemove = [];
            foreach ($nodes as $node) {
                $toRemove[] = $node;
            }
            foreach ($toRemove as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $body = $this->dom->getElementsByTagName('body')->item(0);
        $text = $body ? $body->textContent : $this->dom->textContent;

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Find all JSON-LD script blocks and decode them.
     * Returns array of decoded JSON objects.
     */
    public function getJsonLd(): array
    {
        if (!$this->dom) return [];

        $results = [];
        $scripts = $this->dom->getElementsByTagName('script');

        foreach ($scripts as $script) {
            if (stripos($script->getAttribute('type'), 'application/ld+json') !== false) {
                $json = json_decode(trim($script->textContent), true);
                if ($json !== null) {
                    $results[] = $json;
                }
            }
        }

        return $results;
    }

    /**
     * Get all attribute values from elements of a given tag.
     * E.g., getAttributes('a', 'href') returns all link hrefs.
     */
    public function getAttributes(string $tagName, string $attribute): array
    {
        if (!$this->dom) return [];

        $values = [];
        $elements = $this->dom->getElementsByTagName($tagName);

        foreach ($elements as $el) {
            $val = trim($el->getAttribute($attribute));
            if ($val !== '') {
                $values[] = $val;
            }
        }

        return $values;
    }

    /**
     * Count occurrences of a keyword in text (case-insensitive, multibyte-safe).
     */
    public static function countOccurrences(string $text, string $keyword): int
    {
        if ($text === '' || $keyword === '') return 0;

        $haystack = mb_strtolower($text);
        $needle = mb_strtolower($keyword);
        $count = 0;
        $offset = 0;

        while (($pos = mb_strpos($haystack, $needle, $offset)) !== false) {
            $count++;
            $offset = $pos + mb_strlen($needle);
        }

        return $count;
    }

    /**
     * Extract a snippet of text around a keyword occurrence.
     */
    public static function extractSnippet(string $text, string $keyword, int $contextChars = 50): ?string
    {
        $pos = mb_stripos($text, $keyword);
        if ($pos === false) return null;

        $start = max(0, $pos - $contextChars);
        $length = mb_strlen($keyword) + ($contextChars * 2);
        $snippet = mb_substr($text, $start, $length);

        $prefix = $start > 0 ? '...' : '';
        $suffix = ($start + $length) < mb_strlen($text) ? '...' : '';

        return $prefix . trim($snippet) . $suffix;
    }
}
