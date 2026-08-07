<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('carrier_accounts')) Schema::create('carrier_accounts', function (Blueprint $table) {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('carrier'); $table->string('name'); $table->longText('credentials_encrypted')->nullable();
            $table->boolean('is_enabled')->default(true); $table->timestamps(); $table->index(['company_id', 'carrier']);
        });
        if (! Schema::hasTable('shipment_events')) Schema::create('shipment_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status'); $table->timestamp('occurred_at')->nullable(); $table->string('location')->nullable();
            $table->json('raw_payload')->nullable(); $table->timestamps(); $table->index(['shipment_id', 'occurred_at']);
        });
        if (! Schema::hasTable('shipping_labels')) Schema::create('shipping_labels', function (Blueprint $table) {
            $table->id(); $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('format'); $table->string('path'); $table->string('checksum')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_labels'); Schema::dropIfExists('shipment_events'); Schema::dropIfExists('carrier_accounts');
    }
};
