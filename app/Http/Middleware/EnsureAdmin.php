<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Gerbang panel admin: hanya peran yang punya izin `admin.access`. */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->hasPermission('admin.access'), 403, 'Khusus staf admin.');

        return $next($request);
    }
}
