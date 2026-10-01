<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every tenant-owned model uses this trait. It documents (and, once wired
 * into a global scope, enforces) that rows are always accessed through
 * their owning workspace — e.g. $workspace->brands()->findOrFail($id) —
 * never Model::find($id) on an authorization-sensitive request.
 */
trait BelongsToWorkspace
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
