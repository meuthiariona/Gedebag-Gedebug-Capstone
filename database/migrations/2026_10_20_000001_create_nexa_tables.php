<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('password'); // customer | admin
            $table->string('phone')->nullable()->after('role');
            $table->string('company')->nullable()->after('phone');
            $table->string('address')->nullable()->after('company');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow')->nullable();
            $table->string('title_bold');
            $table->string('title_light')->nullable();
            $table->text('subtitle')->nullable();
            $table->string('image')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->string('secondary_text')->nullable();
            $table->string('secondary_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Kategori produk (Sound System, Lighting, MC / Host, Live Band, DJ, Stage & Rigging)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('icon')->nullable(); // nama ikon lucide
            $table->string('card_style')->default('product'); // product (foto alat, latar abu) | photo (foto penuh)
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->string('unit')->default('hari'); // hari | sesi
            $table->string('image')->nullable();
            $table->unsignedInteger('stock')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Kategori paket (Corporate, Concert & Festival, ...)
        Schema::create('package_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('badge')->nullable();        // CORPORATE & CONFERENCE
            $table->string('summary')->nullable();      // ringkasan isi paket
            $table->text('description')->nullable();
            $table->string('series_label')->nullable(); // TIER 2 EXECUTIVE SERIES
            $table->string('tier')->nullable();         // Tier 2
            $table->unsignedInteger('capacity_min')->nullable();
            $table->unsignedInteger('capacity_max')->nullable();
            $table->unsignedInteger('power_watts')->nullable();
            $table->json('tags')->nullable();           // ["BEST SELLER","TURNKEY SOLUTION"]
            $table->unsignedBigInteger('price');
            $table->string('unit')->default('event');
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->unsignedInteger('stock')->default(1);
            $table->unsignedInteger('engineers')->default(2);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('hardware'); // hardware | talent | service
            $table->unsignedInteger('qty')->default(1);
            $table->string('name');
            $table->string('subtitle')->nullable();
            $table->string('tag')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('package_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->string('unit')->default('event');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // EVT-10231
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('venue_name');
            $table->string('venue_address')->nullable();
            $table->string('venue_note')->nullable();
            $table->unsignedInteger('expected_pax')->nullable();
            $table->string('pic_name')->nullable();
            $table->string('pic_phone')->nullable();
            $table->string('delivery_point')->nullable();
            $table->string('status')->default('pending'); // pending | confirmed | completed | cancelled
            $table->unsignedTinyInteger('stage')->default(0); // indeks pipeline
            $table->string('phase_title')->nullable();
            $table->string('phase_note')->nullable();
            $table->string('fleet_name')->nullable();
            $table->string('driver_name')->nullable();
            $table->text('site_instructions')->nullable();
            $table->string('crew_note')->nullable();
            $table->string('inspection_label')->nullable(); // "Rigging Inspected"
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('logistics_fee')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedTinyInteger('dp_percent')->default(50);
            $table->timestamp('hold_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('item_type'); // product | package | addon | service
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('category')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('days')->default(1);
            $table->string('unit')->nullable();
            $table->unsignedBigInteger('rate')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->timestamps();
        });

        Schema::create('booking_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->string('note')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method'); // QRIS | Virtual Account | Transfer
            $table->string('reference')->nullable();
            $table->string('proof')->nullable();
            $table->string('status')->default('pending'); // pending | verified | rejected
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('wishable');
            $table->timestamps();
            $table->unique(['user_id', 'wishable_type', 'wishable_id']);
        });
    }

    public function down(): void
    {
        foreach (['wishlists', 'payments', 'booking_logs', 'booking_items', 'bookings', 'package_addons', 'package_items', 'packages', 'package_categories', 'products', 'categories', 'hero_slides', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'phone', 'company', 'address']));
    }
};
