<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Hardening untuk staf admin:
 * 1. Session timeout: logout otomatis jika tidak aktif lebih dari X menit (config/security.php).
 * 2. Wajib ganti password jika masih memakai password bawaan / sementara.
 */
class EnforceStaffSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $idleLimit = config('security.admin_idle_minutes') * 60;
        $last = $request->session()->get('last_activity_at');

        if ($last && now()->timestamp - $last > $idleLimit) {
            ActivityLog::record('session_timeout', 'Sesi berakhir otomatis setelah '.config('security.admin_idle_minutes').' menit tidak aktif', null, null, null, $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Sesi Anda berakhir karena tidak aktif. Silakan login kembali.',
            ]);
        }
        $request->session()->put('last_activity_at', now()->timestamp);

        if ($user->must_change_password && ! $request->routeIs('admin.password.*')) {
            return redirect()->route('admin.password.edit');
        }

        return $next($request);
    }
}
