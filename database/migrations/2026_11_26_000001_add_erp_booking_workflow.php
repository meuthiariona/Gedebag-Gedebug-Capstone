<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
        });
        Schema::table('booking_items', function (Blueprint $table) {
            $table->json('inventory_components')->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('submission_key')->nullable();
            $table->unique(['booking_id', 'submission_key']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('negotiation_status')->nullable();
            $table->unsignedBigInteger('negotiated_total')->nullable();
            $table->unsignedBigInteger('initial_estimate')->nullable();
            $table->timestamp('invoice_issued_at')->nullable();
            $table->timestamp('invoice_emailed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['negotiation_status', 'negotiated_total', 'initial_estimate',
                'invoice_issued_at', 'invoice_emailed_at']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['booking_id', 'submission_key']);
            $table->dropColumn('submission_key');
        });
        Schema::table('booking_items', fn (Blueprint $table) => $table->dropColumn('inventory_components'));
        Schema::table('package_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_id'));
    }
};
