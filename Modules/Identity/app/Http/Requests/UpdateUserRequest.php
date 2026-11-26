<?php

namespace Modules\Identity\Http\Requests;

final class UpdateUserRequest extends UserMutationRequest
{
    protected function updating(): bool
    {
        return true;
    }
}
