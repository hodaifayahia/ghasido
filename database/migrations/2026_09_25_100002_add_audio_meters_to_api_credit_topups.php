<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deepgram credit counted in its own units (spec 0007, D10): speech
 * characters and audio seconds, beside Qwen's tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_credit_topups', function (Blueprint $table) {
            $table->bigInteger('amount_characters')->default(0);
            $table->bigInteger('amount_seconds')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('api_credit_topups', function (Blueprint $table) {
            $table->dropColumn(['amount_characters', 'amount_seconds']);
        });
    }
};
