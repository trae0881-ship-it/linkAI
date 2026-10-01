<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('connected_by')->constrained('users');

            $table->string('platform'); // meta_facebook_page|meta_instagram|linkedin|pinterest|x
            $table->string('external_account_id'); // the page/profile id on the platform
            $table->string('external_account_name');
            $table->string('external_account_type')->nullable(); // page|business_account|profile

            // Tokens are Laravel 'encrypted' casts — ciphertext at rest,
            // decrypted only in memory at the moment of the API call, and
            // NEVER included in any log line or audit_logs.meta payload.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('granted_scopes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'platform', 'external_account_id']);
            $table->index(['workspace_id', 'platform', 'is_active']);
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('content_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('media_asset_id')->nullable()->constrained()->nullOnDelete();

            // Deterministic fingerprint of (content_id, social_account_id,
            // approved_content_hash) — the idempotency guarantee. A retry
            // of the same publish attempt always produces the same key,
            // so a duplicate dispatch (queue redelivery, double-click)
            // can never post twice.
            $table->string('idempotency_key')->unique();

            $table->string('platform');
            // scheduled -> publishing -> published -> failed
            $table->string('status')->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_post_id')->nullable();
            $table->text('external_post_url')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);

            $table->timestamps();

            $table->index(['workspace_id', 'content_id']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
        Schema::dropIfExists('social_accounts');
    }
};
