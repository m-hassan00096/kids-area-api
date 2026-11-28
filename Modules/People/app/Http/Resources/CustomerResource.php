<?php

namespace Modules\People\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->resource;

        return [
            'id' => (int) $customer->id,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
        ];
    }
}
