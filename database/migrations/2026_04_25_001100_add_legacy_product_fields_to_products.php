<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_code', 80)->nullable()->after('barcode')->index();
            $table->decimal('conversion', 12, 3)->default(1)->after('unit_id');
            $table->string('group_name', 120)->nullable()->after('composition')->index();
            $table->string('manufacturer_name', 180)->nullable()->after('manufacturer_id')->index();
            $table->decimal('previous_price', 14, 2)->default(0)->after('rack_location');
            $table->decimal('discount_percent', 8, 2)->default(0)->after('cc_rate');
            $table->text('keywords')->nullable()->after('notes');
            $table->mediumText('description')->nullable()->after('keywords');
            $table->string('image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_code',
                'conversion',
                'group_name',
                'manufacturer_name',
                'previous_price',
                'discount_percent',
                'keywords',
                'description',
                'image_path',
            ]);
        });
    }
};
