<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian hardening login: blokir brute force, session timeout staf,
 * wajib ganti password bawaan, dan kebijakan password kuat.
 */
class LoginHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** Di test ini, perilaku asli seeder (staf wajib ganti password) dipertahankan. */
    protected bool $keepForcedPasswordChange = true;

    private function owner(): User
    {
        return User::where('role', 'owner')->firstOrFail();
    }

    public function test_account_is_locked_after_too_many_wrong_passwords(): void
    {
        $max = config('security.login_max_attempts');
        for ($i = 0; $i < $max; $i++) {
            $this->post('/login', ['email' => 'admin@nexa.test', 'password' => 'salah'.$i]);
        }

        // Password BENAR pun ditolak selama masa blokir.
        $this->post('/login', ['email' => 'admin@nexa.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('email'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'login_locked']);
    }

    public function test_lockout_expires_after_configured_time(): void
    {
        for ($i = 0; $i < config('security.login_max_attempts'); $i++) {
            $this->post('/login', ['email' => 'admin@nexa.test', 'password' => 'salah']);
        }
        $this->travel(config('security.login_lockout_seconds') + 1)->seconds();

        $this->post('/login', ['email' => 'admin@nexa.test', 'password' => 'password']);
        $this->assertAuthenticated();
    }

    public function test_wrong_email_and_wrong_password_give_same_message(): void
    {
        $this->post('/login', ['email' => 'tidakada@nexa.test', 'password' => 'x']);
        $msgUnknown = session('errors')->first('email');
        $this->post('/login', ['email' => 'operasional@nexa.test', 'password' => 'x']);
        $msgWrong = session('errors')->first('email');

        $this->assertSame($msgUnknown, $msgWrong, 'Pesan error tidak boleh membocorkan email mana yang terdaftar');
    }

    public function test_login_requests_are_limited_per_ip(): void
    {
        $limit = config('security.login_ip_per_minute');
        for ($i = 0; $i < $limit; $i++) {
            $this->post('/login', ['email' => "user{$i}@contoh.test", 'password' => 'x']);
        }
        $this->post('/login', ['email' => 'lain@contoh.test', 'password' => 'x'])->assertStatus(429);
    }

    public function test_staff_with_default_password_must_change_it_first(): void
    {
        $this->post('/login', ['email' => 'admin@nexa.test', 'password' => 'password'])
            ->assertRedirect(route('admin.password.edit'));

        // Halaman admin lain ditahan sampai password diganti.
        $this->get('/admin/bookings')->assertRedirect(route('admin.password.edit'));
        $this->get('/admin/password')->assertOk()->assertSee('password bawaan');
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $this->actingAs($this->owner());
        foreach (['pendek1!A', 'semuahurufkecil123!', 'TanpaAngka!!xx', 'TanpaSimbol123x', 'password'] as $weak) {
            $this->put('/admin/password', ['current_password' => 'password', 'password' => $weak, 'password_confirmation' => $weak])
                ->assertSessionHasErrors('password');
        }
        $this->put('/admin/password', ['current_password' => 'salah', 'password' => 'GieKuat#2026', 'password_confirmation' => 'GieKuat#2026'])
            ->assertSessionHasErrors('current_password');
    }

    public function test_strong_password_change_unlocks_admin_panel(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)
            ->put('/admin/password', ['current_password' => 'password', 'password' => 'GieKuat#2026', 'password_confirmation' => 'GieKuat#2026'])
            ->assertRedirect(route('admin.dashboard'));

        $owner->refresh();
        $this->assertFalse($owner->must_change_password);
        $this->assertNotNull($owner->password_changed_at);
        $this->get('/admin/bookings')->assertOk();
    }

    public function test_password_set_by_owner_for_other_user_is_temporary(): void
    {
        $owner = $this->owner();
        $owner->forceFill(['must_change_password' => false])->save();
        $staff = User::where('role', 'finance')->first();
        $staff->forceFill(['must_change_password' => false])->save();

        $this->actingAs($owner)->put("/admin/users/{$staff->id}", [
            'name' => $staff->name, 'email' => $staff->email, 'role' => 'finance', 'password' => 'Sementara123',
        ]);

        $this->assertTrue($staff->fresh()->must_change_password);
    }

    public function test_idle_staff_session_is_logged_out(): void
    {
        $owner = $this->owner();
        $owner->forceFill(['must_change_password' => false])->save();

        $this->actingAs($owner)->get('/admin')->assertOk();
        $this->travel(config('security.admin_idle_minutes') + 1)->minutes();

        $this->get('/admin')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue(ActivityLog::where(['action' => 'session_timeout', 'user_id' => $owner->id])->exists());
    }

    public function test_active_staff_session_is_not_logged_out(): void
    {
        $owner = $this->owner();
        $owner->forceFill(['must_change_password' => false])->save();

        $this->actingAs($owner)->get('/admin')->assertOk();
        $this->travel(config('security.admin_idle_minutes') - 1)->minutes();
        $this->get('/admin')->assertOk();
        $this->travel(config('security.admin_idle_minutes') - 1)->minutes();
        $this->get('/admin')->assertOk();
    }

    public function test_staff_cannot_use_remember_me(): void
    {
        $this->post('/login', ['email' => 'operasional@nexa.test', 'password' => 'password', 'remember' => 'on']);
        $this->assertNull(User::where('email', 'operasional@nexa.test')->value('remember_token'));

        $this->post('/logout');
        $this->post('/login', ['email' => 'customer@example.com', 'password' => 'password', 'remember' => 'on']);
        $this->assertNotNull(User::where('email', 'customer@example.com')->value('remember_token'));
    }
}
