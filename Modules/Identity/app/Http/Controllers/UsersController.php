<?php

namespace Modules\Identity\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Http\Requests\UsersIndexRequest;
use Modules\Identity\Models\User;
use Modules\Identity\Queries\UsersQuery;
use Modules\Identity\Services\Avatar\AvatarFormat;
use Modules\Identity\Services\Avatar\ReadUserAvatar;
use Symfony\Component\HttpFoundation\Response;

final class UsersController
{
    public function index(UsersIndexRequest $request, UsersQuery $query): JsonResponse
    {
        return ApiResponse::success($query->index($request->criteria(), request()));
    }

    public function show(Request $request, UsersQuery $query): JsonResponse
    {
        $target = $request->attributes->get('users_target');
        abort_unless($target instanceof User, 404);
        if ($request->query->count() !== 0 || $request->getContent() !== '' || $request->files->count() !== 0) {
            abort(422);
        }

        return ApiResponse::success($query->detail($target, $request));
    }

    // Mutations remain fail closed until their
    // respective reviewed implementation tasks.

    public function store(): never
    {
        abort(503);
    }

    public function update(): never
    {
        abort(503);
    }

    public function destroy(): never
    {
        abort(503);
    }

    public function avatar(Request $request, ReadUserAvatar $read): Response
    {
        $target = $request->attributes->get('users_target');
        abort_unless($target instanceof User, 404);
        abort_if($request->query->count() !== 0 || $request->getContent() !== '' || $request->files->count() !== 0, 422);
        $image = $read->execute($target);

        return response($image->bytes, 200, [
            'Content-Type' => AvatarFormat::mime($image->extension),
            'Content-Disposition' => 'inline; filename="avatar.'.$image->extension.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) strlen($image->bytes),
        ]);
    }
}
