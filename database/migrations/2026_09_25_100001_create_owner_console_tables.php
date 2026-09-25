<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The owner console (spec 0007): the platform owner's own login, the paid
 * API accounts they fund (key, pause, metering start) and the credit
 * ledger. Portable across SQLite and MySQL (AGENTS.md §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Not a `users` row: the Super Admin passes every Gate check and
        // manages users, so the owner lives behind a guard of its own (D1).
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_account_settings', function (Blueprint $table) {
            $table->id();
            $table->string('account', 32)->unique();
            // Encrypted with APP_KEY by the model cast; never sent to the
            // browser (API-02, SEC-03). Null = use the .env key.
            $table->text('api_key')->nullable();
            $table->timestamp('key_updated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            // Usage before the first recharge is not charged against it.
            $table->timestamp('metering_started_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_credit_topups', function (Blueprint $table) {
            $table->id();
            $table->string('account', 32)->index();
            $table->decimal('amount_usd', 12, 4)->default(0);
            $table->bigInteger('amount_tokens')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        // Spend per account is summed by provider over a time window.
        Schema::table('ai_usages', function (Blueprint $table) {
            $table->index(['provider', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usages', function (Blueprint $table) {
            $table->dropIndex(['provider', 'occurred_at']);
        });

        Schema::dropIfExists('api_credit_topups');
        Schema::dropIfExists('api_account_settings');
        Schema::dropIfExists('owners');
    }
};
