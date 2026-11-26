<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource['name'],
            'username' => $this->resource['username'],
            'role' => [
                'id' => $this->resource['role']['id'],
                'name' => $this->resource['role']['name'],
            ],
            'permissions' => $this->resource['permissions'],
        ];
    }
}
