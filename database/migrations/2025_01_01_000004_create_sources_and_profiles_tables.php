<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('submitted_by')->constrained('users');
            $table->text('submitted_url'); // raw, as typed by user — never trusted directly
            $table->text('normalized_url')->nullable();
            // pending -> validating -> fetching -> sanitizing -> analyzing -> analyzed -> failed|rejected
            $table->string('status')->default('pending');
            $table->string('rejection_reason')->nullable(); // e.g. ssrf_blocked, invalid_scheme, too_large
            $table->text('sanitized_text')->nullable(); // parsed, script/style-stripped, size-capped
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'brand_id', 'status']);
        });

        Schema::create('business_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->constrained()->cascadeOnDelete();
            // Schema-validated structured output (brand/offer/audience/seo) — see LlmProviderInterface
            $table->json('brand_data')->nullable();
            $table->json('offer_data')->nullable();
            $table->json('audience_data')->nullable();
            $table->json('seo_data')->nullable();
            // Per-claim provenance: [{claim, status, source_url, evidence}]
            // status in VERIFIED_FROM_SOURCE|SUPPORTED_BY_PROVIDED_DATA|REQUIRES_USER_CONFIRMATION|UNVERIFIED
            $table->json('claims')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
        Schema::dropIfExists('sources');
    }
};
