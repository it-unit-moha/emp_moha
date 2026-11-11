<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_active, 401, 'This account is disabled.');

        return $next($request);
    }
}
