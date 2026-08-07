<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // woocommerce, allegro, ebay
            $table->string('name');
            $table->string('base_url')->nullable();
            $table->longText('credentials_encrypted')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_orders_sync_at')->nullable();
            $table->timestamp('last_token_refresh_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_channels');
    }
};
