<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('publication_id')->constrained()->cascadeOnDelete();

            // impressions|likes|comments|shares|clicks|video_views — one
            // row per metric per snapshot, not a wide mutable row, so
            // history over time is preserved rather than overwritten.
            $table->string('metric');
            $table->unsignedBigInteger('value');
            $table->string('source')->default('platform_api'); // platform_api|utm_click|webhook
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['workspace_id', 'publication_id', 'metric', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
