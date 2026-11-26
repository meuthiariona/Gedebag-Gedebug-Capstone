<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_customer_feedback_is_linked_to_completed_booking_and_visible_to_staff(): void
    {
        $booking = Booking::whereStatus('completed')->firstOrFail();
        $url = route('bookings.feedback.store', $booking);

        $this->actingAs($booking->user)->get(route('bookings.show', $booking))
            ->assertOk()->assertSee('Kirim feedback');
        $this->post($url, ['rating' => 6])->assertSessionHasErrors('rating');
        $this->post($url, ['rating' => 5, 'comment' => 'Peralatan sesuai pesanan dan datang tepat waktu.'])
            ->assertRedirect(route('bookings.show', $booking));
        $this->post($url, ['rating' => 1, 'comment' => 'Duplikat'])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseCount('booking_feedback', 1);
        $this->assertDatabaseHas('booking_feedback', ['booking_id' => $booking->id, 'rating' => 5]);
        $this->get(route('admin.feedback.index'))->assertForbidden();

        $this->actingAs(User::whereRole('owner')->firstOrFail())
            ->get(route('admin.feedback.index'))
            ->assertOk()->assertSee($booking->code)->assertSee('Peralatan sesuai pesanan');
        $this->get(route('admin.feedback.index', ['month' => now()->subMonthNoOverflow()->format('Y-m')]))
            ->assertOk()->assertDontSee('Peralatan sesuai pesanan');
    }

    public function test_guest_can_review_only_a_completed_booking_they_can_access(): void
    {
        $booking = Booking::whereStatus('completed')->firstOrFail();
        $booking->update(['user_id' => null, 'wa_contacted_at' => null]);
        $url = route('bookings.feedback.store', $booking);

        $this->post($url, ['rating' => 4])->assertForbidden();
        $this->get($booking->summary_url)
            ->assertRedirect(route('bookings.show', $booking))
            ->assertCookie('guest_orders', json_encode([$booking->id]));
        $this->withCookie('guest_orders', json_encode([$booking->id]));

        $booking->update(['status' => 'confirmed']);
        $this->post($url, ['rating' => 4])->assertForbidden();
        $booking->update(['status' => 'completed']);

        $this->post($url, ['rating' => 4])->assertRedirect(route('bookings.show', $booking));
        $this->assertDatabaseHas('booking_feedback', ['booking_id' => $booking->id, 'rating' => 4]);
    }
}
