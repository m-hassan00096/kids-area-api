<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Modules\Activities\Models\Activity;

trait ActivityHttpFixtures
{
    private function activityFields(string $name, array $changes = []): array
    {
        return array_replace([
            'name' => $name, 'supportsHourly' => true, 'supportsSubscription' => false,
            'hourlyRate' => '60', 'monthlyPrice' => null, 'isActive' => true,
        ], $changes);
    }

    private function activityFixture(string $name, array $changes = []): Activity
    {
        return Activity::query()->create(array_replace([
            'name' => $name, 'supports_hourly' => true, 'supports_subscription' => false,
            'hourly_rate' => '60.00', 'monthly_price' => null, 'is_active' => true,
        ], $changes))->fresh();
    }

    private function activityData(Activity $activity): array
    {
        return [
            'id' => (int) $activity->id, 'name' => $activity->name,
            'supportsHourly' => $activity->supports_hourly, 'supportsSubscription' => $activity->supports_subscription,
            'hourlyRate' => $activity->hourly_rate, 'monthlyPrice' => $activity->monthly_price,
            'isActive' => $activity->is_active,
        ];
    }

    private function activitySnapshot(): array
    {
        return DB::table('activities')->orderBy('id')->get()->map(static fn ($row) => (array) $row)->all();
    }
}
