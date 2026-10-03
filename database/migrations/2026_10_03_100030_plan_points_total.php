<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Super Admin sets a hotel plan's monthly AI points directly (client
 * request 2026-10-02: "I only control per-person points and the page shows
 * 8,000; I want a field I control"). Empty keeps the calculation: seats ×
 * (points per employee + shared points per seat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->unsignedInteger('points_pool_override')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn('points_pool_override');
        });
    }
};
