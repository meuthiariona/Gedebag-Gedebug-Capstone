@extends('layouts.app')
@section('title', 'Rekap Uang Masuk')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <h1 class="text-2xl font-semibold">Rekap Uang Masuk</h1>
        <form method="GET" class="flex gap-2">
            <input type="month" name="month" aria-label="Bulan laporan" value="{{ $month }}" required class="rounded-lg border border-line px-3">
            <button class="bg-ink text-white rounded-full px-4 py-2 text-sm font-semibold">Tampilkan</button>
        </form>
    </div>
    <div class="grid sm:grid-cols-2 gap-3">
        <div class="bg-white rounded-xl border border-line p-4"><div class="text-sm text-muted">Total uang masuk bulan ini</div><div class="text-xl font-semibold">{{ rupiah($received) }}</div></div>
        <div class="bg-white rounded-xl border border-line p-4"><div class="text-sm text-muted">Peminjam unik berdasarkan bulan mulai sewa</div><div class="text-xl font-semibold">{{ $renters }}</div></div>
    </div>
    <div class="bg-white rounded-xl border border-line overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-soft"><tr><th class="p-3">Tanggal bayar</th><th class="p-3">Booking</th><th class="p-3">Customer</th><th class="p-3">Metode</th><th class="p-3">Referensi</th><th class="p-3 text-right">Nominal</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="p-3">{{ $payment->paid_at?->format('d/m/Y H:i') }}</td>
                        <td class="p-3"><a class="underline font-semibold" href="{{ route('admin.bookings.show', $payment->booking) }}">{{ $payment->booking->code }}</a></td>
                        <td class="p-3">{{ $payment->booking->customer_name }}</td>
                        <td class="p-3">{{ $payment->method }}</td>
                        <td class="p-3">{{ $payment->reference ?: '—' }}</td>
                        <td class="p-3 text-right">{{ rupiah($payment->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-5 text-center text-muted">Belum ada pembayaran terverifikasi untuk bulan ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $payments->links() }}
</div>
@endsection
