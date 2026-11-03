{{-- Halaman login & daftar. Variabel: $mode ('login' | 'register') --}}
@extends('layouts.app')
@section('title', $mode === 'login' ? 'Login' : 'Daftar')
@section('subbar')@endsection

@section('content')
@if ($mode === 'login')
<div class="max-w-md mx-auto px-4 pt-16">
    <div class="border border-line rounded-2xl p-8 shadow-card">
        <h1 class="text-2xl font-semibold tracking-tight">Masuk ke akun</h1>
        <p class="text-[13px] text-muted mt-1">Kelola booking, pembayaran & invoice event Anda.</p>
        <form method="POST" action="{{ route('login') }}" class="space-y-4 mt-6">
            @csrf
            @error('email')<div class="bg-red-50 text-red-700 rounded-xl px-4 py-2.5 text-sm">{{ $message }}</div>@enderror
            <div>
                <label class="text-[12px] font-semibold">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full h-11 px-4 rounded-xl border border-line mt-1 text-sm outline-none focus:border-ink">
            </div>
            <div>
                <label class="text-[12px] font-semibold">Password</label>
                <input type="password" name="password" required class="w-full h-11 px-4 rounded-xl border border-line mt-1 text-sm outline-none focus:border-ink">
            </div>
            <label class="flex items-center gap-2 text-[12px]"><input type="checkbox" name="remember" class="accent-black"> Ingat saya</label>
            <button class="w-full h-11 rounded-full bg-ink text-white text-[13px] font-bold">Login</button>
        </form>
        <p class="text-[12px] text-muted text-center mt-5">Belum punya akun? <a href="{{ route('register') }}" class="font-semibold text-ink underline">Daftar</a></p>
    </div>
</div>
@else
<div class="max-w-lg mx-auto px-4 pt-12">
    <div class="border border-line rounded-2xl p-8 shadow-card">
        <h1 class="text-2xl font-semibold tracking-tight">Buat akun</h1>
        <p class="text-[13px] text-muted mt-1">Untuk melakukan booking peralatan & talent.</p>
        <form method="POST" action="{{ route('register') }}" class="space-y-4 mt-6">
            @csrf
            @if ($errors->any())
                <div class="bg-red-50 text-red-700 rounded-xl px-4 py-2.5 text-sm">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif
            @php($input = 'w-full h-11 px-4 rounded-xl border border-line mt-1 text-sm outline-none focus:border-ink')
            <div><label class="text-[12px] font-semibold">Nama lengkap *</label><input name="name" value="{{ old('name') }}" required class="{{ $input }}"></div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div><label class="text-[12px] font-semibold">Email *</label><input type="email" name="email" value="{{ old('email') }}" required class="{{ $input }}"></div>
                <div><label class="text-[12px] font-semibold">No. HP *</label><input name="phone" value="{{ old('phone') }}" required class="{{ $input }}"></div>
            </div>
            <div><label class="text-[12px] font-semibold">Perusahaan (opsional)</label><input name="company" value="{{ old('company') }}" class="{{ $input }}"></div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div><label class="text-[12px] font-semibold">Password *</label><input type="password" name="password" required class="{{ $input }}"></div>
                <div><label class="text-[12px] font-semibold">Ulangi password *</label><input type="password" name="password_confirmation" required class="{{ $input }}"></div>
            </div>
            <button class="w-full h-11 rounded-full bg-ink text-white text-[13px] font-bold">Daftar</button>
        </form>
        <p class="text-[12px] text-muted text-center mt-5">Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold text-ink underline">Login</a></p>
    </div>
</div>
@endif
@endsection
