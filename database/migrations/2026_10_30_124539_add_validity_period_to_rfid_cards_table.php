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
        Schema::table('rfid_cards', function (Blueprint $table): void {
            $table->dateTime('valid_from')->nullable()->after('is_active');
            $table->dateTime('valid_until')->nullable()->after('valid_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfid_cards', function (Blueprint $table): void {
            $table->dropColumn(['valid_from', 'valid_until']);
        });
    }
};
