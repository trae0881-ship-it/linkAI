<?php

declare(strict_types=1);

namespace Tests\Unit\Site;

use App\Support\SafeTextRenderer;
use Tests\TestCase;

final class SafeTextRendererTest extends TestCase
{
    public function test_escapes_html_and_script_in_admin_text(): void
    {
        $html = (new SafeTextRenderer)->render("# <script>alert(1)</script>\n\n<img src=x onerror=alert(2)>\n- <b>bold</b>")->toHtml();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_renders_headings_lists_and_paragraphs(): void
    {
        $html = (new SafeTextRenderer)->render("# Title\n\n## Sub\nline one\nline two\n\n- a\n- b")->toHtml();

        $this->assertStringContainsString('<h2>Title</h2>', $html);
        $this->assertStringContainsString('<h3>Sub</h3>', $html);
        $this->assertStringContainsString('<p>line one<br>line two</p>', $html);
        $this->assertStringContainsString('<ul><li>a</li><li>b</li></ul>', $html);
    }

    public function test_tokens_are_substituted_then_escaped(): void
    {
        $html = (new SafeTextRenderer)->render('Welcome to {{business_name}}', ['business_name' => 'Acme <i>&</i>'])->toHtml();

        $this->assertStringContainsString('Acme &lt;i&gt;&amp;&lt;/i&gt;', $html);
        $this->assertStringNotContainsString('{{', $html);
    }
}
