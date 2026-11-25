<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjaga Google Calendar tetap sama dengan data booking.
 *
 * - Booking confirmed/completed  → event dibuat / diperbarui
 * - Booking pending/cancelled    → event dihapus (jika pernah dibuat)
 * - Booking dihapus              → event dihapus
 *
 * Kegagalan (internet putus, kuota Google, kalender belum dibagikan) tidak menggagalkan
 * penyimpanan booking: pesan error disimpan di booking dan dicoba ulang oleh
 * perintah terjadwal `calendar:sync`.
 */
class BookingCalendarSync
{
    /** Field booking yang memengaruhi isi event; perubahan field lain (mis. pembayaran) tidak memicu sinkron. */
    public const WATCHED = ['event_name', 'start_date', 'end_date', 'venue_name', 'venue_address', 'status', 'stage',
        'expected_pax', 'pic_name', 'customer_name', 'customer_company'];

    public function __construct(private GoogleCalendar $google) {}

    public function enabled(): bool
    {
        return $this->google->isConfigured();
    }

    public function shouldHaveEvent(Booking $booking): bool
    {
        return in_array($booking->status, config('services.google_calendar.sync_statuses', []), true);
    }

    /** Sinkronkan satu booking. Mengembalikan 'created'|'updated'|'deleted'|'skipped'|'failed'. */
    public function sync(Booking $booking): string
    {
        if (! $this->enabled()) {
            return 'skipped';
        }

        try {
            if ($this->shouldHaveEvent($booking)) {
                $hadEvent = (bool) $booking->google_event_id;
                $id = $this->google->upsertBookingEvent($booking);
                $this->mark($booking, ['google_event_id' => $id, 'google_synced_at' => now(), 'google_sync_error' => null]);

                return $hadEvent ? 'updated' : 'created';
            }

            if ($booking->google_event_id) {
                $this->google->deleteEvent($booking->google_event_id);
                $this->mark($booking, ['google_event_id' => null, 'google_synced_at' => now(), 'google_sync_error' => null]);

                return 'deleted';
            }

            return 'skipped';
        } catch (Throwable $e) {
            Log::warning('Sinkron Google Calendar gagal untuk '.$booking->code.': '.$e->getMessage());
            $this->mark($booking, ['google_sync_error' => mb_strimwidth($e->getMessage(), 0, 250, '…')]);

            return 'failed';
        }
    }

    /** Dipanggil setelah booking dihapus dari database. */
    public function forget(?string $eventId): void
    {
        if (! $eventId || ! $this->enabled()) {
            return;
        }
        try {
            $this->google->deleteEvent($eventId);
        } catch (Throwable $e) {
            Log::warning("Gagal menghapus event Google {$eventId}: ".$e->getMessage());
        }
    }

    /**
     * Booking yang perlu disinkronkan ulang: belum punya event, berubah sejak sinkron terakhir,
     * gagal sebelumnya, atau sudah dibatalkan tapi event-nya masih ada.
     */
    public function pending()
    {
        $statuses = config('services.google_calendar.sync_statuses', []);

        return Booking::query()
            ->where('end_date', '>=', now()->subDays(30)->toDateString()) // event lama tidak perlu diurus
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereIn('status', $statuses)->where(fn ($x) => $x
                    ->whereNull('google_event_id')
                    ->orWhereNotNull('google_sync_error')
                    ->orWhereNull('google_synced_at')
                    ->orWhereColumn('updated_at', '>', 'google_synced_at')))
                ->orWhere(fn ($w) => $w->whereNotIn('status', $statuses)->whereNotNull('google_event_id')))
            ->orderBy('start_date')
            ->get();
    }

    /** Simpan status sinkron tanpa memicu audit log / observer lagi (bukan perubahan oleh pengguna). */
    private function mark(Booking $booking, array $values): void
    {
        $booking->forceFill($values);
        $booking->timestamps = false;
        $booking->saveQuietly();
        $booking->timestamps = true;
    }
}
