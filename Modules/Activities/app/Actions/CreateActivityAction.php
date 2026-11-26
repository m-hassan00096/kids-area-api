<?php

namespace Modules\Activities\Actions;

use Illuminate\Http\Request;
use Modules\Activities\Models\Activity;
use Modules\Activities\Services\ActivityMutation;
use Modules\Activities\Support\ActivityInput;
use RuntimeException;

final readonly class CreateActivityAction
{
    public function __construct(private ActivityMutation $mutation) {}

    public function execute(Request $request, array $fields): void
    {
        $attributes = ActivityInput::attributes($fields, false);
        $this->mutation->execute($request, 'activities.store', static function () use ($attributes): void {
            $activity = Activity::query()->create($attributes);
            if (! $activity->exists) {
                throw new RuntimeException('Unable to create Activity.');
            }
        });
    }
}
