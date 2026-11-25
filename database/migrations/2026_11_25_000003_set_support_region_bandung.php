<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/** Footer "Direct Support": wilayah layanan menjadi Bandung (hanya jika masih nilai contoh lama). */
return new class extends Migration
{
    public function up(): void
    {
        if (Setting::where('key', 'support_region')->value('value') === 'Jakarta & Bali Regional Dispatch') {
            Setting::put('support_region', 'Bandung, Jawa Barat'); // tercatat di audit log
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis; ubah lewat Admin → Pengaturan bila perlu.
    }
};
