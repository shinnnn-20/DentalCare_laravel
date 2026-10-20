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
        Schema::table('services', function (Blueprint $table): void {
            $table->string('category', 100)->nullable()->after('name');
            $table->string('price_display', 150)->nullable()->after('price');
            $table->boolean('requires_price_confirmation')->default(false)->after('price_display');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['category', 'price_display', 'requires_price_confirmation']);
        });
    }
};
