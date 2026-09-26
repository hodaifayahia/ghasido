<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual subscribers: learners who use GHASIDO on their own, with no
 * hotel (user request 2026-09-25). The learner is a normal `employee` user
 * with hotel_id NULL and a department to study; this row holds everything
 * that a hotel and its plan would otherwise decide for them: the access
 * window, whether AI and voice practice are included, what an AI action and
 * ten minutes of voice cost in points, and an optional daily turn cap. The
 * monthly AI points are users.ai_points_allocated, as for every employee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('ai_enabled')->default(true);
            $table->boolean('voice_enabled')->default(true);
            $table->unsignedInteger('daily_ai_turns')->nullable();
            $table->unsignedInteger('ai_action_points')->default(50);
            $table->unsignedInteger('voice_points_per_10_minutes')->default(100);
            $table->unsignedBigInteger('price_dzd')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('ends_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_subscriptions');
    }
};
