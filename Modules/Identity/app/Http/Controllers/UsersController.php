<?php

namespace Modules\Identity\Http\Controllers;

final class UsersController
{
    // T10 establishes the protected transport; later tasks implement the
    // authorized operations. Until then, every operation fails closed.
    public function index(): never
    {
        abort(503);
    }

    public function show(): never
    {
        abort(503);
    }

    public function store(): never
    {
        abort(503);
    }

    public function update(): never
    {
        abort(503);
    }

    public function destroy(): never
    {
        abort(503);
    }

    public function avatar(): never
    {
        abort(503);
    }
}
