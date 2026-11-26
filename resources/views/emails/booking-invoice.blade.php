<p>Halo {{ $booking->customer_name }},</p>
<p>Pembayaran untuk {{ $booking->event_name }} sudah dikonfirmasi.</p>
<ul>
    <li>Invoice {{ $booking->invoice_no }} · {{ $booking->payment_status === 'paid' ? 'Lunas' : 'DP Dibayar' }}</li>
    <li>Total tagihan {{ rupiah($booking->total) }}</li>
    <li>Sudah dibayar {{ rupiah($booking->amount_paid) }}</li>
    <li>Sisa tagihan {{ rupiah($booking->remaining) }}</li>
</ul>
<p><a href="{{ $invoiceUrl }}">Buka invoice</a></p>
<p>Terima kasih,<br>GIE Entertainment</p>
