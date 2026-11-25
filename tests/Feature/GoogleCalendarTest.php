<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integrasi Google Calendar: halaman kalender, gabungan event, dan sinkron booking → Google.
 * Google API dipalsukan (Http::fake), jadi test tidak butuh internet maupun akun Google.
 */
class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private string $credentialsPath;

    /** Respons palsu Google; bisa diganti per test. */
    private array $googleResponses = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer(); // sinkron dijalankan langsung (di produksi: setelah respons terkirim)

        // Kunci service account palsu (RSA asli supaya penandatanganan JWT benar-benar diuji)
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'gcal');
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'client_email' => 'gie-calendar@gie-capstone.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config([
            'services.google_calendar.credentials' => $this->credentialsPath,
            'services.google_calendar.calendar_id' => 'gie@group.calendar.google.com',
        ]);
        Cache::flush();

        $this->googleResponses = [
            'list' => Http::response(['items' => [
                [ // event yang dibuat tim langsung di Google Calendar
                    'id' => 'manual1', 'status' => 'confirmed', 'summary' => 'Survei lokasi Trans Convention',
                    'start' => ['dateTime' => now()->addDays(3)->setTime(10, 0)->toRfc3339String()],
                    'end' => ['dateTime' => now()->addDays(3)->setTime(12, 0)->toRfc3339String()],
                    'htmlLink' => 'https://calendar.google.com/event?eid=manual1', 'location' => 'Bandung',
                ],
                [ // event buatan aplikasi: tidak boleh tampil dobel
                    'id' => 'appevt', 'status' => 'confirmed', 'summary' => '[EVT-1] Duplikat',
                    'start' => ['date' => now()->addDays(5)->toDateString()], 'end' => ['date' => now()->addDays(6)->toDateString()],
                    'extendedProperties' => ['private' => ['source' => 'gie-app', 'booking_id' => '1']],
                ],
            ]]),
            'create' => Http::response(['id' => 'evt123'], 200),
            'update' => Http::response(['id' => 'evt123'], 200),
            'delete' => Http::response(null, 204),
        ];

        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_starts_with($url, 'https://oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]);
            }
            if (str_contains($url, '/calendar/v3/calendars/')) {
                return match ($request->method()) {
                    'GET' => $this->googleResponses['list'],
                    'POST' => $this->googleResponses['create'],
                    'PUT' => $this->googleResponses['update'],
                    'DELETE' => $this->googleResponses['delete'],
                };
            }

            return Http::response('unexpected', 500);
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);
        parent::tearDown();
    }

    private function role(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    private function pendingBooking(): Booking
    {
        return Booking::where('status', 'pending')->firstOrFail();
    }

    private function updatePayload(Booking $b, array $override = []): array
    {
        return array_merge([
            'event_name' => $b->event_name, 'start_date' => $b->start_date->toDateString(), 'end_date' => $b->end_date->toDateString(),
            'venue_name' => $b->venue_name, 'status' => $b->status, 'stage' => $b->stage,
        ], $override);
    }

    public function test_calendar_page_is_available_to_staff_only(): void
    {
        foreach (['owner', 'finance', 'operations'] as $role) {
            $this->actingAs($this->role($role))->get('/admin/bookings/calendar')
                ->assertOk()->assertSee('Event mendatang')->assertSee('Terhubung ke Google Calendar');
        }
        $this->actingAs($this->role('customer'))->get('/admin/bookings/calendar')->assertForbidden();
        $this->actingAs($this->role('customer'))->get('/admin/bookings/calendar/events?start=2026-11-01&end=2026-12-01')->assertForbidden();
    }

    public function test_upcoming_list_shows_bookings_and_google_events(): void
    {
        $next = Booking::where('status', '!=', 'cancelled')->where('end_date', '>=', today())->orderBy('start_date')->first();

        $this->actingAs($this->role('operations'))->get('/admin/bookings/calendar')
            ->assertSee($next->event_name)
            ->assertSee('Survei lokasi Trans Convention')
            ->assertDontSee('Duplikat');
    }

    public function test_events_feed_merges_bookings_and_google_without_duplicates(): void
    {
        $from = now()->subMonths(2)->toDateString();
        $to = now()->addMonths(3)->toDateString();

        $events = collect($this->actingAs($this->role('owner'))
            ->getJson("/admin/bookings/calendar/events?start={$from}&end={$to}")
            ->assertOk()->json());

        $this->assertTrue($events->contains('id', 'g-manual1'), 'event manual Google harus tampil');
        $this->assertFalse($events->contains('id', 'g-appevt'), 'event buatan aplikasi tidak boleh dobel');
        $this->assertGreaterThan(0, $events->where('extendedProps.source', 'booking')->count());

        // Tanggal akhir booking dikirim eksklusif (+1 hari) sesuai format FullCalendar
        $b = Booking::where('status', '!=', 'cancelled')->first();
        $item = $events->firstWhere('id', 'b-'.$b->id);
        $this->assertSame($b->end_date->copy()->addDay()->toDateString(), $item['end']);
    }

    public function test_confirming_booking_creates_google_event(): void
    {
        $b = $this->pendingBooking();

        $this->actingAs($this->role('operations'))
            ->put("/admin/bookings/{$b->id}", $this->updatePayload($b, ['status' => 'confirmed']))
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_contains($r->url(), '/calendars/'.rawurlencode('gie@group.calendar.google.com').'/events')
            && $r['summary'] === '['.$b->code.'] '.$b->event_name
            && $r['start']['date'] === $b->start_date->toDateString()
            && $r['end']['date'] === $b->end_date->copy()->addDay()->toDateString()
            && $r['extendedProperties']['private']['booking_id'] === (string) $b->id
            && $r->hasHeader('Authorization', 'Bearer fake-token'));

        $b->refresh();
        $this->assertSame('evt123', $b->google_event_id);
        $this->assertNull($b->google_sync_error);

        // Kolom sinkron tidak mengotori audit trail (yang tercatat hanya perubahan oleh pengguna)
        $this->assertFalse(ActivityLog::where('subject_type', 'Booking')->where('subject_id', $b->id)
            ->where('description', 'like', '%google%')->exists());
    }

    public function test_payment_only_changes_do_not_call_google(): void
    {
        $b = Booking::where('status', 'confirmed')->firstOrFail();
        Http::fake(); // reset catatan request
        $b->update(['amount_paid' => $b->amount_paid + 1000]);

        Http::assertNothingSent();
    }

    public function test_cancelling_booking_deletes_google_event(): void
    {
        $b = Booking::where('status', 'confirmed')->firstOrFail();
        $b->forceFill(['google_event_id' => 'evt999'])->saveQuietly();

        $this->actingAs($this->role('operations'))
            ->put("/admin/bookings/{$b->id}", $this->updatePayload($b, ['status' => 'cancelled']));

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/events/evt999'));
        $this->assertNull($b->fresh()->google_event_id);
    }

    public function test_deleting_booking_deletes_google_event(): void
    {
        $b = Booking::where('status', 'confirmed')->firstOrFail();
        $b->forceFill(['google_event_id' => 'evt777'])->saveQuietly();

        $this->actingAs($this->role('owner'))->delete("/admin/bookings/{$b->id}");

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/events/evt777'));
    }

    public function test_failed_sync_is_recorded_and_retried_by_scheduler(): void
    {
        $b = $this->pendingBooking();
        $this->googleResponses['create'] = Http::response(['error' => ['message' => 'Backend Error']], 500);

        $this->actingAs($this->role('operations'))
            ->put("/admin/bookings/{$b->id}", $this->updatePayload($b, ['status' => 'confirmed']))
            ->assertSessionHasNoErrors(); // booking tetap tersimpan walau Google gagal

        $b->refresh();
        $this->assertSame('confirmed', $b->status);
        $this->assertNull($b->google_event_id);
        $this->assertStringContainsString('Backend Error', $b->google_sync_error);

        // Google pulih → perintah terjadwal mengirim ulang
        $this->googleResponses['create'] = Http::response(['id' => 'evt-retry'], 200);
        $this->artisan('calendar:sync')->assertSuccessful();

        $b->refresh();
        $this->assertSame('evt-retry', $b->google_event_id);
        $this->assertNull($b->google_sync_error);
        $this->assertTrue(ActivityLog::where('action', 'calendar_synced')->exists());
    }

    public function test_manual_sync_requires_bookings_manage(): void
    {
        $this->actingAs($this->role('finance'))->post('/admin/bookings/calendar/sync')->assertForbidden();

        $this->actingAs($this->role('operations'))->post('/admin/bookings/calendar/sync')
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(Booking::whereIn('status', ['confirmed', 'completed'])->count(),
            Booking::whereNotNull('google_event_id')->count());
    }

    public function test_without_configuration_no_google_calls_are_made(): void
    {
        config(['services.google_calendar.calendar_id' => null]);
        Http::fake();
        $b = $this->pendingBooking();

        $this->actingAs($this->role('operations'))
            ->put("/admin/bookings/{$b->id}", $this->updatePayload($b, ['status' => 'confirmed']));
        $this->actingAs($this->role('owner'))->get('/admin/bookings/calendar')
            ->assertOk()->assertSee('Google Calendar belum terhubung');

        Http::assertNothingSent();
    }
}
