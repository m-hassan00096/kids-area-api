<?php

namespace Modules\Activities\Actions;

use Illuminate\Http\Request;
use Modules\Activities\Models\Activity;
use Modules\Activities\Services\ActivityMutation;
use RuntimeException;

final readonly class DeleteActivityAction
{
    public function __construct(private ActivityMutation $mutation) {}

    public function execute(Request $request): void
    {
        $this->mutation->execute($request, 'activities.delete', static function () use ($request): void {
            $id = $request->attributes->get('activities_target_id');
            abort_unless(is_int($id), 404);
            $activity = Activity::query()->whereKey($id)->lockForUpdate()->first();
            abort_if($activity === null, 404);
            // This standalone catalogue has no owning dependencies yet.
            if ($activity->delete() !== true) {
                throw new RuntimeException('Unable to delete Activity.');
            }
        });
    }
}
