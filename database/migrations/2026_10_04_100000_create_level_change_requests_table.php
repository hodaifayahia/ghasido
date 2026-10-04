<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A learner's request to change level before finishing their level's
 * Post-test (client request 2026-10-04: "the level is locked until the
 * Post-test; a mistaken level is changed by request, approved or refused by
 * the Super Admin").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->index();
            $table->string('from_level', 20)->nullable();
            $table->string('to_level', 20);
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_change_requests');
    }
};
