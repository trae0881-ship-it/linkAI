<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_concepts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('campaign_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('reasoning');
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->json('risks')->nullable();
            // Per-platform fit score/notes, e.g. {"instagram": {"score": 8, "note": "..."}}
            $table->json('platform_suitability')->nullable();
            $table->json('trend_references')->nullable(); // which trend(s) this concept draws on, if any
            $table->json('media_brief')->nullable(); // structured brief later expanded into AI prompts

            // draft -> proposed -> selected -> rejected
            $table->string('status')->default('proposed');
            $table->foreignUuid('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selected_at')->nullable();

            $table->timestamps();

            $table->index(['workspace_id', 'brand_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_concepts');
    }
};
