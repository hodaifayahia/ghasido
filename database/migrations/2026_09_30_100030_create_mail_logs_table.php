<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings → Email's "Recent emails" (client report 2026-09-30: "sending
 * email does not work"): every email the platform tried to send, with the
 * mail server's answer, so the Super Admin sees what happened to each one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to')->nullable();
            $table->string('subject')->nullable();
            $table->string('mailable')->nullable();
            $table->string('mailer', 32)->nullable();
            $table->string('status', 16)->index();
            $table->text('error')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
