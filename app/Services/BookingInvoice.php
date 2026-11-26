<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class BookingInvoice
{
    public function send(int $bookingId): void
    {
        if (config('mail.default') === 'log') {
            return;
        }

        try {
            DB::transaction(function () use ($bookingId) {
                $booking = Booking::lockForUpdate()->find($bookingId);
                if (! $booking?->invoice_issued_at || $booking->invoice_emailed_at
                    || ! $booking->customer_email || ! in_array($booking->status, ['confirmed', 'completed'], true)) {
                    return;
                }

                if (! $booking->access_token) {
                    $booking->updateQuietly(['access_token' => Str::random(40)]);
                }
                $invoiceUrl = URL::signedRoute('bookings.emailed-invoice', ['booking' => $booking->code, 'token' => $booking->access_token]);
                Mail::send('emails.booking-invoice', compact('booking', 'invoiceUrl'), function ($message) use ($booking) {
                    $message->to($booking->customer_email)->subject('Invoice '.$booking->invoice_no.' · GIE Entertainment');
                });
                $booking->updateQuietly(['invoice_emailed_at' => now()]);
            });
        } catch (Throwable $error) {
            Log::warning('Pengiriman invoice akan dicoba ulang.', [
                'booking_id' => $bookingId,
                'exception' => $error::class,
            ]);
        }
    }
}
