<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_app_credentials')) {
            return;
        }

        Schema::create('marketplace_app_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('marketplace', 50);
            $table->string('name')->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('redirect_uri')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'marketplace', 'is_active'], 'mac_cmp_market_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_app_credentials');
    }
};