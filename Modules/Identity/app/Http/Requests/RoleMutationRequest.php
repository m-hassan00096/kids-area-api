<?php

namespace Modules\Identity\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use JsonException;
use Modules\Identity\Support\PermissionCatalogue;
use stdClass;

abstract class RoleMutationRequest extends FormRequest
{
    private array $fields = [];

    abstract protected function updating(): bool;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function validationData(): array
    {
        // A JSON object is required; decode its native types without
        // collapsing an empty permissions object into an empty list.
        if (! $this->isJson() || $this->query->count() !== 0 || $this->files->count() !== 0) {
            return ['_invalid' => true];
        }
        if ($this->getContent() === '') {
            return [];
        }
        try {
            $body = json_decode($this->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(400);
        }

        return $body instanceof stdClass ? get_object_vars($body) : ['_invalid' => true];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $data = $validator->getData();
            $invalid = static fn (string $field) => $validator->errors()->add($field, ApiResponse::text('api.errors.validation'));
            if (array_diff(array_keys($data), ['name', 'isActive', 'permissions']) !== []) {
                $invalid('request');

                return;
            }

            $name = $data['name'] ?? null;
            if (! is_string($name) || ! mb_check_encoding($name, 'UTF-8')
                || preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $name) !== 0) {
                $invalid('name');
            } else {
                $name = Str::trim($name);
                if ($name === '' || mb_strlen($name) > 255) {
                    $invalid('name');
                }
            }
            $active = array_key_exists('isActive', $data) ? $data['isActive'] : ($this->updating() ? null : true);
            if (! is_bool($active)) {
                $invalid('isActive');
            }
            $permissions = $data['permissions'] ?? null;
            if (! is_array($permissions) || ! array_is_list($permissions) || count($permissions) > count(PermissionCatalogue::keys())) {
                $invalid('permissions');
            } else {
                foreach ($permissions as $permission) {
                    if (! is_string($permission) || ! in_array($permission, PermissionCatalogue::keys(), true)) {
                        $invalid('permissions');

                        break;
                    }
                }
                if ($validator->errors()->isEmpty() && count(array_unique($permissions, SORT_STRING)) !== count($permissions)) {
                    $invalid('permissions');
                }
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $this->fields = ['name' => $name, 'isActive' => $active, 'permissions' => $permissions];
        }];
    }

    public function fields(): array
    {
        return $this->fields;
    }
}
