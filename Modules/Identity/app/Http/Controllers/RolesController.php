<?php

namespace Modules\Identity\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Http\Requests\RolesIndexRequest;
use Modules\Identity\Queries\RolesQuery;

final class RolesController
{
    public function index(RolesIndexRequest $request, RolesQuery $query): JsonResponse
    {
        return ApiResponse::success($query->index($request->criteria(), request()));
    }

    public function show(Request $request, RolesQuery $query): JsonResponse
    {
        $id = $request->attributes->get('roles_target_id');
        abort_unless(is_int($id), 404);
        abort_if($request->query->count() !== 0 || $request->getContent() !== '' || $request->files->count() !== 0, 422);

        return ApiResponse::success($query->detail($id, $request));
    }
}
