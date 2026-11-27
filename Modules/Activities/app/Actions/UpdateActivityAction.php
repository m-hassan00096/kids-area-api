<?php

namespace Modules\Activities\Actions;

use Illuminate\Http\Request;
use Modules\Activities\Models\Activity;
use Modules\Activities\Services\ActivityMutation;
use Modules\Activities\Support\ActivityInput;
use RuntimeException;

final readonly class UpdateActivityAction
{
    public function __construct(private ActivityMutation $mutation) {}

    public function execute(Request $request, array $fields): void
    {
        $attributes = ActivityInput::attributes($fields, true);
        $this->mutation->execute($request, 'activities.update', static function () use ($request, $attributes): void {
            $id = $request->attributes->get('activities_target_id');
            abort_unless(is_int($id), 404);
            $activity = Activity::query()->whereKey($id)->lockForUpdate()->first();
            abort_if($activity === null, 404);
            if (! $activity->fill($attributes)->save()) {
                throw new RuntimeException('Unable to update Activity.');
            }
        });
    }
}
