<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUser $action): JsonResponse
    {
        $user = $action->handle($request->safeData(), $request->ip());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }
}
