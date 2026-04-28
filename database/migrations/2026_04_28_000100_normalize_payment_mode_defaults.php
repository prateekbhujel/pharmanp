<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dropdown_options')) {
            return;
        }

        $now = now();

        DB::table('dropdown_options')
            ->where('alias', 'payment_mode')
            ->whereNotIn('data', ['cash', 'bank'])
            ->update([
                'data' => 'bank',
                'updated_at' => $now,
            ]);

        $legacyQr = DB::table('dropdown_options')
            ->where('alias', 'payment_mode')
            ->where('name', 'QR Payment')
            ->first();

        if ($legacyQr && ! DB::table('dropdown_options')->where('alias', 'payment_mode')->where('name', 'FonePay QR')->exists()) {
            DB::table('dropdown_options')
                ->where('id', $legacyQr->id)
                ->update([
                    'name' => 'FonePay QR',
                    'data' => 'bank',
                    'updated_at' => $now,
                ]);
        }

        foreach ($this->paymentModeDefaults() as $row) {
            $exists = DB::table('dropdown_options')
                ->where('alias', $row['alias'])
                ->where('name', $row['name'])
                ->exists();

            if ($exists) {
                DB::table('dropdown_options')
                    ->where('alias', $row['alias'])
                    ->where('name', $row['name'])
                    ->update([
                        'data' => $row['data'],
                        'status' => 1,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('dropdown_options')->insert([
                    'alias' => $row['alias'],
                    'name' => $row['name'],
                    'data' => $row['data'],
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('dropdown_options')) {
            return;
        }

        DB::table('dropdown_options')
            ->where('alias', 'payment_mode')
            ->where('name', 'FonePay QR')
            ->delete();
    }

    private function paymentModeDefaults(): array
    {
        return [
            ['alias' => 'payment_mode', 'name' => 'Cash', 'data' => 'cash'],
            ['alias' => 'payment_mode', 'name' => 'Bank Transfer', 'data' => 'bank'],
            ['alias' => 'payment_mode', 'name' => 'Cheque', 'data' => 'bank'],
            ['alias' => 'payment_mode', 'name' => 'FonePay QR', 'data' => 'bank'],
        ];
    }
};
