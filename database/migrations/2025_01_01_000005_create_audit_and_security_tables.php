<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // e.g. content.approved, source.analyzed, social.connected
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->json('meta')->nullable(); // never store secrets/tokens here
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            // ssrf_attempt, cross_tenant_denied, login_lockout, webhook_signature_failed,
            // oauth_token_refresh_failed, rate_limit_exceeded, prompt_injection_detected, etc.
            $table->string('event_type');
            $table->string('severity')->default('info'); // info|warning|critical
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['event_type', 'severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('audit_logs');
    }
};
