<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Pengujian audit trail: setiap perubahan data penting tercatat lengkap
 * (siapa, kapan, sebelum/sesudah) dan log tidak bisa dimanipulasi.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function role(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    public function test_seeding_does_not_fill_audit_log(): void
    {
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_discount_change_records_who_and_before_after(): void
    {
        $finance = $this->role('finance');
        $booking = Booking::where('amount_paid', 0)->first() ?? Booking::first();
        $before = (int) $booking->discount;

        $this->actingAs($finance)->put("/admin/bookings/{$booking->id}", [
            'discount' => '1000', 'dp_percent' => $booking->dp_percent,
        ])->assertSessionHasNoErrors();

        $log = ActivityLog::where('subject_type', 'Booking')->where('subject_id', $booking->id)
            ->where('action', 'updated')->whereNotNull('new_values->discount')->firstOrFail();

        $this->assertSame($finance->id, $log->user_id);
        $this->assertSame('finance', $log->user_role);
        $this->assertSame($before, (int) $log->old_values['discount']);
        $this->assertSame(1000, (int) $log->new_values['discount']);
        $this->assertNotNull($log->ip_address);
    }

    public function test_deleted_payment_is_kept_in_log(): void
    {
        $payment = Payment::first();
        $this->actingAs($this->role('owner'))->delete("/admin/payments/{$payment->id}");

        $log = ActivityLog::where(['action' => 'deleted', 'subject_type' => 'Payment', 'subject_id' => $payment->id])->firstOrFail();
        $this->assertSame((int) $payment->amount, (int) $log->old_values['amount'], 'Nominal pembayaran yang dihapus tetap terekam');
    }

    public function test_setting_change_by_owner_is_logged(): void
    {
        $owner = $this->role('owner');
        $this->actingAs($owner);
        $key = Setting::first()->key;
        Setting::put($key, 'NILAI-BARU-123');

        $log = ActivityLog::where(['subject_type' => 'Setting', 'action' => 'updated'])->latest('id')->firstOrFail();
        $this->assertSame('NILAI-BARU-123', $log->new_values['value']);
        $this->assertSame($owner->id, $log->user_id);
    }

    public function test_password_is_never_stored_in_log(): void
    {
        $owner = $this->role('owner');
        $target = $this->role('finance');
        $this->actingAs($owner)->put("/admin/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email, 'role' => 'finance', 'password' => 'RahasiaBaru123',
        ]);

        $log = ActivityLog::where(['subject_type' => 'User', 'subject_id' => $target->id, 'action' => 'updated'])->firstOrFail();
        $this->assertStringNotContainsString('RahasiaBaru123', json_encode($log->toArray()));
        $this->assertStringContainsString('disamarkan', $log->new_values['password']);
    }

    public function test_failed_and_successful_logins_are_logged(): void
    {
        $this->post('/login', ['email' => 'admin@nexa.test', 'password' => 'salah']);
        $this->post('/login', ['email' => 'admin@nexa.test', 'password' => 'password']);

        $this->assertDatabaseHas('activity_logs', ['action' => 'login_failed', 'description' => 'Login gagal untuk email: admin@nexa.test']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'user_id' => $this->role('owner')->id]);
    }

    public function test_log_entries_cannot_be_edited_or_deleted(): void
    {
        $log = ActivityLog::record('updated', 'contoh');

        $this->assertThrows(fn () => $log->update(['description' => 'dipalsukan']), LogicException::class);
        $this->assertThrows(fn () => $log->delete(), LogicException::class);
        $this->assertSame('contoh', $log->fresh()->description);
    }

    public function test_only_owner_can_open_audit_log(): void
    {
        ActivityLog::record('updated', 'contoh', null, ['discount' => 0, 'meta' => ['a' => 1]], ['discount' => 5000, 'meta' => null]);

        $this->actingAs($this->role('owner'))->get('/admin/activity-logs')->assertOk()->assertSee('contoh')->assertSee('5000');
        $this->actingAs($this->role('owner'))->get('/admin/activity-logs?action=login&subject=Booking&q=x&from=2026-01-01')->assertOk();
        $this->actingAs($this->role('owner'))->get('/admin/activity-logs/export')->assertOk();
        $this->actingAs($this->role('finance'))->get('/admin/activity-logs')->assertForbidden();
        $this->actingAs($this->role('operations'))->get('/admin/activity-logs')->assertForbidden();
        $this->actingAs($this->role('operations'))->get('/admin/activity-logs/export')->assertForbidden();
    }
}
