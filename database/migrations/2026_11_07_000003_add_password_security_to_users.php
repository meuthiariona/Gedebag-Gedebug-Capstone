<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening login: akun staf dengan password bawaan/sementara wajib ganti password saat login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });

        // Akun staf yang sudah ada dianggap masih memakai password lama → wajib ganti.
        DB::table('users')->whereIn('role', ['owner', 'finance', 'operations'])->update(['must_change_password' => true]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['must_change_password', 'password_changed_at']));
    }
};
