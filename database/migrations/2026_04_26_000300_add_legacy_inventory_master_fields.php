<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'description')) {
                $table->text('description')->nullable()->after('factor');
            }
        });

        Schema::table('batches', function (Blueprint $table) {
            if (! Schema::hasColumn('batches', 'storage_location')) {
                $table->string('storage_location', 120)->nullable()->after('barcode')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            if (Schema::hasColumn('batches', 'storage_location')) {
                $table->dropColumn('storage_location');
            }
        });

        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
