<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A second price for international customers (client decision 2026-09-26):
 * Algerian hotels pay the DZD price, everyone else the USD price. Both are
 * set per plan by the Super Admin; nothing is converted automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->decimal('price_usd', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn('price_usd');
        });
    }
};
