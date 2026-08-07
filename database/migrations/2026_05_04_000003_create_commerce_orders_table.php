<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commerce_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('external_order_id');
            $table->string('order_number')->nullable();
            $table->string('status_source')->nullable();
            $table->string('status_normalized')->default('NEW');
            $table->string('payment_status')->nullable();
            $table->string('shipping_status')->nullable();
            $table->string('currency', 8)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('shipping_country')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['sales_channel_id', 'external_order_id']);
            $table->index(['company_id', 'ordered_at']);
            $table->index(['status_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_orders');
    }
};
