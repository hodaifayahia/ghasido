<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replies to a message sent from Messages & Reminders (client request
 * 2026-11-07: "with the notification I send them they can only read it;
 * they cannot answer me or give me their evaluation").
 *
 * On `reminders`:
 * - `batch` groups the rows one Send wrote (one row per recipient), so the
 *   admin reads every answer to one message together.
 * - `allow_replies` lets the recipient answer; `ask_rating` adds a 1-5
 *   star rating to that answer.
 *
 * `reminder_replies` holds one answer per reminder row (so one per learner
 * per message), editable by the learner. The text is stored as written.
 * `read_at` drives the admin's unread badge. Cascades follow `reminders`,
 * whose rows go only with their user; a log row with a reply cannot be
 * deleted from the log (ReminderPolicy::delete, DATA-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->string('batch', 36)->nullable();
            $table->boolean('allow_replies')->default(false);
            $table->boolean('ask_rating')->default(false);

            $table->index('batch');
        });

        Schema::create('reminder_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reminder_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_replies');

        Schema::table('reminders', function (Blueprint $table): void {
            $table->dropIndex(['batch']);
            $table->dropColumn(['batch', 'allow_replies', 'ask_rating']);
        });
    }
};
