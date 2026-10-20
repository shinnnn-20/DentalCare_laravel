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
        Schema::create('paymongo_test_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('checkout_session_id')->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('paid_payment_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paymongo_test_checkouts');
    }
};
