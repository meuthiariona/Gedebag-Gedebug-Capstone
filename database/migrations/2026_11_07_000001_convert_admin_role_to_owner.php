<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RBAC: peran lama "admin" (akses penuh) diubah menjadi "owner".
 * Peran baru "finance" & "operations" diatur di config/rbac.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'owner']);
    }

    public function down(): void
    {
        DB::table('users')->whereIn('role', ['owner', 'finance', 'operations'])->update(['role' => 'admin']);
    }
};
