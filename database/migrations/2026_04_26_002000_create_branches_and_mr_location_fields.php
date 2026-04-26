<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Branches (HQ + sub-branches) ─────────────────────────────────
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->nullable()->index();
            // 'hq' | 'branch'
            $table->string('type', 20)->default('branch')->index();
            // null = HQ itself; set to HQ id for sub-branches
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // ── 2. Attach MR to a branch ─────────────────────────────────────────
        Schema::table('medical_representatives', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->index()->after('company_id');
        });

        // ── 3. Tag sales invoices with branch ────────────────────────────────
        if (Schema::hasTable('sales_invoices')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_invoices', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index()->after('store_id');
                }
            });
        }

        // ── 4. GPS check-in on visits ────────────────────────────────────────
        Schema::table('representative_visits', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('notes');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('location_name')->nullable()->after('longitude');
        });

        // ── 5. Seed a default HQ branch so existing data has a home ──────────
        \Illuminate\Support\Facades\DB::table('branches')->insertOrIgnore([
            'id'        => 1,
            'name'      => 'Head Office',
            'code'      => 'HQ',
            'type'      => 'hq',
            'parent_id' => null,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('representative_visits', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_name']);
        });

        if (Schema::hasTable('sales_invoices') && Schema::hasColumn('sales_invoices', 'branch_id')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                $table->dropColumn('branch_id');
            });
        }

        Schema::table('medical_representatives', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
