<?php

namespace App\Http\Controllers;

use App\Services\Select\SelectRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SelectController
{
    public function __invoke(Request $request, SelectRegistry $registry): JsonResponse
    {
        $query = $request->query->all();
        if (array_keys($query) !== ['selects'] || $request->getContent() !== '' || $request->files->count() !== 0) {
            throw ValidationException::withMessages(['selects' => ApiResponse::text('api.errors.validation')]);
        }

        $context = $request->attributes->get('staff_context');
        abort_unless(is_array($context), 403);

        return ApiResponse::success($registry->resolve($query['selects'], $context['permissions'] ?? []));
    }
}
