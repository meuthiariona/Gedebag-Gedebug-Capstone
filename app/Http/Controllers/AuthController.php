<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth', ['mode' => 'login']);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Hardening: blokir sementara setelah N kali gagal untuk email + IP yang sama (anti brute force).
        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        $max = config('security.login_max_attempts');
        if (RateLimiter::tooManyAttempts($key, $max)) {
            event(new Lockout($request));
            $wait = RateLimiter::availableIn($key);

            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Terlalu banyak percobaan login. Coba lagi dalam '.ceil($wait / 60).' menit.',
            ]);
        }

        // Staf admin tidak boleh "Ingat saya": cookie jangka panjang akan melewati session timeout.
        $user = User::where('email', $credentials['email'])->first();
        $remember = $request->boolean('remember') && ! $user?->isAdmin();

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($key, config('security.login_lockout_seconds'));
            $left = RateLimiter::remaining($key, $max);

            // Pesan sama untuk email salah maupun password salah, supaya tidak membocorkan email terdaftar.
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Email atau password salah.'.($left > 0 && $left <= 2 ? " Sisa {$left} percobaan sebelum diblokir sementara." : ''),
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('last_activity_at', now()->timestamp);
        $this->claimGuestOrders();

        if (Auth::user()->must_change_password) {
            return redirect()->route('admin.password.edit');
        }

        return redirect()->intended(Auth::user()->isAdmin() ? route('admin.dashboard') : route('home'));
    }

    public function registerForm()
    {
        return view('auth', ['mode' => 'register']);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users'],
            'phone' => ['required', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data + ['role' => 'customer']);
        Auth::login($user);
        $request->session()->regenerate();
        $this->claimGuestOrders();

        return redirect()->intended(route('home'))->with('success', 'Akun berhasil dibuat. Selamat datang, '.$user->name.'!');
    }

    public function logout(Request $request)
    {
        // Simpan keranjang & tanggal agar tidak hilang setelah logout
        $keep = $request->session()->only(['cart', 'rental_start', 'rental_end']);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        session($keep);

        return redirect()->route('home');
    }

    /** Pesanan yang dibuat tanpa login di browser ini otomatis ditautkan ke akun yang baru login/daftar. */
    private function claimGuestOrders(): void
    {
        if ($ids = guest_order_ids()) {
            \App\Models\Booking::whereIn('id', $ids)->whereNull('user_id')->update(['user_id' => Auth::id()]);
        }
    }
}
