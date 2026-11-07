@extends('layouts.app')
@section('title', 'Ganti Password')

@section('content')
@php $cls = 'w-full h-10 px-3 rounded-xl border border-line text-sm outline-none focus:border-ink mt-1'; @endphp
<div class="max-w-lg">
    @if ($forced)
        <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 text-sm mb-4 flex gap-2">
            <i data-lucide="shield-alert" class="w-4 h-4 mt-0.5 shrink-0"></i>
            <div>Akun Anda masih memakai password bawaan atau sementara. Demi keamanan, ganti password dulu sebelum memakai panel admin.</div>
        </div>
    @endif

    <form action="{{ route('admin.password.update') }}" method="POST" class="bg-white border border-line rounded-2xl p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="text-[12px] font-semibold">Password saat ini</label>
            <input type="password" name="current_password" required autocomplete="current-password" class="{{ $cls }}">
        </div>
        <div>
            <label class="text-[12px] font-semibold">Password baru</label>
            <input type="password" name="password" required autocomplete="new-password" class="{{ $cls }}">
            <p class="text-[11px] text-muted mt-1">Minimal {{ config('security.staff_password_min') }} karakter, berisi huruf besar, huruf kecil, angka, dan simbol.</p>
        </div>
        <div>
            <label class="text-[12px] font-semibold">Ulangi password baru</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $cls }}">
        </div>
        <button class="h-11 px-8 rounded-full bg-ink text-white text-[13px] font-bold">Simpan Password</button>
    </form>
</div>
@endsection
