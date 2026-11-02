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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->after('method');
            $table->string('provider_transaction_id')->nullable()->unique()->after('provider');
            $table->string('provider_checkout_session_id')->nullable()->index()->after('provider_transaction_id');
            $table->boolean('is_test')->default(false)->after('provider_checkout_session_id');
            $table->timestamp('paid_at')->nullable()->after('is_test');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider_checkout_session_id']);
            $table->dropUnique(['provider_transaction_id']);
            $table->dropColumn([
                'provider',
                'provider_transaction_id',
                'provider_checkout_session_id',
                'is_test',
                'paid_at',
            ]);
        });
    }
};
