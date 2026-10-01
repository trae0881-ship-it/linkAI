<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditLogController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $logs = $workspace->auditLogs()
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->input('action').'%'))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($logs);
    }
}
