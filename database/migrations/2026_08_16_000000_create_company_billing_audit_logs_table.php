<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable audit trail for a company's License Hub connection lifecycle
 * (connect/disconnect/failed attempts). Deliberately its own table, not
 * sync_logs — sync_logs records provider order-sync outcomes per
 * SalesChannel, this records billing-identity events per Company and is
 * meant to be readable by the company's own admin, not just support staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_billing_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('workspace_id', 64)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_billing_audit_logs');
    }
};
