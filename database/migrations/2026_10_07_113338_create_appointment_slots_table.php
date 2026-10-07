<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicateSlot = DB::table('appointments')
            ->select('starts_at')
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'no_show'])
            ->groupBy('starts_at')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicateSlot !== null) {
            throw new RuntimeException('Cannot reserve appointment slots while active appointments share a date and time.');
        }

        Schema::create('appointment_slots', function (Blueprint $table): void {
            $table->dateTime('starts_at')->primary();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->cascadeOnDelete();
        });

        DB::table('appointments')
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'no_show'])
            ->orderBy('id')
            ->chunkById(500, function ($appointments): void {
                foreach ($appointments as $appointment) {
                    DB::table('appointment_slots')->insert([
                        'starts_at' => $appointment->starts_at,
                        'appointment_id' => $appointment->id,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_slots');
    }
};
