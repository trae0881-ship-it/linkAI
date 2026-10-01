<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('creative_concept_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('requested_quantity')->default(1);
            $table->string('media_format'); // image|video|carousel
            // Full production-ready prompt(s) sent to the media provider,
            // expanded from the concept's media_brief.
            $table->json('generation_prompts');

            // Platform-specific caption/hashtag/CTA/tracking-link sets,
            // e.g. {"instagram": {"caption": "...", "hashtags": [...], "cta": "...", "tracking_url": "..."}}
            $table->json('platform_copy')->nullable();

            // draft -> generating -> generated -> pending_review -> approved -> rejected -> publishing -> published -> failed
            $table->string('status')->default('draft');

            // SHA-256 of the exact approved payload (prompts+copy+media asset ids).
            // Publishing re-hashes at send-time and refuses to publish on mismatch.
            $table->string('approved_content_hash')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['workspace_id', 'brand_id', 'status']);
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('content_id')->constrained()->cascadeOnDelete();

            $table->string('type'); // image|video
            $table->string('storage_disk')->default('s3');
            $table->string('storage_path'); // never a public URL stored directly — signed URLs generated on read
            $table->string('mime_type');
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('sha256')->nullable(); // content-hash for integrity/dedup

            // pending -> generating -> ready -> failed
            $table->string('status')->default('pending');
            $table->string('provider')->nullable();
            $table->string('provider_job_id')->nullable(); // for async video polling

            $table->timestamps();

            $table->index(['workspace_id', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('content');
    }
};
