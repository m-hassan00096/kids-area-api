<?php

namespace Modules\People\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use JsonException;
use Modules\People\Support\CustomerInput;
use stdClass;

abstract class CustomerMutationRequest extends FormRequest
{
    private array $fields = [];

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
            try {
                $this->fields = CustomerInput::validate($validator->getData());
            } catch (ValidationException $failure) {
                foreach ($failure->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        }];
    }

    public function fields(): array
    {
        return $this->fields;
    }
}
