<?php

declare(strict_types=1);

namespace App\Services\Crawler;

/**
 * Reduces raw, untrusted HTML to plain text safe to hand to an LLM.
 * This is a security boundary, not just a formatting step: everything
 * capable of carrying executable content or disguised instructions
 * (script, style, iframe, object/embed, on* attributes, comments,
 * data: URIs) is removed before any text reaches the model.
 */
final class HtmlSanitizer
{
    private const array STRIP_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'svg', 'form'];

    public function sanitize(string $html): string
    {
        $html = $this->stripComments($html);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?>'.$html,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET,
        );
        libxml_clear_errors();

        foreach (self::STRIP_TAGS as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $this->stripEventHandlerAttributes($dom);

        $text = trim(preg_replace('/[ \t]+/', ' ', $dom->textContent ?? ''));
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        $max = (int) config('crawler.max_extracted_chars', 20000);

        return mb_substr($text, 0, $max);
    }

    private function stripComments(string $html): string
    {
        return preg_replace('/<!--(.*?)-->/s', '', $html) ?? $html;
    }

    private function stripEventHandlerAttributes(\DOMDocument $dom): void
    {
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//@*[starts-with(name(), "on")]') as $attr) {
            /** @var \DOMAttr $attr */
            $attr->ownerElement?->removeAttribute($attr->name);
        }

        foreach ($xpath->query('//a[starts-with(@href, "javascript:")] | //*[starts-with(@src, "javascript:")]') as $node) {
            /** @var \DOMElement $node */
            $node->removeAttribute('href');
            $node->removeAttribute('src');
        }
    }
}
