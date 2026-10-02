<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The helper languages a customer asks for when they subscribe (client
 * request 2026-10-01), kept on the account that sent the request (the
 * hotel's manager or the individual), so the Super Admin sees them on the
 * request and can add any missing language before they start: offered
 * languages by code, any other by the name the customer typed.
 *
 * A new hotel request from the free sign-up form also rings the Super
 * Admin's bell now; `request_read_at` marks it seen there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('requested_helper_languages')->nullable();
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->timestamp('request_read_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('requested_helper_languages');
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('request_read_at');
        });
    }
};
