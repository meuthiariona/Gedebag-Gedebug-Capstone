<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Ganti password staf, dengan kebijakan password kuat. */
class PasswordController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.password', ['forced' => $request->user()->must_change_password]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required', 'confirmed', 'different:current_password',
                Password::min(config('security.staff_password_min'))->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'current_password.current_password' => 'Password saat ini salah.',
            'password.different' => 'Password baru harus berbeda dari password lama.',
        ]);

        $user->forceFill([
            'password' => $request->password,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')->with('success', 'Password berhasil diganti.');
    }
}
