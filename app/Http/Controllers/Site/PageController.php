<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BillingPlan;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Support\SafeTextRenderer;
use Illuminate\Contracts\View\View;

final class PageController extends Controller
{
    public function __construct(private readonly SafeTextRenderer $renderer) {}

    public function home(): View
    {
        return $this->show('home');
    }

    public function show(string $slug): View
    {
        $page = SitePage::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();
        $settings = SiteSetting::publicSettings();

        $vars = $settings + ['site_url' => rtrim((string) config('app.url'), '/')];

        return view('site.page', [
            'page' => $page,
            'slug' => $slug,
            'settings' => $settings,
            'body' => $this->renderer->render($page->body, $vars),
            'plans' => in_array($slug, ['home', 'services'], true) ? BillingPlan::query()->active()->get() : collect(),
            'pages' => SitePage::query()->where('is_published', true)->pluck('title', 'slug'),
        ]);
    }
}
