<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sales_channels')) {
            return;
        }

        Schema::table('sales_channels', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_channels', 'is_enabled')) $table->boolean('is_enabled')->default(true)->index();
            if (!Schema::hasColumn('sales_channels', 'last_sync_started_at')) $table->timestamp('last_sync_started_at')->nullable();
            if (!Schema::hasColumn('sales_channels', 'last_sync_finished_at')) $table->timestamp('last_sync_finished_at')->nullable();
            if (!Schema::hasColumn('sales_channels', 'last_successful_sync_at')) $table->timestamp('last_successful_sync_at')->nullable();
            if (!Schema::hasColumn('sales_channels', 'last_error_code')) $table->string('last_error_code', 64)->nullable();
            if (!Schema::hasColumn('sales_channels', 'consecutive_failures')) $table->unsignedInteger('consecutive_failures')->default(0);
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive for production data.
    }
};
