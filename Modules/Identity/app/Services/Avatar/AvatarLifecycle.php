<?php

namespace Modules\Identity\Services\Avatar;

use App\Http\Middleware\RequestId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Modules\Identity\Models\User;
use SensitiveParameter;
use Throwable;

final readonly class AvatarLifecycle
{
    public function __construct(private AvatarStorage $storage) {}

    public function stage(#[SensitiveParameter] NormalizedAvatar $image): string
    {
        if (DB::connection()->transactionLevel() !== 0) {
            throw new LogicException('Stage avatars before opening the transaction.');
        }

        return $this->storage->stage($image);
    }

    // Caller owns this attempt's candidate and captures old key under lock.
    // Unknown outcome (including post-commit failure) retains both.
    public function finish(?string $candidate, ?string $old, string $outcome): void
    {
        if (! in_array($outcome, ['committed', 'rolled_back', 'unknown'], true)) {
            throw new LogicException('Unknown avatar lifecycle outcome.');
        }
        if ($outcome === 'unknown' || DB::connection()->transactionLevel() !== 0) {
            $this->warn('retained_uncertain');

            return;
        }
        $key = $outcome === 'committed' ? $old : $candidate;
        if ($key === null || ($outcome === 'committed' && $key === $candidate)) {
            return;
        }
        try {
            if (! AvatarFormat::validKey($key) || User::query()->useWritePdo()->where('avatar_key', $key)->exists()) {
                return;
            }
            // Keys are immutable and never reused. No arbitrary/age-based sweep.
            $this->storage->delete($key);
        } catch (Throwable) {
            $this->warn('cleanup_failed');
        }
    }

    private function warn(string $outcome): void
    {
        Log::warning('Avatar lifecycle retained file.', [
            'request_id' => app()->bound('request') ? RequestId::id(request()) : null,
            'outcome' => $outcome,
        ]);
    }
}
