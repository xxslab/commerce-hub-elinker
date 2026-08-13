<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sales_channels')) {
            Schema::table('sales_channels', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_channels', 'webhook_secret_encrypted')) {
                    $table->longText('webhook_secret_encrypted')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Production-safe migration: intentionally do not remove existing data.
    }
};
