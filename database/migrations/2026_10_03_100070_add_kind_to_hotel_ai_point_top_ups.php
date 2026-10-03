<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid recharges and free extra points the Super Admin gives on top of the
 * plan, any time after approval (client request 2026-10-03), told apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_ai_point_top_ups', function (Blueprint $table): void {
            $table->string('kind', 20)->default('paid');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_ai_point_top_ups', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
