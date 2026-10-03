<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signed-in users write to the GHASIDO team from Help (client request
 * 2026-10-02: "when they have a problem or want to extend, they send me a
 * message"). It lands with the public contact messages, so the Super
 * Admin's bell and email already carry it; `user_id` and `topic` say who
 * wrote and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('topic');
        });
    }
};
