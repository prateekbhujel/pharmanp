<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_mode_id')->nullable()->after('party_id')->index();
        });

        $modes = DB::table('dropdown_options')
            ->where('alias', 'payment_mode')
            ->get(['id', 'name', 'data']);

        foreach ($modes as $mode) {
            DB::table('payments')
                ->whereNull('payment_mode_id')
                ->where(function ($query) use ($mode) {
                    $query->where('payment_mode', $mode->data)
                        ->orWhere('payment_mode', $mode->name);
                })
                ->update(['payment_mode_id' => $mode->id]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payment_mode_id']);
            $table->dropColumn('payment_mode_id');
        });
    }
};
