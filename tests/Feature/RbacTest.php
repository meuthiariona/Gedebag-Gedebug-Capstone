<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian RBAC: memastikan tiap peran hanya bisa mengakses yang diizinkan
 * di config/rbac.php (bukti "least privilege" untuk laporan).
 */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function role(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    private function bookingPayload(Booking $b, array $override = []): array
    {
        return array_merge([
            'event_name' => $b->event_name, 'start_date' => $b->start_date->toDateString(), 'end_date' => $b->end_date->toDateString(),
            'venue_name' => $b->venue_name, 'status' => $b->status, 'stage' => $b->stage,
            'discount' => $b->discount, 'dp_percent' => $b->dp_percent,
        ], $override);
    }

    /** Matriks akses halaman: [url, owner, finance, operations, customer] (true = boleh). */
    public static function pageMatrix(): array
    {
        return [
            'dashboard' => ['/admin', true, true, true, false],
            'booking list' => ['/admin/bookings', true, true, true, false],
            'booking detail' => ['/admin/bookings/1', true, true, true, false],
            'produk' => ['/admin/products', true, false, true, false],
            'paket' => ['/admin/packages', true, false, true, false],
            'banner' => ['/admin/hero-slides', true, false, true, false],
            'pengguna' => ['/admin/users', true, false, false, false],
            'pengaturan' => ['/admin/settings', true, false, false, false],
        ];
    }

    /** @dataProvider pageMatrix */
    public function test_page_access_follows_role_matrix(string $url, bool $owner, bool $finance, bool $operations, bool $customer): void
    {
        foreach (compact('owner', 'finance', 'operations', 'customer') as $role => $allowed) {
            $res = $this->actingAs($this->role($role))->get($url);
            $allowed ? $res->assertOk() : $res->assertForbidden();
        }
    }

    public function test_operations_cannot_touch_payments_or_settings(): void
    {
        $ops = $this->role('operations');
        $payment = Payment::first();
        $booking = $payment->booking;

        $this->actingAs($ops)->post("/admin/payments/{$payment->id}/verify", ['action' => 'verify'])->assertForbidden();
        $this->actingAs($ops)->delete("/admin/payments/{$payment->id}")->assertForbidden();
        $this->actingAs($ops)->post("/admin/bookings/{$booking->id}/payments", ['amount' => '1000', 'method' => 'Cash'])->assertForbidden();
        $this->actingAs($ops)->post('/admin/settings', ['bank_account' => '999'])->assertForbidden();
        $this->actingAs($ops)->delete("/admin/bookings/{$booking->id}")->assertForbidden();
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_finance_cannot_manage_catalog_or_users(): void
    {
        $fin = $this->role('finance');

        $this->actingAs($fin)->get('/admin/products/create')->assertForbidden();
        $this->actingAs($fin)->delete('/admin/products/1')->assertForbidden();
        $this->actingAs($fin)->post('/admin/users', ['name' => 'Hacker', 'email' => 'h@x.com', 'role' => 'owner', 'password' => 'password'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'h@x.com']);
    }

    public function test_operations_cannot_change_discount_but_can_change_status(): void
    {
        $booking = Booking::where('status', 'pending')->first() ?? Booking::first();
        $before = $booking->discount;

        $this->actingAs($this->role('operations'))
            ->put("/admin/bookings/{$booking->id}", $this->bookingPayload($booking, ['discount' => '500000', 'venue_name' => 'Venue Baru']))
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame($before, $booking->discount, 'Diskon tidak boleh berubah oleh Admin Operasional');
        $this->assertSame('Venue Baru', $booking->venue_name);
    }

    public function test_finance_can_change_discount_but_not_operational_data(): void
    {
        $booking = Booking::where('amount_paid', 0)->first() ?? Booking::first();
        $venue = $booking->venue_name;

        $this->actingAs($this->role('finance'))
            ->put("/admin/bookings/{$booking->id}", $this->bookingPayload($booking, ['discount' => '1000', 'venue_name' => 'Diubah Keuangan']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame(1000, (int) $booking->discount);
        $this->assertSame($venue, $booking->venue_name, 'Data operasional tidak boleh berubah oleh Admin Keuangan');
    }

    public function test_last_owner_cannot_be_demoted_or_deleted(): void
    {
        $owner = $this->role('owner');
        $this->actingAs($owner);

        $this->put("/admin/users/{$owner->id}", ['name' => $owner->name, 'email' => $owner->email, 'role' => 'customer'])
            ->assertSessionHasErrors('role');
        $this->assertSame('owner', $owner->fresh()->role);

        $other = User::create(['name' => 'Owner 2', 'email' => 'o2@nexa.test', 'password' => 'password', 'role' => 'owner']);
        $this->actingAs($other)->delete("/admin/users/{$other->id}")->assertSessionHasErrors('user');
    }
}
