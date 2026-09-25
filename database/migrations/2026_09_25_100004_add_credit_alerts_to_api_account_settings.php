<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the "credit running low" and "credit used up" emails went out for
 * an account (spec 0007, D12), so each is sent once per recharge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_account_settings', function (Blueprint $table) {
            $table->timestamp('low_alert_sent_at')->nullable();
            $table->timestamp('empty_alert_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('api_account_settings', function (Blueprint $table) {
            $table->dropColumn(['low_alert_sent_at', 'empty_alert_sent_at']);
        });
    }
};
