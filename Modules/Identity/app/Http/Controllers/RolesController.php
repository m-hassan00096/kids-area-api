<?php

namespace Modules\Identity\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Actions\CreateRoleAction;
use Modules\Identity\Actions\DeleteRoleAction;
use Modules\Identity\Actions\UpdateRoleAction;
use Modules\Identity\Http\Requests\CreateRoleRequest;
use Modules\Identity\Http\Requests\RolesIndexRequest;
use Modules\Identity\Http\Requests\UpdateRoleRequest;
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

    public function store(CreateRoleRequest $request, CreateRoleAction $action): JsonResponse
    {
        $action->execute(request(), $request->fields());

        return ApiResponse::success(message: ApiResponse::text('identity.role_created'), status: 201);
    }

    public function update(UpdateRoleRequest $request, UpdateRoleAction $action): JsonResponse
    {
        $action->execute(request(), $request->fields());

        return ApiResponse::success(message: ApiResponse::text('identity.role_updated'));
    }

    public function destroy(Request $request, DeleteRoleAction $action): JsonResponse
    {
        abort_if($request->query->count() !== 0 || $request->getContent() !== '' || $request->files->count() !== 0, 422);
        $action->execute($request);

        return ApiResponse::success(message: ApiResponse::text('identity.role_deleted'));
    }
}
