<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('appointment_availability_blocks')) {
            Schema::create('appointment_availability_blocks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('reason', 60);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('appointment_availability_blocks', 'availability_blocks_branch_active_start_idx')) {
            Schema::table('appointment_availability_blocks', function (Blueprint $table): void {
                $table->index(['branch_id', 'is_active', 'starts_at'], 'availability_blocks_branch_active_start_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_availability_blocks');
    }
};
