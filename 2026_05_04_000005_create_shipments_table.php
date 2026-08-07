<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commerce_order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier'); // inpost, dpd, dhl
            $table->string('external_shipment_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('status')->default('CREATED');
            $table->string('label_format')->nullable();
            $table->string('label_path')->nullable();
            $table->timestamp('last_tracking_sync_at')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index(['carrier', 'tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
