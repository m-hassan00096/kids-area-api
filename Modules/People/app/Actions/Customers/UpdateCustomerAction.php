<?php

namespace Modules\People\Actions\Customers;

use Illuminate\Http\Request;
use Modules\People\Models\Customer;
use Modules\People\Services\Customers\CustomerMutation;
use Modules\People\Support\CustomerInput;
use RuntimeException;
use SensitiveParameter;

final readonly class UpdateCustomerAction
{
    public function __construct(private CustomerMutation $mutation) {}

    public function execute(#[SensitiveParameter] Request $request, #[SensitiveParameter] array $fields): void
    {
        $attributes = CustomerInput::validate($fields);
        $this->mutation->execute($request, 'people.customers.update', static function () use ($request, $attributes): void {
            $id = $request->attributes->get('customers_target_id');
            abort_unless(is_int($id), 404);
            $customer = Customer::query()->whereKey($id)->lockForUpdate()->first();
            abort_if($customer === null, 404);
            if (! $customer->fill($attributes)->save()) {
                throw new RuntimeException('Unable to update Customer.');
            }
        });
    }
}
