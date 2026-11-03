<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('branches')->insert([
            ['name' => 'General Trias Branch', 'slug' => 'general-trias', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Dasmariñas Branch', 'slug' => 'dasmarinas', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Trece Branch', 'slug' => 'trece', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('patient_id')
                ->constrained()
                ->restrictOnDelete();
        });

        Schema::table('appointment_slots', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->after('starts_at');
            $table->dropPrimary();
            $table->unique(['branch_id', 'starts_at']);
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
        });

    }

    public function down(): void
    {
        $duplicateSlot = DB::table('appointment_slots')
            ->select('starts_at')
            ->groupBy('starts_at')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicateSlot !== null) {
            throw new RuntimeException('Cannot roll back branch-aware slots while different branches share a reserved time.');
        }

        Schema::table('appointment_slots', function (Blueprint $table): void {
            $table->dropForeign(['branch_id']);
            $table->dropUnique(['branch_id', 'starts_at']);
            $table->dropColumn('branch_id');
            $table->primary('starts_at');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
