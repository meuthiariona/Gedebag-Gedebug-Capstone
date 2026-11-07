@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')
@php
    $cls = 'h-10 px-3 rounded-xl border border-line text-sm outline-none focus:border-ink bg-white';
    $badge = [
        'created' => 'bg-emerald-50 text-emerald-700', 'updated' => 'bg-sky-50 text-sky-700', 'deleted' => 'bg-red-50 text-red-700',
        'login' => 'bg-soft text-ink', 'logout' => 'bg-soft text-muted', 'login_failed' => 'bg-amber-50 text-amber-700',
        'login_locked' => 'bg-red-50 text-red-700', 'session_timeout' => 'bg-soft text-muted',
    ];
    $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_null($v) ? '—' : (string) $v);
@endphp

<div class="bg-white border border-line rounded-2xl p-4 mb-4 text-[12.5px] text-muted flex flex-wrap items-center gap-x-6 gap-y-2">
    <span class="flex items-center gap-1.5"><i data-lucide="shield-check" class="w-4 h-4 text-ink"></i> Semua perubahan data penting tercatat otomatis dan <b class="text-ink">tidak bisa diubah atau dihapus</b>.</span>
    @if ($failedToday)
        <a href="{{ route('admin.activity-logs.index', ['action' => 'login_failed', 'from' => today()->toDateString()]) }}" class="flex items-center gap-1.5 text-amber-700 font-semibold">
            <i data-lucide="triangle-alert" class="w-4 h-4"></i> {{ $failedToday }} login gagal hari ini
        </a>
    @endif
</div>

<form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
    <div class="flex items-center gap-2 bg-white border border-line rounded-full px-4 h-10 w-full sm:w-64">
        <i data-lucide="search" class="w-4 h-4 text-muted"></i>
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari keterangan / pengguna..." class="text-sm outline-none w-full">
    </div>
    <select name="action" class="{{ $cls }}">
        <option value="">Semua aksi</option>
        @foreach (\App\Models\ActivityLog::ACTIONS as $k => $v)<option value="{{ $k }}" @selected(($filters['action'] ?? '') === $k)>{{ $v }}</option>@endforeach
    </select>
    <select name="subject" class="{{ $cls }}">
        <option value="">Semua data</option>
        @foreach (\App\Models\ActivityLog::SUBJECTS as $k => $v)<option value="{{ $k }}" @selected(($filters['subject'] ?? '') === $k)>{{ $v }}</option>@endforeach
    </select>
    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $cls }}" title="Dari tanggal">
    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $cls }}" title="Sampai tanggal">
    <button class="h-10 px-5 rounded-full bg-ink text-white text-[13px] font-bold">Filter</button>
    @if (array_filter($filters))<a href="{{ route('admin.activity-logs.index') }}" class="text-[12px] text-muted">Reset</a>@endif
    <a href="{{ route('admin.activity-logs.export', $filters) }}" class="ml-auto h-10 px-5 rounded-full bg-white border border-line text-[13px] font-semibold flex items-center gap-2"><i data-lucide="download" class="w-4 h-4"></i> Unduh CSV</a>
</form>

<div class="bg-white border border-line rounded-2xl overflow-x-auto">
    <table class="w-full text-[13px]">
        <thead class="bg-soft/60 text-left text-[11px] uppercase tracking-wider text-muted">
            <tr>
                <th class="px-4 py-3 font-semibold">Waktu</th>
                <th class="px-4 py-3 font-semibold">Pengguna</th>
                <th class="px-4 py-3 font-semibold">Aksi</th>
                <th class="px-4 py-3 font-semibold">Keterangan</th>
                <th class="px-4 py-3 font-semibold">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($logs as $log)
                <tr class="align-top hover:bg-soft/40" x-data="{ open: false }">
                    <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('j M Y') }}<div class="text-[11px] text-muted">{{ $log->created_at->format('H:i:s') }}</div></td>
                    <td class="px-4 py-3">
                        <b>{{ $log->user_name }}</b>
                        @if ($log->user_role)<div class="text-[11px] text-muted">{{ config("rbac.roles.{$log->user_role}.label", $log->user_role) }}</div>@endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $badge[$log->action] ?? 'bg-soft' }}">{{ $log->action_label }}</span>
                        @if ($log->subject_label)<div class="text-[11px] text-muted mt-1">{{ $log->subject_label }}</div>@endif
                    </td>
                    <td class="px-4 py-3 min-w-[280px]">
                        {{ $log->description }}
                        @if ($log->changes_list)
                            <button type="button" @click="open = !open" class="ml-1 text-[11px] font-semibold text-sky-700" x-text="open ? 'Sembunyikan detail' : 'Lihat detail'"></button>
                            <table x-show="open" x-cloak class="mt-2 w-full text-[11.5px] border border-line rounded-lg overflow-hidden">
                                <tr class="bg-soft/60 text-muted"><th class="text-left px-2 py-1">Field</th><th class="text-left px-2 py-1">Sebelum</th><th class="text-left px-2 py-1">Sesudah</th></tr>
                                @foreach ($log->changes_list as $field => [$old, $new])
                                    <tr class="border-t border-line">
                                        <td class="px-2 py-1 font-semibold">{{ $field }}</td>
                                        <td class="px-2 py-1 text-red-700 break-all">{{ \Illuminate\Support\Str::limit($fmt($old), 120) }}</td>
                                        <td class="px-2 py-1 text-emerald-700 break-all">{{ \Illuminate\Support\Str::limit($fmt($new), 120) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-[11px] text-muted whitespace-nowrap" title="{{ $log->user_agent }}">{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-muted">Belum ada aktivitas tercatat.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
