<?php

namespace Modules\Identity\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JsonException;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        if ($this->isJson() && $this->getContent() !== '') {
            try {
                json_decode($this->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                abort(400);
            }
        }

        return $this->isJson() ? $this->json()->all() : $this->request->all();
    }

    public function rules(): array
    {
        return ['username' => ['present'], 'password' => ['present']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $data = $validator->getData();
            if (! is_string($data['username'] ?? null)
                || preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $data['username']) !== 1) {
                $validator->errors()->add('username', ApiResponse::text('identity.invalid_username'));
            }
            // Laravel's required rule treats whitespace-only strings as empty.
            // The approved password contract does not trim or forbid spaces.
            if (! is_string($data['password'] ?? null) || $data['password'] === '') {
                $validator->errors()->add('password', __('validation.required', ['attribute' => __('validation.attributes.password')]));
            }
            if (array_diff(array_keys($data), ['username', 'password']) !== [] || $this->query->count() !== 0 || $this->files->count() !== 0) {
                $validator->errors()->add('request', ApiResponse::text('identity.unsupported_field'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'username.present' => __('validation.required', ['attribute' => __('validation.attributes.username')]),
            'password.present' => __('validation.required', ['attribute' => __('validation.attributes.password')]),
        ];
    }
}
