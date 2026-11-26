<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Services\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ErpBookingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function requestData(array $rows, Carbon $date): array
    {
        return [
            'customer_name' => 'Customer ERP',
            'customer_email' => 'customer-erp@example.com',
            'customer_phone' => '081211112333',
            'event_name' => 'Acara ERP',
            'venue_name' => 'Hall A',
            'venue_address' => 'Jl. Test 1',
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'pic_name' => 'PIC',
            'pic_phone' => '081211112344',
            'items' => $rows,
        ];
    }

    public function test_manual_package_and_web_orders_share_stock_and_rejection_releases_it(): void
    {
        $owner = User::whereRole('owner')->firstOrFail();
        $date = today()->addDays(20);
        $product = Product::active()->firstOrFail();
        $product->update(['stock' => 2]);
        $package = Package::active()->firstOrFail();
        $component = $package->items()->firstOrFail();
        $component->update(['qty' => 2, 'product_id' => $product->id]);

        $this->actingAs($owner)->get('/admin/bookings/create')->assertOk();
        $data = $this->requestData([['selection' => 'package:'.$package->id, 'qty' => 1]], $date);
        $this->post('/admin/bookings', $data)->assertSessionHasNoErrors();
        $booking = Booking::whereEventName('Acara ERP')->firstOrFail();
        $this->assertSame('pending', $booking->negotiation_status);
        $this->assertSame(0, Availability::product($product, $date, $date));

        $component->update(['qty' => 1]);
        $this->assertSame(0, Availability::product($product, $date, $date), 'Existing bookings keep their item composition');

        auth()->logout();
        $this->get("/products/{$product->id}/availability?month=".$date->format('Y-m'))->assertOk()->assertSee('Penuh');
        $this->post("/cart/product/{$product->id}", ['qty' => 1]);
        $this->post('/checkout', $data)->assertSessionHasErrors('start_date');
        $this->assertSame(1, Booking::whereEventName('Acara ERP')->count());

        $this->actingAs($owner)->post("/admin/bookings/{$booking->id}/negotiate", ['decision' => 'reject'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(2, Availability::product($product, $date, $date));
    }

    public function test_acc_sets_final_price_and_dp_issues_one_invoice_with_two_cash_receipts(): void
    {
        $this->actingAs(User::whereRole('owner')->firstOrFail());
        $product = Product::active()->firstOrFail();

        $this->post('/admin/bookings', $this->requestData([['selection' => 'product:'.$product->id, 'qty' => 1]], today()->addDays(20)))
            ->assertSessionHasNoErrors();
        $booking = Booking::whereEventName('Acara ERP')->firstOrFail();
        $estimate = (int) $booking->total;
        $this->get("/my-bookings/{$booking->code}/invoice")->assertNotFound();

        $this->post("/admin/bookings/{$booking->id}/negotiate", ['decision' => 'approve', 'negotiated_total' => 100000])
            ->assertSessionHasNoErrors();
        $this->assertEquals($estimate, $booking->fresh()->initial_estimate);
        $this->assertEquals(100000, $booking->fresh()->total);
        $this->assertSame('pending', $booking->fresh()->status);

        $payment = ['amount' => (string) $booking->fresh()->dp_amount, 'method' => 'Transfer', 'submission_key' => (string) Str::uuid()];
        $this->post("/admin/bookings/{$booking->id}/payments", $payment)->assertSessionHasNoErrors();
        $this->post("/admin/bookings/{$booking->id}/payments", $payment)->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('dp_paid', $booking->payment_status);
        $this->assertNotNull($booking->invoice_issued_at);
        $this->assertNotNull($booking->invoice_emailed_at);
        $this->assertSame(1, $booking->payments()->count());
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());
        $this->assertStringContainsString($booking->invoice_no, $transport->messages()->first()->getOriginalMessage()->getSubject());

        $invoiceNo = $booking->invoice_no;
        $this->post("/admin/bookings/{$booking->id}/payments", [
            'amount' => (string) $booking->remaining, 'method' => 'Transfer', 'submission_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame($invoiceNo, $booking->invoice_no);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertCount(1, $transport->messages());
        $this->get('/admin/cash-receipts')->assertOk()->assertSee($booking->code)
            ->assertViewHas('payments', function ($payments) use ($booking) {
                $receipts = $payments->getCollection()->where('booking_id', $booking->id);

                return $receipts->count() === 2 && (int) $receipts->sum('amount') === 100000;
            });

        auth()->logout();
        $this->get("/invoices/{$booking->code}")->assertForbidden();
        $this->get(URL::signedRoute('bookings.emailed-invoice', ['booking' => $booking->code, 'token' => $booking->access_token]))
            ->assertOk()->assertSee($invoiceNo)->assertSee('Paid in full');
    }

    public function test_expired_hold_is_released_and_full_payment_can_issue_the_first_invoice(): void
    {
        $this->actingAs(User::whereRole('owner')->firstOrFail());
        $product = Product::active()->firstOrFail();
        $product->update(['stock' => 1]);
        $date = today()->addDays(35);

        $this->post('/admin/bookings', $this->requestData([['selection' => 'product:'.$product->id, 'qty' => 1]], $date))
            ->assertSessionHasNoErrors();
        $booking = Booking::whereEventName('Acara ERP')->firstOrFail();
        $this->assertSame(0, Availability::product($product, $date));
        $this->travel(49)->hours();
        $this->assertSame(1, Availability::product($product, $date));
        $this->withSession(['last_activity_at' => now()->timestamp]);

        $this->post("/admin/bookings/{$booking->id}/negotiate", ['decision' => 'approve', 'negotiated_total' => 125000])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, Availability::product($product, $date));
        $this->post("/admin/bookings/{$booking->id}/payments", ['amount' => '125.000', 'method' => 'Transfer'])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertNotNull($booking->fresh()->invoice_emailed_at);
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
    }
}
