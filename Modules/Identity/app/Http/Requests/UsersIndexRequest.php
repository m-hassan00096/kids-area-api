<?php

namespace Modules\Identity\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UsersIndexRequest extends FormRequest
{
    private array $criteria = [];

    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return $this->query->all();
    }

    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $data = $validator->getData();
            $invalid = static fn (string $key) => $validator->errors()->add($key, ApiResponse::text('api.errors.validation'));
            if (array_diff(array_keys($data), ['filter', 'page', 'perPage']) !== []
                || $this->getContent() !== '' || $this->files->count() !== 0) {
                $invalid('request');
            }

            $filter = $data['filter'] ?? [];
            if (! is_array($filter) || array_diff(array_keys($filter), ['search', 'roleId', 'isActive']) !== []) {
                $invalid('filter');

                return;
            }

            $search = null;
            if (array_key_exists('search', $filter)) {
                $search = $filter['search'];
                if (! is_string($search) || ! mb_check_encoding($search, 'UTF-8') || mb_strlen($search) > 255) {
                    $invalid('filter.search');
                }
            }

            $roleId = null;
            if (array_key_exists('roleId', $filter)) {
                $roleId = $this->positiveInteger($filter['roleId']);
                if ($roleId === null) {
                    $invalid('filter.roleId');
                }
            }

            $active = null;
            if (array_key_exists('isActive', $filter)) {
                $active = match ($filter['isActive']) {
                    '1', 'true' => true,
                    '0', 'false' => false,
                    default => null,
                };
                if ($active === null) {
                    $invalid('filter.isActive');
                }
            }

            $page = array_key_exists('page', $data) ? $this->positiveInteger($data['page']) : 1;
            $perPage = array_key_exists('perPage', $data) ? $this->positiveInteger($data['perPage']) : 15;
            if ($page === null) {
                $invalid('page');
            }
            if ($perPage === null) {
                $invalid('perPage');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->criteria = [
                'requested' => $filter,
                'search' => $search,
                'roleId' => $roleId,
                'isActive' => $active,
                'page' => $page,
                'perPage' => min($perPage, 100),
            ];
        }];
    }

    public function criteria(): array
    {
        return $this->criteria;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            return null;
        }
        $digits = ltrim($value, '0');
        $max = (string) PHP_INT_MAX;
        if ($digits === '' || strlen($digits) > strlen($max)
            || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            return null;
        }

        return (int) $digits;
    }
}
