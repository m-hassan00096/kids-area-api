<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Services\Avatar\AvatarImageNormalizer;
use RuntimeException;
use Spatie\QueryBuilder\Exceptions\InvalidFieldQuery;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\Exceptions\InvalidIncludeQuery;
use Spatie\QueryBuilder\Exceptions\InvalidSortQuery;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UsersBoundaryProbe
{
    public const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png';

    public const PATH = '/private/synthetic-avatar-sentinel.png';

    public const RAW = 'synthetic-multipart-content-sentinel';

    public function index(): never
    {
        $this->execute();
    }

    public function show(): never
    {
        $this->execute();
    }

    public function store(): never
    {
        $this->execute();
    }

    public function update(): never
    {
        $this->execute();
    }

    public function destroy(): never
    {
        $this->execute();
    }

    public function avatar(): never
    {
        $this->execute();
    }

    private function execute(): never
    {
        $request = request();
        $mode = $request->headers->get('X-Identity-Test-Mode');
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        if ($mode === 'users-probe-transport') {
            abort(503);
        }
        if ($mode === 'users-probe-avatar-validation') {
            $file = $request->files->get('avatar');
            abort_unless($file instanceof UploadedFile, 422);
            app(AvatarImageNormalizer::class)->fromUpload($file);
            $request->attributes->set('_test_avatar_validated', true);
            abort(503);
        }
        if ($mode === 'users-probe-normalized') {
            $request->attributes->set('_test_users_fingerprint', hash('sha256', serialize([
                $body, $request->query->all(), $request->attributes->get('users_target')?->id,
            ])));
            abort(503);
        }

        $unknown = collect(['synthetic-redaction-sentinel']);
        $allowed = collect(['synthetic-approved']);
        match ($mode) {
            'users-probe-filter' => throw new InvalidFilterQuery($unknown, $allowed),
            'users-probe-sort' => throw new InvalidSortQuery($unknown, $allowed),
            'users-probe-include' => throw new InvalidIncludeQuery($unknown, $allowed),
            'users-probe-fields' => throw new InvalidFieldQuery($unknown, $allowed),
            'users-probe-unknown' => throw new UnknownUsersQueryException(400, 'synthetic-redaction-sentinel'),
            'users-probe-conflict' => abort(409, 'synthetic-redaction-sentinel'),
            default => null,
        };

        // Read-only synthetic binding probe, never a real mutation.
        $values = [$body['password'] ?? self::RAW, $body['email'] ?? self::RAW,
            $body['phone'] ?? self::RAW, self::KEY, self::PATH, self::RAW];
        DB::selectOne('SELECT ? AS synthetic_users_sensitive_probe, ? AS synthetic_email, ? AS synthetic_phone, ? AS synthetic_key, ? AS synthetic_path, ? AS synthetic_raw', $values);
        if ($mode === 'users-probe-fault') {
            throw new RuntimeException(implode(' ', $values));
        }

        abort(503);
    }
}
