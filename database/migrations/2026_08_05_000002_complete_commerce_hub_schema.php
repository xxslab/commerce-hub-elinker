<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sales_channels')) {
            Schema::table('sales_channels', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_channels', 'sync_status')) $table->string('sync_status')->default('idle')->index();
                if (! Schema::hasColumn('sales_channels', 'last_sync_count')) $table->unsignedInteger('last_sync_count')->default(0);
                if (! Schema::hasColumn('sales_channels', 'last_error')) $table->text('last_error')->nullable();
                if (! Schema::hasColumn('sales_channels', 'last_error_message')) $table->text('last_error_message')->nullable();
                if (! Schema::hasColumn('sales_channels', 'access_token_encrypted')) $table->longText('access_token_encrypted')->nullable();
                if (! Schema::hasColumn('sales_channels', 'refresh_token_encrypted')) $table->longText('refresh_token_encrypted')->nullable();
                if (! Schema::hasColumn('sales_channels', 'token_expires_at')) $table->timestamp('token_expires_at')->nullable();
                if (! Schema::hasColumn('sales_channels', 'sync_cursor')) $table->string('sync_cursor')->nullable();
            });
        }

        if (Schema::hasTable('commerce_orders')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('commerce_orders', 'external_order_number')) $table->string('external_order_number')->nullable();
                if (! Schema::hasColumn('commerce_orders', 'products_total')) $table->decimal('products_total', 12, 2)->nullable();
                if (! Schema::hasColumn('commerce_orders', 'shipping_total')) $table->decimal('shipping_total', 12, 2)->nullable();
                if (! Schema::hasColumn('commerce_orders', 'discount_total')) $table->decimal('discount_total', 12, 2)->nullable();
                if (! Schema::hasColumn('commerce_orders', 'tax_total')) $table->decimal('tax_total', 12, 2)->nullable();
                if (! Schema::hasColumn('commerce_orders', 'payment_method')) $table->string('payment_method')->nullable();
                if (! Schema::hasColumn('commerce_orders', 'shipping_method')) $table->string('shipping_method')->nullable();
                if (! Schema::hasColumn('commerce_orders', 'customer_note')) $table->text('customer_note')->nullable();
                if (! Schema::hasColumn('commerce_orders', 'internal_note')) $table->text('internal_note')->nullable();
                if (! Schema::hasColumn('commerce_orders', 'last_synced_at')) $table->timestamp('last_synced_at')->nullable();
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'variant')) $table->string('variant')->nullable();
            });
        }

        if (! Schema::hasTable('sync_runs')) {
            Schema::create('sync_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sales_channel_id')->nullable()->constrained()->nullOnDelete();
                $table->string('operation');
                $table->string('status')->default('running');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->unsignedInteger('fetched_count')->default(0);
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->string('error_code')->nullable();
                $table->uuid('correlation_id')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'status', 'started_at']);
            });
        }
    }

    public function down(): void
    {
        // Production-safe migration: intentionally do not remove existing data.
    }
};
