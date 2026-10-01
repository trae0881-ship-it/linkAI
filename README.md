# Link2Social AI

URL → business intelligence → trends → creative concepts → human-selected media → captions/UTM links → human approval → official-API publishing → analytics.
Laravel 11 / PHP 8.3 / PostgreSQL / Redis / Horizon / S3-compatible storage / Railway.

## Setup
    composer install
    cp .env.example .env && php artisan key:generate
    php artisan migrate
    php artisan test
    php artisan serve            # dashboard at /app/
    php artisan horizon          # queue worker (separate terminal)
    php artisan schedule:work    # metrics sync (separate terminal)

## Status: implemented vs integration points
Implemented: public website (home/about/services/contact/privacy/refund/terms/disclaimer) + platform admin panel, Razorpay billing, auth (lockout, resets, verification), tenants/RBAC, SSRF-safe crawler, sanitizer,
prompt-injection-hardened extraction, concepts, prompts, image generation (OpenAI), captions,
deterministic UTM links, approval hash-pinning, Meta (Facebook Pages + Instagram) OAuth/publish/metrics,
idempotent publishing, audit/security logs, dashboard, Railway files.

Integration points (interfaces exist; no fake implementations):
- Video generation: VideoProviderInterface (NullVideoProvider until a vendor is chosen)
- LinkedIn / Pinterest / X: UnimplementedPlatformAdapter (see its docblock for endpoints/scopes)
- Trends: SerpApi provider is wired; NullTrendsProvider is the default when unset
- No standalone "trend research" endpoint: trends feed concept generation and show per concept

## Meta OAuth
Set META_REDIRECT_URI to the dashboard page, e.g. https://YOUR-HOST/app/ . The dashboard receives
?code&state, calls the API callback, lists ALL pages/profiles, and activates the ones you tick.

## Not yet verified
This code was written without a PHP runtime or Packagist access, so it has never been executed.
Run `composer install && php artisan test` first and expect to fix small errors.
