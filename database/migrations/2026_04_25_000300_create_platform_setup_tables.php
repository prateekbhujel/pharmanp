<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 120)->unique();
            $table->string('status', 40)->default('active')->index();
            $table->string('plan_code', 80)->default('starter')->index();
            $table->date('trial_ends_on')->nullable()->index();
            $table->timestamp('suspended_at')->nullable()->index();
            $table->text('suspension_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name', 80);
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->string('status', 40)->default('open')->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'name'], 'fiscal_years_company_name_unique');
            $table->index(['company_id', 'starts_on', 'ends_on'], 'fiscal_years_company_period_idx');
        });

        Schema::create('feature_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('module', 80)->index();
            $table->string('code', 120)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 40)->default('planned')->index();
            $table->boolean('is_billable')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('tenant_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('feature_code', 120)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'company_id', 'feature_code'], 'tenant_feature_unique');
        });

        Schema::create('setup_invites', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 128)->unique();
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable()->index();
            $table->string('status', 40)->default('active')->index();
            $table->json('requested_features')->nullable();
            $table->json('prefill')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('onboarding_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('step_code', 120)->index();
            $table->string('status', 40)->default('pending')->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'step_code'], 'onboarding_user_step_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_states');
        Schema::dropIfExists('setup_invites');
        Schema::dropIfExists('tenant_feature_flags');
        Schema::dropIfExists('feature_catalog_items');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
