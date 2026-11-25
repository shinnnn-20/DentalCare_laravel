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
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('payment_method', 20)->nullable();
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->string('payment_proof_status', 20)->nullable();
            $table->foreignId('payment_proof_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_proof_reviewed_at')->nullable();
            $table->foreignId('deposit_payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign(['payment_proof_reviewed_by']);
            $table->dropForeign(['deposit_payment_id']);
            $table->dropUnique(['deposit_payment_id']);
            $table->dropColumn([
                'payment_method',
                'deposit_amount',
                'payment_proof_path',
                'payment_proof_status',
                'payment_proof_reviewed_by',
                'payment_proof_reviewed_at',
                'deposit_payment_id',
            ]);
        });
    }
};
