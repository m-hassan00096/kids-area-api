<?php

namespace Modules\Identity\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use SensitiveParameter;
use stdClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class NormalizeUsersInput
{
    public function handle(#[SensitiveParameter] Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $filter = $request->query->all()['filter'] ?? null;
            if ($request->is('api/v1/users') && is_array($filter) && is_string($filter['search'] ?? null)) {
                $filter['search'] = Str::trim($filter['search']);
                $request->query->set('filter', $filter);
            }

            return $next($request);
        }

        // Do not let query parameters supply write fields. Required-field,
        // uniqueness, role/state and image-content checks follow in FormRequests/Actions.
        if ($request->query->count() !== 0) {
            $this->invalid('request');
        }

        $update = $request->isMethod('PUT');
        $create = $request->isMethod('POST');
        $mediaType = strtolower(trim(explode(';', (string) $request->headers->get('Content-Type'), 2)[0]));
        $multipart = $mediaType === 'multipart/form-data' && $request->getRealMethod() === 'POST';
        if ($request->isJson()) {
            if ($request->getContent() === '') {
                $data = [];
            } else {
                try {
                    $decoded = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    abort(400);
                }
                if (! $decoded instanceof stdClass) {
                    $this->invalid('request');
                }
                $data = $request->json()->all();
            }
        } elseif ($multipart) {
            $data = $request->request->all();
            // FirstPartyCors already enforced the sole allowed override.
            if ($update) {
                unset($data['_method']);
            }
        } elseif ($request->isMethod('DELETE') && $request->getContent() === '' && $request->request->count() === 0) {
            $data = [];
        } else {
            $this->invalid('request');
        }

        $allowed = $create || $update ? ['name', 'username', 'email', 'phone', 'roleId', 'password', 'passwordConfirmation', 'avatar'] : [];
        if ($update) {
            $allowed[] = 'isActive';
            $allowed[] = 'removeAvatar';
        }
        if (array_diff(array_keys($data), $allowed) !== []) {
            $this->invalid('request');
        }

        // Symfony can discard UPLOAD_ERR_NO_FILE entries. Inspect only the
        // native parsed file metadata, never the raw multipart wire body.
        if ($multipart && $_FILES !== []) {
            $native = $_FILES['avatar'] ?? null;
            if (array_keys($_FILES) !== ['avatar'] || ! is_array($native)
                || ! is_string($native['name'] ?? null) || ($native['error'] ?? null) !== UPLOAD_ERR_OK) {
                $this->invalid('avatar');
            }
        }
        $files = $request->files->all();
        if ($files !== [] && (! ($create || $update) || ! $multipart || array_keys($files) !== ['avatar'] || ! $files['avatar'] instanceof UploadedFile)) {
            $this->invalid('avatar');
        }
        if (array_key_exists('avatar', $data) && $data['avatar'] !== null) {
            $this->invalid('avatar');
        }

        foreach (['name', 'username', 'email', 'phone', 'password', 'passwordConfirmation'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $optional = in_array($field, ['email', 'phone'], true) || ($update && in_array($field, ['password', 'passwordConfirmation'], true));
            if ($optional && ($data[$field] === null || $data[$field] === '')) {
                $data[$field] = null;

                continue;
            }
            if (! is_string($data[$field])) {
                $this->invalid($field);
            }
        }

        if (isset($data['name'])) {
            $data['name'] = Str::trim($data['name']);
            if ($data['name'] === '' || mb_strlen($data['name']) > 255) {
                $this->invalid('name');
            }
        }
        if (isset($data['username']) && preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $data['username']) !== 1) {
            $this->invalid('username');
        }
        if (isset($data['phone']) && preg_match('/\A[0-9]{11}\z/', $data['phone']) !== 1) {
            $this->invalid('phone');
        }
        if (isset($data['email']) && ($data['email'] !== Str::trim($data['email'])
            || Validator::make(['email' => $data['email']], ['email' => ['required', 'email:rfc', 'max:254']])->fails())) {
            $this->invalid('email');
        }

        if (array_key_exists('roleId', $data)) {
            $value = $data['roleId'];
            if ($multipart && is_string($value) && preg_match('/\A[0-9]+\z/', $value) === 1) {
                $digits = ltrim($value, '0');
                $max = (string) PHP_INT_MAX;
                if ($digits !== '' && (strlen($digits) < strlen($max)
                    || (strlen($digits) === strlen($max) && strcmp($digits, $max) <= 0))) {
                    $value = (int) $digits;
                }
            }
            if (! is_int($value) || $value < 1) {
                $this->invalid('roleId');
            }
            $data['roleId'] = $value;
        }
        foreach (['isActive', 'removeAvatar'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($multipart && is_string($value)) {
                $value = match ($value) {
                    '1', 'true' => true,
                    '0', 'false' => false,
                    default => $value,
                };
            }
            if (! is_bool($value)) {
                $this->invalid($field);
            }
            $data[$field] = $value;
        }
        if (($data['removeAvatar'] ?? false) && $files !== []) {
            $this->invalid('avatar');
        }

        // Keep omitted keys omitted, including the update contact presence
        // distinction. Never hash passwords or decode/store images here.
        ($request->isJson() ? $request->json() : $request->request)->replace($data);

        return $next($request);
    }

    private function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => ApiResponse::text('api.errors.validation')]);
    }
}
