<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dropdown_options') && ! Schema::hasColumn('dropdown_options', 'meta')) {
            Schema::table('dropdown_options', function (Blueprint $table) {
                $table->json('meta')->nullable()->after('data');
            });
        }

        $now = now();
        foreach ($this->defaults() as $row) {
            $exists = DB::table('dropdown_options')
                ->where('alias', $row['alias'])
                ->where('name', $row['name'])
                ->exists();

            if ($exists) {
                DB::table('dropdown_options')
                    ->where('alias', $row['alias'])
                    ->where('name', $row['name'])
                    ->update([
                        'data' => $row['data'] ?? null,
                        'status' => 1,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('dropdown_options')->insert([
                    'alias' => $row['alias'],
                    'name' => $row['name'],
                    'data' => $row['data'] ?? null,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dropdown_options') && Schema::hasColumn('dropdown_options', 'meta')) {
            Schema::table('dropdown_options', function (Blueprint $table) {
                $table->dropColumn('meta');
            });
        }
    }

    private function defaults(): array
    {
        return [
            ['alias' => 'adjustment_type', 'name' => 'Add Stock', 'data' => 'in'],
            ['alias' => 'adjustment_type', 'name' => 'Subtract Stock', 'data' => 'out'],
            ['alias' => 'adjustment_type', 'name' => 'Expired Stock', 'data' => 'out'],
            ['alias' => 'adjustment_type', 'name' => 'Damaged Stock', 'data' => 'out'],
            ['alias' => 'adjustment_type', 'name' => 'Returned to Stock', 'data' => 'in'],
            ['alias' => 'payment_type', 'name' => 'Full Payment', 'data' => null],
            ['alias' => 'payment_type', 'name' => 'Partial Payment', 'data' => null],
            ['alias' => 'payment_type', 'name' => 'Credit', 'data' => null],
            ['alias' => 'payment_type', 'name' => 'Advance', 'data' => null],
            ['alias' => 'payment_mode', 'name' => 'QR Payment', 'data' => 'qr'],
        ];
    }
};
