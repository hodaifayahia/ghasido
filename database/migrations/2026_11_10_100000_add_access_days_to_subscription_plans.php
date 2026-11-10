<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How long a plan gives access, in days, counted from the day the account is
 * approved; the price covers the whole period (client request 2026-11-10:
 * "one month may not be enough to finish the course", 60 days for now,
 * editable by the Super Admin). Existing plans become 60 days. AI points stay
 * monthly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->unsignedInteger('access_days')->default(60);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn('access_days');
        });
    }
};
