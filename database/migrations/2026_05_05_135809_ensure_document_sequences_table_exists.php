<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_sequences')) {
            Schema::create('document_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('scope_key', 120);
                $table->string('type', 80);
                $table->string('date_part', 20)->default('');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('last_sequence')->default(0);
                $table->timestamps();
                $table->unique(['scope_key', 'type', 'date_part'], 'document_sequences_scope_type_date_unique');
                $table->index(['tenant_id', 'company_id', 'type'], 'document_sequences_tenant_company_type_idx');
            });
        }
    }

    public function down(): void
    {
        // No down needed as it's a repair migration
    }
};
