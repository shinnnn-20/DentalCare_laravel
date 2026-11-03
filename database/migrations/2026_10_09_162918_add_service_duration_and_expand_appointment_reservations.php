<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'duration_minutes')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->unsignedSmallInteger('duration_minutes')->default(30);
            });
        }

        if (! Schema::hasColumn('appointments', 'duration_minutes')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->unsignedSmallInteger('duration_minutes')->default(30);
            });
        }

        if (! Schema::hasIndex('appointment_slots', 'appointment_slots_appointment_id_index')) {
            Schema::table('appointment_slots', function (Blueprint $table): void {
                $table->index('appointment_id');
            });
        }

        if (Schema::hasIndex('appointment_slots', 'appointment_slots_appointment_id_unique')) {
            Schema::table('appointment_slots', function (Blueprint $table): void {
                $table->dropUnique('appointment_slots_appointment_id_unique');
            });
        }

        DB::table('appointment_slots')->whereNull('appointment_id')->delete();
        DB::table('appointment_slots')
            ->whereIn('appointment_id', function ($query): void {
                $query->select('id')
                    ->from('appointments')
                    ->whereIn('status', ['cancelled', 'rejected', 'completed', 'no_show']);
            })
            ->delete();

        DB::table('appointments')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->whereNotIn('appointments.status', ['cancelled', 'rejected', 'completed', 'no_show'])
            ->select([
                'appointments.id',
                'appointments.starts_at',
                'services.duration_minutes',
            ])
            ->orderBy('appointments.id')
            ->chunk(500, function ($appointments): void {
                foreach ($appointments as $appointment) {
                    $startsAt = Carbon::parse($appointment->starts_at);
                    $slotCount = (int) ceil((int) $appointment->duration_minutes / 10);
                    $reservationRows = [];

                    DB::table('appointments')
                        ->where('id', $appointment->id)
                        ->update(['duration_minutes' => $appointment->duration_minutes]);

                    for ($slot = 0; $slot < $slotCount; $slot++) {
                        $reservationRows[] = [
                            'starts_at' => $startsAt->copy()->addMinutes($slot * 10)->format('Y-m-d H:i:s'),
                            'appointment_id' => $appointment->id,
                        ];
                    }

                    DB::table('appointment_slots')->insertOrIgnore($reservationRows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('appointment_slots')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('appointments')
                    ->whereColumn('appointments.id', 'appointment_slots.appointment_id')
                    ->whereColumn('appointments.starts_at', 'appointment_slots.starts_at');
            })
            ->delete();

        Schema::table('appointment_slots', function (Blueprint $table): void {
            $table->dropIndex('appointment_slots_appointment_id_index');
            $table->unique('appointment_id');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('duration_minutes');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('duration_minutes');
        });
    }
};
