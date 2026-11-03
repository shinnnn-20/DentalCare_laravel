<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table): void {
            $table->boolean('is_add_on')->default(false)->after('service_name');
        });

        DB::table('audit_logs')
            ->where('action', 'additional_services_added')
            ->where('record_type', 'Bill')
            ->whereNotNull('record_id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $auditLogs): void {
                foreach ($auditLogs as $auditLog) {
                    preg_match_all('/\(#(\d+)\)/', $auditLog->description ?? '', $matches);
                    $serviceIds = array_map('intval', $matches[1]);

                    if ($serviceIds === []) {
                        continue;
                    }

                    DB::table('bill_items')
                        ->where('bill_id', $auditLog->record_id)
                        ->whereIn('service_id', $serviceIds)
                        ->update(['is_add_on' => true]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table): void {
            $table->dropColumn('is_add_on');
        });
    }
};
