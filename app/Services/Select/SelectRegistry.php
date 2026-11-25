<?php

namespace App\Services\Select;

use App\Support\ApiResponse;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SelectRegistry
{
    private array $providers = [];

    public function register(string $key, SelectProvider $provider): void
    {
        if (isset($this->providers[$key])) {
            throw new LogicException('Duplicate registered Select provider.');
        }
        $this->providers[$key] = $provider;
    }

    public function resolve(mixed $requested, array $permissions): array
    {
        $keys = is_string($requested) ? explode(',', $requested) : [];
        if ($keys === [] || count($keys) > 2 || count(array_unique($keys)) !== count($keys)
            || array_diff($keys, array_keys($this->providers)) !== []) {
            throw ValidationException::withMessages(['selects' => ApiResponse::text('api.errors.validation')]);
        }

        // Complete authorization pass first. No provider reads on a mixed
        // unauthorized request, regardless of the order of requested keys.
        foreach ($keys as $key) {
            abort_unless($this->providers[$key]->allows($permissions), 403);
        }

        $data = [];
        foreach ($keys as $key) {
            $data[] = ['label' => $key, 'options' => $this->providers[$key]->options()];
        }

        return $data;
    }
}
