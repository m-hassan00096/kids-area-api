<?php

namespace Modules\Activities\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Activities\Enums\ActivityBillingMode;

final class ActivitiesIndexRequest extends FormRequest
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
            $invalid = static fn (string $field) => $validator->errors()->add($field, ApiResponse::text('api.errors.validation'));
            if (array_diff(array_keys($data), ['filter', 'sort', 'page', 'perPage']) !== []
                || $this->getContent() !== '' || $this->files->count() !== 0) {
                $invalid('request');
            }

            $filter = array_key_exists('filter', $data) ? $data['filter'] : [];
            if (! is_array($filter) || array_diff(array_keys($filter), ['search', 'isActive', 'billingMode']) !== []) {
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

            $active = null;
            if (array_key_exists('isActive', $filter)) {
                $active = match ($filter['isActive']) {
                    '0' => false,
                    '1' => true,
                    default => null,
                };
                if ($active === null) {
                    $invalid('filter.isActive');
                }
            }
            $mode = null;
            if (array_key_exists('billingMode', $filter)) {
                $mode = match ($filter['billingMode']) {
                    '0' => ActivityBillingMode::HOURLY,
                    '1' => ActivityBillingMode::SUBSCRIPTION,
                    default => null,
                };
                if ($mode === null) {
                    $invalid('filter.billingMode');
                }
            }

            $sort = array_key_exists('sort', $data) ? $data['sort'] : '-id';
            if (! in_array($sort, ['id', '-id', 'name', '-name'], true)) {
                $invalid('sort');
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
                'requested' => $filter, 'search' => $search, 'isActive' => $active,
                'billingMode' => $mode, 'sort' => $sort, 'page' => $page, 'perPage' => min($perPage, 100),
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
        $maximum = (string) PHP_INT_MAX;
        if ($digits === '' || strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }

        return (int) $digits;
    }
}
