@extends('layouts.app')
@section('title', 'Invoice '.$booking->invoice_no)

@section('subbar')
<div x-data="{ show: true }" x-show="show" class="bg-soft/70 border-b border-line no-print">
    <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-2.5 flex items-center gap-2 text-[11px]">
        <i data-lucide="circle-check" class="w-4 h-4"></i>
        Invoice <b>#{{ $booking->invoice_no }}</b> generated successfully for <b>{{ $booking->customer_email ?: $booking->customer_phone }}</b> &amp; customer primary contact.
        <button @click="show = false" class="ml-auto"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
</div>
@endsection

@section('content')
@php
    $lines = $booking->items;
    $gross = $lines->where('item_type', '!=', 'service')->sum('subtotal');
    $dpPay = $booking->payments->where('status', 'verified')->sortBy('paid_at')->first();
@endphp
<div class="max-w-[900px] mx-auto px-4 sm:px-6 pt-6">
    <div class="flex flex-wrap items-center gap-2 no-print">
        <div class="text-[11px] text-muted">
            <a href="{{ route('home') }}" class="hover:underline">Home</a> / <a href="{{ route('bookings.index') }}" class="hover:underline">My Bookings</a> /
            <a href="{{ route('bookings.show', $booking) }}" class="hover:underline">Booking #{{ $booking->code }}</a> / <span class="text-ink font-semibold">Invoice</span>
        </div>
        <div class="ml-auto flex gap-2">
            <a href="{{ route('bookings.show', $booking) }}" class="h-9 px-4 rounded-full bg-soft text-[11.5px] font-semibold flex items-center gap-1.5"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Booking Details</a>
            <button onclick="window.print()" class="h-9 px-4 rounded-full bg-soft text-[11.5px] font-semibold flex items-center gap-1.5"><i data-lucide="printer" class="w-3.5 h-3.5"></i> Print</button>
            <button onclick="window.print()" class="h-9 px-4 rounded-full bg-ink text-white text-[11.5px] font-bold flex items-center gap-1.5"><i data-lucide="download" class="w-3.5 h-3.5"></i> Download PDF</button>
        </div>
    </div>

    <div class="bg-white border border-line rounded-2xl shadow-card p-6 sm:p-10 mt-5 print:border-0 print:shadow-none print:p-0">
        <div class="flex flex-col sm:flex-row justify-between gap-6">
            <div>
                @include('partials.ui', ['ui' => 'logo'])
                <div class="text-[11px] font-bold uppercase tracking-wide mt-3">{{ setting('company_name') }}</div>
                <div class="text-[11px] text-muted whitespace-pre-line">{{ setting('company_address') }}</div>
                <div class="text-[11px] font-semibold mt-1">NPWP: {{ setting('npwp') }}</div>
                <div class="text-[11px] text-muted">{{ setting('support_email') }} · {{ setting('support_phone') }}</div>
            </div>
            <div class="sm:text-right">
                <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full {{ $booking->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                    {{ $booking->payment_status === 'paid' ? 'Paid in full' : ($booking->payment_status === 'dp_paid' ? 'DP Paid · '.$booking->paid_percent.'% Settled' : 'Awaiting Payment') }}
                </span>
                <div class="text-3xl font-bold tracking-tight mt-3">COMMERCIAL INVOICE</div>
                <div class="text-[12px] font-semibold">Tax &amp; Hardware Dispatch Voucher</div>
                <div class="bg-soft rounded-xl p-3 mt-4 text-[11.5px] space-y-1 text-left sm:ml-auto sm:w-60">
                    <div class="flex justify-between"><span class="text-muted">Invoice No.</span><b>{{ $booking->invoice_no }}</b></div>
                    <div class="flex justify-between"><span class="text-muted">Date Issued</span><span>{{ $booking->created_at->format('j M Y') }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Due Date</span><span>{{ $booking->start_date->format('j M Y') }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Booking Ref</span><b>#{{ $booking->code }}</b></div>
                </div>
            </div>
        </div>

        <div class="bg-soft rounded-xl p-5 mt-8 grid sm:grid-cols-2 gap-6 text-[11.5px]">
            <div>
                <div class="eyebrow text-muted">Billed To</div>
                <div class="font-semibold text-[14px] mt-1">{{ $booking->customer_name }}</div>
                @if ($booking->customer_company)<div class="font-semibold">{{ $booking->customer_company }}</div>@endif
                <div class="text-muted mt-1">{{ $booking->customer_email }}</div>
                <div class="text-muted">{{ $booking->customer_phone }}</div>
                <div class="text-muted">{{ $booking->user?->address }}</div>
            </div>
            <div>
                <div class="eyebrow text-muted">Event Delivery Logistics</div>
                <div class="font-semibold text-[14px] mt-1">{{ $booking->event_name }}</div>
                <div class="text-muted flex items-center gap-1"><i data-lucide="map-pin" class="w-3 h-3"></i> {{ $booking->venue_name }}</div>
                <div class="mt-1"><b>Production Window:</b> {{ $booking->start_date->format('j M Y') }} – {{ $booking->end_date->format('j M Y') }} ({{ $booking->days }} Rental Days)</div>
                <div><b>Site Liaison (PIC):</b> {{ $booking->pic_name }} ({{ $booking->pic_phone }})</div>
            </div>
        </div>

        <div class="flex justify-between items-end mt-8"><div class="eyebrow">Itemized Line Items</div><div class="text-[10.5px] text-muted">Currency: IDR (Rupiah)</div></div>
        <div class="overflow-x-auto">
            <table class="w-full text-[11.5px] mt-2 min-w-[640px]">
                <thead><tr class="bg-soft text-left eyebrow">
                    <th class="py-2.5 px-3 rounded-l-lg">Description</th><th class="px-2">Category</th><th class="px-2 text-center">Qty</th><th class="px-2 text-center">Duration</th><th class="px-2 text-right">Rate</th><th class="px-3 text-right rounded-r-lg">Subtotal</th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($lines as $l)
                        <tr>
                            <td class="py-3.5 px-3"><div class="font-semibold text-[13px]">{{ $l->name }}</div><div class="text-[10.5px] text-muted">{{ $l->description }}</div></td>
                            <td class="px-2"><span class="text-[9px] font-semibold uppercase bg-soft rounded px-1.5 py-0.5 text-muted">{{ $l->category ?: $l->item_type }}</span></td>
                            <td class="px-2 text-center">{{ $l->qty }} {{ $l->item_type === 'service' ? 'Trip' : ($l->unit === 'sesi' ? 'Session' : 'Units') }}</td>
                            <td class="px-2 text-center text-muted">{{ $l->item_type === 'service' ? 'Roundtrip' : ($l->unit === 'hari' ? $l->days.' Days' : 'Per '.ucfirst($l->unit ?? 'event')) }}</td>
                            <td class="px-2 text-right">{{ $l->rate ? rupiah($l->rate).' /'.($l->unit ?? '') : 'Included' }}</td>
                            <td class="px-3 text-right font-semibold">{{ $l->subtotal ? rupiah($l->subtotal) : 'Rp 0' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid sm:grid-cols-2 gap-6 mt-8">
            <div class="space-y-4">
                <div class="bg-soft rounded-xl p-4 text-[11.5px]">
                    <div class="eyebrow">Remittance & Bank Instructions</div>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="font-extrabold text-[14px] bg-white rounded px-2 py-1">{{ setting('bank_name', 'BCA') }}</span>
                        <div><div class="font-semibold">{{ setting('bank_account') }}</div><div class="text-muted">Virtual Account: {{ setting('bank_holder') }}</div></div>
                    </div>
                    @if ($booking->remaining)
                        <div class="mt-3"><b>Settlement Note:</b> Final balance ({{ rupiah($booking->remaining) }}) is scheduled upon soundcheck verification &amp; gear handover on {{ $booking->start_date->format('j F Y') }} at {{ $booking->venue_name }}.</div>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-[11px] px-2">
                    <span class="grid place-items-center w-10 h-10 rounded-full bg-soft"><i data-lucide="shield-check" class="w-4 h-4"></i></span>
                    <div><div class="font-semibold">{{ setting('site_name', 'NEXA') }} Central Billing &amp; Logistics Control</div><div class="text-muted">Digitally authorized on {{ $booking->created_at->format('j M Y, H:i') }} WIB</div></div>
                </div>
            </div>
            <div class="bg-soft rounded-xl p-4 text-[12px]">
                <div class="space-y-1.5">
                    <div class="flex justify-between"><span class="text-muted">Gross Equipment Subtotal</span><b>{{ rupiah($gross) }}</b></div>
                    @if ($booking->discount)<div class="flex justify-between"><span class="text-muted">Package Bundle Discount</span><span class="text-red-600">- {{ rupiah($booking->discount) }}</span></div>@endif
                    <div class="flex justify-between"><span class="text-muted">Freight, Rigging &amp; Transit</span><b>{{ rupiah($booking->logistics_fee) }}</b></div>
                </div>
                <div class="bg-white rounded-lg p-3 mt-3">
                    <div class="flex justify-between items-center"><span class="font-semibold">Grand Total</span><span class="text-2xl font-bold">{{ rupiah($booking->total) }}</span></div>
                    <div class="flex justify-between text-[11px] mt-1"><span class="text-muted">Paid to date</span><span>{{ rupiah($booking->amount_paid) }}</span></div>
                    @if ($dpPay)<div class="flex justify-between text-[11px]"><span class="text-muted">Transaction Ref:</span><span>{{ $dpPay->reference ?: '-' }}</span></div>@endif
                </div>
                <div class="flex justify-between items-end mt-3">
                    <div><div class="eyebrow">Remaining Balance Due</div><div class="text-[10.5px] text-muted">Due at venue handover</div></div>
                    <span class="text-xl font-bold">{{ rupiah($booking->remaining) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-soft rounded-xl p-4 mt-6 text-[11px]">
            <div class="font-semibold">Commercial Rental Terms &amp; Safety Compliance</div>
            <p class="text-muted mt-1 whitespace-pre-line">{{ setting('invoice_terms') }}</p>
        </div>
    </div>
</div>
@if (request('print'))
    @push('scripts')<script>window.addEventListener('load', () => setTimeout(() => window.print(), 600));</script>@endpush
@endif
@endsection
