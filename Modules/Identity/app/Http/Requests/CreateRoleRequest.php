<?php

namespace Modules\Identity\Http\Requests;

final class CreateRoleRequest extends RoleMutationRequest
{
    protected function updating(): bool
    {
        return false;
    }
}
