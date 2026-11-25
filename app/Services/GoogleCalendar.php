<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien Google Calendar API v3 memakai Service Account (OAuth 2.0 JWT bearer, RS256).
 * Ditulis tanpa library google/apiclient supaya dependensi tetap kecil:
 * cukup openssl bawaan PHP + HTTP client Laravel.
 *
 * Alur: kunci privat service account → tanda tangani JWT → tukar ke access token (berlaku 1 jam,
 * di-cache 55 menit) → panggil Calendar API.
 */
class GoogleCalendar
{
    private const SCOPE = 'https://www.googleapis.com/auth/calendar.events';
    private const API = 'https://www.googleapis.com/calendar/v3';

    /** Penanda di event supaya event buatan aplikasi bisa dibedakan dari event yang dibuat manual. */
    public const SOURCE = 'gie-app';

    public function isConfigured(): bool
    {
        return filled($this->calendarId()) && is_readable((string) config('services.google_calendar.credentials'));
    }

    public function calendarId(): ?string
    {
        return config('services.google_calendar.calendar_id');
    }

    /** Email service account (ditampilkan di halaman kalender untuk panduan berbagi kalender). */
    public function serviceAccountEmail(): ?string
    {
        try {
            return $this->credentials()['client_email'] ?? null;
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Ambil event di rentang waktu tertentu.
     *
     * @return array<int, array> item event mentah dari Google
     */
    public function listEvents(Carbon $from, Carbon $to): array
    {
        $items = [];
        $pageToken = null;
        do {
            $res = $this->request('get', '/calendars/'.rawurlencode($this->calendarId()).'/events', array_filter([
                'timeMin' => $from->toRfc3339String(),
                'timeMax' => $to->toRfc3339String(),
                'singleEvents' => 'true',   // event berulang dipecah per kejadian
                'orderBy' => 'startTime',
                'maxResults' => 250,
                'pageToken' => $pageToken,
            ]));
            $items = array_merge($items, $res['items'] ?? []);
            $pageToken = $res['nextPageToken'] ?? null;
        } while ($pageToken && count($items) < 1000);

        return $items;
    }

    /** Buat atau perbarui event untuk sebuah booking. Mengembalikan ID event Google. */
    public function upsertBookingEvent(Booking $booking): string
    {
        $body = $this->eventBody($booking);
        $path = '/calendars/'.rawurlencode($this->calendarId()).'/events';

        if ($booking->google_event_id) {
            try {
                $res = $this->request('put', $path.'/'.rawurlencode($booking->google_event_id), [], $body);

                return $res['id'];
            } catch (GoogleCalendarException $e) {
                // Event terhapus manual di Google → buat ulang.
                if (! in_array($e->status, [404, 410], true)) {
                    throw $e;
                }
            }
        }

        return $this->request('post', $path, [], $body)['id'];
    }

    public function deleteEvent(string $eventId): void
    {
        try {
            $this->request('delete', '/calendars/'.rawurlencode($this->calendarId()).'/events/'.rawurlencode($eventId));
        } catch (GoogleCalendarException $e) {
            if (! in_array($e->status, [404, 410], true)) { // sudah tidak ada = tujuan tercapai
                throw $e;
            }
        }
    }

    /** Isi event Google untuk sebuah booking (event seharian, tanggal selesai eksklusif). */
    public function eventBody(Booking $booking): array
    {
        $lines = array_filter([
            'Kode booking: '.$booking->code,
            'Status: '.(Booking::STATUSES[$booking->status] ?? $booking->status),
            'Customer: '.$booking->customer_name.($booking->customer_company ? ' ('.$booking->customer_company.')' : ''),
            $booking->expected_pax ? 'Perkiraan tamu: '.$booking->expected_pax.' pax' : null,
            $booking->pic_name ? 'PIC lokasi: '.$booking->pic_name : null,
            'Tahap: '.(Booking::STAGES[$booking->stage]['label'] ?? '-'),
            '',
            'Detail: '.route('admin.bookings.show', $booking),
        ], fn ($l) => $l !== null);

        return [
            'summary' => '['.$booking->code.'] '.$booking->event_name,
            'location' => trim($booking->venue_name.($booking->venue_address ? ', '.$booking->venue_address : '')),
            'description' => implode("\n", $lines),
            'start' => ['date' => $booking->start_date->toDateString()],
            'end' => ['date' => $booking->end_date->copy()->addDay()->toDateString()],
            'colorId' => $booking->status === 'completed' ? '8' : '10', // abu-abu / hijau
            'transparency' => 'opaque',
            'extendedProperties' => ['private' => [
                'source' => self::SOURCE,
                'booking_id' => (string) $booking->id,
                'booking_code' => $booking->code,
            ]],
        ];
    }

    // ------------------------------------------------------------------

    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        // Coba ulang hanya untuk gangguan jaringan / server Google (5xx), bukan untuk kesalahan request (4xx).
        $req = Http::withToken($this->accessToken())->acceptJson()->timeout(10)->retry(2, 300,
            fn ($e) => $e instanceof ConnectionException || ($e instanceof RequestException && $e->response->serverError()),
            throw: false);
        $url = self::API.$path.($query ? '?'.http_build_query($query) : '');
        $res = $body === null ? $req->{$method}($url) : $req->{$method}($url, $body);

        if ($res->failed()) {
            if ($res->status() === 401) {
                Cache::forget($this->tokenCacheKey()); // token kedaluwarsa/dicabut, ambil baru di percobaan berikutnya
            }
            throw new GoogleCalendarException($res->status(), $res->json('error.message') ?? $res->body());
        }

        return $res->json() ?? [];
    }

    private function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(55), function () {
            $cred = $this->credentials();
            $now = time();
            $jwt = $this->jwt([
                'iss' => $cred['client_email'],
                'scope' => self::SCOPE,
                'aud' => $cred['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $cred['private_key']);

            $res = Http::asForm()->timeout(10)->post($cred['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
            if ($res->failed() || ! $res->json('access_token')) {
                throw new GoogleCalendarException($res->status(), 'Gagal mendapat token Google: '.($res->json('error_description') ?? $res->body()));
            }

            return $res->json('access_token');
        });
    }

    private function jwt(array $claims, string $privateKey): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $input = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode($claims));
        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Kunci privat service account tidak valid.');
        }

        return $input.'.'.$b64($signature);
    }

    private function credentials(): array
    {
        $path = (string) config('services.google_calendar.credentials');
        if (! is_readable($path)) {
            throw new RuntimeException('File kredensial Google Calendar tidak ditemukan.');
        }
        $cred = json_decode((string) file_get_contents($path), true);
        if (! is_array($cred) || empty($cred['client_email']) || empty($cred['private_key'])) {
            throw new RuntimeException('File kredensial Google Calendar bukan kunci service account yang valid.');
        }

        return $cred;
    }

    private function tokenCacheKey(): string
    {
        return 'google-calendar-token:'.md5((string) config('services.google_calendar.credentials'));
    }
}
