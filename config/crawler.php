<?php

declare(strict_types=1);

return [
    'max_redirects' => (int) env('CRAWLER_MAX_REDIRECTS', 5),
    'connect_timeout' => (int) env('CRAWLER_CONNECT_TIMEOUT', 8),
    'total_timeout' => (int) env('CRAWLER_TOTAL_TIMEOUT', 25),
    'max_html_bytes' => (int) env('CRAWLER_MAX_HTML_BYTES', 5 * 1024 * 1024),
    'max_resource_bytes' => (int) env('CRAWLER_MAX_RESOURCE_BYTES', 10 * 1024 * 1024),
    'max_pages' => (int) env('CRAWLER_MAX_PAGES', 5),
    'max_depth' => (int) env('CRAWLER_MAX_DEPTH', 2),
    'user_agent' => env('CRAWLER_USER_AGENT', 'Link2SocialBot/1.0'),

    // Sanitized text handed to the LLM is capped independently of the
    // raw HTML cap above — keeps prompt size/cost bounded.
    'max_extracted_chars' => (int) env('CRAWLER_MAX_EXTRACTED_CHARS', 20000),
];
