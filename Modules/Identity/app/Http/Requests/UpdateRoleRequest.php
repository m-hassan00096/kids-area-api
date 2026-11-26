<?php

namespace Modules\Identity\Http\Requests;

final class UpdateRoleRequest extends RoleMutationRequest
{
    protected function updating(): bool
    {
        return true;
    }
}
