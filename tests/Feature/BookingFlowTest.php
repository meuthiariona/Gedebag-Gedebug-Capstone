<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_public_pages_render(): void
    {
        $package = Package::first();
        foreach (['/', '/products', '/products?category=lighting', '/products?q=mc', '/packages', '/packages?type=corporate', "/packages/{$package->slug}", '/cart', '/login', '/register'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_customer_pages_render(): void
    {
        $user = User::where('role', 'customer')->first();
        $booking = $user->bookings()->first();
        $this->actingAs($user);
        foreach (['/my-bookings', '/my-bookings?tab=completed', '/wishlist', "/my-bookings/{$booking->code}", "/my-bookings/{$booking->code}/invoice", "/order/{$booking->access_token}"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs(User::where('role', 'owner')->first());
        $booking = Booking::first();
        foreach (['/admin', '/admin/settings', '/admin/bookings', "/admin/bookings/{$booking->id}", '/admin/products', '/admin/products/create', '/admin/products/1/edit',
            '/admin/categories', '/admin/packages', '/admin/packages/1/edit', '/admin/package-categories', '/admin/hero-slides', '/admin/users', '/admin/users/1/edit'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_customer_cannot_open_admin_or_others_booking(): void
    {
        $other = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'password']);
        $this->actingAs($other)->get('/admin')->assertForbidden();
        $this->actingAs($other)->get('/my-bookings/'.Booking::first()->code)->assertForbidden();
    }

    public function test_guest_checkout_then_whatsapp_negotiation(): void
    {
        $product = Product::where('unit', 'hari')->first();
        $package = Package::has('addons')->first();
        $addon = $package->addons->first();

        $this->post("/cart/product/{$product->id}", ['qty' => 2]);
        $this->post("/cart/package/{$package->id}", ['addons' => [$addon->id]]);

        // Tamu (tanpa login) bisa membuka checkout
        $this->assertGuest();
        $this->get('/checkout')->assertOk()->assertSee('Data Diri');

        // Data diri wajib diisi
        $this->post('/checkout', ['event_name' => 'X'])->assertSessionHasErrors(['customer_name', 'customer_phone', 'start_date', 'end_date']);

        $res = $this->post('/checkout', [
            'customer_name' => 'Budi Tamu', 'customer_phone' => '0812-1111-2222', 'customer_email' => 'budi@example.com',
            'event_name' => 'Test Event', 'venue_name' => 'Hall A', 'venue_address' => 'Jl. Test 1',
            'start_date' => now()->addDays(60)->toDateString(), 'end_date' => now()->addDays(62)->toDateString(),
            'pic_name' => 'PIC', 'pic_phone' => '0812', 'notes' => 'Butuh genset',
        ]);

        $booking = Booking::latest('id')->first();
        $res->assertRedirect(route('orders.summary', $booking->access_token));
        $expected = $product->price * 2 * 3 + $package->price + $addon->price + (int) setting('logistics_fee');
        $this->assertNull($booking->user_id);
        $this->assertSame('Budi Tamu', $booking->customer_name);
        $this->assertSame($expected, (int) $booking->total);
        $this->assertSame('pending', $booking->status);
        $this->assertSame(now()->addDays(60)->toDateString(), $booking->start_date->toDateString()); // tanggal dari form checkout
        $this->assertSame(now()->addDays(62)->toDateString(), $booking->end_date->toDateString());

        // Stok ter-reserve untuk tanggal tsb
        $this->assertSame($package->stock - 1, \App\Services\Availability::package($package, $booking->start_date, $booking->end_date));

        // Halaman ringkasan + template WA berisi detail pesanan
        $msg = $booking->whatsappMessage();
        foreach ([$booking->code, 'Budi Tamu', '0812-1111-2222', 'Test Event', 'Hall A', $product->name, $package->name, $addon->name, rupiah($booking->total), 'Butuh genset', $booking->summary_url] as $needle) {
            $this->assertStringContainsString($needle, $msg);
        }
        $this->assertStringStartsWith('https://wa.me/'.wa_number(setting('whatsapp')).'?text=', $booking->whatsappUrl());
        $this->get("/order/{$booking->access_token}")->assertOk()->assertSee('Chat via WhatsApp')->assertSee('Budi Tamu');
        $this->get('/order/token-salah')->assertNotFound();

        // Checkout menyimpan pesanan di cookie browser → muncul di My Bookings tanpa login
        $res->assertCookie('guest_orders', json_encode([$booking->id]));
        $this->withCookie('guest_orders', json_encode([$booking->id]));
        $this->get('/my-bookings')->assertOk()->assertSee($booking->code)->assertSee('Test Event');
        // Detail booking customer = tampilan customer (pipeline, billing), bukan form admin
        $this->get("/my-bookings/{$booking->code}")->assertOk()
            ->assertSee('Fulfillment & Rigging Pipeline', false)->assertSee('Payment & Billing Overview', false)
            ->assertDontSee('Detail & Status Operasional', false)->assertDontSee('Catat Pembayaran');

        // Tombol WA: catat kontak, buka WhatsApp; ringkasan lalu beralih ke detail booking
        $this->get("/order/{$booking->access_token}/whatsapp")->assertRedirect($booking->whatsappUrl());
        $this->assertNotNull($booking->fresh()->wa_contacted_at);
        $this->assertSame('Negosiasi harga via WhatsApp', $booking->fresh()->phase_title);
        $this->get("/order/{$booking->access_token}")->assertRedirect(route('bookings.show', $booking));

        // Browser lain (tanpa cookie & tanpa login) tidak bisa membuka detail booking
        $this->withCookie('guest_orders', '[]');
        $this->get("/my-bookings/{$booking->code}")->assertForbidden();
        $this->get('/my-bookings')->assertOk()->assertDontSee($booking->code);

        // Login/daftar dari browser yang sama → pesanan tamu otomatis masuk ke akun
        $this->withCookie('guest_orders', json_encode([$booking->id]));
        $this->post('/register', ['name' => 'Budi', 'email' => 'budi2@example.com', 'phone' => '0812', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $this->assertSame(User::where('email', 'budi2@example.com')->value('id'), $booking->fresh()->user_id);
        auth()->logout();

        // Setelah deal di WA, admin mencatat diskon & pembayaran → booking terkonfirmasi
        $admin = User::where('role', 'owner')->first();
        $this->actingAs($admin)->get("/admin/bookings/{$booking->id}")->assertOk()->assertSee('Budi Tamu');
        $this->post("/admin/bookings/{$booking->id}/payments", ['amount' => (string) $booking->dp_amount, 'method' => 'Transfer'])
            ->assertSessionHasErrors('amount');
        $this->post("/admin/bookings/{$booking->id}/negotiate", ['decision' => 'approve', 'negotiated_total' => $expected])
            ->assertSessionHasNoErrors();
        $this->post("/admin/bookings/{$booking->id}/payments", ['amount' => (string) $booking->dp_amount, 'method' => 'Transfer'])
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(1, $booking->stage);
        $this->assertSame('dp_paid', $booking->payment_status);
    }

    public function test_admin_payment_cannot_exceed_remaining_balance(): void
    {
        $this->actingAs(User::where('role', 'owner')->first());
        $booking = Booking::where('status', 'pending')->first(); // belum dibayar
        $total = $booking->total;

        // Melebihi sisa tagihan → ditolak
        $this->post("/admin/bookings/{$booking->id}/payments", ['amount' => number_format($total + 1, 0, ',', '.'), 'method' => 'Transfer'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, (int) $booking->fresh()->amount_paid);

        // Bayar penuh → lunas; pembayaran berikutnya ditolak
        $this->post("/admin/bookings/{$booking->id}/payments", ['amount' => number_format($total, 0, ',', '.'), 'method' => 'Transfer'])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->post("/admin/bookings/{$booking->id}/payments", ['amount' => '1.000', 'method' => 'Transfer'])->assertSessionHasErrors('amount');
        $this->assertSame($total, (int) $booking->fresh()->amount_paid);

        // Diskon tidak boleh membuat total < yang sudah dibayar
        $fresh = $booking->fresh();
        $form = $fresh->only(['event_name', 'venue_name', 'status', 'dp_percent']) + ['start_date' => $fresh->start_date->toDateString(), 'end_date' => $fresh->end_date->toDateString(), 'stage' => $fresh->stage];
        $this->put("/admin/bookings/{$booking->id}", $form + ['discount' => '50.000'])->assertSessionHasErrors('discount');
        $this->assertSame(0, (int) $booking->fresh()->discount);

        // Hapus pembayaran yang salah → jumlah dibayar ikut berkurang
        $payment = $booking->payments()->latest('id')->first();
        $this->delete("/admin/payments/{$payment->id}")->assertRedirect();
        $this->assertSame(0, (int) $booking->fresh()->amount_paid);
        $this->assertSame(0, $booking->payments()->count());
    }

    public function test_admin_can_create_product_with_upload(): void
    {
        Storage::fake('public');
        $this->actingAs(User::where('role', 'owner')->first());
        $this->post('/admin/products', [
            'category_id' => 1, 'name' => 'Wireless Mic Set', 'price' => '350.000', 'unit' => 'hari', 'stock' => 5,
            'image' => UploadedFile::fake()->image('mic.jpg'), 'is_active' => 1,
        ])->assertRedirect();

        $p = Product::where('name', 'Wireless Mic Set')->first();
        $this->assertSame(350000, (int) $p->price);
        $this->assertSame('wireless-mic-set', $p->slug);
        Storage::disk('public')->assertExists($p->image);
        $this->get('/products')->assertSee('Wireless Mic Set');
    }

    public function test_dates_are_chosen_at_checkout_not_in_header_bar(): void
    {
        // Bar "Event Availability Engine" sudah dihapus; footer menampilkan wilayah Bandung
        $this->get('/')->assertOk()->assertDontSee('Check Availability')->assertDontSee('Event Availability Engine')
            ->assertSee('Bandung, Jawa Barat');
        $this->post('/availability', ['start' => now()->toDateString()])->assertNotFound();

        $product = Product::where('unit', 'hari')->first();
        $this->post("/cart/product/{$product->id}", ['qty' => 1]);
        $this->get('/checkout')->assertOk()->assertSee('Tanggal Mulai Sewa')->assertSee('Tanggal Selesai Sewa');

        $base = ['customer_name' => 'Sari', 'customer_phone' => '0812-3333-4444', 'event_name' => 'Seminar',
            'venue_name' => 'Aula', 'venue_address' => 'Jl. A', 'pic_name' => 'Sari', 'pic_phone' => '0812'];

        // Tanggal lampau / terbalik ditolak
        $this->post('/checkout', $base + ['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('start_date');
        $this->post('/checkout', $base + ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(8)->toDateString()])
            ->assertSessionHasErrors('end_date');

        // Stok penuh di tanggal itu → ditolak dengan pesan yang menyebut tanggalnya
        $this->post("/cart/product/{$product->id}", ['qty' => $product->stock]); // total qty melebihi stok
        $this->post('/checkout', $base + ['start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(11)->toDateString()])
            ->assertSessionHasErrors('start_date');
        $this->assertSame(0, Booking::where('event_name', 'Seminar')->count());

        // Harga harian mengikuti lama sewa dari form: 4 hari
        $this->patch('/cart/product-'.$product->id, ['qty' => 1]);
        $this->post('/checkout', $base + ['start_date' => now()->addDays(20)->toDateString(), 'end_date' => now()->addDays(23)->toDateString()]);
        $booking = Booking::where('event_name', 'Seminar')->firstOrFail();
        $this->assertSame($product->price * 4 + (int) setting('logistics_fee'), (int) $booking->total);
    }
}
