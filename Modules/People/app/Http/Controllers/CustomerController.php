<?php

namespace Modules\People\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\People\Actions\Customers\CreateCustomerAction;
use Modules\People\Actions\Customers\UpdateCustomerAction;
use Modules\People\Http\Requests\Customers\StoreCustomerRequest;
use Modules\People\Http\Requests\Customers\UpdateCustomerRequest;
use Modules\People\Http\Resources\CustomerResource;
use Modules\People\Models\Customer;

final class CustomerController
{
    public function show(Request $request): JsonResponse
    {
        $id = $request->attributes->get('customers_target_id');
        abort_unless(is_int($id), 404);
        abort_if($request->query->count() !== 0 || $request->getContent() !== '' || $request->files->count() !== 0, 422);
        $customer = Customer::query()->useWritePdo()->whereKey($id)->first(['id', 'name', 'mobile']);
        abort_if($customer === null, 404);

        return ApiResponse::success((new CustomerResource($customer))->resolve($request));
    }

    public function store(StoreCustomerRequest $request, CreateCustomerAction $action): JsonResponse
    {
        $data = $action->execute(request(), $request->fields());

        return ApiResponse::success($data, ApiResponse::text('people.customers.created'), 201);
    }

    public function update(UpdateCustomerRequest $request, UpdateCustomerAction $action): JsonResponse
    {
        $action->execute(request(), $request->fields());

        return ApiResponse::success(message: ApiResponse::text('people.customers.updated'));
    }
}
