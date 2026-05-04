<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jwt_revocations')) {
            return;
        }

        Schema::create('jwt_revocations', function (Blueprint $table) {
            $table->id();
            $table->string('jti', 80)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jwt_revocations');
    }
};
