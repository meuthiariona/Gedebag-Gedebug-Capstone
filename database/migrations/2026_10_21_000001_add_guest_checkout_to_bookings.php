<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Checkout tanpa login: data pemesan disimpan langsung di booking,
 * dan ringkasan pesanan bisa dibuka lewat link rahasia (access_token).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_phone')->nullable()->after('customer_name');
            $table->string('customer_email')->nullable()->after('customer_phone');
            $table->string('customer_company')->nullable()->after('customer_email');
            $table->string('access_token', 40)->nullable()->unique()->after('code');
        });

        // Isi data pemesan untuk booking lama dari akun user-nya
        foreach (DB::table('bookings')->get() as $b) {
            $u = DB::table('users')->find($b->user_id);
            DB::table('bookings')->where('id', $b->id)->update([
                'access_token' => Str::random(40),
                'customer_name' => $u?->name ?? $b->pic_name,
                'customer_phone' => $u?->phone ?? $b->pic_phone,
                'customer_email' => $u?->email,
                'customer_company' => $u?->company,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['access_token']);
            $table->dropColumn(['customer_name', 'customer_phone', 'customer_email', 'customer_company', 'access_token']);
        });
    }
};
