<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Services\BookingCalendarSync;
use App\Services\GoogleCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kalender booking: menggabungkan booking dari database dengan event di Google Calendar GIE
 * (mis. meeting, survei lokasi, atau event yang dicatat tim langsung di Google).
 */
class CalendarController extends Controller
{
    /** Warna per status booking (dipakai kalender & legenda). */
    public const COLORS = [
        'pending' => '#f59e0b',
        'confirmed' => '#0b0b0c',
        'completed' => '#9ca3af',
        'google' => '#2563eb',
    ];

    public function __construct(private GoogleCalendar $google, private BookingCalendarSync $sync) {}

    public function index()
    {
        $tz = $this->tz();
        $today = now($tz)->startOfDay();
        $until = $today->copy()->addDays(60);

        // Event mendatang: booking yang belum selesai (termasuk yang sedang berjalan hari ini)
        $upcoming = Booking::where('status', '!=', 'cancelled')
            ->where('end_date', '>=', $today->toDateString())
            ->where('start_date', '<=', $until->toDateString())
            ->orderBy('start_date')->limit(15)->get()
            ->map(fn (Booking $b) => [
                'source' => 'booking',
                'title' => $b->event_name,
                'code' => $b->code,
                'start' => Carbon::parse($b->start_date->toDateString(), $tz),
                'when' => $b->date_range,
                'where' => $b->venue_name,
                'status' => $b->status,
                'url' => route('admin.bookings.show', $b),
                'synced' => (bool) $b->google_event_id,
                'sync_error' => $b->google_sync_error,
            ]);

        [$googleEvents, $googleError] = $this->googleEvents($today, $until);
        $upcoming = $upcoming->concat(collect($googleEvents)->map(fn ($e) => [
            'source' => 'google',
            'title' => $e['summary'] ?? '(Tanpa judul)',
            'code' => null,
            'start' => $this->googleStart($e),
            'when' => $this->googleWhen($e),
            'where' => $e['location'] ?? null,
            'status' => null,
            'url' => $e['htmlLink'] ?? null,
            'synced' => true,
            'sync_error' => null,
        ]))->sortBy('start')->take(15)->values();

        return view('admin.bookings.calendar', [
            'upcoming' => $upcoming,
            'configured' => $this->google->isConfigured(),
            'calendarId' => $this->google->calendarId(),
            'serviceEmail' => $this->google->serviceAccountEmail(),
            'googleError' => $googleError,
            'failedCount' => Booking::whereNotNull('google_sync_error')->count(),
            'pendingCount' => $this->sync->enabled() ? $this->sync->pending()->count() : 0,
            'colors' => self::COLORS,
        ]);
    }

    /** Sumber data FullCalendar: ?start=...&end=... (ISO 8601). */
    public function events(Request $request)
    {
        $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date', 'after:start']]);
        $tz = $this->tz();
        $from = Carbon::parse($request->query('start'))->setTimezone($tz);
        $to = Carbon::parse($request->query('end'))->setTimezone($tz);
        if ($from->diffInDays($to) > 400) {
            abort(422, 'Rentang terlalu panjang.');
        }

        $events = Booking::where('status', '!=', 'cancelled')
            ->where('start_date', '<', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get()
            ->map(fn (Booking $b) => [
                'id' => 'b-'.$b->id,
                'title' => $b->code.' · '.$b->event_name,
                'start' => $b->start_date->toDateString(),
                'end' => $b->end_date->copy()->addDay()->toDateString(), // FullCalendar: tanggal akhir eksklusif
                'allDay' => true,
                'url' => route('admin.bookings.show', $b),
                'color' => self::COLORS[$b->status] ?? self::COLORS['pending'],
                'extendedProps' => [
                    'source' => 'booking', 'status' => $b->status, 'venue' => $b->venue_name,
                    'customer' => $b->customer_name, 'synced' => (bool) $b->google_event_id,
                ],
            ]);

        [$googleEvents] = $this->googleEvents($from, $to);
        foreach ($googleEvents as $e) {
            $allDay = isset($e['start']['date']);
            $events->push([
                'id' => 'g-'.$e['id'],
                'title' => $e['summary'] ?? '(Tanpa judul)',
                'start' => $e['start']['date'] ?? $e['start']['dateTime'] ?? null,
                'end' => $e['end']['date'] ?? $e['end']['dateTime'] ?? null,
                'allDay' => $allDay,
                'url' => $e['htmlLink'] ?? null,
                'color' => self::COLORS['google'],
                'extendedProps' => ['source' => 'google', 'venue' => $e['location'] ?? null],
            ]);
        }

        return response()->json($events->values());
    }

    /** Sinkron manual: kirim ulang semua booking yang belum/ gagal tersinkron. */
    public function sync()
    {
        if (! $this->sync->enabled()) {
            return back()->withErrors(['calendar' => 'Google Calendar belum dikonfigurasi di server.']);
        }

        $result = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($this->sync->pending() as $booking) {
            $result[$this->sync->sync($booking)]++;
        }
        Cache::forever('google-calendar-events-version', (int) Cache::get('google-calendar-events-version', 1) + 1); // buang cache event

        $summary = "{$result['created']} dibuat, {$result['updated']} diperbarui, {$result['deleted']} dihapus, {$result['failed']} gagal";
        ActivityLog::record('calendar_synced', 'Sinkron Google Calendar: '.$summary, null, null, $result);

        $message = 'Sinkron Google Calendar selesai: '.$summary.'.';

        return $result['failed']
            ? back()->withErrors(['calendar' => $message.' Lihat kolom status di daftar event mendatang.'])
            : back()->with('success', $message);
    }

    // ------------------------------------------------------------------

    /**
     * Event Google di luar booking (event buatan aplikasi dilewati karena sudah tampil dari database).
     * Di-cache 2 menit supaya pindah-pindah bulan di kalender tidak membebani kuota API.
     *
     * @return array{0: array, 1: ?string} [daftar event, pesan error]
     */
    private function googleEvents(Carbon $from, Carbon $to): array
    {
        if (! $this->google->isConfigured()) {
            return [[], null];
        }

        try {
            $key = $this->cacheKeyPrefix().':'.$from->toDateString().':'.$to->toDateString();
            $items = Cache::remember($key, now()->addMinutes(2), fn () => $this->google->listEvents($from, $to));

            return [array_values(array_filter($items, fn ($e) => ($e['status'] ?? '') !== 'cancelled'
                && ($e['extendedProperties']['private']['source'] ?? null) !== GoogleCalendar::SOURCE)), null];
        } catch (Throwable $e) {
            Log::warning('Gagal membaca Google Calendar: '.$e->getMessage());

            return [[], 'Event dari Google Calendar tidak bisa dimuat saat ini.'];
        }
    }

    private function cacheKeyPrefix(): string
    {
        return 'google-calendar-events:'.Cache::get('google-calendar-events-version', 1);
    }

    private function googleStart(array $e): Carbon
    {
        return isset($e['start']['date'])
            ? Carbon::parse($e['start']['date'], $this->tz())
            : Carbon::parse($e['start']['dateTime'] ?? 'now')->setTimezone($this->tz());
    }

    private function googleWhen(array $e): string
    {
        $start = $this->googleStart($e);
        if (isset($e['start']['date'])) {
            $end = Carbon::parse($e['end']['date'] ?? $e['start']['date'], $this->tz())->subDay();

            return $end->gt($start) ? $start->format('d M').' – '.$end->format('d M Y') : $start->format('d M Y');
        }

        return $start->format('d M Y, H:i');
    }

    private function tz(): string
    {
        return config('services.google_calendar.timezone', 'Asia/Jakarta');
    }
}
