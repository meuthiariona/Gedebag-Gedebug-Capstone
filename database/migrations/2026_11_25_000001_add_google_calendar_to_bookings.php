<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('notes');   // ID event di Google Calendar
            $table->timestamp('google_synced_at')->nullable()->after('google_event_id');
            $table->string('google_sync_error')->nullable()->after('google_synced_at'); // pesan gagal terakhir, untuk dicoba ulang
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['google_event_id', 'google_synced_at', 'google_sync_error']);
        });
    }
};
