<?php

namespace Modules\Identity\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Identity\Actions\ChangePasswordAction;
use Modules\Identity\Http\Requests\ChangePasswordRequest;

final class ProfileController
{
    public function password(ChangePasswordRequest $request, ChangePasswordAction $action): JsonResponse
    {
        $data = $request->validated();
        $action->execute($request, $data['currentPassword'], $data['password']);

        return ApiResponse::success(message: ApiResponse::text('identity.password_changed'));
    }
}
