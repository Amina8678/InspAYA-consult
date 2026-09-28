<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // $this->authorize('update', $model): controller-level policy checks
    // (NFR-SEC-06), in addition to route middleware.
    use AuthorizesRequests;
}
