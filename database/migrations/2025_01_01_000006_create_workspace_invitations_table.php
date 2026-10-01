<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invited_by')->constrained('users');
            $table->string('email');
            $table->string('role');
            $table->string('token_hash')->unique(); // raw token only ever sent via email, never stored
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // Application logic (CreateWorkspaceInvitation) checks for an
            // existing unaccepted invite before creating a new one —
            // nullable accepted_at makes a DB-level unique constraint
            // here unreliable across Postgres/MySQL NULL semantics.
            $table->index(['workspace_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invitations');
    }
};
