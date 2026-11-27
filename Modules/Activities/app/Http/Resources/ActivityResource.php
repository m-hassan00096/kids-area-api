<?php

namespace Modules\Activities\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activity = $this->resource;

        return [
            'id' => (int) $activity->id,
            'name' => $activity->name,
            'supportsHourly' => (bool) $activity->supports_hourly,
            'supportsSubscription' => (bool) $activity->supports_subscription,
            'hourlyRate' => $activity->hourly_rate,
            'monthlyPrice' => $activity->monthly_price,
            'isActive' => (bool) $activity->is_active,
        ];
    }
}
