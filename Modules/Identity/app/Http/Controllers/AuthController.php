<?php

namespace Modules\Identity\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Actions\LoginAction;
use Modules\Identity\Actions\LogoutAction;
use Modules\Identity\Http\Requests\LoginRequest;
use Modules\Identity\Http\Resources\MeResource;

final class AuthController
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $data = $request->validated();
        $result = $action->execute($request, $data['username'], $data['password']);

        return match ($result->status) {
            200 => ApiResponse::success(),
            401 => ApiResponse::error(ApiResponse::text('identity.invalid_login'), 401),
            429 => ApiResponse::error(ApiResponse::text('identity.login_throttled'), 429, [], ['Retry-After' => (string) $result->retryAfter]),
        };
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success((new MeResource($request->attributes->get('staff_context')))->resolve($request));
    }

    public function logout(Request $request, LogoutAction $action): JsonResponse
    {
        $action->execute($request);

        return ApiResponse::success();
    }
}
