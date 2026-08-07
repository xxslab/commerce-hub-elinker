<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CompanyAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user() && in_array($request->user()->role ?? 'owner', ['owner', 'admin'], true), 403);
        return $next($request);
    }
}
