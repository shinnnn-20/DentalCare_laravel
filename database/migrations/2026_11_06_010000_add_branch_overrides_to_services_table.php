<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique('services_name_unique');
            $table->foreignId('branch_id')->nullable()->after('name')->constrained()->restrictOnDelete();
            $table->foreignId('base_service_id')->nullable()->after('branch_id')->constrained('services')->restrictOnDelete();
            $table->unique(['branch_id', 'name'], 'services_branch_name_unique');
            $table->unique(['branch_id', 'base_service_id'], 'services_branch_base_unique');
        });
    }

    public function down(): void
    {
        $duplicateNamesExist = DB::table('services')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateNamesExist) {
            throw new RuntimeException('Branch-specific services must be consolidated before this migration can be rolled back.');
        }

        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique('services_branch_name_unique');
            $table->dropUnique('services_branch_base_unique');
            $table->dropConstrainedForeignId('base_service_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->unique('name');
        });
    }
};
