<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The contact profile every admin account must complete (owner request
 * 2026-09-25): first and last name, phone and address, beside the email.
 * Admin roles only: learners are never asked for a phone or an address
 * (PRIV-03 keeps research participants' data minimal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone', 'address']);
        });
    }
};
