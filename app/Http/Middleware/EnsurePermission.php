<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Pemakaian di route: ->middleware('permission:finance.manage')
 * Beberapa izin dipisah "|" berarti cukup punya salah satunya.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permissions)
    {
        $user = $request->user();
        $allowed = $user && collect(explode('|', $permissions))
            ->contains(fn ($p) => $user->hasPermission($p));

        abort_unless($allowed, 403, 'Peran Anda tidak memiliki akses ke halaman atau aksi ini.');

        return $next($request);
    }
}
