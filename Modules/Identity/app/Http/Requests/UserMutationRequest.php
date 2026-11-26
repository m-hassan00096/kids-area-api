<?php

namespace Modules\Identity\Http\Requests;

use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Modules\Identity\Models\User;
use Modules\Identity\Services\Avatar\AvatarImageNormalizer;
use Modules\Identity\Services\Avatar\NormalizedAvatar;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class UserMutationRequest extends FormRequest
{
    private array $fields = [];

    private ?NormalizedAvatar $image = null;

    abstract protected function updating(): bool;

    public function authorize(): bool
    {
        // Read trusted context from the original HTTP request.
        $binding = request()->attributes->get('staff_binding');
        $context = request()->attributes->get('staff_context');
        $permission = $this->updating() ? 'identity.users.update' : 'identity.users.create';
        if (! is_object($binding) || ! is_array($context) || ! in_array($permission, $context['permissions'] ?? [], true)) {
            return false;
        }
        if (! $this->updating()) {
            return true;
        }

        $target = $this->target();
        $actorId = (int) $binding->auth_user_id;
        $data = $this->validationData();

        return ! ($target->id === 1 && ($actorId !== 1 || ($data['isActive'] ?? null) === false))
            && ! ($actorId === $target->id && is_string($data['password'] ?? null) && $data['password'] !== '');
    }

    public function validationData(): array
    {
        return $this->isJson() ? $this->json()->all() : $this->request->all();
    }

    public function rules(): array
    {
        // Preserve whitespace in passwords; check exact types below.
        $rules = ['name' => ['present'], 'username' => ['present'], 'roleId' => ['present']];
        if ($this->updating()) {
            $rules += ['email' => ['present'], 'phone' => ['present'], 'isActive' => ['present']];
        } else {
            $rules += ['password' => ['present'], 'passwordConfirmation' => ['present']];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $data = $validator->getData();
            $invalid = static fn (string $field) => $validator->errors()->add($field, ApiResponse::text('api.errors.validation'));
            $allowed = ['name', 'username', 'email', 'phone', 'roleId', 'password', 'passwordConfirmation', 'avatar'];
            if ($this->updating()) {
                $allowed = [...$allowed, 'isActive', 'removeAvatar'];
            }
            if (array_diff(array_keys($data), $allowed) !== [] || $this->query->count() !== 0) {
                $invalid('request');
            }
            $name = $data['name'] ?? null;
            if (! is_string($name) || ! mb_check_encoding($name, 'UTF-8') || Str::trim($name) === '' || mb_strlen($name) > 255) {
                $invalid('name');
            }
            if (! is_string($data['username'] ?? null) || preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $data['username']) !== 1) {
                $invalid('username');
            }
            $email = $data['email'] ?? null;
            $phone = $data['phone'] ?? null;
            if ($email !== null && (! is_string($email) || $email === '' || $email !== Str::trim($email)
                || ! mb_check_encoding($email, 'UTF-8')
                || ValidatorFacade::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:254']])->fails())) {
                $invalid('email');
            }
            if ($phone !== null && (! is_string($phone) || preg_match('/\A[0-9]{11}\z/', $phone) !== 1)) {
                $invalid('phone');
            }
            $roleId = $data['roleId'] ?? null;
            if (! is_int($roleId) || $roleId < 1) {
                $invalid('roleId');
            }
            if ($this->updating() && ! is_bool($data['isActive'] ?? null)) {
                $invalid('isActive');
            }
            if (array_key_exists('removeAvatar', $data) && ! is_bool($data['removeAvatar'])) {
                $invalid('removeAvatar');
            }

            $password = $data['password'] ?? null;
            $confirmation = $data['passwordConfirmation'] ?? null;
            if ($this->updating() && ($password === null || $password === '')) {
                $password = null;
                if ($confirmation !== null && $confirmation !== '') {
                    $invalid('passwordConfirmation');
                }
            } else {
                if (! is_string($password) || ! mb_check_encoding($password, 'UTF-8') || mb_strlen($password) < 8) {
                    $invalid('password');
                }
                if (! is_string($confirmation) || $confirmation !== $password) {
                    $invalid('passwordConfirmation');
                }
            }
            $files = $this->files->all();
            $file = $files['avatar'] ?? null;
            if (($files !== [] && (array_keys($files) !== ['avatar'] || ! $file instanceof UploadedFile))
                || ($data['avatar'] ?? null) !== null || (($data['removeAvatar'] ?? false) && $file !== null)) {
                $invalid('avatar');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $target = $this->updating() ? $this->target() : null;
            $role = DB::table('roles')->useWritePdo()->where('id', $roleId)->first(['id', 'guard_name', 'is_active']);
            if ($role === null || $role->guard_name !== 'web') {
                $invalid('roleId');
            } elseif (! (bool) $role->is_active) {
                $memberships = $target === null ? collect() : DB::table('model_has_roles')->useWritePdo()
                    ->where('model_type', $target->getMorphClass())->where('model_id', $target->id)->pluck('role_id');
                if ($target === null || $memberships->count() !== 1 || (int) $memberships->first() !== $roleId
                    || (! $target->is_active && $data['isActive'])) {
                    $invalid('roleId');
                }
            }

            // Laravel's presence verifier uses the primary connection.
            foreach (['username' => $data['username'], 'email' => $email, 'phone' => $phone] as $field => $value) {
                if ($value === null) {
                    continue;
                }
                $rule = Rule::unique('users', $field);
                if ($target !== null) {
                    $rule->ignore($target->id, 'id');
                }
                if (ValidatorFacade::make([$field => $value], [$field => [$rule]])->fails()) {
                    $invalid($field);
                }
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($file instanceof UploadedFile) {
                try {
                    $this->image = app(AvatarImageNormalizer::class)->fromUpload($file);
                } catch (ValidationException) {
                    $invalid('avatar');

                    return;
                }
            }

            $this->fields = [
                'name' => Str::trim($name),
                'username' => $data['username'],
                'email' => $email,
                'phone' => $phone,
                'roleId' => $roleId,
                'password' => $password,
                'isActive' => $this->updating() ? $data['isActive'] : true,
                'removeAvatar' => $data['removeAvatar'] ?? false,
            ];
        }];
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function normalizedAvatar(): ?NormalizedAvatar
    {
        return $this->image;
    }

    private function target(): User
    {
        $target = request()->attributes->get('users_target');
        abort_unless($target instanceof User, 404);

        return $target;
    }
}
