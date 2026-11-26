<?php

namespace Modules\Activities\Http\Requests;

final class CreateActivityRequest extends ActivityMutationRequest
{
    protected function updating(): bool
    {
        return false;
    }
}
