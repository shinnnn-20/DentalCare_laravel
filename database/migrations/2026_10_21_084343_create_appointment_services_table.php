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
        Schema::create('appointment_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['appointment_id', 'service_id']);
        });

        if (Schema::hasColumn('appointments', 'service_id')) {
            $existing = DB::table('appointments')
                ->whereNotNull('service_id')
                ->select(['id as appointment_id', 'service_id', 'created_at', 'updated_at'])
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all();

            if (! empty($existing)) {
                DB::table('appointment_service')->insertOrIgnore($existing);
            }

            Schema::table('appointments', function (Blueprint $table): void {
                $table->foreignId('service_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_service');

        if (Schema::hasColumn('appointments', 'service_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->foreignId('service_id')->nullable(false)->change();
            });
        }
    }
};
