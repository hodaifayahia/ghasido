<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Every subscription price in DZD gets a USD twin for international customers
 * (user request 2026-09-26): each plan's price for extra AI points (per 1,000)
 * and for an extra employee seat, the currency a point top-up was paid in, and
 * an individual subscriber's price. Nothing is converted automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->unsignedBigInteger('extra_points_price_dzd')->default(0);
            $table->decimal('extra_points_price_usd', 10, 2)->default(0);
            $table->unsignedBigInteger('extra_seat_price_dzd')->default(0);
            $table->decimal('extra_seat_price_usd', 10, 2)->default(0);
        });

        Schema::table('hotel_ai_point_top_ups', function (Blueprint $table): void {
            $table->string('currency', 3)->default('DZD');
            $table->decimal('amount_usd', 12, 2)->nullable();
        });

        Schema::table('individual_subscriptions', function (Blueprint $table): void {
            $table->decimal('price_usd', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('individual_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('price_usd');
        });

        Schema::table('hotel_ai_point_top_ups', function (Blueprint $table): void {
            $table->dropColumn(['currency', 'amount_usd']);
        });

        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn(['extra_points_price_dzd', 'extra_points_price_usd', 'extra_seat_price_dzd', 'extra_seat_price_usd']);
        });
    }
};
