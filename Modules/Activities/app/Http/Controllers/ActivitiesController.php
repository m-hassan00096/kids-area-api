<?php

namespace Modules\Activities\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Activities\Actions\CreateActivityAction;
use Modules\Activities\Actions\DeleteActivityAction;
use Modules\Activities\Actions\UpdateActivityAction;
use Modules\Activities\Http\Requests\ActivitiesIndexRequest;
use Modules\Activities\Http\Requests\CreateActivityRequest;
use Modules\Activities\Http\Requests\UpdateActivityRequest;
use Modules\Activities\Queries\ActivitiesQuery;

final class ActivitiesController
{
    public function index(ActivitiesIndexRequest $request, ActivitiesQuery $query): JsonResponse
    {
        return ApiResponse::success($query->index($request->criteria(), request()));
    }

    public function show(Request $request, ActivitiesQuery $query): JsonResponse
    {
        $id = $request->attributes->get('activities_target_id');
        abort_unless(is_int($id), 404);
        abort_if($request->query->count() !== 0 || $request->getContent() !== '' || $request->files->count() !== 0, 422);

        return ApiResponse::success($query->detail($id, $request));
    }

    public function store(CreateActivityRequest $request, CreateActivityAction $action): JsonResponse
    {
        $action->execute(request(), $request->fields());

        return ApiResponse::success(message: ApiResponse::text('activities.created'), status: 201);
    }

    public function update(UpdateActivityRequest $request, UpdateActivityAction $action): JsonResponse
    {
        $action->execute(request(), $request->fields());

        return ApiResponse::success(message: ApiResponse::text('activities.updated'));
    }

    public function destroy(Request $request, DeleteActivityAction $action): JsonResponse
    {
        abort_if($request->query->count() !== 0 || $request->getContent() !== '' || $request->files->count() !== 0, 422);
        $action->execute($request);

        return ApiResponse::success(message: ApiResponse::text('activities.deleted'));
    }
}
