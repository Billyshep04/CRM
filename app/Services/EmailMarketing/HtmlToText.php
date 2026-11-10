<?php

namespace App\Services\EmailMarketing;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Builds the plain-text part of a campaign from its HTML. Outlook-only markup, styles
 * and hidden preheader text are left out; links become "text (url)".
 */
class HtmlToText
{
    private const BLOCK_TAGS = ['p', 'div', 'table', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'section', 'header', 'footer', 'center'];

    public static function convert(string $html): string
    {
        // Outlook-only blocks (<!--[if mso]>…<![endif]-->) are dropped; "not Outlook" blocks are kept.
        $html = preg_replace('/<!--\[if\s+!mso\]><!--(?:\s*--)?>/i', '', $html);
        $html = preg_replace('/<!--<!\[endif\]-->/i', '', $html);
        $html = preg_replace('/<!--\[if[^\]]*\]>.*?<!\[endif\]-->/is', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//head|//style|//script|//title|//*[contains(translate(@style, " ", ""), "display:none")]') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $body = $xpath->query('//body')->item(0) ?? $document->documentElement;
        $text = $body ? self::walk($body) : '';

        $text = preg_replace('/[\x{034F}\x{200B}-\x{200D}\x{2007}\x{FEFF}]/u', '', $text);
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = preg_replace("/ *\n */", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private static function walk(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $out .= preg_replace('/\s+/u', ' ', $child->textContent);

                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if ($tag === 'br') {
                $out .= "\n";

                continue;
            }
            if ($tag === 'img') {
                continue;
            }

            $inner = self::walk($child);

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                $label = trim($inner);
                if ($label === '') {
                    // Image-only links (e.g. a logo) use the image's alt text as the label.
                    $image = $child->getElementsByTagName('img')->item(0);
                    $label = $image ? trim($image->getAttribute('alt')) : '';
                }
                if ($href !== '' && !str_starts_with($href, '#') && $href !== $label) {
                    $link = preg_replace('/^(mailto|tel):/i', '', $href);
                    $inner = $label === '' ? $href : ($link === $label ? $label : "{$label} ({$href})");
                }
            }

            if (in_array($tag, ['td', 'th'], true)) {
                $inner .= ' ';
            }

            $out .= in_array($tag, self::BLOCK_TAGS, true) ? "\n{$inner}\n" : $inner;
        }

        return $out;
    }
}
