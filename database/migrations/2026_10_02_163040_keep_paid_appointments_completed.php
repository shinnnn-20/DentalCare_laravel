<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('appointments')
            ->where('status', 'paid')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('bills')
                    ->whereColumn('bills.appointment_id', 'appointments.id')
                    ->where('bills.payment_status', 'paid');
            })
            ->update(['status' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('appointments')
            ->where('status', 'completed')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('bills')
                    ->whereColumn('bills.appointment_id', 'appointments.id')
                    ->where('bills.payment_status', 'paid');
            })
            ->update(['status' => 'paid']);
    }
};
