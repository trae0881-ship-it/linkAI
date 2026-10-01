<?php

declare(strict_types=1);

namespace App\Http\Requests\Brand;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [\App\Models\Brand::class, $this->route('workspace')->id]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            // Format-only check. This URL is NOT fetched here — actual
            // retrieval happens only through Source creation, which runs
            // the full SSRF-safe validator/fetcher (crawling module).
            'website_url' => ['nullable', 'string', 'max:2048', 'url'],
        ];
    }

    public function safeData(): array
    {
        return [
            'workspace_id' => $this->route('workspace')->id,
            'name' => $this->string('name')->trim()->toString(),
            'website_url' => $this->filled('website_url')
                ? $this->string('website_url')->trim()->toString()
                : null,
        ];
    }
}
