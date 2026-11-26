<?php

namespace Modules\Identity\Http\Requests;

final class CreateUserRequest extends UserMutationRequest
{
    protected function updating(): bool
    {
        return false;
    }
}
