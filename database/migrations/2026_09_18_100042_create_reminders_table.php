<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per reminder per recipient (REM-04, REM-06, spec 0003 B.7).
 *
 * This is the log the Messages screen reads and the proof REM-05 asks for:
 * a reminder that could not go out is stored as `blocked` with its reason,
 * not silently dropped. `subject` and `body` are the rendered text at send
 * time, so editing a template later never rewrites history.
 *
 * `template_id`, `automation_rule_id` and `sent_by` null on delete so the
 * log survives its template, its rule and its sender (DATA-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('reminder_templates')->nullOnDelete();
            $table->foreignId('automation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('subject');
            $table->text('body');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status');
            $table->string('blocked_reason')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            // The runner's "already reminded by this rule lately" check.
            $table->index(['automation_rule_id', 'user_id', 'created_at']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
