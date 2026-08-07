<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sales_channels')) {
            Schema::table('sales_channels', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_channels', 'sync_status')) {
                    $table->string('sync_status', 20)->default('idle')->index();
                }
                if (!Schema::hasColumn('sales_channels', 'last_sync_at')) {
                    $table->timestamp('last_sync_at')->nullable();
                }
                if (!Schema::hasColumn('sales_channels', 'last_orders_sync_at')) {
                    $table->timestamp('last_orders_sync_at')->nullable();
                }
                if (!Schema::hasColumn('sales_channels', 'last_sync_count')) {
                    $table->unsignedInteger('last_sync_count')->default(0);
                }
                if (!Schema::hasColumn('sales_channels', 'last_error')) {
                    $table->text('last_error')->nullable();
                }
                if (!Schema::hasColumn('sales_channels', 'base_url')) {
                    $table->string('base_url')->nullable();
                }
                if (!Schema::hasColumn('sales_channels', 'credentials_encrypted')) {
                    $table->text('credentials_encrypted')->nullable();
                }
            });
        }

        if (Schema::hasTable('commerce_orders')) {
            Schema::table('commerce_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('commerce_orders', 'raw_payload_json')) {
                    $table->longText('raw_payload_json')->nullable();
                }
                if (!Schema::hasColumn('commerce_orders', 'status_normalized')) {
                    $table->string('status_normalized', 50)->nullable()->index();
                }
                if (!Schema::hasColumn('commerce_orders', 'status_source')) {
                    $table->string('status_source', 100)->nullable();
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('order_items', 'commerce_order_id')) {
                    $table->unsignedBigInteger('commerce_order_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        // Hotfix migration is intentionally non-destructive.
    }
};
