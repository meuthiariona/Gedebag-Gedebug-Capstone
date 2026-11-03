<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Email atau password salah.']);
        }

        $request->session()->regenerate();
        $this->claimGuestOrders();

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
