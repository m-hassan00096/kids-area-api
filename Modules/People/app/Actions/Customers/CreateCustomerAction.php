<?php

namespace Modules\People\Actions\Customers;

use Illuminate\Http\Request;
use Modules\People\Http\Resources\CustomerResource;
use Modules\People\Models\Customer;
use Modules\People\Services\Customers\CustomerMutation;
use Modules\People\Support\CustomerInput;
use RuntimeException;
use SensitiveParameter;

final readonly class CreateCustomerAction
{
    public function __construct(private CustomerMutation $mutation) {}

    public function execute(#[SensitiveParameter] Request $request, #[SensitiveParameter] array $fields): array
    {
        $attributes = CustomerInput::validate($fields);

        return $this->mutation->execute($request, 'people.customers.store', static function () use ($request, $attributes): array {
            $customer = new Customer($attributes);
            if (! $customer->save() || ! $customer->exists) {
                throw new RuntimeException('Unable to create Customer.');
            }

            // Resolve the committed-response projection inside the transaction.
            // The mutation coordinator returns it only after acknowledged commit.
            return (new CustomerResource($customer))->resolve($request);
        });
    }
}
