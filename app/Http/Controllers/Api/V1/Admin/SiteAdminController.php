<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\SitePage;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SiteAdminController extends Controller
{
    public function pages(): JsonResponse
    {
        $pages = SitePage::query()->get()->keyBy('slug');
        $out = [];
        foreach (SitePage::SLUGS as $slug => $title) {
            $p = $pages->get($slug);
            $out[] = [
                'slug' => $slug,
                'title' => $p->title ?? $title,
                'body' => $p->body ?? '',
                'meta_description' => $p->meta_description ?? '',
                'is_published' => $p ? $p->is_published : false,
                'updated_at' => $p->updated_at ?? null,
            ];
        }

        return response()->json(['data' => $out]);
    }

    public function updatePage(UpdatePageRequest $request, string $slug): JsonResponse
    {
        abort_unless(array_key_exists($slug, SitePage::SLUGS), 404);

        $page = SitePage::query()->firstOrNew(['slug' => $slug]);
        $page->fill([
            'title' => $request->string('title')->trim()->toString(),
            'body' => $request->input('body'),
            'meta_description' => $request->filled('meta_description') ? $request->string('meta_description')->trim()->toString() : null,
            'is_published' => $request->boolean('is_published'),
            'updated_by' => $request->user()->id,
        ])->save();

        AuditLog::record(null, $request->user()->id, 'site.page_updated', SitePage::class, $page->id, ['slug' => $slug], $request->ip());

        return response()->json(['data' => ['slug' => $slug, 'updated_at' => $page->updated_at]]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => SiteSetting::publicSettings()]);
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated() as $name => $value) {
            SiteSetting::write($name, $value === null ? '' : (string) $value);
        }

        AuditLog::record(null, $request->user()->id, 'site.settings_updated', null, null, ['fields' => array_keys($request->validated())], $request->ip());

        return response()->json(['data' => SiteSetting::publicSettings()]);
    }

    public function messages(): JsonResponse
    {
        return response()->json(ContactMessage::query()->orderByDesc('created_at')->paginate(30));
    }

    public function markMessage(Request $request, string $id): JsonResponse
    {
        $request->validate(['is_read' => ['required', 'boolean']]);
        $message = ContactMessage::query()->findOrFail($id);
        $message->update(['is_read' => $request->boolean('is_read')]);

        return response()->json(['data' => ['id' => $message->id, 'is_read' => $message->is_read]]);
    }
}
