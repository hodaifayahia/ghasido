<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Super Admin's replies to contact and support messages, from the
 * Inbox (client request 2026-10-03: "I only get a notification; I cannot
 * read the message on the site or answer it").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_message_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_replies');
    }
};
