<?php

namespace Modules\Identity\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JsonException;

final class ChangePasswordRequest extends FormRequest
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
        return ['currentPassword' => ['present'], 'password' => ['present'], 'passwordConfirmation' => ['present']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $data = $validator->getData();
            if (! is_string($data['currentPassword'] ?? null) || $data['currentPassword'] === '') {
                $validator->errors()->add('currentPassword', __('validation.required', ['attribute' => __('validation.attributes.currentPassword')]));
            }
            if (! is_string($data['password'] ?? null) || mb_strlen($data['password']) < 8) {
                $validator->errors()->add('password', __('validation.min.string', ['attribute' => __('validation.attributes.password'), 'min' => 8]));
            }
            if (! is_string($data['passwordConfirmation'] ?? null) || $data['passwordConfirmation'] !== ($data['password'] ?? null)) {
                $validator->errors()->add('passwordConfirmation', __('validation.confirmed', ['attribute' => __('validation.attributes.password')]));
            }
            if (array_diff(array_keys($data), ['currentPassword', 'password', 'passwordConfirmation']) !== [] || $this->query->count() !== 0 || $this->files->count() !== 0) {
                $validator->errors()->add('request', ApiResponse::text('identity.unsupported_field'));
            }
        }];
    }

    public function messages(): array
    {
        return [
            'currentPassword.present' => __('validation.required', ['attribute' => __('validation.attributes.currentPassword')]),
            'password.present' => __('validation.required', ['attribute' => __('validation.attributes.password')]),
            'passwordConfirmation.present' => __('validation.required', ['attribute' => __('validation.attributes.passwordConfirmation')]),
        ];
    }
}
