<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Renders admin-authored page text as HTML WITHOUT ever trusting the text: every piece is
 * HTML-escaped, and only a tiny fixed syntax is understood ("# " heading, "## " subheading,
 * "- " list items, blank line = new paragraph). {{tokens}} are replaced before escaping.
 */
final class SafeTextRenderer
{
    /** @param array<string, string> $vars */
    public function render(string $text, array $vars = []): HtmlString
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{'.$key.'}}', $value, $text);
        }

        $html = '';
        $para = [];
        $list = [];

        $flush = function () use (&$html, &$para, &$list): void {
            if ($para !== []) {
                $html .= '<p>'.implode('<br>', array_map('e', $para)).'</p>';
                $para = [];
            }
            if ($list !== []) {
                $html .= '<ul>'.implode('', array_map(fn (string $i): string => '<li>'.e($i).'</li>', $list)).'</ul>';
                $list = [];
            }
        };

        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            $line = rtrim($line);

            if ($line === '') {
                $flush();
            } elseif (str_starts_with($line, '## ')) {
                $flush();
                $html .= '<h3>'.e(substr($line, 3)).'</h3>';
            } elseif (str_starts_with($line, '# ')) {
                $flush();
                $html .= '<h2>'.e(substr($line, 2)).'</h2>';
            } elseif (str_starts_with($line, '- ')) {
                if ($para !== []) {
                    $flush();
                }
                $list[] = substr($line, 2);
            } else {
                if ($list !== []) {
                    $flush();
                }
                $para[] = $line;
            }
        }
        $flush();

        return new HtmlString($html);
    }
}
