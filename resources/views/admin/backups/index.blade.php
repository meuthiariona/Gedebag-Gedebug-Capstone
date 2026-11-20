@extends('layouts.app')
@section('title', 'Backup & Restore')

@section('content')
@php
    $types = \App\Services\BackupService::TYPES;
    $badge = ['auto' => 'bg-sky-50 text-sky-700', 'manual' => 'bg-soft text-ink', 'pre-restore' => 'bg-amber-50 text-amber-700', 'upload' => 'bg-violet-50 text-violet-700'];
    $kb = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : number_format($b / 1024, 1).' KB';
    $last = $backups[0] ?? null;
@endphp

<div class="grid lg:grid-cols-3 gap-4 mb-5">
    <div class="bg-white border border-line rounded-2xl p-5">
        <div class="text-[11px] uppercase tracking-wider text-muted font-semibold">Backup terakhir</div>
        @if ($last)
            <div class="text-lg font-bold mt-1">{{ \Illuminate\Support\Carbon::parse($last['created_at'])->diffForHumans() }}</div>
            <div class="text-[12px] text-muted">{{ \Illuminate\Support\Carbon::parse($last['created_at'])->timezone('Asia/Jakarta')->format('j M Y, H:i') }} WIB</div>
        @else
            <div class="text-lg font-bold mt-1 text-red-600">Belum ada backup</div>
        @endif
    </div>
    <div class="bg-white border border-line rounded-2xl p-5">
        <div class="text-[11px] uppercase tracking-wider text-muted font-semibold">Jadwal otomatis</div>
        <div class="text-lg font-bold mt-1">{{ config('backup.schedule') === '0 1 * * *' ? 'Setiap hari, 01:00 WIB' : 'Cron: '.config('backup.schedule') }}</div>
        <div class="text-[12px] text-muted">Menyimpan {{ config('backup.keep') }} backup otomatis terakhir</div>
    </div>
    <div class="bg-white border border-line rounded-2xl p-5 flex flex-col justify-between gap-3">
        <div class="text-[12px] text-muted">Isi backup: seluruh data (booking, pembayaran, katalog, pengguna, pengaturan) + foto/file upload, dengan checksum SHA-256.</div>
        <form action="{{ route('admin.backups.store') }}" method="POST">@csrf
            <button class="w-full h-10 rounded-full bg-ink text-white text-[13px] font-bold flex items-center justify-center gap-2"><i data-lucide="database-backup" class="w-4 h-4"></i> Backup sekarang</button>
        </form>
    </div>
</div>

<div class="bg-white border border-line rounded-2xl overflow-x-auto" x-data="{ target: null }">
    <table class="w-full text-[13px]">
        <thead class="bg-soft/60 text-left text-[11px] uppercase tracking-wider text-muted">
            <tr>
                <th class="px-4 py-3 font-semibold">Waktu</th>
                <th class="px-4 py-3 font-semibold">Jenis</th>
                <th class="px-4 py-3 font-semibold">Isi</th>
                <th class="px-4 py-3 font-semibold">Ukuran</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($backups as $b)
                <tr class="hover:bg-soft/40">
                    <td class="px-4 py-3 whitespace-nowrap">
                        {{ \Illuminate\Support\Carbon::parse($b['created_at'])->timezone('Asia/Jakarta')->format('j M Y, H:i') }}
                        <div class="text-[11px] text-muted">{{ $b['name'] }}</div>
                    </td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $badge[$b['type']] ?? 'bg-soft' }}">{{ $types[$b['type']] ?? $b['type'] }}</span></td>
                    <td class="px-4 py-3 text-[12px]">{{ number_format($b['rows']) }} baris data · {{ $b['uploads'] }} file</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $kb($b['size']) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.backups.download', $b['name']) }}" class="inline-flex items-center gap-1 h-8 px-3 rounded-full bg-soft text-[12px] font-semibold"><i data-lucide="download" class="w-3.5 h-3.5"></i> Unduh</a>
                        <button type="button" @click="target = @js($b['name'])" class="inline-flex items-center gap-1 h-8 px-3 rounded-full bg-amber-50 text-amber-800 text-[12px] font-semibold"><i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Restore</button>
                        <form action="{{ route('admin.backups.destroy', $b['name']) }}" method="POST" class="inline" onsubmit="return confirm('Hapus file backup ini?')">@csrf @method('DELETE')
                            <button class="inline-flex items-center h-8 px-3 rounded-full text-red-600 hover:bg-red-50 text-[12px]"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-muted">Belum ada backup. Klik "Backup sekarang" untuk membuat yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Dialog konfirmasi restore --}}
    <div x-show="target" x-cloak class="fixed inset-0 z-50 bg-black/40 grid place-items-center p-4" @keydown.escape.window="target = null">
        <form :action="'{{ url('admin/backups') }}/' + target + '/restore'" method="POST" class="bg-white rounded-2xl p-6 max-w-md w-full space-y-3" @click.outside="target = null">
            @csrf
            <h2 class="font-semibold flex items-center gap-2"><i data-lucide="triangle-alert" class="w-5 h-5 text-amber-600"></i> Restore dari backup?</h2>
            <p class="text-[13px] text-muted">Semua data saat ini akan <b class="text-ink">diganti</b> dengan isi <b class="text-ink" x-text="target"></b>. Sebelum itu, sistem otomatis membuat backup pengaman dari kondisi sekarang. Audit log tidak ikut ditimpa.</p>
            <label class="text-[12px] font-semibold block">Ketik <b>RESTORE</b> untuk melanjutkan</label>
            <input name="confirm" autocomplete="off" class="w-full h-10 px-3 rounded-xl border border-line text-sm outline-none focus:border-ink">
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="target = null" class="h-10 px-5 rounded-full bg-soft text-[13px] font-semibold">Batal</button>
                <button class="h-10 px-5 rounded-full bg-amber-600 text-white text-[13px] font-bold">Restore</button>
            </div>
        </form>
    </div>
</div>

<form action="{{ route('admin.backups.upload') }}" method="POST" enctype="multipart/form-data" class="mt-5 bg-white border border-line rounded-2xl p-5 flex flex-wrap items-center gap-3">
    @csrf
    <div class="text-[13px]">
        <b>Upload file backup</b>
        <div class="text-[12px] text-muted">Untuk memulihkan dari salinan di laptop lain / flashdisk / cloud. File dicek keasliannya dulu (checksum).</div>
    </div>
    <input type="file" name="file" accept=".zip" required class="text-[12px] ml-auto">
    <button class="h-10 px-5 rounded-full bg-white border border-line text-[13px] font-semibold flex items-center gap-2"><i data-lucide="upload" class="w-4 h-4"></i> Upload</button>
</form>
@endsection
