<?php

namespace Modules\Activities\Http\Requests;

final class UpdateActivityRequest extends ActivityMutationRequest
{
    protected function updating(): bool
    {
        return true;
    }
}
