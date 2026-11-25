<?php

namespace App\Http\Controllers;

final class SelectController
{
    public function __invoke(): never
    {
        // T18 delivers registry validation, authorization and dispatch.
        abort(503);
    }
}
