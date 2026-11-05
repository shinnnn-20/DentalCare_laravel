<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('audit_logs')
            ->where(function (Builder $query): void {
                $query->whereNull('description')
                    ->orWhere('description', '')
                    ->orWhereRaw("TRIM(description) = ''");
            })
            ->orderBy('id')
            ->chunkById(500, function (Collection $logs): void {
                foreach ($logs as $log) {
                    $record = $log->record_type === null
                        ? strtolower(str_replace('_', ' ', $log->module)).' activity'
                        : $log->record_type.' #'.$log->record_id;
                    $description = 'Historical event: '.str_replace('_', ' ', $log->action)
                        .' for '.$record.'. Details were not captured when this event occurred.';

                    DB::table('audit_logs')
                        ->where('id', $log->id)
                        ->update(['description' => $description]);
                }
            });
    }

    public function down(): void
    {
        DB::table('audit_logs')
            ->where('description', 'like', 'Historical event: % Details were not captured when this event occurred.')
            ->update(['description' => null]);
    }
};
