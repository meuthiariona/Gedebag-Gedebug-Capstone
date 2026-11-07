<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail: catatan permanen "siapa melakukan apa, kapan, dari mana,
 * dan nilai sebelum/sesudahnya". Hanya ditambah (append-only), tidak pernah diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Pelaku. Nama & peran disalin (snapshot) supaya log tetap terbaca walau akunnya dihapus/diganti perannya.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_role')->nullable();
            // Apa yang terjadi
            $table->string('action', 40);               // created | updated | deleted | login | login_failed | logout
            $table->string('subject_type')->nullable(); // mis. Payment, Booking, Setting
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            // Dari mana
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
