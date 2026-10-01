<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SecurityEventController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $events = SecurityEvent::query()
            ->where('workspace_id', $workspace->id)
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->input('severity')))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($events);
    }
}
